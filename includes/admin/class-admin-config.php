<?php
/**
 * Página de configurações: identidade visual, agenda, URLs e textos.
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_Admin_Config {

	const PAGINA = 'jpx-eventos-config';

	/**
	 * Hooks.
	 */
	public static function iniciar() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'registrar' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'update_option_jpx_eventos_opcoes', array( __CLASS__, 'apos_salvar' ), 10, 2 );
		add_action(
			'add_option_jpx_eventos_opcoes',
			function ( $nome, $valor ) {
				self::apos_salvar( jpx_ev_opcoes_padrao(), $valor );
			},
			10,
			2
		);
		add_filter( 'plugin_action_links_' . plugin_basename( JPX_EV_FILE ), array( __CLASS__, 'link_plugins' ) );
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . JPX_EV_Tipos::CPT, __( 'Configurações da agenda', 'jpx-eventos' ), __( 'Configurações', 'jpx-eventos' ), 'manage_options', self::PAGINA, array( __CLASS__, 'pagina' ) );
	}

	/**
	 * Link "Configurações" na lista de plugins.
	 *
	 * @param array $l Links.
	 * @return array
	 */
	public static function link_plugins( $l ) {
		array_unshift( $l, '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . JPX_EV_Tipos::CPT . '&page=' . self::PAGINA ) ) . '">' . esc_html__( 'Configurações', 'jpx-eventos' ) . '</a>' );
		return $l;
	}

	/**
	 * CSS.
	 *
	 * @param string $hook Página.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, self::PAGINA ) ) {
			return;
		}
		wp_enqueue_style( 'jpx-ev-admin', JPX_EV_URL . 'assets/css/admin.css', array(), JPX_EV_VERSION );
	}

	/**
	 * Registra a opção.
	 */
	public static function registrar() {
		register_setting( 'jpx_eventos', 'jpx_eventos_opcoes', array( 'sanitize_callback' => array( __CLASS__, 'sanitizar' ) ) );
	}

	/**
	 * Sanitiza.
	 *
	 * @param array $v Valores.
	 * @return array
	 */
	public static function sanitizar( $v ) {
		$p   = jpx_ev_opcoes_padrao();
		$v   = is_array( $v ) ? $v : array();
		$out = array(
			'cor_primaria'     => jpx_ev_sanitizar_cor( $v['cor_primaria'] ?? '', $p['cor_primaria'] ),
			'cor_escura'       => jpx_ev_sanitizar_cor( $v['cor_escura'] ?? '', $p['cor_escura'] ),
			'raio'             => max( 0, min( 32, (int) ( $v['raio'] ?? $p['raio'] ) ) ),
			'fonte_titulos'    => sanitize_text_field( $v['fonte_titulos'] ?? '' ),
			'fonte_texto'      => sanitize_text_field( $v['fonte_texto'] ?? '' ),
			'slug'             => sanitize_title( $v['slug'] ?? '' ) ? sanitize_title( $v['slug'] ) : $p['slug'],
			'nucleo_singular'  => sanitize_text_field( $v['nucleo_singular'] ?? '' ) ? sanitize_text_field( $v['nucleo_singular'] ) : $p['nucleo_singular'],
			'nucleo_plural'    => sanitize_text_field( $v['nucleo_plural'] ?? '' ) ? sanitize_text_field( $v['nucleo_plural'] ) : $p['nucleo_plural'],
			'pagina_agenda'    => (int) ( $v['pagina_agenda'] ?? 0 ),
			'modo_single'      => in_array( $v['modo_single'] ?? '', array( 'auto', 'desligado' ), true ) ? $v['modo_single'] : 'auto',
			'itens_por_pagina' => max( 3, min( 48, (int) ( $v['itens_por_pagina'] ?? $p['itens_por_pagina'] ) ) ),
			'mostrar_feriados' => empty( $v['mostrar_feriados'] ) ? 0 : 1,
			'horizonte_meses'  => max( 3, min( 60, (int) ( $v['horizonte_meses'] ?? $p['horizonte_meses'] ) ) ),
			'local_padrao'     => (int) ( $v['local_padrao'] ?? 0 ),
		);
		return $out;
	}

	/**
	 * Depois de salvar: regras de URL e horizonte de recorrência.
	 *
	 * @param array $antes  Antes.
	 * @param array $depois Depois.
	 */
	public static function apos_salvar( $antes, $depois ) {
		update_option( 'jpx_eventos_config_salva', 1, false );
		$antes = is_array( $antes ) ? $antes : array();
		foreach ( array( 'slug', 'nucleo_singular' ) as $k ) {
			if ( ( $antes[ $k ] ?? '' ) !== ( $depois[ $k ] ?? '' ) ) {
				update_option( 'jpx_eventos_flush', 1, false );
			}
		}
		if ( (int) ( $antes['horizonte_meses'] ?? 0 ) !== (int) ( $depois['horizonte_meses'] ?? 0 ) ) {
			JPX_EV_Ocorrencias::gerar_todos();
		}
	}

	/**
	 * Cores sugeridas a partir do tema (Divi ou theme.json).
	 *
	 * @return array [ rótulo => hex ]
	 */
	private static function cores_tema() {
		$c = array();
		if ( function_exists( 'et_get_option' ) ) {
			$a = jpx_ev_sanitizar_cor( et_get_option( 'accent_color' ) );
			if ( $a ) {
				$c[ __( 'Destaque do Divi', 'jpx-eventos' ) ] = $a;
			}
		}
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$pal = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );
			foreach ( (array) $pal as $item ) {
				$h = jpx_ev_sanitizar_cor( $item['color'] ?? '' );
				if ( $h && ! in_array( $h, array( '#FFFFFF', '#000000' ), true ) && count( $c ) < 8 ) {
					$c[ $item['name'] ?? $h ] = $h;
				}
			}
		}
		$mod = jpx_ev_sanitizar_cor( get_theme_mod( 'accent_color' ) );
		if ( $mod && ! in_array( $mod, $c, true ) ) {
			$c[ __( 'Destaque do tema', 'jpx-eventos' ) ] = $mod;
		}
		return $c;
	}

	/**
	 * Página.
	 */
	public static function pagina() {
		$o      = jpx_ev_opcao();
		$n      = 'jpx_eventos_opcoes';
		$tema   = self::cores_tema();
		$locais = get_terms( array( 'taxonomy' => JPX_EV_Tipos::LOCAL, 'hide_empty' => false ) );
		?>
		<div class="wrap jpx-cfg">
			<h1><?php esc_html_e( 'Configurações da agenda', 'jpx-eventos' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'jpx_eventos' ); ?>

				<div class="jpx-cfg__grade">
					<div>
						<h2><?php esc_html_e( 'Identidade visual', 'jpx-eventos' ); ?> <?php echo JPX_EV_Admin_Ajuda::dica( __( 'Use as cores do site. Os quadradinhos “Do seu tema” aplicam as cores que o tema já usa.', 'jpx-eventos' ), 'configuracoes' ); // phpcs:ignore ?></h2>
						<p class="description"><?php esc_html_e( 'Tudo na agenda usa estas cores e fontes. O plugin escurece a cor principal automaticamente onde for preciso para manter o texto legível.', 'jpx-eventos' ); ?></p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="jpx-cp"><?php esc_html_e( 'Cor principal', 'jpx-eventos' ); ?></label></th>
								<td>
									<input type="color" id="jpx-cp" name="<?php echo esc_attr( $n ); ?>[cor_primaria]" value="<?php echo esc_attr( $o['cor_primaria'] ); ?>" data-jpx-token="primaria">
									<?php if ( $tema ) : ?>
										<span class="jpx-cfg__sugestoes"><?php esc_html_e( 'Do seu tema:', 'jpx-eventos' ); ?>
											<?php foreach ( $tema as $rot => $hex ) : ?>
												<button type="button" class="jpx-cfg__amostra" style="background:<?php echo esc_attr( $hex ); ?>" data-cor="<?php echo esc_attr( $hex ); ?>" title="<?php echo esc_attr( $rot . ' ' . $hex ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Usar %s', 'jpx-eventos' ), $rot ) ); ?>"></button>
											<?php endforeach; ?>
										</span>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="jpx-ce"><?php esc_html_e( 'Cor escura', 'jpx-eventos' ); ?></label></th>
								<td><input type="color" id="jpx-ce" name="<?php echo esc_attr( $n ); ?>[cor_escura]" value="<?php echo esc_attr( $o['cor_escura'] ); ?>" data-jpx-token="escura"> <span class="description"><?php esc_html_e( 'Títulos, cabeçalho da agenda e botões escuros.', 'jpx-eventos' ); ?></span></td>
							</tr>
							<tr>
								<th scope="row"><label for="jpx-r"><?php esc_html_e( 'Arredondamento', 'jpx-eventos' ); ?></label></th>
								<td><input type="range" id="jpx-r" min="0" max="28" name="<?php echo esc_attr( $n ); ?>[raio]" value="<?php echo (int) $o['raio']; ?>" data-jpx-token="raio"> <output data-jpx-raio><?php echo (int) $o['raio']; ?>px</output></td>
							</tr>
							<tr>
								<th scope="row"><label for="jpx-ft"><?php esc_html_e( 'Fonte dos títulos', 'jpx-eventos' ); ?></label></th>
								<td><input type="text" id="jpx-ft" class="regular-text" name="<?php echo esc_attr( $n ); ?>[fonte_titulos]" value="<?php echo esc_attr( $o['fonte_titulos'] ); ?>" placeholder="<?php esc_attr_e( 'Vazio = a mesma do tema', 'jpx-eventos' ); ?>"><p class="description"><?php esc_html_e( 'Nome de uma fonte que o tema já carrega (ex.: Poppins). O plugin não baixa fontes, para não pesar o site.', 'jpx-eventos' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><label for="jpx-fb"><?php esc_html_e( 'Fonte do texto', 'jpx-eventos' ); ?></label></th>
								<td><input type="text" id="jpx-fb" class="regular-text" name="<?php echo esc_attr( $n ); ?>[fonte_texto]" value="<?php echo esc_attr( $o['fonte_texto'] ); ?>" placeholder="<?php esc_attr_e( 'Vazio = a mesma do tema', 'jpx-eventos' ); ?>"></td>
							</tr>
						</table>
					</div>
					<div class="jpx-cfg__previa" data-jpx-cfg-previa aria-hidden="true">
						<div class="jpx-cfg__previa-hero"><small><?php esc_html_e( 'Agenda', 'jpx-eventos' ); ?></small><strong><?php esc_html_e( 'Próximos eventos', 'jpx-eventos' ); ?></strong></div>
						<div class="jpx-cfg__previa-card">
							<div class="jpx-cfg__previa-capa"><span>27</span><em><b>OUT</b>27</em></div>
							<div class="jpx-cfg__previa-corpo"><strong><?php esc_html_e( 'Palestra de exemplo', 'jpx-eventos' ); ?></strong><span>Ter, 27 de out · 19:00</span><i><?php esc_html_e( 'Inscrever-se', 'jpx-eventos' ); ?></i></div>
						</div>
					</div>
				</div>

				<h2><?php esc_html_e( 'Agenda', 'jpx-eventos' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="jpx-pag"><?php esc_html_e( 'Página da agenda', 'jpx-eventos' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => $n . '[pagina_agenda]',
									'id'                => 'jpx-pag',
									'selected'          => (int) $o['pagina_agenda'],
									'show_option_none'  => __( '— Usar o arquivo /eventos/ —', 'jpx-eventos' ),
									'option_none_value' => 0,
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Para onde apontam os links "Agenda completa". Coloque o shortcode [jpx_agenda] nessa página.', 'jpx-eventos' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jpx-ipp"><?php esc_html_e( 'Eventos por página', 'jpx-eventos' ); ?></label></th>
						<td><input type="number" id="jpx-ipp" min="3" max="48" name="<?php echo esc_attr( $n ); ?>[itens_por_pagina]" value="<?php echo (int) $o['itens_por_pagina']; ?>" class="small-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="jpx-lp"><?php esc_html_e( 'Local padrão', 'jpx-eventos' ); ?></label></th>
						<td>
							<select id="jpx-lp" name="<?php echo esc_attr( $n ); ?>[local_padrao]">
								<option value="0"><?php esc_html_e( '— Nenhum —', 'jpx-eventos' ); ?></option>
								<?php foreach ( is_wp_error( $locais ) ? array() : $locais as $l ) : ?>
									<option value="<?php echo (int) $l->term_id; ?>" <?php selected( (int) $o['local_padrao'], $l->term_id ); ?>><?php echo esc_html( $l->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Já vem marcado em todo evento novo.', 'jpx-eventos' ); ?> <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . JPX_EV_Tipos::LOCAL . '&post_type=' . JPX_EV_Tipos::CPT ) ); ?>"><?php esc_html_e( 'Gerenciar locais', 'jpx-eventos' ); ?></a></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Feriados', 'jpx-eventos' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[mostrar_feriados]" value="1" <?php checked( $o['mostrar_feriados'] ); ?>> <?php esc_html_e( 'Mostrar feriados nacionais no calendário', 'jpx-eventos' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Página do evento', 'jpx-eventos' ); ?> <?php echo JPX_EV_Admin_Ajuda::dica( __( 'Deixe em Automática. Use Manual só se a página do evento for montada num construtor (ex.: Divi Theme Builder) com o shortcode [jpx_evento].', 'jpx-eventos' ), 'implantacao' ); // phpcs:ignore ?></th>
						<td>
							<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[modo_single]" value="auto" <?php checked( $o['modo_single'], 'auto' ); ?>> <?php esc_html_e( 'Automática — o plugin monta a página dentro do tema', 'jpx-eventos' ); ?></label><br>
							<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[modo_single]" value="desligado" <?php checked( $o['modo_single'], 'desligado' ); ?>> <?php esc_html_e( 'Manual — eu uso o shortcode [jpx_evento] no template do construtor (Divi, Elementor…)', 'jpx-eventos' ); ?></label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Endereços e nomes', 'jpx-eventos' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="jpx-slug"><?php esc_html_e( 'Endereço dos eventos', 'jpx-eventos' ); ?></label></th>
						<td><code><?php echo esc_html( home_url( '/' ) ); ?></code><input type="text" id="jpx-slug" name="<?php echo esc_attr( $n ); ?>[slug]" value="<?php echo esc_attr( $o['slug'] ); ?>" class="regular-text" style="width:160px"><code>/nome-do-evento/</code><p class="description"><?php esc_html_e( 'Mudar isso troca os links de todos os eventos.', 'jpx-eventos' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="jpx-ns"><?php esc_html_e( 'Como chamar os grupos', 'jpx-eventos' ); ?></label></th>
						<td><input type="text" id="jpx-ns" name="<?php echo esc_attr( $n ); ?>[nucleo_singular]" value="<?php echo esc_attr( $o['nucleo_singular'] ); ?>" style="width:160px" aria-label="<?php esc_attr_e( 'Singular', 'jpx-eventos' ); ?>"> / <input type="text" name="<?php echo esc_attr( $n ); ?>[nucleo_plural]" value="<?php echo esc_attr( $o['nucleo_plural'] ); ?>" style="width:160px" aria-label="<?php esc_attr_e( 'Plural', 'jpx-eventos' ); ?>"><p class="description"><?php esc_html_e( 'Ex.: Núcleo/Núcleos, Comissão/Comissões, Setor/Setores.', 'jpx-eventos' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="jpx-hz"><?php esc_html_e( 'Recorrentes: gerar datas para', 'jpx-eventos' ); ?></label></th>
						<td><input type="number" id="jpx-hz" min="3" max="60" name="<?php echo esc_attr( $n ); ?>[horizonte_meses]" value="<?php echo (int) $o['horizonte_meses']; ?>" class="small-text"> <?php esc_html_e( 'meses à frente (renovado todo dia)', 'jpx-eventos' ); ?></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Shortcodes', 'jpx-eventos' ); ?></h2>
			<table class="widefat striped jpx-cfg__sc">
				<tbody>
					<tr><td><code>[jpx_agenda]</code></td><td><?php esc_html_e( 'Agenda completa: lista, calendário, busca e filtros. Opções: cabecalho="nao", titulo="…", descricao="…", visao="calendario", tipo="palestra", nucleo="slug".', 'jpx-eventos' ); ?></td></tr>
					<tr><td><code>[jpx_calendario]</code></td><td><?php esc_html_e( 'Só o calendário mensal. Opções: tipo, nucleo, filtros="sim".', 'jpx-eventos' ); ?></td></tr>
					<tr><td><code>[jpx_eventos]</code></td><td><?php esc_html_e( 'Próximos eventos para a home ou página de um núcleo. Opções: quantidade="4", layout="compacto", nucleo="slug", tipo="slug", titulo="…", link_agenda="nao", passados="sim", agrupar="nao" (mostra cada data de eventos recorrentes), se_vazio="mensagem" (sem próximos eventos, mostra só o aviso em vez dos últimos realizados).', 'jpx-eventos' ); ?></td></tr>
					<tr><td><code>[jpx_evento]</code></td><td><?php esc_html_e( 'Página do evento completa, para templates de construtores (ex.: Divi Theme Builder).', 'jpx-eventos' ); ?></td></tr>
					<tr><td><code>[lista_eventos] [eventos_home] [evento_detalhe]</code></td><td><?php esc_html_e( 'Shortcodes da versão anterior — continuam funcionando com o visual novo.', 'jpx-eventos' ); ?></td></tr>
					<tr><td><code><?php echo esc_html( JPX_EV_ICS::url_agenda() ); ?></code></td><td><?php esc_html_e( 'Link da agenda para assinar no Google Agenda / Outlook / celular.', 'jpx-eventos' ); ?></td></tr>
				</tbody>
			</table>
		</div>
		<script>
		( function () {
			var p = document.querySelector( '[data-jpx-cfg-previa]' );
			var cp = document.getElementById( 'jpx-cp' ), ce = document.getElementById( 'jpx-ce' ), r = document.getElementById( 'jpx-r' ), out = document.querySelector( '[data-jpx-raio]' );
			function atualiza() {
				p.style.setProperty( '--p', cp.value );
				p.style.setProperty( '--i', ce.value );
				p.style.setProperty( '--r', r.value + 'px' );
				out.textContent = r.value + 'px';
			}
			[ cp, ce, r ].forEach( function ( el ) { el.addEventListener( 'input', atualiza ); } );
			document.querySelectorAll( '[data-cor]' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () { cp.value = b.getAttribute( 'data-cor' ); atualiza(); } );
			} );
			atualiza();
		} )();
		</script>
		<?php
	}
}
