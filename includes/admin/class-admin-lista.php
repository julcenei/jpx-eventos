<?php
/**
 * Lista de eventos no painel: colunas, ordenação por data e "Duplicar".
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_Admin_Lista {

	/**
	 * Hooks.
	 */
	public static function iniciar() {
		$cpt = JPX_EV_Tipos::CPT;
		add_filter( "manage_{$cpt}_posts_columns", array( __CLASS__, 'colunas' ) );
		add_action( "manage_{$cpt}_posts_custom_column", array( __CLASS__, 'coluna' ), 10, 2 );
		add_filter( "manage_edit-{$cpt}_sortable_columns", array( __CLASS__, 'ordenaveis' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'ordenar' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'acoes' ), 10, 2 );
		add_action( 'admin_action_jpx_ev_duplicar', array( __CLASS__, 'duplicar' ) );
		add_action( 'admin_head-edit.php', array( __CLASS__, 'css' ) );
	}

	/**
	 * Link de duplicar (atalho).
	 *
	 * @param int $id Evento.
	 * @return string
	 */
	public static function url_duplicar( $id ) {
		return JPX_EV_Evento::url_duplicar( $id );
	}

	/**
	 * Colunas.
	 *
	 * @param array $c Colunas.
	 * @return array
	 */
	public static function colunas( $c ) {
		$novo = array();
		foreach ( $c as $k => $v ) {
			if ( 'date' === $k ) {
				continue;
			}
			$novo[ $k ] = $v;
			if ( 'title' === $k ) {
				$novo['jpx_quando'] = __( 'Quando', 'jpx-eventos' );
				$novo['jpx_local']  = __( 'Local', 'jpx-eventos' );
			}
		}
		return $novo;
	}

	/**
	 * Conteúdo das colunas.
	 *
	 * @param string $col Coluna.
	 * @param int    $id  Post.
	 */
	public static function coluna( $col, $id ) {
		$d = JPX_EV_Evento::dados( $id );
		if ( ! $d ) {
			return;
		}
		if ( 'jpx_quando' === $col ) {
			$hoje = jpx_ev_hoje();
			if ( $d['recorrente'] ) {
				$p = JPX_EV_Ocorrencias::proxima( $id );
				echo '<strong>' . esc_html( $p ? jpx_ev_formatar_data( $p['oc_inicio'], 'curto_ano' ) : jpx_ev_formatar_data( $d['inicio'], 'curto_ano' ) ) . '</strong>';
				echo '<br><span class="jpx-lista__sub">↻ ' . esc_html( $d['recorrencia_txt'] ) . '</span>';
			} else {
				echo '<strong>' . esc_html( $d['varios_dias'] ? jpx_ev_formatar_periodo( $d['inicio'], $d['fim'] ) . ' ' . substr( $d['fim'], 0, 4 ) : jpx_ev_formatar_data( $d['inicio'], 'curto_ano' ) ) . '</strong>';
			}
			if ( $d['horario'] ) {
				echo '<br><span class="jpx-lista__sub">' . esc_html( $d['horario'] ) . '</span>';
			}
			if ( ! $d['recorrente'] && $d['fim'] < $hoje ) {
				echo ' <span class="jpx-lista__selo">' . esc_html__( 'Encerrado', 'jpx-eventos' ) . '</span>';
			}
		} elseif ( 'jpx_local' === $col ) {
			echo esc_html( implode( ' · ', array_filter( array( $d['local_nome'], $d['cidade'] ) ) ) );
		}
	}

	/**
	 * Colunas ordenáveis.
	 *
	 * @param array $c Colunas.
	 * @return array
	 */
	public static function ordenaveis( $c ) {
		$c['jpx_quando'] = 'jpx_data';
		return $c;
	}

	/**
	 * Ordena pela data do evento (mais recentes primeiro, por padrão).
	 *
	 * @param WP_Query $q Consulta.
	 */
	public static function ordenar( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || JPX_EV_Tipos::CPT !== $q->get( 'post_type' ) ) {
			return;
		}
		$ob = $q->get( 'orderby' );
		if ( ! $ob || 'jpx_data' === $ob ) {
			$ordem = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( $_GET['order'] ) ) ? 'ASC' : 'DESC'; // phpcs:ignore
			$q->set(
				'meta_query',
				array(
					'relation' => 'OR',
					'jpx_data' => array( 'key' => 'data_evento_inicio', 'compare' => 'EXISTS' ),
					array( 'key' => 'data_evento_inicio', 'compare' => 'NOT EXISTS' ),
				)
			);
			$q->set( 'orderby', array( 'jpx_data' => $ordem ) );
		}
	}

	/**
	 * Ação "Duplicar".
	 *
	 * @param array   $a    Ações.
	 * @param WP_Post $post Post.
	 * @return array
	 */
	public static function acoes( $a, $post ) {
		if ( JPX_EV_Tipos::CPT === $post->post_type && current_user_can( 'edit_post', $post->ID ) ) {
			$a['jpx_duplicar'] = '<a href="' . esc_url( self::url_duplicar( $post->ID ) ) . '">' . esc_html__( 'Duplicar', 'jpx-eventos' ) . '</a>';
		}
		return $a;
	}

	/**
	 * Duplica e abre o rascunho.
	 */
	public static function duplicar() {
		$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		check_admin_referer( 'jpx_ev_duplicar_' . $id );
		if ( ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'jpx-eventos' ) );
		}
		$novo = JPX_EV_Evento::duplicar( $id );
		if ( is_wp_error( $novo ) ) {
			wp_die( esc_html( $novo->get_error_message() ) );
		}
		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $novo ) );
		exit;
	}

	/**
	 * Estilo das colunas.
	 */
	public static function css() {
		if ( JPX_EV_Tipos::CPT !== ( get_current_screen()->post_type ?? '' ) ) {
			return;
		}
		echo '<style>.column-jpx_quando{width:210px}.jpx-lista__sub{color:#646970;font-size:12px}.jpx-lista__selo{display:inline-block;margin-top:4px;padding:1px 6px;border-radius:3px;background:#f0f0f1;color:#50575e;font-size:11px}</style>';
	}
}
