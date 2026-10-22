<?php
/**
 * Arquivo de eventos / página de tipo ou núcleo.
 * Para personalizar, copie para seu-tema/jpx-eventos/arquivo.php.
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

$jpx_atts = array( 'cabecalho' => 'sim' );
if ( is_tax() ) {
	$jpx_termo = get_queried_object();
	if ( $jpx_termo instanceof WP_Term ) {
		$jpx_atts[ JPX_EV_Tipos::TIPO === $jpx_termo->taxonomy ? 'tipo' : 'nucleo' ] = $jpx_termo->slug;
		$jpx_atts['chamada']   = JPX_EV_Tipos::TIPO === $jpx_termo->taxonomy ? __( 'Agenda', 'jpx-eventos' ) : jpx_ev_opcao( 'nucleo_singular' );
		$jpx_atts['titulo']    = $jpx_termo->name;
		$jpx_atts['descricao'] = wp_strip_all_tags( term_description( $jpx_termo ) );
	}
} else {
	$jpx_atts['titulo'] = __( 'Agenda de eventos', 'jpx-eventos' );
}

get_header();
?>
<main id="main-content" class="jpx-arquivo">
	<div class="jpx-arquivo__container">
		<?php echo JPX_EV_Frontend::sc_agenda( $jpx_atts ); // phpcs:ignore ?>
	</div>
</main>
<?php
get_footer();
