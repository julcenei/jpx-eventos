<?php
/**
 * Registro do tipo de post "evento" e das taxonomias (tipo, núcleo, local).
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_Tipos {

	const CPT     = 'evento';
	const TIPO    = 'jpx_tipo';
	const NUCLEO  = 'jpx_nucleo';
	const LOCAL   = 'jpx_local';

	/**
	 * Hooks.
	 */
	public static function iniciar() {
		add_action( 'init', array( __CLASS__, 'registrar' ), 5 );
		add_action( 'init', array( __CLASS__, 'talvez_flush' ), 99 );
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'sem_gutenberg' ), 20, 2 );
	}

	/**
	 * Registra CPT e taxonomias.
	 */
	public static function registrar() {
		$slug  = sanitize_title( jpx_ev_opcao( 'slug' ) );
		$slug  = $slug ? $slug : 'eventos';
		$nsing = jpx_ev_opcao( 'nucleo_singular' );
		$nplu  = jpx_ev_opcao( 'nucleo_plural' );

		// Taxonomias antes do tipo de post: assim /eventos/tipo/x/ não é confundido com um evento.
		register_taxonomy(
			self::TIPO,
			self::CPT,
			array(
				'labels'            => array(
					'name'          => __( 'Tipos de evento', 'jpx-eventos' ),
					'singular_name' => __( 'Tipo', 'jpx-eventos' ),
					'menu_name'     => __( 'Tipos', 'jpx-eventos' ),
					'add_new_item'  => __( 'Adicionar tipo', 'jpx-eventos' ),
					'edit_item'     => __( 'Editar tipo', 'jpx-eventos' ),
					'search_items'  => __( 'Buscar tipos', 'jpx-eventos' ),
					'not_found'     => __( 'Nenhum tipo encontrado', 'jpx-eventos' ),
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'meta_box_cb'       => false,
				'rewrite'           => array( 'slug' => $slug . '/tipo', 'with_front' => false ),
			)
		);

		register_taxonomy(
			self::NUCLEO,
			self::CPT,
			array(
				'labels'            => array(
					'name'          => $nplu,
					'singular_name' => $nsing,
					'menu_name'     => $nplu,
					'add_new_item'  => sprintf( __( 'Adicionar %s', 'jpx-eventos' ), jpx_ev_minusc( $nsing ) ),
					'edit_item'     => sprintf( __( 'Editar %s', 'jpx-eventos' ), jpx_ev_minusc( $nsing ) ),
					'search_items'  => sprintf( __( 'Buscar %s', 'jpx-eventos' ), jpx_ev_minusc( $nplu ) ),
					'not_found'     => __( 'Nada encontrado', 'jpx-eventos' ),
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'meta_box_cb'       => false,
				'rewrite'           => array( 'slug' => $slug . '/' . sanitize_title( $nsing ), 'with_front' => false ),
			)
		);

		register_taxonomy(
			self::LOCAL,
			self::CPT,
			array(
				'labels'            => array(
					'name'          => __( 'Locais salvos', 'jpx-eventos' ),
					'singular_name' => __( 'Local', 'jpx-eventos' ),
					'menu_name'     => __( 'Locais salvos', 'jpx-eventos' ),
					'add_new_item'  => __( 'Adicionar local', 'jpx-eventos' ),
					'edit_item'     => __( 'Editar local', 'jpx-eventos' ),
					'search_items'  => __( 'Buscar locais', 'jpx-eventos' ),
					'not_found'     => __( 'Nenhum local salvo', 'jpx-eventos' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => true,
				'hierarchical'      => false,
				'show_admin_column' => false,
				'show_in_rest'      => false,
				'meta_box_cb'       => false,
				'rewrite'           => false,
			)
		);

		register_post_type(
			self::CPT,
			array(
				'labels'        => array(
					'name'               => __( 'Eventos', 'jpx-eventos' ),
					'singular_name'      => __( 'Evento', 'jpx-eventos' ),
					'add_new'            => __( 'Novo evento', 'jpx-eventos' ),
					'add_new_item'       => __( 'Novo evento', 'jpx-eventos' ),
					'edit_item'          => __( 'Editar evento', 'jpx-eventos' ),
					'new_item'           => __( 'Novo evento', 'jpx-eventos' ),
					'view_item'          => __( 'Ver evento', 'jpx-eventos' ),
					'search_items'       => __( 'Buscar eventos', 'jpx-eventos' ),
					'not_found'          => __( 'Nenhum evento encontrado', 'jpx-eventos' ),
					'not_found_in_trash' => __( 'Nenhum evento na lixeira', 'jpx-eventos' ),
					'all_items'          => __( 'Todos os eventos', 'jpx-eventos' ),
					'menu_name'          => __( 'Eventos', 'jpx-eventos' ),
				),
				'public'        => true,
				'has_archive'   => true,
				'rewrite'       => array( 'slug' => $slug, 'with_front' => false ),
				'supports'      => array( 'title', 'editor', 'thumbnail', 'author', 'revisions' ),
				'menu_icon'     => 'dashicons-calendar-alt',
				'menu_position' => 6,
				'show_in_rest'  => true,
			)
		);
	}

	/**
	 * Regrava as regras de URL quando o slug muda.
	 */
	public static function talvez_flush() {
		if ( get_option( 'jpx_eventos_flush' ) ) {
			delete_option( 'jpx_eventos_flush' );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Desliga o editor de blocos para eventos (usamos a tela própria de cadastro).
	 *
	 * @param bool   $usar Valor atual.
	 * @param string $tipo Tipo de post.
	 * @return bool
	 */
	public static function sem_gutenberg( $usar, $tipo ) {
		return self::CPT === $tipo ? false : $usar;
	}

	/**
	 * Cria os tipos padrão (uma vez).
	 */
	public static function criar_padroes() {
		self::registrar();
		if ( get_option( 'jpx_eventos_tipos_criados' ) ) {
			return;
		}
		$padroes = array(
			'Palestra' => '',
			'Reunião'  => '#2456C7',
			'Curso'    => '#B25E09',
			'Encontro' => '#7A3FC2',
		);
		foreach ( $padroes as $nome => $cor ) {
			if ( term_exists( $nome, self::TIPO ) ) {
				continue;
			}
			$t = wp_insert_term( $nome, self::TIPO );
			if ( ! is_wp_error( $t ) && $cor ) {
				update_term_meta( $t['term_id'], 'jpx_cor', $cor );
			}
		}
		update_option( 'jpx_eventos_tipos_criados', 1, false );
	}

	/**
	 * Cores de um termo (tipo ou núcleo). Sem cor definida, usa a cor principal.
	 *
	 * @param WP_Term|null $termo Termo.
	 * @return array{cor:string,forte:string,suave:string,css:string}
	 */
	public static function cores( $termo ) {
		$cor = $termo ? jpx_ev_sanitizar_cor( get_term_meta( $termo->term_id, 'jpx_cor', true ) ) : '';
		if ( ! $cor ) {
			return array(
				'cor'   => '',
				'forte' => jpx_ev_cor_forte( jpx_ev_opcao( 'cor_primaria' ) ),
				'suave' => jpx_ev_cor_suave( jpx_ev_opcao( 'cor_primaria' ), 0.88 ),
				'css'   => '--c:var(--jpx-strong);--c-soft:var(--jpx-soft)',
			);
		}
		$forte = jpx_ev_cor_forte( $cor );
		$suave = jpx_ev_cor_suave( $forte, 0.9 );
		return array(
			'cor'   => $cor,
			'forte' => $forte,
			'suave' => $suave,
			'css'   => '--c:' . $forte . ';--c-soft:' . $suave,
		);
	}
}
