<?php
/**
 * Ajuda dentro do painel: guia (manual), checklist de primeiros passos e dicas "?" nos campos.
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_Admin_Ajuda {

	const PAGINA = 'jpx-eventos-ajuda';

	/**
	 * Hooks.
	 */
	public static function iniciar() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'primeiros_passos' ), 5 );
		add_action( 'admin_post_jpx_ev_pp', array( __CLASS__, 'alternar_pp' ) );
		add_action( 'load-edit-tags.php', array( __CLASS__, 'marcar_tipos_vistos' ) );
	}

	/**
	 * Submenu "Ajuda".
	 */
	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . JPX_EV_Tipos::CPT, __( 'Guia do JPX Eventos', 'jpx-eventos' ), __( 'Ajuda', 'jpx-eventos' ), 'edit_posts', self::PAGINA, array( __CLASS__, 'pagina' ) );
	}

	/**
	 * URL do guia, opcionalmente numa seção.
	 *
	 * @param string $secao Âncora.
	 * @return string
	 */
	public static function url( $secao = '' ) {
		$u = admin_url( 'edit.php?post_type=' . JPX_EV_Tipos::CPT . '&page=' . self::PAGINA );
		return $secao ? add_query_arg( 'secao', sanitize_key( $secao ), $u ) : $u;
	}

	/**
	 * Botão "?" com explicação curta e link para o guia.
	 *
	 * @param string $texto Explicação.
	 * @param string $secao Seção do guia.
	 * @return string
	 */
	public static function dica( $texto, $secao = '' ) {
		$id = wp_unique_id( 'jpx-dica-' );
		$h  = '<span class="jpx-dica"><button type="button" class="jpx-dica__btn" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '" aria-label="' . esc_attr__( 'Ajuda sobre este campo', 'jpx-eventos' ) . '">?</button>';
		$h .= '<span class="jpx-dica__balao" id="' . esc_attr( $id ) . '" role="tooltip" hidden>' . esc_html( $texto );
		if ( $secao ) {
			$h .= ' <a href="' . esc_url( self::url( $secao ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ver no guia', 'jpx-eventos' ) . ' →</a>';
		}
		return $h . '</span></span>';
	}

	/**
	 * Telas do plugin.
	 *
	 * @return bool
	 */
	private static function na_tela_do_plugin() {
		$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $s ) {
			return false;
		}
		return JPX_EV_Tipos::CPT === $s->post_type || in_array( $s->taxonomy, array( JPX_EV_Tipos::TIPO, JPX_EV_Tipos::NUCLEO, JPX_EV_Tipos::LOCAL ), true );
	}

	/**
	 * CSS/JS das dicas (em todas as telas do plugin).
	 */
	public static function assets() {
		if ( ! self::na_tela_do_plugin() ) {
			return;
		}
		wp_enqueue_style( 'jpx-ev-admin', JPX_EV_URL . 'assets/css/admin.css', array(), JPX_EV_VERSION );
		wp_enqueue_script( 'jpx-ev-dicas', JPX_EV_URL . 'assets/js/admin-dicas.js', array(), JPX_EV_VERSION, true );
	}

	/**
	 * Página do guia (o manual em HTML que acompanha o plugin).
	 */
	public static function pagina() {
		$secao = isset( $_GET['secao'] ) ? sanitize_key( $_GET['secao'] ) : ''; // phpcs:ignore
		$src   = JPX_EV_URL . 'manual/index.html?v=' . rawurlencode( JPX_EV_VERSION ) . ( $secao ? '#' . $secao : '' );
		?>
		<div class="wrap jpx-ajuda">
			<div class="jpx-ajuda__topo">
				<h1 class="wp-heading-inline"><?php esc_html_e( 'Guia do JPX Eventos', 'jpx-eventos' ); ?></h1>
				<a class="page-title-action" href="<?php echo esc_url( $src ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Abrir em nova aba', 'jpx-eventos' ); ?></a>
				<a class="page-title-action" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . JPX_EV_Tipos::CPT ) ); ?>"><?php esc_html_e( 'Criar um evento agora', 'jpx-eventos' ); ?></a>
			</div>
			<iframe class="jpx-ajuda__guia" src="<?php echo esc_url( $src ); ?>" title="<?php esc_attr_e( 'Guia do JPX Eventos', 'jpx-eventos' ); ?>"></iframe>
		</div>
		<?php
	}

	/**
	 * Marca que a tela de tipos foi visitada (passo do checklist).
	 */
	public static function marcar_tipos_vistos() {
		if ( isset( $_GET['taxonomy'] ) && JPX_EV_Tipos::TIPO === sanitize_key( $_GET['taxonomy'] ) ) { // phpcs:ignore
			update_user_meta( get_current_user_id(), 'jpx_ev_tipos_vistos', 1 );
		}
	}

	/**
	 * Passos e situação de cada um.
	 *
	 * @return array[]
	 */
	public static function passos() {
		global $wpdb;
		$cpt     = JPX_EV_Tipos::CPT;
		$pag     = (int) jpx_ev_opcao( 'pagina_agenda' );
		$no_site = false;
		if ( $pag && get_post_status( $pag ) ) {
			$no_site = (bool) preg_match( '/\[(jpx_agenda|jpx_calendario|lista_eventos)/', (string) get_post_field( 'post_content', $pag ) );
		}
		if ( ! $no_site ) {
			$no_site = (bool) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('page','post') AND (post_content LIKE '%[jpx_agenda%' OR post_content LIKE '%[lista_eventos%' OR post_content LIKE '%[jpx_calendario%') LIMIT 1" ); // phpcs:ignore
		}
		$publicados = (int) wp_count_posts( $cpt )->publish;
		return array(
			array(
				'feito'  => (bool) get_option( 'jpx_eventos_config_salva' ),
				'titulo' => __( 'Ajuste cores e escolha a página da agenda', 'jpx-eventos' ),
				'texto'  => __( 'Deixe a agenda com a cara do site.', 'jpx-eventos' ),
				'url'    => admin_url( 'edit.php?post_type=' . $cpt . '&page=jpx-eventos-config' ),
				'acao'   => __( 'Abrir configurações', 'jpx-eventos' ),
			),
			array(
				'feito'  => (int) wp_count_terms( array( 'taxonomy' => JPX_EV_Tipos::LOCAL, 'hide_empty' => false ) ) > 0,
				'titulo' => __( 'Cadastre os locais mais usados', 'jpx-eventos' ),
				'texto'  => __( 'Depois é só clicar no local ao criar um evento.', 'jpx-eventos' ),
				'url'    => admin_url( 'edit-tags.php?taxonomy=' . JPX_EV_Tipos::LOCAL . '&post_type=' . $cpt ),
				'acao'   => __( 'Cadastrar locais', 'jpx-eventos' ),
			),
			array(
				'feito'  => (bool) get_user_meta( get_current_user_id(), 'jpx_ev_tipos_vistos', true ),
				'titulo' => __( 'Confira os tipos de evento e as cores', 'jpx-eventos' ),
				'texto'  => __( 'Palestra, Reunião, Curso… cada um com sua cor no calendário.', 'jpx-eventos' ),
				'url'    => admin_url( 'edit-tags.php?taxonomy=' . JPX_EV_Tipos::TIPO . '&post_type=' . $cpt ),
				'acao'   => __( 'Ver tipos', 'jpx-eventos' ),
			),
			array(
				'feito'  => $publicados > 0,
				'titulo' => __( 'Publique o primeiro evento', 'jpx-eventos' ),
				'texto'  => __( 'São 4 passos curtos — leva menos de um minuto.', 'jpx-eventos' ),
				'url'    => admin_url( 'post-new.php?post_type=' . $cpt ),
				'acao'   => __( 'Criar evento', 'jpx-eventos' ),
			),
			array(
				'feito'  => $no_site,
				'titulo' => __( 'Coloque a agenda no site', 'jpx-eventos' ),
				'texto'  => __( 'Cole [jpx_agenda] na página da agenda.', 'jpx-eventos' ),
				'url'    => self::url( 'no-site' ),
				'acao'   => __( 'Como fazer', 'jpx-eventos' ),
			),
		);
	}

	/**
	 * Checklist "Primeiros passos" no topo das telas do plugin.
	 */
	public static function primeiros_passos() {
		if ( ! self::na_tela_do_plugin() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$s = get_current_screen();
		if ( $s && 'post' === $s->base ) {
			return; // Não atrapalha o cadastro.
		}
		$passos = self::passos();
		$feitos = count( array_filter( wp_list_pluck( $passos, 'feito' ) ) );
		$oculto = get_user_meta( get_current_user_id(), 'jpx_ev_pp_oculto', true );
		if ( $oculto || count( $passos ) === $feitos ) {
			return;
		}
		$pct = round( 100 * $feitos / count( $passos ) );
		?>
		<div class="jpx-pp" role="region" aria-label="<?php esc_attr_e( 'Primeiros passos', 'jpx-eventos' ); ?>">
			<div class="jpx-pp__topo">
				<div>
					<h2><?php esc_html_e( 'Primeiros passos com a agenda', 'jpx-eventos' ); ?></h2>
					<p><?php echo esc_html( sprintf( __( '%1$d de %2$d concluídos', 'jpx-eventos' ), $feitos, count( $passos ) ) ); ?></p>
				</div>
				<div class="jpx-pp__acoes">
					<a class="button" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Ver o guia', 'jpx-eventos' ); ?></a>
					<a class="jpx-pp__ocultar" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jpx_ev_pp' ), 'jpx_ev_pp' ) ); ?>"><?php esc_html_e( 'Ocultar', 'jpx-eventos' ); ?></a>
				</div>
			</div>
			<div class="jpx-pp__barra" role="progressbar" aria-valuenow="<?php echo (int) $pct; ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?php echo (int) $pct; ?>%"></span></div>
			<ol class="jpx-pp__lista">
				<?php foreach ( $passos as $i => $p ) : ?>
					<li class="<?php echo $p['feito'] ? 'is-feito' : ''; ?>">
						<span class="jpx-pp__n" aria-hidden="true"><?php echo $p['feito'] ? '✓' : (int) ( $i + 1 ); ?></span>
						<span class="jpx-pp__txt"><strong><?php echo esc_html( $p['titulo'] ); ?></strong><small><?php echo esc_html( $p['texto'] ); ?></small></span>
						<?php if ( ! $p['feito'] ) : ?>
							<a href="<?php echo esc_url( $p['url'] ); ?>"><?php echo esc_html( $p['acao'] ); ?></a>
						<?php else : ?>
							<span class="screen-reader-text"><?php esc_html_e( 'Concluído', 'jpx-eventos' ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
	}

	/**
	 * Ocultar o checklist.
	 */
	public static function alternar_pp() {
		check_admin_referer( 'jpx_ev_pp' );
		update_user_meta( get_current_user_id(), 'jpx_ev_pp_oculto', 1 );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . JPX_EV_Tipos::CPT ) );
		exit;
	}
}
