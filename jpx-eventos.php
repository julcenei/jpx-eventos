<?php
/**
 * Plugin Name:       JPX Eventos
 * Plugin URI:        https://jotapix.com.br
 * Description:       Agenda de eventos com calendário, cards, recorrência e cadastro rápido. Sucessor do Eventos JPX.
 * Version:           2.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jotapix Agência Criativa
 * Author URI:        https://jotapix.com.br
 * Text Domain:       jpx-eventos
 * License:           Proprietária
 */

defined( 'ABSPATH' ) || exit;

define( 'JPX_EV_VERSION', '2.1.0' );
define( 'JPX_EV_DB_VERSION', 1 );
define( 'JPX_EV_FILE', __FILE__ );
define( 'JPX_EV_DIR', plugin_dir_path( __FILE__ ) );
define( 'JPX_EV_URL', plugin_dir_url( __FILE__ ) );
define( 'JPX_EV_LEGADO', 'eventos-jpx/eventos-personalizados.php' );

require_once JPX_EV_DIR . 'includes/helpers.php';
require_once JPX_EV_DIR . 'includes/class-recorrencia.php';
require_once JPX_EV_DIR . 'includes/class-feriados.php';
require_once JPX_EV_DIR . 'includes/class-tipos.php';
require_once JPX_EV_DIR . 'includes/class-evento.php';
require_once JPX_EV_DIR . 'includes/class-ocorrencias.php';
require_once JPX_EV_DIR . 'includes/class-migracao.php';
require_once JPX_EV_DIR . 'includes/class-render.php';
require_once JPX_EV_DIR . 'includes/class-frontend.php';
require_once JPX_EV_DIR . 'includes/class-single.php';
require_once JPX_EV_DIR . 'includes/class-ics.php';
require_once JPX_EV_DIR . 'includes/class-rest.php';

if ( is_admin() ) {
	require_once JPX_EV_DIR . 'includes/admin/class-admin-editor.php';
	require_once JPX_EV_DIR . 'includes/admin/class-admin-lista.php';
	require_once JPX_EV_DIR . 'includes/admin/class-admin-calendario.php';
	require_once JPX_EV_DIR . 'includes/admin/class-admin-config.php';
	require_once JPX_EV_DIR . 'includes/admin/class-admin-termos.php';
	require_once JPX_EV_DIR . 'includes/admin/class-admin-ajuda.php';
}

register_activation_hook( __FILE__, array( 'JPX_EV_Migracao', 'ativar' ) );
register_deactivation_hook( __FILE__, array( 'JPX_EV_Migracao', 'desativar' ) );

add_action( 'plugins_loaded', 'jpx_ev_iniciar' );

/**
 * Inicializa todos os módulos.
 */
function jpx_ev_iniciar() {
	add_action(
		'init',
		function () {
			load_plugin_textdomain( 'jpx-eventos', false, dirname( plugin_basename( JPX_EV_FILE ) ) . '/languages' );
		},
		1
	);

	JPX_EV_Tipos::iniciar();
	JPX_EV_Ocorrencias::iniciar();
	JPX_EV_Migracao::iniciar();
	JPX_EV_Frontend::iniciar();
	JPX_EV_Single::iniciar();
	JPX_EV_ICS::iniciar();
	JPX_EV_Rest::iniciar();

	if ( is_admin() ) {
		JPX_EV_Admin_Editor::iniciar();
		JPX_EV_Admin_Lista::iniciar();
		JPX_EV_Admin_Calendario::iniciar();
		JPX_EV_Admin_Config::iniciar();
		JPX_EV_Admin_Termos::iniciar();
		JPX_EV_Admin_Ajuda::iniciar();
	}
}
