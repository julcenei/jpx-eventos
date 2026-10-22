<?php
/**
 * Campos extras de tipos (cor), núcleos (cor, página) e locais (endereço, cidade).
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_Admin_Termos {

	/**
	 * Campos por taxonomia.
	 *
	 * @return array
	 */
	private static function campos() {
		return array(
			JPX_EV_Tipos::TIPO   => array(
				'jpx_cor' => array( 'tipo' => 'cor', 'rotulo' => __( 'Cor', 'jpx-eventos' ), 'ajuda' => __( 'Usada na capa, etiqueta e calendário. Vazio = cor principal da agenda.', 'jpx-eventos' ) ),
			),
			JPX_EV_Tipos::NUCLEO => array(
				'jpx_cor'    => array( 'tipo' => 'cor', 'rotulo' => __( 'Cor', 'jpx-eventos' ), 'ajuda' => '' ),
				'jpx_pagina' => array( 'tipo' => 'url', 'rotulo' => __( 'Página do grupo no site', 'jpx-eventos' ), 'ajuda' => __( 'Opcional. O link "Ver" na página do evento leva para cá.', 'jpx-eventos' ) ),
			),
			JPX_EV_Tipos::LOCAL  => array(
				'jpx_endereco' => array( 'tipo' => 'texto', 'rotulo' => __( 'Endereço', 'jpx-eventos' ), 'ajuda' => __( 'Rua, número, bairro.', 'jpx-eventos' ) ),
				'jpx_cidade'   => array( 'tipo' => 'texto', 'rotulo' => __( 'Cidade', 'jpx-eventos' ), 'ajuda' => __( 'Ex.: Caçador – SC', 'jpx-eventos' ) ),
			),
		);
	}

	/**
	 * Hooks.
	 */
	public static function iniciar() {
		foreach ( array( JPX_EV_Tipos::TIPO, JPX_EV_Tipos::NUCLEO, JPX_EV_Tipos::LOCAL ) as $tax ) {
			add_action( "{$tax}_add_form_fields", array( __CLASS__, 'form_novo' ) );
			add_action( "{$tax}_edit_form_fields", array( __CLASS__, 'form_editar' ), 10, 2 );
			add_action( "created_{$tax}", array( __CLASS__, 'salvar' ) );
			add_action( "edited_{$tax}", array( __CLASS__, 'salvar' ) );
			add_filter( "manage_edit-{$tax}_columns", array( __CLASS__, 'colunas' ) );
			add_filter( "manage_{$tax}_custom_column", array( __CLASS__, 'coluna' ), 10, 3 );
		}
	}

	/**
	 * Input de um campo.
	 *
	 * @param string $k     Chave.
	 * @param array  $c     Definição.
	 * @param string $valor Valor.
	 */
	private static function input( $k, $c, $valor ) {
		if ( 'cor' === $c['tipo'] ) {
			echo '<input type="color" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $valor ? $valor : '#0E7C66' ) . '"' . ( $valor ? '' : ' data-vazio="1"' ) . '> ';
			echo '<label><input type="checkbox" name="' . esc_attr( $k ) . '_padrao" value="1" ' . checked( ! $valor, true, false ) . '> ' . esc_html__( 'Usar a cor principal', 'jpx-eventos' ) . '</label>';
		} else {
			echo '<input type="' . ( 'url' === $c['tipo'] ? 'url' : 'text' ) . '" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $valor ) . '" class="regular-text">';
		}
		if ( $c['ajuda'] ) {
			echo '<p class="description">' . esc_html( $c['ajuda'] ) . '</p>';
		}
	}

	/**
	 * Formulário "Adicionar".
	 *
	 * @param string $tax Taxonomia.
	 */
	public static function form_novo( $tax ) {
		wp_nonce_field( 'jpx_ev_termo', 'jpx_ev_termo_nonce' );
		foreach ( self::campos()[ $tax ] as $k => $c ) {
			echo '<div class="form-field"><label for="' . esc_attr( $k ) . '">' . esc_html( $c['rotulo'] ) . '</label>';
			self::input( $k, $c, '' );
			echo '</div>';
		}
	}

	/**
	 * Formulário "Editar".
	 *
	 * @param WP_Term $t   Termo.
	 * @param string  $tax Taxonomia.
	 */
	public static function form_editar( $t, $tax ) {
		wp_nonce_field( 'jpx_ev_termo', 'jpx_ev_termo_nonce' );
		foreach ( self::campos()[ $tax ] as $k => $c ) {
			echo '<tr class="form-field"><th scope="row"><label for="' . esc_attr( $k ) . '">' . esc_html( $c['rotulo'] ) . '</label></th><td>';
			self::input( $k, $c, (string) get_term_meta( $t->term_id, $k, true ) );
			echo '</td></tr>';
		}
	}

	/**
	 * Salva.
	 *
	 * @param int $id Termo.
	 */
	public static function salvar( $id ) {
		if ( ! isset( $_POST['jpx_ev_termo_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['jpx_ev_termo_nonce'] ), 'jpx_ev_termo' ) || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$t = get_term( $id );
		if ( ! $t || is_wp_error( $t ) || ! isset( self::campos()[ $t->taxonomy ] ) ) {
			return;
		}
		foreach ( self::campos()[ $t->taxonomy ] as $k => $c ) {
			$v = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
			if ( 'cor' === $c['tipo'] ) {
				$v = ! empty( $_POST[ $k . '_padrao' ] ) ? '' : jpx_ev_sanitizar_cor( $v );
			} elseif ( 'url' === $c['tipo'] ) {
				$v = esc_url_raw( $v );
			}
			update_term_meta( $id, $k, wp_slash( $v ) );
		}
		// O local mudou: atualiza os eventos que o usam.
		if ( JPX_EV_Tipos::LOCAL === $t->taxonomy ) {
			$ids = get_objects_in_term( $id, JPX_EV_Tipos::LOCAL );
			foreach ( (array) $ids as $pid ) {
				update_post_meta( $pid, 'local_evento', wp_slash( $t->name ) );
				update_post_meta( $pid, '_jpx_endereco', wp_slash( get_term_meta( $id, 'jpx_endereco', true ) ) );
				update_post_meta( $pid, 'cidade_evento', wp_slash( get_term_meta( $id, 'jpx_cidade', true ) ) );
			}
		}
	}

	/**
	 * Colunas.
	 *
	 * @param array $c Colunas.
	 * @return array
	 */
	public static function colunas( $c ) {
		$tax = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : ''; // phpcs:ignore
		unset( $c['description'] );
		$novo = array();
		foreach ( $c as $k => $v ) {
			$novo[ $k ] = $v;
			if ( 'name' === $k ) {
				if ( JPX_EV_Tipos::LOCAL === $tax ) {
					$novo['jpx_endereco'] = __( 'Endereço', 'jpx-eventos' );
				} else {
					$novo['jpx_cor'] = __( 'Cor', 'jpx-eventos' );
				}
			}
		}
		return $novo;
	}

	/**
	 * Conteúdo das colunas.
	 *
	 * @param string $out Saída.
	 * @param string $col Coluna.
	 * @param int    $id  Termo.
	 * @return string
	 */
	public static function coluna( $out, $col, $id ) {
		if ( 'jpx_cor' === $col ) {
			$cor = JPX_EV_Tipos::cores( get_term( $id ) )['forte'];
			return '<span style="display:inline-block;width:18px;height:18px;border-radius:5px;vertical-align:middle;background:' . esc_attr( $cor ) . '"></span>';
		}
		if ( 'jpx_endereco' === $col ) {
			return esc_html( implode( ' · ', array_filter( array( get_term_meta( $id, 'jpx_endereco', true ), get_term_meta( $id, 'jpx_cidade', true ) ) ) ) );
		}
		return $out;
	}
}
