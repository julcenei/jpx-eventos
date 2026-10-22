<?php
/**
 * Leitura e gravação dos dados de um evento.
 *
 * Mantém as chaves do plugin antigo (data_evento_inicio, hora_evento, local_evento…)
 * para que os eventos já cadastrados continuem valendo sem conversão de banco.
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_Evento {

	/** @var array Cache por requisição. */
	private static $cache = array();

	/**
	 * Limpa o cache de um evento.
	 *
	 * @param int $id ID.
	 */
	public static function limpar_cache( $id ) {
		unset( self::$cache[ (int) $id ] );
	}

	/**
	 * Dados normalizados de um evento.
	 *
	 * @param int|WP_Post $post Post.
	 * @return array|null
	 */
	public static function dados( $post ) {
		$post = get_post( $post );
		if ( ! $post || JPX_EV_Tipos::CPT !== $post->post_type ) {
			return null;
		}
		$id = (int) $post->ID;
		if ( isset( self::$cache[ $id ] ) ) {
			return self::$cache[ $id ];
		}
		$m = function ( $k ) use ( $id ) {
			return get_post_meta( $id, $k, true );
		};

		$inicio = jpx_ev_ymd_para_iso( $m( 'data_evento_inicio' ) );
		if ( ! $inicio ) {
			$inicio = get_post_time( 'Y-m-d', false, $post );
		}
		$varios = (bool) $m( 'evento_varios_dias' );
		$fim    = $varios ? jpx_ev_ymd_para_iso( $m( 'data_evento_fim' ) ) : '';
		if ( ! $fim || $fim < $inicio ) {
			$fim    = $inicio;
			$varios = false;
		}

		$tipos   = get_the_terms( $id, JPX_EV_Tipos::TIPO );
		$tipo    = ( $tipos && ! is_wp_error( $tipos ) ) ? $tipos[0] : null;
		$nucleos = get_the_terms( $id, JPX_EV_Tipos::NUCLEO );
		$nucleo  = ( $nucleos && ! is_wp_error( $nucleos ) ) ? $nucleos[0] : null;
		$locais  = get_the_terms( $id, JPX_EV_Tipos::LOCAL );
		$local_t = ( $locais && ! is_wp_error( $locais ) ) ? $locais[0] : null;

		$nucleo_url = '';
		if ( $nucleo ) {
			$nucleo_url = esc_url_raw( get_term_meta( $nucleo->term_id, 'jpx_pagina', true ) );
			if ( ! $nucleo_url ) {
				$link       = get_term_link( $nucleo );
				$nucleo_url = is_wp_error( $link ) ? '' : $link;
			}
		}

		$regra = JPX_EV_Recorrencia::normalizar( $m( '_jpx_recorrencia' ), $inicio );
		$exc   = $m( '_jpx_excecoes' );

		$local_nome = (string) $m( 'local_evento' );
		$endereco   = (string) $m( '_jpx_endereco' );
		$cidade     = (string) $m( 'cidade_evento' );

		$valor_tipo = (string) $m( '_jpx_valor_tipo' );
		$valor      = (string) $m( '_jpx_valor' );
		$publico    = (string) $m( '_jpx_publico' );
		$pubs       = jpx_ev_publicos();

		$d = array(
			'id'             => $id,
			'status'         => $post->post_status,
			'titulo'         => get_the_title( $post ),
			'url'            => get_permalink( $post ),
			'tipo'           => $tipo,
			'tipo_nome'      => $tipo ? $tipo->name : '',
			'cores'          => JPX_EV_Tipos::cores( $tipo ),
			'nucleo'         => $nucleo,
			'nucleo_nome'    => $nucleo ? $nucleo->name : '',
			'nucleo_url'     => $nucleo_url,
			'inicio'         => $inicio,
			'fim'            => $fim,
			'varios_dias'    => $varios,
			'dia_inteiro'    => (bool) $m( '_jpx_dia_inteiro' ),
			'hora_inicio'    => jpx_ev_sanitizar_hora( $m( '_jpx_hora_inicio' ) ),
			'hora_fim'       => jpx_ev_sanitizar_hora( $m( '_jpx_hora_fim' ) ),
			'hora_legado'    => (string) $m( 'hora_evento' ),
			'local_id'       => $local_t ? (int) $local_t->term_id : 0,
			'local_nome'     => $local_nome,
			'endereco'       => $endereco,
			'cidade'         => $cidade,
			'mapa_url'       => self::mapa_url( $local_nome, $endereco, $cidade ),
			'resumo'         => (string) $m( 'descricao_curta' ),
			'inscricao'      => esc_url_raw( (string) $m( '_jpx_inscricao' ) ),
			'valor_tipo'     => $valor_tipo,
			'valor'          => $valor,
			'valor_rotulo'   => self::rotulo_valor( $valor_tipo, $valor ),
			'valor_curto'    => 'solidario' === $valor_tipo ? __( 'Solidário', 'jpx-eventos' ) : self::rotulo_valor( $valor_tipo, $valor ),
			'publico'        => $publico,
			'publico_rotulo' => isset( $pubs[ $publico ] ) ? $pubs[ $publico ] : '',
			'publico_curto'  => 'aberto' === $publico ? __( 'Aberto', 'jpx-eventos' ) : ( isset( $pubs[ $publico ] ) ? $pubs[ $publico ] : '' ),
			'recorrencia'    => $regra,
			'recorrente'     => JPX_EV_Recorrencia::repete( $regra ),
			'recorrencia_txt' => JPX_EV_Recorrencia::repete( $regra ) ? JPX_EV_Recorrencia::descrever( $regra ) : '',
			'excecoes'       => is_array( $exc ) ? $exc : array(),
			'programacao'    => self::ler_programacao( (string) $m( '_jpx_programacao' ) ),
			'programacao_txt' => (string) $m( '_jpx_programacao' ),
			'thumb_id'       => (int) get_post_thumbnail_id( $post ),
		);
		$d['horario'] = jpx_ev_formatar_horario( $d['hora_inicio'], $d['hora_fim'], $d['dia_inteiro'], $d['hora_legado'] );

		self::$cache[ $id ] = apply_filters( 'jpx_ev_dados_evento', $d, $post );
		return self::$cache[ $id ];
	}

	/**
	 * Rótulo do valor.
	 *
	 * @param string $tipo  gratuito|solidario|pago.
	 * @param string $valor Texto livre.
	 * @return string
	 */
	public static function rotulo_valor( $tipo, $valor ) {
		switch ( $tipo ) {
			case 'gratuito':
				return __( 'Gratuito', 'jpx-eventos' );
			case 'solidario':
				return $valor ? sprintf( __( 'Ingresso solidário: %s', 'jpx-eventos' ), $valor ) : __( 'Ingresso solidário', 'jpx-eventos' );
			case 'pago':
				return $valor ? $valor : __( 'Pago', 'jpx-eventos' );
		}
		return '';
	}

	/**
	 * Link do Google Maps.
	 *
	 * @param string $nome     Local.
	 * @param string $endereco Endereço.
	 * @param string $cidade   Cidade.
	 * @return string
	 */
	public static function mapa_url( $nome, $endereco, $cidade ) {
		$q = trim( implode( ', ', array_filter( array( $nome, $endereco, $cidade ) ) ) );
		return $q ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $q ) : '';
	}

	/**
	 * Converte o texto da programação em linhas [hora, texto].
	 *
	 * @param string $txt Texto (uma linha por item).
	 * @return array[]
	 */
	public static function ler_programacao( $txt ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $txt ) as $linha ) {
			$linha = trim( $linha );
			if ( '' === $linha ) {
				continue;
			}
			if ( preg_match( '/^(\d{1,2}[:h]\d{2}|\d{1,2}h)\s*[|\-–—]?\s*(.+)$/u', $linha, $mm ) ) {
				$h     = jpx_ev_interpretar_hora( $mm[1] );
				$out[] = array( $h['inicio'] ? $h['inicio'] : $mm[1], $mm[2] );
			} else {
				$out[] = array( '', $linha );
			}
		}
		return $out;
	}

	/**
	 * Grava os dados do evento.
	 *
	 * @param int   $id Post.
	 * @param array $c  Campos.
	 */
	public static function salvar( $id, array $c ) {
		$id = (int) $id;

		$inicio = jpx_ev_sanitizar_data( $c['inicio'] ?? '' );
		if ( ! $inicio ) {
			$inicio = jpx_ev_hoje();
		}
		$varios = ! empty( $c['varios_dias'] );
		$fim    = $varios ? jpx_ev_sanitizar_data( $c['fim'] ?? '' ) : '';
		if ( ! $fim || $fim <= $inicio ) {
			$varios = false;
			$fim    = '';
		}
		$dia_todo = ! empty( $c['dia_inteiro'] );
		$h_ini    = $dia_todo ? '' : jpx_ev_sanitizar_hora( $c['hora_inicio'] ?? '' );
		$h_fim    = $dia_todo ? '' : jpx_ev_sanitizar_hora( $c['hora_fim'] ?? '' );
		if ( $h_fim && $h_ini && $h_fim <= $h_ini && ! $varios ) {
			$h_fim = '';
		}

		update_post_meta( $id, 'data_evento_inicio', wp_slash( jpx_ev_iso_para_ymd( $inicio ) ) );
		update_post_meta( $id, 'evento_varios_dias', wp_slash( $varios ? 1 : 0 ) );
		if ( $varios ) {
			update_post_meta( $id, 'data_evento_fim', wp_slash( jpx_ev_iso_para_ymd( $fim ) ) );
		} else {
			delete_post_meta( $id, 'data_evento_fim' );
		}
		update_post_meta( $id, '_jpx_dia_inteiro', wp_slash( $dia_todo ? 1 : 0 ) );
		update_post_meta( $id, '_jpx_hora_inicio', wp_slash( $h_ini ) );
		update_post_meta( $id, '_jpx_hora_fim', wp_slash( $h_fim ) );
		// Mantém o campo antigo legível; não apaga um horário antigo não convertido.
		if ( $h_ini || $dia_todo ) {
			update_post_meta( $id, 'hora_evento', wp_slash( jpx_ev_formatar_horario( $h_ini, $h_fim, $dia_todo ) ) );
		}

		// Taxonomias.
		foreach ( array( 'tipo' => JPX_EV_Tipos::TIPO, 'nucleo' => JPX_EV_Tipos::NUCLEO ) as $campo => $tax ) {
			if ( array_key_exists( $campo, $c ) ) {
				$tid = (int) $c[ $campo ];
				wp_set_object_terms( $id, $tid > 0 ? array( $tid ) : array(), $tax );
			}
		}

		// Local.
		if ( array_key_exists( 'local_id', $c ) || array_key_exists( 'local_nome', $c ) ) {
			$lid = (int) ( $c['local_id'] ?? 0 );
			if ( $lid > 0 && ( $t = get_term( $lid, JPX_EV_Tipos::LOCAL ) ) && ! is_wp_error( $t ) ) {
				$nome = $t->name;
				$end  = (string) get_term_meta( $lid, 'jpx_endereco', true );
				$cid  = (string) get_term_meta( $lid, 'jpx_cidade', true );
			} else {
				$lid  = 0;
				$nome = sanitize_text_field( $c['local_nome'] ?? '' );
				$end  = sanitize_text_field( $c['local_endereco'] ?? '' );
				$cid  = sanitize_text_field( $c['local_cidade'] ?? '' );
				$tax_local = get_taxonomy( JPX_EV_Tipos::LOCAL );
				if ( $nome && ! empty( $c['salvar_local'] ) && $tax_local && current_user_can( $tax_local->cap->manage_terms ) ) {
					$existe = term_exists( $nome, JPX_EV_Tipos::LOCAL );
					$novo   = $existe ? $existe : wp_insert_term( $nome, JPX_EV_Tipos::LOCAL );
					if ( ! is_wp_error( $novo ) ) {
						$lid = (int) $novo['term_id'];
						update_term_meta( $lid, 'jpx_endereco', wp_slash( $end ) );
						update_term_meta( $lid, 'jpx_cidade', wp_slash( $cid ) );
					}
				}
			}
			wp_set_object_terms( $id, $lid ? array( $lid ) : array(), JPX_EV_Tipos::LOCAL );
			update_post_meta( $id, 'local_evento', wp_slash( $nome ) );
			update_post_meta( $id, '_jpx_endereco', wp_slash( $end ) );
			update_post_meta( $id, 'cidade_evento', wp_slash( $cid ) );
		}

		// Textos e opções.
		if ( array_key_exists( 'resumo', $c ) ) {
			update_post_meta( $id, 'descricao_curta', wp_slash( sanitize_textarea_field( $c['resumo'] ) ) );
		}
		if ( array_key_exists( 'inscricao', $c ) ) {
			update_post_meta( $id, '_jpx_inscricao', wp_slash( esc_url_raw( trim( (string) $c['inscricao'] ) ) ) );
		}
		if ( array_key_exists( 'valor_tipo', $c ) ) {
			$vt = in_array( $c['valor_tipo'], array( 'gratuito', 'solidario', 'pago' ), true ) ? $c['valor_tipo'] : '';
			update_post_meta( $id, '_jpx_valor_tipo', wp_slash( $vt ) );
			update_post_meta( $id, '_jpx_valor', wp_slash( 'gratuito' === $vt ? '' : sanitize_text_field( $c['valor'] ?? '' ) ) );
		}
		if ( array_key_exists( 'publico', $c ) ) {
			update_post_meta( $id, '_jpx_publico', wp_slash( array_key_exists( $c['publico'], jpx_ev_publicos() ) ? $c['publico'] : '' ) );
		}
		if ( array_key_exists( 'programacao', $c ) ) {
			update_post_meta( $id, '_jpx_programacao', wp_slash( sanitize_textarea_field( $c['programacao'] ) ) );
		}

		// Recorrência e exceções.
		if ( array_key_exists( 'recorrencia', $c ) ) {
			$regra = JPX_EV_Recorrencia::normalizar( $c['recorrencia'], $inicio );
			if ( $varios ) {
				$regra['freq'] = 'nenhuma';
			}
			update_post_meta( $id, '_jpx_recorrencia', wp_slash( $regra ) );
		}
		if ( array_key_exists( 'excecoes', $c ) ) {
			update_post_meta( $id, '_jpx_excecoes', wp_slash( self::sanitizar_excecoes( $c['excecoes'] ) ) );
		}

		self::limpar_cache( $id );
		JPX_EV_Ocorrencias::gerar( $id );
		do_action( 'jpx_ev_evento_salvo', $id, $c );
	}

	/**
	 * Sanitiza a lista de exceções de recorrência.
	 *
	 * @param mixed $exc Exceções.
	 * @return array
	 */
	public static function sanitizar_excecoes( $exc ) {
		$out = array();
		if ( is_string( $exc ) ) {
			$exc = json_decode( $exc, true );
		}
		if ( ! is_array( $exc ) ) {
			return $out;
		}
		foreach ( $exc as $data => $e ) {
			$data = jpx_ev_sanitizar_data( $data );
			if ( ! $data || ! is_array( $e ) ) {
				continue;
			}
			$status = ( $e['status'] ?? '' ) === 'cancelada' ? 'cancelada' : 'alterada';
			$item   = array(
				'status'      => $status,
				'local'       => sanitize_text_field( $e['local'] ?? '' ),
				'hora_inicio' => jpx_ev_sanitizar_hora( $e['hora_inicio'] ?? '' ),
				'hora_fim'    => jpx_ev_sanitizar_hora( $e['hora_fim'] ?? '' ),
				'nota'        => sanitize_text_field( $e['nota'] ?? '' ),
			);
			if ( 'alterada' === $status && ! $item['local'] && ! $item['hora_inicio'] && ! $item['nota'] ) {
				continue;
			}
			$out[ $data ] = $item;
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Link (com nonce) para duplicar um evento.
	 *
	 * @param int $id Evento.
	 * @return string
	 */
	public static function url_duplicar( $id ) {
		return wp_nonce_url( admin_url( 'admin.php?action=jpx_ev_duplicar&post=' . (int) $id ), 'jpx_ev_duplicar_' . (int) $id );
	}

	/**
	 * Duplica um evento como rascunho.
	 *
	 * @param int $id Original.
	 * @return int|WP_Error Novo ID.
	 */
	public static function duplicar( $id ) {
		$p = get_post( $id );
		if ( ! $p || JPX_EV_Tipos::CPT !== $p->post_type ) {
			return new WP_Error( 'jpx_ev', __( 'Evento não encontrado.', 'jpx-eventos' ) );
		}
		$novo = wp_insert_post(
			array(
				'post_type'    => JPX_EV_Tipos::CPT,
				'post_status'  => 'draft',
				'post_title'   => $p->post_title,
				'post_content' => $p->post_content,
				'post_author'  => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $novo ) ) {
			return $novo;
		}
		$pular = array( '_edit_lock', '_edit_last', '_jpx_excecoes', '_wp_old_slug' );
		foreach ( get_post_meta( $id ) as $k => $vals ) {
			if ( in_array( $k, $pular, true ) ) {
				continue;
			}
			foreach ( $vals as $v ) {
				add_post_meta( $novo, $k, wp_slash( maybe_unserialize( $v ) ) );
			}
		}
		foreach ( array( JPX_EV_Tipos::TIPO, JPX_EV_Tipos::NUCLEO, JPX_EV_Tipos::LOCAL ) as $tax ) {
			wp_set_object_terms( $novo, wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) ), $tax );
		}
		return $novo;
	}
}
