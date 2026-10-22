"""Gera as capturas anotadas e as animações do manual do JPX Eventos."""
import asyncio, json, sys
from PIL import Image, ImageDraw, ImageFont
from playwright.async_api import async_playwright

BASE = 'http://localhost:8899'
OUT = __import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)), '..', 'manual', 'img') + '/'
COR = (232, 89, 12)          # laranja das marcações (contrasta com azul do WP e verde)
FONTE = ImageFont.truetype('/usr/share/fonts/opentype/inter/Inter-Bold.otf', 17)
ESCALA = 2                    # deviceScaleFactor: imagens nítidas
LARG_MAX = 1600               # largura máxima salva

CURSOR_JS = """
(() => { if (document.getElementById('jpx-cursor')) return;
 const c=document.createElement('div'); c.id='jpx-cursor';
 c.style.cssText='position:fixed;z-index:999999;width:22px;height:22px;border-radius:50%;background:rgba(232,89,12,.35);border:2px solid #E8590C;pointer-events:none;transform:translate(-50%,-50%);transition:left .45s ease,top .45s ease;left:-50px;top:-50px';
 document.body.appendChild(c); })()
"""

async def caixa(pg, sel):
    loc = pg.locator(sel).first
    if await loc.count() == 0:
        return None
    bb = await loc.bounding_box()
    if not bb:
        return None
    sx, sy = await pg.evaluate('[scrollX, scrollY]')
    return {'x': bb['x'] + sx, 'y': bb['y'] + sy, 'w': bb['width'], 'h': bb['height']}

def anotar(caminho_png, recorte, marcas, destino, larg=None):
    img = Image.open(caminho_png).convert('RGB')
    x0, y0, x1, y1 = [int(v * ESCALA) for v in recorte]
    d = ImageDraw.Draw(img)
    for n, b in marcas:
        if not b: continue
        bx, by, bw, bh = [v * ESCALA for v in (b['x'], b['y'], b['w'], b['h'])]
        pad = 4 * ESCALA
        d.rounded_rectangle([bx - pad, by - pad, bx + bw + pad, by + bh + pad], radius=8 * ESCALA, outline=COR, width=3 * ESCALA)
        r = 15 * ESCALA
        cx, cy = bx - pad, by - pad
        cx = max(cx, x0 + r + 2); cy = max(cy, y0 + r + 2)
        d.ellipse([cx - r, cy - r, cx + r, cy + r], fill=COR, outline=(255, 255, 255), width=2 * ESCALA)
        f = ImageFont.truetype('/usr/share/fonts/opentype/inter/Inter-Bold.otf', 17 * ESCALA)
        rot = chr(64 + n) if isinstance(n, int) else str(n)
        d.text((cx, cy), rot, fill=(255, 255, 255), font=f, anchor='mm')
    img = img.crop((max(0, x0), max(0, y0), min(img.width, x1), min(img.height, y1)))
    alvo = larg or LARG_MAX
    if img.width > alvo:
        img = img.resize((alvo, int(img.height * alvo / img.width)), Image.LANCZOS)
    img.save(OUT + destino + '.webp', 'WEBP', quality=80, method=6)
    print('ok', destino, img.size)

class Animacao:
    def __init__(self, pg, recorte, nome, larg=1100):
        self.pg, self.recorte, self.nome, self.larg, self.quadros = pg, recorte, nome, larg, []
    async def quadro(self, ms=120):
        await self.pg.screenshot(path='/tmp/claude-0/q.png')
        img = Image.open('/tmp/claude-0/q.png').convert('RGB')
        x0, y0, x1, y1 = [int(v * ESCALA) for v in self.recorte]
        img = img.crop((x0, y0, x1, y1))
        img = img.resize((self.larg, int(img.height * self.larg / img.width)), Image.LANCZOS)
        self.quadros.append((img, ms))
    async def cursor_para(self, sel, quadros=4):
        b = await caixa(self.pg, sel)
        await self.pg.evaluate("([x,y])=>{const c=document.getElementById('jpx-cursor'); c.style.left=x+'px'; c.style.top=y+'px';}", [b['x'] - await self.pg.evaluate('scrollX') + b['w'] / 2, b['y'] - await self.pg.evaluate('scrollY') + b['h'] / 2])
        for _ in range(quadros):
            await self.pg.wait_for_timeout(110); await self.quadro(110)
    def salvar(self):
        imgs = [q[0] for q in self.quadros]; dur = [q[1] for q in self.quadros]
        imgs[0].save(OUT + self.nome + '.webp', 'WEBP', save_all=True, append_images=imgs[1:], duration=dur, loop=0, quality=72, method=4)
        print('anim', self.nome, len(imgs), 'quadros')

