<?php
/**
 * Endpoints REST (namespace jpx-eventos/v1).
 *
 * Públicos:  GET agenda (fragmentos HTML da lista/calendário).
 * Painel:    GET admin/mes, POST admin/rapido, POST admin/mover, POST admin/previa.
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_Rest {

	const NS = 'jpx-eventos/v1';

	/**
	 * Hooks.
	 */
	public static function iniciar() {
		add_action( 'rest_api_init', array( __CLASS__, 'rotas' ) );
	}

	/**
	 * Registra rotas.
	 */
	public static function rotas() {
		register_rest_route(
			self::NS,
			'/agenda',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'agenda' ),
				'permission_callback' => '__return_true',
			)
		);
		$editor = function () {
			return current_user_can( 'edit_posts' );
		};
		register_rest_route( self::NS, '/admin/mes', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'admin_mes' ), 'permission_callback' => $editor ) );
		register_rest_route( self::NS, '/admin/rapido', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'admin_rapido' ), 'permission_callback' => $editor ) );
		register_rest_route( self::NS, '/admin/mover', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'admin_mover' ), 'permission_callback' => $editor ) );
		register_rest_route( self::NS, '/admin/previa', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'admin_previa' ), 'permission_callback' => $editor ) );
	}

	/**
	 * Fragmentos da agenda.
	 *
	 * @param WP_REST_Request $r Requisição.
	 * @return WP_REST_Response
	 */
	public static function agenda( WP_REST_Request $r ) {
		$atts = array(
			'visao'      => 'cal' === $r->get_param( 'visao' ) ? 'calendario' : 'lista',
			'tipo'       => jpx_ev_slugs( $r->get_param( 'fixo_tipo' ) ),
			'nucleo'     => jpx_ev_slugs( $r->get_param( 'fixo_nucleo' ) ),
			'por_pagina' => (int) $r->get_param( 'por_pagina' ) ? (int) $r->get_param( 'por_pagina' ) : jpx_ev_opcao( 'itens_por_pagina' ),
		);
		$fonte = array(
			'jpx_v'      => $r->get_param( 'visao' ),
			'jpx_aba'    => $r->get_param( 'aba' ),
			'jpx_tipo'   => $r->get_param( 'tipo' ),
			'jpx_nucleo' => $r->get_param( 'nucleo' ),
			'jpx_q'      => $r->get_param( 'q' ),
			'jpx_mes'    => $r->get_param( 'mes' ),
			'jpx_pag'    => $r->get_param( 'pag' ),
		);
		$base = wp_validate_redirect( esc_url_raw( (string) $r->get_param( 'base' ) ), '' );
		JPX_EV_Render::$base = $base ? remove_query_arg( array( 'jpx_v', 'jpx_aba', 'jpx_tipo', 'jpx_nucleo', 'jpx_q', 'jpx_mes', 'jpx_pag' ), $base ) : home_url( '/' );
		$e   = JPX_EV_Render::estado( $atts, array_filter( $fonte, 'is_scalar' ) );
		$out = array( 'contagem' => JPX_EV_Render::contagens( $e ) );
		if ( 'cal' === $e['visao'] ) {
			$c             = JPX_EV_Render::calendario( $e );
			$out['html']   = $c['html'];
			$out['titulo'] = $c['titulo'];
		} else {
			$l           = JPX_EV_Render::lista( $e, $e['pag'] > 1 );
			$out['html'] = $l['html'];
			$out['mais'] = $l['mais'];
		}
		$res = rest_ensure_response( $out );
		$res->header( 'Cache-Control', 'public, max-age=60' );
		return $res;
	}

	/**
	 * Ocorrências de um mês para o calendário do painel.
	 *
	 * @param WP_REST_Request $r Requisição.
	 * @return WP_REST_Response
	 */
	public static function admin_mes( WP_REST_Request $r ) {
		$mes = (string) $r->get_param( 'mes' );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $mes ) ) {
			$mes = wp_date( 'Y-m' );
		}
		$prim = jpx_ev_dt( $mes . '-01' );
		$de   = $prim->format( 'Y-m-d' );
		$ate  = $prim->modify( 'last day of this month' )->format( 'Y-m-d' );
		$r1   = JPX_EV_Ocorrencias::consultar( array( 'de' => $de, 'ate' => $ate, 'canceladas' => true ) );
		$evs  = array();
		foreach ( $r1['itens'] as $oc ) {
			$evs[] = array(
				'id'         => $oc['id'],
				'oc'         => $oc['oc_id'],
				'titulo'     => $oc['titulo'],
				'inicio'     => $oc['oc_inicio'],
				'fim'        => $oc['oc_fim'],
				'hora'       => $oc['oc_hora_inicio'],
				'horario'    => $oc['oc_horario'],
				'local'      => $oc['oc_local'],
				'tipo'       => $oc['tipo_nome'],
				'cor'        => $oc['cores']['forte'],
				'suave'      => $oc['cores']['suave'],
				'recorrente' => $oc['recorrente'],
				'regra'      => $oc['recorrencia_txt'],
				'status'     => $oc['oc_status'],
				'editar'     => get_edit_post_link( $oc['id'], 'raw' ),
				'ver'        => JPX_EV_Render::url_ocorrencia( $oc ),
				'duplicar'   => JPX_EV_Evento::url_duplicar( $oc['id'] ),
			);
		}
		// Rascunhos e agendados também aparecem no painel.
		$rasc = get_posts(
			array(
				'post_type'      => JPX_EV_Tipos::CPT,
				'post_status'    => array( 'draft', 'future', 'pending' ),
				'posts_per_page' => 100,
				'meta_query'     => array(
					array(
						'key'     => 'data_evento_inicio',
						'value'   => array( str_replace( '-', '', $de ), str_replace( '-', '', $ate ) ),
						'compare' => 'BETWEEN',
					),
				),
			)
		);
		foreach ( $rasc as $p ) {
			if ( ! current_user_can( 'edit_post', $p->ID ) ) {
				continue;
			}
			$d     = JPX_EV_Evento::dados( $p );
			$evs[] = array(
				'id'         => $p->ID,
				'oc'         => 0,
				'titulo'     => $d['titulo'] ? $d['titulo'] : __( '(sem título)', 'jpx-eventos' ),
				'inicio'     => $d['inicio'],
				'fim'        => $d['fim'],
				'hora'       => $d['hora_inicio'],
				'horario'    => $d['horario'],
				'local'      => $d['local_nome'],
				'tipo'       => $d['tipo_nome'],
				'cor'        => $d['cores']['forte'],
				'suave'      => $d['cores']['suave'],
				'recorrente' => $d['recorrente'],
				'regra'      => $d['recorrencia_txt'],
				'status'     => 'future' === $p->post_status ? 'agendado' : 'rascunho',
				'editar'     => get_edit_post_link( $p->ID, 'raw' ),
				'ver'        => get_preview_post_link( $p ),
				'duplicar'   => JPX_EV_Evento::url_duplicar( $p->ID ),
			);
		}
		return rest_ensure_response(
			array(
				'mes'      => $mes,
				'titulo'   => jpx_ev_formatar_data( $de, 'mes_ano' ),
				'hoje'     => jpx_ev_hoje(),
				'eventos'  => $evs,
				'feriados' => JPX_EV_Feriados::entre( $de, $ate ),
			)
		);
	}

	/**
	 * Cria um evento pelo calendário do painel (criação rápida).
	 *
	 * @param WP_REST_Request $r Requisição.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function admin_rapido( WP_REST_Request $r ) {
		$titulo = sanitize_text_field( (string) $r->get_param( 'titulo' ) );
		$data   = jpx_ev_sanitizar_data( (string) $r->get_param( 'data' ) );
		if ( '' === $titulo || ! $data ) {
			return new WP_Error( 'jpx_ev', __( 'Informe o nome e a data.', 'jpx-eventos' ), array( 'status' => 400 ) );
		}
		$publicar = current_user_can( 'publish_posts' ) && 'rascunho' !== $r->get_param( 'status' );
		$id       = wp_insert_post(
			array(
				'post_type'   => JPX_EV_Tipos::CPT,
				'post_status' => 'draft',
				'post_title'  => $titulo,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$repete = (string) $r->get_param( 'repete' );
		$regra  = array( 'freq' => 'nenhuma' );
		foreach ( JPX_EV_Recorrencia::sugestoes( $data ) as $s ) {
			if ( $s['chave'] === $repete ) {
				$regra = $s['regra'];
			}
		}
		JPX_EV_Evento::salvar(
			$id,
			array(
				'inicio'      => $data,
				'hora_inicio' => (string) $r->get_param( 'hora_inicio' ),
				'hora_fim'    => (string) $r->get_param( 'hora_fim' ),
				'tipo'        => (int) $r->get_param( 'tipo' ),
				'local_id'    => (int) $r->get_param( 'local' ),
				'valor_tipo'  => 'gratuito',
				'recorrencia' => $regra,
			)
		);
		if ( $publicar ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
		}
		return rest_ensure_response(
			array(
				'id'     => $id,
				'editar' => get_edit_post_link( $id, 'raw' ),
				'status' => get_post_status( $id ),
			)
		);
	}

	/**
	 * Move um evento (não recorrente) para outra data, mantendo a duração.
	 *
	 * @param WP_REST_Request $r Requisição.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function admin_mover( WP_REST_Request $r ) {
		$id   = (int) $r->get_param( 'id' );
		$nova = jpx_ev_sanitizar_data( (string) $r->get_param( 'data' ) );
		if ( ! $id || ! $nova || ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'jpx_ev', __( 'Não foi possível mover.', 'jpx-eventos' ), array( 'status' => 400 ) );
		}
		$d = JPX_EV_Evento::dados( $id );
		if ( ! $d ) {
			return new WP_Error( 'jpx_ev', __( 'Evento não encontrado.', 'jpx-eventos' ), array( 'status' => 404 ) );
		}
		if ( $d['recorrente'] ) {
			return new WP_Error( 'jpx_ev', __( 'Eventos recorrentes mudam de data na tela do evento.', 'jpx-eventos' ), array( 'status' => 400 ) );
		}
		$dias = (int) jpx_ev_dt( $d['inicio'] )->diff( jpx_ev_dt( $d['fim'] ) )->days;
		JPX_EV_Evento::salvar(
			$id,
			array(
				'inicio'      => $nova,
				'fim'         => jpx_ev_dt( $nova )->modify( '+' . $dias . ' days' )->format( 'Y-m-d' ),
				'varios_dias' => $dias > 0,
				'dia_inteiro' => $d['dia_inteiro'],
				'hora_inicio' => $d['hora_inicio'],
				'hora_fim'    => $d['hora_fim'],
			)
		);
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Prévia das próximas datas de uma regra (tela de cadastro).
	 *
	 * @param WP_REST_Request $r Requisição.
	 * @return WP_REST_Response
	 */
	public static function admin_previa( WP_REST_Request $r ) {
		$inicio = jpx_ev_sanitizar_data( (string) $r->get_param( 'inicio' ) );
		if ( ! $inicio ) {
			return rest_ensure_response( array( 'sugestoes' => array(), 'datas' => array(), 'descricao' => '' ) );
		}
		$regra = JPX_EV_Recorrencia::normalizar( (array) $r->get_param( 'regra' ), $inicio );
		$todas = JPX_EV_Recorrencia::repete( $regra ) ? JPX_EV_Recorrencia::expandir( $inicio, $regra, wp_date( 'Y-m-d', strtotime( '+10 years' ) ) ) : array( $inicio );
		$hoje  = jpx_ev_hoje();
		$prox  = array_values( array_filter( $todas, function ( $d ) use ( $hoje ) { return $d >= $hoje; } ) );
		$lista = array();
		foreach ( array_slice( $prox ? $prox : $todas, 0, 8 ) as $d ) {
			$lista[] = array( 'data' => $d, 'rotulo' => jpx_ev_formatar_data( $d, 'curto_ano' ) );
		}
		return rest_ensure_response(
			array(
				'sugestoes' => JPX_EV_Recorrencia::sugestoes( $inicio ),
				'descricao' => JPX_EV_Recorrencia::descrever( $regra ),
				'total'     => ( 'nunca' === $regra['fim'] || count( $todas ) >= JPX_EV_Recorrencia::LIMITE ) ? null : count( $todas ),
				'datas'     => $lista,
			)
		);
	}
}
