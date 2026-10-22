<?php
/**
 * Calendário no painel: ver o mês, criar com um clique no dia e arrastar para mudar a data.
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_Admin_Calendario {

	const PAGINA = 'jpx-eventos-calendario';

	/**
	 * Hooks.
	 */
	public static function iniciar() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'ordenar_menu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Submenu.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . JPX_EV_Tipos::CPT,
			__( 'Calendário de eventos', 'jpx-eventos' ),
			__( 'Calendário', 'jpx-eventos' ),
			'edit_posts',
			self::PAGINA,
			array( __CLASS__, 'pagina' ),
			0
		);
	}

	/**
	 * Coloca "Calendário" no topo do menu Eventos.
	 */
	public static function ordenar_menu() {
		global $submenu;
		$pai = 'edit.php?post_type=' . JPX_EV_Tipos::CPT;
		if ( empty( $submenu[ $pai ] ) ) {
			return;
		}
		$cal  = array();
		$rest = array();
		foreach ( $submenu[ $pai ] as $item ) {
			if ( self::PAGINA === $item[2] ) {
				$cal[] = $item;
			} else {
				$rest[] = $item;
			}
		}
		$submenu[ $pai ] = array_merge( $cal, $rest ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	}

	/**
	 * CSS/JS.
	 *
	 * @param string $hook Página.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, self::PAGINA ) ) {
			return;
		}
		wp_enqueue_style( 'jpx-ev-admin', JPX_EV_URL . 'assets/css/admin.css', array(), JPX_EV_VERSION );
		wp_enqueue_script( 'jpx-ev-cal', JPX_EV_URL . 'assets/js/admin-calendario.js', array(), JPX_EV_VERSION, true );

		$tipos = array();
		$lt = get_terms( array( 'taxonomy' => JPX_EV_Tipos::TIPO, 'hide_empty' => false ) );
		foreach ( is_wp_error( $lt ) ? array() : $lt as $t ) {
			$tipos[] = array( 'id' => $t->term_id, 'nome' => $t->name, 'cor' => JPX_EV_Tipos::cores( $t )['forte'] );
		}
		$locais = array();
		$ll = get_terms( array( 'taxonomy' => JPX_EV_Tipos::LOCAL, 'hide_empty' => false ) );
		foreach ( is_wp_error( $ll ) ? array() : $ll as $l ) {
			$locais[] = array( 'id' => $l->term_id, 'nome' => $l->name );
		}
		wp_localize_script(
			'jpx-ev-cal',
			'JPX_EV_CAL',
			array(
				'rest'        => esc_url_raw( rest_url( 'jpx-eventos/v1/' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'novo'        => admin_url( 'post-new.php?post_type=' . JPX_EV_Tipos::CPT ),
				'mes'         => isset( $_GET['mes'] ) && preg_match( '/^\d{4}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['mes'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['mes'] ) ) : wp_date( 'Y-m' ), // phpcs:ignore
				'tipos'       => $tipos,
				'locais'      => $locais,
				'localPadrao' => (int) jpx_ev_opcao( 'local_padrao' ),
				'podePublicar' => current_user_can( 'publish_posts' ),
				'dias'        => array_map( 'ucfirst', jpx_ev_nomes( 'dias_curto' ) ),
				'diasLongos'  => jpx_ev_nomes( 'dias' ),
				'meses'       => array_values( jpx_ev_nomes( 'meses' ) ),
				'txt'         => array(
					'novoEm'     => __( 'Novo evento', 'jpx-eventos' ),
					'nome'       => __( 'Nome do evento', 'jpx-eventos' ),
					'das'        => __( 'das', 'jpx-eventos' ),
					'as'         => __( 'às', 'jpx-eventos' ),
					'semLocal'   => __( 'Local a definir', 'jpx-eventos' ),
					'semTipo'    => __( 'Sem tipo', 'jpx-eventos' ),
					'publicar'   => __( 'Publicar', 'jpx-eventos' ),
					'rascunho'   => __( 'Salvar rascunho', 'jpx-eventos' ),
					'mais'       => __( 'Mais opções', 'jpx-eventos' ),
					'enter'      => __( 'Enter publica · Esc fecha', 'jpx-eventos' ),
					'editar'     => __( 'Editar', 'jpx-eventos' ),
					'ver'        => __( 'Ver no site', 'jpx-eventos' ),
					'duplicar'   => __( 'Duplicar', 'jpx-eventos' ),
					'fechar'     => __( 'Fechar', 'jpx-eventos' ),
					'naoRepete'  => __( 'Não se repete', 'jpx-eventos' ),
					'recorrente' => __( 'Evento recorrente: para mudar a data, abra o evento.', 'jpx-eventos' ),
					'soltar'     => __( 'Soltar aqui', 'jpx-eventos' ),
					'publicado'  => __( 'Evento publicado!', 'jpx-eventos' ),
					'movido'     => __( 'Data alterada.', 'jpx-eventos' ),
					'erro'       => __( 'Não foi possível salvar.', 'jpx-eventos' ),
					'rascunhoSelo' => __( 'Rascunho', 'jpx-eventos' ),
					'agendadoSelo' => __( 'Agendado', 'jpx-eventos' ),
					'canceladaSelo' => __( 'Cancelada', 'jpx-eventos' ),
				),
			)
		);
	}

	/**
	 * Página.
	 */
	public static function pagina() {
		?>
		<div class="wrap jpx-acal" data-jpx-acal>
			<div class="jpx-acal__topo">
				<div class="jpx-acal__titulo">
					<h1 class="wp-heading-inline"><?php esc_html_e( 'Calendário de eventos', 'jpx-eventos' ); ?></h1><?php echo JPX_EV_Admin_Ajuda::dica( __( 'O jeito mais rápido de cadastrar: clique num dia, digite o nome e aperte Enter.', 'jpx-eventos' ), 'calendario-painel' ); // phpcs:ignore ?>
					<a class="page-title-action" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . JPX_EV_Tipos::CPT ) ); ?>"><?php esc_html_e( 'Novo evento', 'jpx-eventos' ); ?></a>
				</div>
				<div class="jpx-acal__nav">
					<button type="button" class="button" data-nav="-1" aria-label="<?php esc_attr_e( 'Mês anterior', 'jpx-eventos' ); ?>">‹</button>
					<strong data-jpx-titulo></strong>
					<button type="button" class="button" data-nav="1" aria-label="<?php esc_attr_e( 'Próximo mês', 'jpx-eventos' ); ?>">›</button>
					<button type="button" class="button" data-nav="0"><?php esc_html_e( 'Hoje', 'jpx-eventos' ); ?></button>
				</div>
			</div>
			<div class="jpx-acal__dica">
				<span><strong><?php esc_html_e( 'Clique num dia', 'jpx-eventos' ); ?></strong> <?php esc_html_e( 'para criar um evento ali mesmo', 'jpx-eventos' ); ?></span>
				<span><strong><?php esc_html_e( 'Arraste', 'jpx-eventos' ); ?></strong> <?php esc_html_e( 'um evento para mudar a data', 'jpx-eventos' ); ?></span>
				<span><strong><?php esc_html_e( 'Clique num evento', 'jpx-eventos' ); ?></strong> <?php esc_html_e( 'para editar ou duplicar', 'jpx-eventos' ); ?></span>
			</div>
			<div class="jpx-acal__grade" data-jpx-grade aria-live="polite"></div>
			<div class="jpx-acal__aviso" data-jpx-aviso role="status" hidden></div>
		</div>
		<?php
	}
}