async def login(ctx):
    pg = await ctx.new_page()
    await pg.goto(BASE + '/wp-login.php')
    await pg.fill('#user_login', 'admin'); await pg.fill('#user_pass', 'admin'); await pg.click('#wp-submit')
    await pg.wait_for_load_state('networkidle')
    return pg

async def main():
    so = sys.argv[1:]  # opcional: gerar só algumas
    quer = lambda n: not so or n in so
    async with async_playwright() as p:
        b = await p.chromium.launch(args=['--lang=pt-BR'], env={'LANG': 'pt_BR.UTF-8', 'LANGUAGE': 'pt_BR', 'LC_ALL': 'pt_BR.UTF-8', 'HOME': '/root', 'PATH': '/usr/bin:/bin'})
        ctx = await b.new_context(viewport={'width': 1400, 'height': 900}, device_scale_factor=ESCALA, locale='pt-BR', timezone_id='America/Sao_Paulo')
        pg = await login(ctx)
        tmp = '/tmp/claude-0/cap.png'

        if quer('painel-inicio'):
            await pg.goto(BASE + '/wp-admin/edit.php?post_type=evento', wait_until='networkidle')
            await pg.screenshot(path=tmp, full_page=True)
            anotar(tmp, (0, 32, 1400, 640), [(1, await caixa(pg, '#menu-posts-evento')), (2, await caixa(pg, '.jpx-pp'))], 'painel-inicio')

        # ---------- Cadastro em 4 passos ----------
        if quer('cadastro'):
            await pg.evaluate("1")
            await pg.goto(BASE + '/wp-admin/post-new.php?post_type=evento', wait_until='networkidle')
            await pg.fill('#title', 'Oficina de Instagram para negócios')
            await pg.click('label.jpx-ed__chip:has-text("Curso")')
            await pg.select_option('#jpx-nucleo', label='Comércio e Serviços')
            await pg.fill('#jpx-inicio', '2026-11-26'); await pg.dispatch_event('#jpx-inicio', 'change')
            await pg.fill('#jpx-hi', '19:00'); await pg.dispatch_event('#jpx-hi', 'change')
            await pg.click('label.jpx-ed__chip--local:has-text("Auditório principal")')
            await pg.fill('#jpx-insc', 'https://forms.gle/exemplo')
            await pg.wait_for_timeout(2500)
            await pg.screenshot(path=tmp, full_page=True)
            marcas = [(1, await caixa(pg, '#titlewrap')), (2, await caixa(pg, '#jpx_ev_previa')), (3, await caixa(pg, '#publishing-action'))]
            fim = (await caixa(pg, '.jpx-ed__expandir'))
            tt = await caixa(pg, '#titlediv')
            anotar(tmp, (165, tt['y'] - 24, 1400, fim['y'] + fim['h'] + 20), marcas, 'cadastro-4-passos')
            # repetição
            await pg.select_option('#jpx-repete', 'mensal_ultima'); await pg.wait_for_timeout(1200)
            await pg.check('input[name="jpx[regra][fim]"][value="data"]'); await pg.fill('input[name="jpx[regra][ate]"]', '2027-06-30'); await pg.dispatch_event('input[name="jpx[regra][ate]"]', 'change'); await pg.wait_for_timeout(1200)
            await pg.screenshot(path=tmp, full_page=True)
            q = await caixa(pg, '.jpx-ed__passo:nth-of-type(2)')
            anotar(tmp, (q['x'] - 10, q['y'] - 10, q['x'] + q['w'] + 10, q['y'] + q['h'] + 10),
                   [(1, await caixa(pg, '#jpx-repete')), (2, await caixa(pg, '[data-jpx-termina]')), (3, await caixa(pg, '[data-jpx-resumo]')), (4, await caixa(pg, '[data-jpx-datas] li:nth-child(2) .jpx-ed__acoes'))], 'repeticao')
            # exceções
            await pg.click('[data-jpx-datas] li:nth-child(3) [data-acao="cancelar"]'); await pg.wait_for_timeout(900)
            await pg.click('[data-jpx-datas] li:nth-child(2) [data-acao="alterar"]'); await pg.wait_for_timeout(300)
            await pg.fill('[data-jpx-datas] li:nth-child(2) [data-c="local"]', 'Loja Exemplo (anfitriã)')
            await pg.click('[data-jpx-datas] li:nth-child(2) [data-acao="aplicar"]'); await pg.wait_for_timeout(1200)
            await pg.screenshot(path=tmp, full_page=True)
            l = await caixa(pg, '.jpx-ed__previa')
            anotar(tmp, (l['x'] - 8, l['y'] - 8, l['x'] + l['w'] + 8, l['y'] + l['h'] + 8), [(1, await caixa(pg, '[data-jpx-datas] li:nth-child(2)')), (2, await caixa(pg, '[data-jpx-datas] li:nth-child(3)'))], 'excecoes')
            # dica
            await pg.select_option('#jpx-repete', 'nenhuma'); await pg.wait_for_timeout(800)
            await pg.click('.jpx-ed__passo:nth-of-type(3) .jpx-dica__btn'); await pg.wait_for_timeout(300)
            await pg.screenshot(path=tmp, full_page=True)
            s3 = await caixa(pg, '.jpx-ed__passo:nth-of-type(3)')
            anotar(tmp, (s3['x'] - 8, s3['y'] - 8, s3['x'] + s3['w'] + 8, s3['y'] + 230), [(1, await caixa(pg, '.jpx-ed__passo:nth-of-type(3) .jpx-dica__btn'))], 'dica')
            await pg.keyboard.press('Escape')
            # vários dias / dia inteiro
            await pg.check('input[name="jpx[varios_dias]"]'); await pg.fill('#jpx-fim', '2026-11-28'); await pg.dispatch_event('#jpx-fim', 'change'); await pg.wait_for_timeout(400)
            await pg.screenshot(path=tmp, full_page=True)
            s2 = await caixa(pg, '.jpx-ed__passo:nth-of-type(2)')
            anotar(tmp, (s2['x'] - 8, s2['y'] - 8, s2['x'] + s2['w'] + 8, s2['y'] + s2['h'] + 8), [(1, await caixa(pg, '.jpx-ed__opcoes')), (2, await caixa(pg, '[data-jpx-fim]'))], 'varios-dias')
            await pg.uncheck('input[name="jpx[varios_dias]"]')
            # novo local
            await pg.click('label.jpx-ed__chip--novo'); await pg.wait_for_timeout(300)
            await pg.fill('#jpx-ln', 'Câmara de Vereadores'); await pg.fill('#jpx-le', 'Av. Brasil, 500'); await pg.fill('#jpx-lc', 'Cidade Exemplo – SC')
            await pg.screenshot(path=tmp, full_page=True)
            s3 = await caixa(pg, '.jpx-ed__passo:nth-of-type(3)')
            anotar(tmp, (s3['x'] - 8, s3['y'] - 8, s3['x'] + s3['w'] + 8, s3['y'] + s3['h'] + 8), [(1, await caixa(pg, 'label.jpx-ed__chip--novo')), (2, await caixa(pg, '.jpx-ed__salvar-local'))], 'novo-local')
            # descrição
            await pg.click('[data-jpx-expandir]'); await pg.wait_for_timeout(700)
            await pg.fill('#jpx-resumo', 'Aprenda a planejar posts, usar os Stories e responder clientes pelo Direct.')
            await pg.fill('#jpx-prog', '19:00 Boas-vindas\n19:15 Planejamento de conteúdo\n20:30 Prática com o celular')
            await pg.screenshot(path=tmp, full_page=True)
            e = await caixa(pg, '[data-jpx-expandir]'); x = await caixa(pg, '[data-jpx-extras]')
            anotar(tmp, (e['x'] - 8, e['y'] - 8, e['x'] + e['w'] + 8, x['y'] + x['h'] + 8), [(1, await caixa(pg, '#wp-content-wrap')), (2, await caixa(pg, '#jpx-resumo')), (3, await caixa(pg, '#jpx-prog'))], 'descricao')

        # ---------- Calendário do painel ----------
        if quer('calendario'):
            await pg.goto(BASE + '/wp-admin/edit.php?post_type=evento&page=jpx-eventos-calendario&mes=2026-11', wait_until='networkidle')
            await pg.wait_for_timeout(800)
            await pg.screenshot(path=tmp, full_page=True)
            g = await caixa(pg, '.jpx-acal')
            anotar(tmp, (g['x'] - 10, g['y'] - 6, g['x'] + g['w'] + 10, g['y'] + g['h'] + 10), [(1, await caixa(pg, '.jpx-acal__nav')), (2, await caixa(pg, '[data-dia="2026-11-18"]')), (3, await caixa(pg, '[data-dia="2026-11-05"] [data-ev]'))], 'calendario-painel')
            # animação: criação rápida (janela alta para não rolar a página)
            await pg.set_viewport_size({'width': 1400, 'height': 1500})
            await pg.reload(wait_until='networkidle'); await pg.wait_for_timeout(800)
            await pg.evaluate('window.scrollTo(0,0)')
            await pg.evaluate(CURSOR_JS)
            gr = await caixa(pg, '.jpx-acal__grade')
            a = Animacao(pg, (gr['x'] - 4, gr['y'] - 4, gr['x'] + gr['w'] + 4, gr['y'] + gr['h'] + 4), 'anim-criacao-rapida')
            for _ in range(6): await a.quadro(140)
            await a.cursor_para('[data-dia="2026-11-19"]')
            await pg.click('[data-dia="2026-11-19"]'); await pg.wait_for_timeout(400)
            for _ in range(4): await a.quadro(140)
            texto = 'Café com associados'
            for i in range(1, len(texto) + 1, 2):
                await pg.fill('#jpx-q-nome', texto[:i]); await a.quadro(90)
            await pg.fill('#jpx-q-nome', texto); await a.quadro(400)
            await a.cursor_para('.jpx-acal__chip:has-text("Encontro")', 3)
            await pg.click('.jpx-acal__chip:has-text("Encontro")'); await a.quadro(500)
            await a.cursor_para('.jpx-acal__form [type="submit"]', 3)
            await pg.keyboard.press('Enter'); await pg.wait_for_timeout(1500)
            for _ in range(14): await a.quadro(140)
            a.salvar()
            # animação: arrastar
            a2 = Animacao(pg, a.recorte, 'anim-arrastar')
            for _ in range(4): await a2.quadro(140)
            await a2.cursor_para('[data-dia="2026-11-19"] [data-ev][draggable="true"]')
            await pg.dispatch_event('[data-dia="2026-11-19"] [data-ev][draggable="true"]', 'mouseover')
            src = await caixa(pg, '[data-dia="2026-11-19"] [data-ev][draggable="true"]'); dst = await caixa(pg, '[data-dia="2026-11-20"]')
            sy = await pg.evaluate('scrollY')
            await pg.mouse.move(src['x'] + 30, src['y'] - sy + src['h'] / 2); await pg.mouse.down()
            for k in range(1, 7):
                xx = src['x'] + 30 + (dst['x'] + 40 - src['x'] - 30) * k / 6
                await pg.mouse.move(xx, src['y'] - sy + src['h'] / 2 + 6 * k)
                await pg.evaluate("([x,y])=>{const c=document.getElementById('jpx-cursor'); c.style.transition='none'; c.style.left=x+'px'; c.style.top=y+'px';}", [xx, src['y'] - sy + src['h'] / 2 + 6 * k])
                await a2.quadro(120)
            await pg.mouse.up()
            if await pg.locator('[data-dia="2026-11-19"] [data-ev][draggable="true"]').count():
                await pg.drag_and_drop('[data-dia="2026-11-19"] [data-ev][draggable="true"]', '[data-dia="2026-11-20"]')
            await pg.wait_for_timeout(1500)
            for _ in range(14): await a2.quadro(140)
            a2.salvar()
            # quadro estático do popover
            await pg.set_viewport_size({'width': 1400, 'height': 900})
            await pg.click('[data-dia="2026-11-23"]'); await pg.wait_for_timeout(700)
            await pg.fill('#jpx-q-nome', 'Reunião da diretoria')
            await pg.evaluate("document.getElementById('jpx-cursor').remove()")
            await pg.screenshot(path=tmp, full_page=True)
            pop = await caixa(pg, '.jpx-acal__pop')
            anotar(tmp, (pop['x'] - 180, pop['y'] - 90, pop['x'] + pop['w'] + 30, pop['y'] + pop['h'] + 20), [(1, await caixa(pg, '#jpx-q-nome')), (2, await caixa(pg, '.jpx-acal__chips')), (3, await caixa(pg, '.jpx-acal__linha--2')), (4, await caixa(pg, '.jpx-acal__pop-rodape a'))], 'criacao-rapida')

        # ---------- Lista, termos, configurações ----------
        if quer('lista'):
            await pg.goto(BASE + '/wp-admin/edit.php?post_type=evento', wait_until='networkidle')
            await pg.evaluate("document.querySelector('.jpx-pp') && document.querySelector('.jpx-pp').remove()")
            linha = 'tr:has-text("Planejamento financeiro")'
            await pg.hover(linha); await pg.wait_for_timeout(300)
            await pg.screenshot(path=tmp, full_page=True)
            t = await caixa(pg, '.wp-list-table')
            anotar(tmp, (t['x'] - 10, t['y'] - 60, t['x'] + t['w'] + 10, t['y'] + 420), [(1, await caixa(pg, linha + ' .row-actions')), (2, await caixa(pg, 'th#jpx_quando'))], 'lista-eventos')
        if quer('termos'):
            await pg.goto(BASE + '/wp-admin/edit-tags.php?taxonomy=jpx_local&post_type=evento', wait_until='networkidle')
            await pg.evaluate("document.querySelector('.jpx-pp') && document.querySelector('.jpx-pp').remove()")
            await pg.screenshot(path=tmp, full_page=True)
            ad = await caixa(pg, '#addtag')
            anotar(tmp, (165, 40, 1400, ad['y'] + ad['h'] + 24), [(1, await caixa(pg, '#addtag .form-field:has(#jpx_endereco)')), (2, await caixa(pg, '.wp-list-table th#jpx_endereco'))], 'locais')
            tid = await pg.evaluate("1")
            await pg.goto(BASE + '/wp-admin/edit-tags.php?taxonomy=jpx_tipo&post_type=evento', wait_until='networkidle')
            await pg.evaluate("document.querySelector('.jpx-pp') && document.querySelector('.jpx-pp').remove()")
            await pg.screenshot(path=tmp, full_page=True)
            ad = await caixa(pg, '#addtag')
            anotar(tmp, (165, 40, 1400, ad['y'] + ad['h'] + 24), [(1, await caixa(pg, '#addtag .form-field:has(#jpx_cor)')), (2, await caixa(pg, 'th#jpx_cor'))], 'tipos')
        if quer('config'):
            await pg.goto(BASE + '/wp-admin/edit.php?post_type=evento&page=jpx-eventos-config', wait_until='networkidle')
            await pg.evaluate("document.querySelector('.jpx-pp') && document.querySelector('.jpx-pp').remove()")
            await pg.screenshot(path=tmp, full_page=True)
            pv = await caixa(pg, '[data-jpx-cfg-previa]')
            anotar(tmp, (160, 40, 1400, pv['y'] + pv['h'] + 30), [(1, await caixa(pg, '#jpx-cp')), (2, await caixa(pg, '.jpx-cfg__sugestoes')), (3, await caixa(pg, '[data-jpx-cfg-previa]'))], 'configuracoes')

        await ctx.close()

        # ---------- Site (visitante) ----------
        if quer('site'):
            for larg, suf in ((1280, ''), (390, '-celular')):
                c2 = await b.new_context(viewport={'width': larg, 'height': 900}, device_scale_factor=ESCALA, locale='pt-BR')
                v = await c2.new_page()
                await v.goto(BASE + '/agenda/', wait_until='networkidle')
                await v.screenshot(path=tmp, full_page=True)
                ag = await caixa(v, '.jpx-agenda')
                if suf:
                    anotar(tmp, (0, ag['y'] - 10, larg, ag['y'] + 1500), [], 'site-agenda' + suf, larg=780)
                else:
                    anotar(tmp, (ag['x'] - 20, ag['y'] - 20, ag['x'] + ag['w'] + 20, ag['y'] + 1250),
                           [(1, await caixa(v, '.jpx-alternar')), (2, await caixa(v, '.jpx-busca')), (3, await caixa(v, '.jpx-chips')), (4, await caixa(v, '.jpx-abas')), (5, await caixa(v, '.jpx-destaque'))], 'site-agenda')
                await v.goto(BASE + '/agenda/?jpx_v=cal&jpx_mes=2026-11', wait_until='networkidle')
                await v.screenshot(path=tmp, full_page=True)
                cal = await caixa(v, '.jpx-cal')
                anotar(tmp, (cal['x'] - 20, cal['y'] - 20, cal['x'] + cal['w'] + 20, cal['y'] + min(cal['h'], 1400) + 20), [], 'site-calendario' + suf, larg=780 if suf else None)
                await v.click('.jpx-mini >> nth=3'); await v.wait_for_timeout(700)
                await v.screenshot(path=tmp)
                anotar(tmp, (0, 0, larg, 900), [], 'site-modal' + suf, larg=780 if suf else 1400)
                await v.keyboard.press('Escape')
                await v.goto(BASE + '/eventos/planejamento-financeiro-para-pequenas-empresas/', wait_until='networkidle')
                await v.screenshot(path=tmp, full_page=True)
                body = await caixa(v, '.jpx-single')
                anotar(tmp, (0, 0, larg, body['y'] + min(body['h'], 1500) + 20),
                       [] if suf else [(1, await caixa(v, '.jpx-painel__quando')), (2, await caixa(v, '.jpx-painel .jpx-btn--cheio')), (3, await caixa(v, '.jpx-painel__agendas')), (4, await caixa(v, '.jpx-prog'))], 'site-evento' + suf, larg=780 if suf else None)
                if not suf:
                    await v.goto(BASE + '/inicio/', wait_until='networkidle')
                    await v.screenshot(path=tmp, full_page=True)
                    w = await caixa(v, '.jpx-widget')
                    anotar(tmp, (w['x'] - 20, w['y'] - 20, w['x'] + w['w'] + 20, w['y'] + w['h'] + 20), [], 'site-home')
                    await v.goto(BASE + '/eventos/reuniao-mensal-do-nucleo-de-industria/', wait_until='networkidle')
                    await v.screenshot(path=tmp, full_page=True)
                    dd = await caixa(v, '.jpx-datas')
                    anotar(tmp, (dd['x'] - 20, dd['y'] - 80, dd['x'] + dd['w'] + 20, dd['y'] + dd['h'] + 20), [], 'site-proximas-datas')
                await c2.close()
        await b.close()

asyncio.run(main())
