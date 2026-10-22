<?php
$T=[]; foreach (get_terms(['taxonomy'=>'jpx_tipo','hide_empty'=>false]) as $t) $T[$t->name]=$t->term_id;
$N=[]; foreach (['Jovens Empreendedores'=>'#7A3FC2','Mulheres Empreendedoras'=>'#C2410C','Comércio e Serviços'=>'#0B7A75','Indústria'=>'#8A5A2B'] as $n=>$c){ $r=wp_insert_term($n,'jpx_nucleo'); $N[$n]=$r['term_id']; update_term_meta($r['term_id'],'jpx_cor',$c); }
$L=[]; foreach (['Auditório principal'=>['Rua das Flores, 120 – Centro','Cidade Exemplo – SC'],'Sala de reuniões'=>['Rua das Flores, 120 – Centro','Cidade Exemplo – SC'],'Online'=>['','']] as $n=>$e){ $r=wp_insert_term($n,'jpx_local'); $L[$n]=$r['term_id']; update_term_meta($r['term_id'],'jpx_endereco',$e[0]); update_term_meta($r['term_id'],'jpx_cidade',$e[1]); }
$ev=[
 ['Planejamento financeiro para pequenas empresas','2026-10-27','19:00','21:30','Palestra','Comércio e Serviços','Auditório principal',['freq'=>'nenhuma'],'Como organizar o caixa, definir preços e se preparar para o fim de ano.','gratuito','aberto','https://forms.gle/exemplo',"18:30 Credenciamento e café\n19:00 Palestra\n20:45 Perguntas e encerramento"],
 ['Reunião mensal do núcleo de Indústria','2026-10-29','08:00','09:30','Reunião','Indústria','Sala de reuniões',['freq'=>'mensal','modo_mensal'=>'posicao','posicao'=>-1,'dia_semana'=>4,'fim'=>'data','ate'=>'2027-12-31'],'Encontro mensal das indústrias associadas para pautas do setor e ações conjuntas.','gratuito','nucleados','',''],
 ['Networking de jovens empreendedores','2026-11-04','19:00','22:00','Encontro','Jovens Empreendedores','Auditório principal',['freq'=>'nenhuma'],'Noite de conexões com rodadas rápidas de apresentação.','gratuito','aberto','https://forms.gle/exemplo',''],
 ['Café das Mulheres Empreendedoras','2026-11-05','07:30','09:00','Reunião','Mulheres Empreendedoras','Sala de reuniões',['freq'=>'mensal','modo_mensal'=>'posicao','posicao'=>1,'dia_semana'=>4,'fim'=>'nunca'],'Café da manhã mensal do núcleo, com troca de experiências.','gratuito','nucleados','',''],
 ['Liderança e gestão de equipes','2026-11-10','19:00','22:00','Curso','','Auditório principal',['freq'=>'nenhuma'],'Curso de três noites sobre feedback, delegação e rotina de liderança.','pago','associados','https://www.sympla.com.br/exemplo','','2026-11-12','R$ 180'],
 ['Vendas pelo WhatsApp: do atendimento ao pós-venda','2026-11-18','19:00','21:00','Palestra','Comércio e Serviços','Auditório principal',['freq'=>'nenhuma'],'Técnicas práticas para vender mais pelo WhatsApp sem perder o cliente.','solidario','aberto','https://forms.gle/exemplo','','','1 kg de alimento'],
 ['Segurança do trabalho: o que muda na NR-1','2026-11-24','14:00','17:00','Curso','Indústria','Online',['freq'=>'nenhuma'],'Capacitação para gestores e RH sobre a atualização da NR-1.','gratuito','associados','https://forms.gle/exemplo',''],
 ['Encontro de fim de ano dos associados','2026-12-10','19:30','23:00','Encontro','','Auditório principal',['freq'=>'nenhuma'],'Celebração com associados, parceiros e núcleos.','pago','associados','https://forms.gle/exemplo','','','R$ 90'],
 ['Palestra: tendências do varejo','2026-09-17','19:00','21:00','Palestra','Comércio e Serviços','Auditório principal',['freq'=>'nenhuma'],'O que muda no comportamento do consumidor.','gratuito','aberto','',''],
 ['Rodada de negócios','2026-08-20','08:30','12:00','Encontro','','Auditório principal',['freq'=>'nenhuma'],'Encontros rápidos entre compradores e fornecedores.','gratuito','associados','',''],
];
foreach($ev as $e){
  $id=wp_insert_post(['post_type'=>'evento','post_status'=>'publish','post_title'=>$e[0],'post_content'=>'<p>'.$e[8].'</p><p>Mais informações pelo telefone da associação.</p>']);
  JPX_EV_Evento::salvar($id,['inicio'=>$e[1],'hora_inicio'=>$e[2],'hora_fim'=>$e[3],'tipo'=>$T[$e[4]],'nucleo'=>$e[5]?$N[$e[5]]:0,'local_id'=>$L[$e[6]],'recorrencia'=>$e[7],'resumo'=>$e[8],'valor_tipo'=>$e[9],'publico'=>$e[10],'inscricao'=>$e[11],'programacao'=>$e[12],'varios_dias'=>!empty($e[13]),'fim'=>$e[13]??'','valor'=>$e[14]??'']);
  if(strpos($e[0],'Indústria')!==false) JPX_EV_Evento::salvar($id,['inicio'=>$e[1],'hora_inicio'=>$e[2],'hora_fim'=>$e[3],'excecoes'=>['2026-11-26'=>['status'=>'alterada','local'=>'Metalúrgica Exemplo (anfitriã)'],'2026-12-31'=>['status'=>'cancelada','nota'=>'Recesso de fim de ano']]]);
}
$ag=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Agenda','post_name'=>'agenda','post_content'=>'[jpx_agenda descricao="Palestras, cursos e encontros para associados e para a comunidade."]']);
wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Início','post_name'=>'inicio','post_content'=>'[jpx_eventos titulo="Próximos eventos" quantidade="3"]']);
$o=jpx_ev_opcao(); $o['pagina_agenda']=$ag; $o['local_padrao']=$L['Auditório principal']; update_option('jpx_eventos_opcoes',$o);
delete_option('jpx_eventos_config_salva');
echo "ok\n";
