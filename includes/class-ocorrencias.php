<?php
/**
 * Tabela de ocorrências: cada data concreta de cada evento.
 *
 * Listagens e calendários consultam esta tabela (indexada), o que resolve
 * eventos de vários dias e recorrentes sem consultas pesadas em postmeta.
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_Ocorrencias {

	/**
	 * Nome da tabela.
	 *
	 * @return string
	 */
	public static function tabela() {
		global $wpdb;
		return $wpdb->prefix . 'jpx_ocorrencias';
	}

	/**
	 * Hooks.
	 */
	public static function iniciar() {
		add_action( 'transition_post_status', array( __CLASS__, 'ao_mudar_status' ), 20, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'ao_excluir' ) );
		add_action( 'jpx_ev_diario', array( __CLASS__, 'estender_recorrentes' ) );
		add_action( 'set_object_terms', array( __CLASS__, 'ao_mudar_termos' ), 10, 4 );
	}

	/**
	 * Cria/atualiza a tabela.
	 */
	public static function criar_tabela() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t       = self::tabela();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$t} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				evento_id bigint(20) unsigned NOT NULL,
				data_inicio date NOT NULL,
				data_fim date NOT NULL,
				hora_inicio varchar(5) NOT NULL DEFAULT '',
				hora_fim varchar(5) NOT NULL DEFAULT '',
				status varchar(12) NOT NULL DEFAULT 'ativa',
				local varchar(255) NOT NULL DEFAULT '',
				nota varchar(255) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				UNIQUE KEY evento_data (evento_id,data_inicio),
				KEY evento_id (evento_id),
				KEY data_inicio (data_inicio),
				KEY data_fim (data_fim)
			) {$charset};"
		);
	}

	/**
	 * Data limite de geração (hoje + horizonte).
	 *
	 * @return string
	 */
	public static function horizonte() {
		$meses = max( 3, min( 60, (int) jpx_ev_opcao( 'horizonte_meses' ) ) );
		return wp_date( 'Y-m-d', strtotime( '+' . $meses . ' months' ) );
	}

	/**
	 * (Re)gera as ocorrências de um evento.
	 *
	 * @param int $id Evento.
	 */
	public static function gerar( $id ) {
		global $wpdb;
		$id = (int) $id;
		$wpdb->delete( self::tabela(), array( 'evento_id' => $id ), array( '%d' ) );

		$post = get_post( $id );
		if ( ! $post || JPX_EV_Tipos::CPT !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}
		JPX_EV_Evento::limpar_cache( $id );
		$d = JPX_EV_Evento::dados( $post );
		if ( ! $d || ! $d['inicio'] ) {
			return;
		}

		$dur   = (int) jpx_ev_dt( $d['inicio'] )->diff( jpx_ev_dt( $d['fim'] ) )->days;
		$desde = wp_date( 'Y-m-d', strtotime( '-12 months' ) );
		$datas = $d['recorrente']
			? JPX_EV_Recorrencia::expandir( $d['inicio'], $d['recorrencia'], self::horizonte(), $desde )
			: array( $d['inicio'] );

		if ( ! $datas ) {
			return;
		}
		$valores = array();
		foreach ( $datas as $ini ) {
			$e      = isset( $d['excecoes'][ $ini ] ) ? $d['excecoes'][ $ini ] : null;
			$fim    = $dur ? jpx_ev_dt( $ini )->modify( '+' . $dur . ' days' )->format( 'Y-m-d' ) : $ini;
			$status = $e ? $e['status'] : 'ativa';
			$h_ini  = $e && $e['hora_inicio'] ? $e['hora_inicio'] : $d['hora_inicio'];
			$h_fim  = $d['hora_fim'];
			if ( $e && $e['hora_inicio'] ) {
				$h_fim = $e['hora_fim'];
				if ( ! $h_fim && $d['hora_inicio'] && $d['hora_fim'] ) {
					$m     = function ( $h ) { return (int) substr( $h, 0, 2 ) * 60 + (int) substr( $h, 3, 2 ); };
					$t     = min( 23 * 60 + 59, $m( $h_ini ) + max( 0, $m( $d['hora_fim'] ) - $m( $d['hora_inicio'] ) ) );
					$h_fim = sprintf( '%02d:%02d', intdiv( $t, 60 ), $t % 60 );
				}
			}
			$valores[] = $wpdb->prepare(
				'(%d,%s,%s,%s,%s,%s,%s,%s)',
				$id,
				$ini,
				$fim,
				$h_ini,
				$h_fim,
				$status,
				$e ? $e['local'] : '',
				$e ? $e['nota'] : ''
			);
		}
		foreach ( array_chunk( $valores, 200 ) as $lote ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- valores já preparados acima.
			$wpdb->query( 'INSERT IGNORE INTO ' . self::tabela() . ' (evento_id,data_inicio,data_fim,hora_inicio,hora_fim,status,local,nota) VALUES ' . implode( ',', $lote ) );
		}
	}

	/**
	 * Regenera todos os eventos publicados.
	 */
	public static function gerar_todos() {
		$ids = get_posts(
			array(
				'post_type'      => JPX_EV_Tipos::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $ids as $id ) {
			self::gerar( $id );
		}
		return count( $ids );
	}

	/**
	 * Rotina diária: estende o horizonte dos recorrentes.
	 */
	public static function estender_recorrentes() {
		$ids = get_posts(
			array(
				'post_type'      => JPX_EV_Tipos::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_jpx_recorrencia',
						'value'   => '"nenhuma"',
						'compare' => 'NOT LIKE',
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			self::gerar( $id );
		}
	}

	/**
	 * Publicar, despublicar, lixeira, agendado que entra no ar…
	 *
	 * @param string  $novo   Novo status.
	 * @param string  $antigo Status anterior.
	 * @param WP_Post $post   Post.
	 */
	public static function ao_mudar_status( $novo, $antigo, $post ) {
		if ( JPX_EV_Tipos::CPT === $post->post_type && $novo !== $antigo ) {
			self::gerar( $post->ID );
		}
	}

	/**
	 * Mantém a tabela coerente quando termos mudam (não afeta datas, só cache).
	 *
	 * @param int    $object_id ID.
	 * @param array  $terms     Termos.
	 * @param array  $tt_ids    IDs.
	 * @param string $taxonomy  Taxonomia.
	 */
	public static function ao_mudar_termos( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, array( JPX_EV_Tipos::TIPO, JPX_EV_Tipos::NUCLEO, JPX_EV_Tipos::LOCAL ), true ) ) {
			JPX_EV_Evento::limpar_cache( $object_id );
		}
	}

	/**
	 * Remove as ocorrências de um evento excluído.
	 *
	 * @param int $id Post.
	 */
	public static function ao_excluir( $id ) {
		global $wpdb;
		$wpdb->delete( self::tabela(), array( 'evento_id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Resolve um filtro de termo (id, slug ou lista) em term_taxonomy_ids.
	 *
	 * @param mixed  $valor Valor.
	 * @param string $tax   Taxonomia.
	 * @return int[]
	 */
	private static function tt_ids( $valor, $tax ) {
		$out = array();
		foreach ( array_filter( array_map( 'trim', is_array( $valor ) ? $valor : explode( ',', (string) $valor ) ) ) as $v ) {
			$t = is_numeric( $v ) ? get_term( (int) $v, $tax ) : get_term_by( 'slug', sanitize_title( $v ), $tax );
			if ( $t && ! is_wp_error( $t ) ) {
				$out[] = (int) $t->term_taxonomy_id;
			}
		}
		return $out;
	}

	/**
	 * Consulta ocorrências.
	 *
	 * @param array $args {
	 *   @type string $de          Ocorrências que terminam a partir desta data.
	 *   @type string $ate         Ocorrências que começam até esta data.
	 *   @type bool   $passados    Apenas encerradas (antes de hoje).
	 *   @type mixed  $tipo        Tipo(s).
	 *   @type mixed  $nucleo      Núcleo(s).
	 *   @type string $busca       Texto.
	 *   @type bool   $por_evento  Uma linha por evento (a próxima, ou a última se passados).
	 *   @type int    $limite      Quantidade (0 = todas).
	 *   @type int    $offset      Deslocamento.
	 *   @type int    $excluir     ID de evento a excluir.
	 *   @type int    $evento      Apenas este evento.
	 *   @type bool   $canceladas  Incluir canceladas.
	 * }
	 * @return array{itens:array,total:int}
	 */
	public static function consultar( array $args = array() ) {
		global $wpdb;
		$a = wp_parse_args(
			$args,
			array(
				'de'         => '',
				'ate'        => '',
				'passados'   => false,
				'tipo'       => '',
				'nucleo'     => '',
				'busca'      => '',
				'por_evento' => false,
				'limite'     => 0,
				'offset'     => 0,
				'excluir'    => 0,
				'evento'     => 0,
				'canceladas' => false,
			)
		);
		$t     = self::tabela();
		$hoje  = jpx_ev_hoje();
		$conds = array( "p.post_status = 'publish'", "p.post_password = ''" );

		if ( ! $a['canceladas'] ) {
			$conds[] = "o.status <> 'cancelada'";
		}
		if ( $a['passados'] ) {
			$conds[] = $wpdb->prepare( 'o.data_fim < %s', $hoje );
		} elseif ( $a['de'] ) {
			$conds[] = $wpdb->prepare( 'o.data_fim >= %s', $a['de'] );
		}
		if ( $a['ate'] ) {
			$conds[] = $wpdb->prepare( 'o.data_inicio <= %s', $a['ate'] );
		}
		if ( $a['evento'] ) {
			$conds[] = $wpdb->prepare( 'o.evento_id = %d', $a['evento'] );
		}
		if ( $a['excluir'] ) {
			$conds[] = $wpdb->prepare( 'o.evento_id <> %d', $a['excluir'] );
		}
		foreach ( array( 'tipo' => JPX_EV_Tipos::TIPO, 'nucleo' => JPX_EV_Tipos::NUCLEO ) as $campo => $tax ) {
			if ( '' === $a[ $campo ] || null === $a[ $campo ] ) {
				continue;
			}
			$tt = self::tt_ids( $a[ $campo ], $tax );
			if ( ! $tt ) {
				return array( 'itens' => array(), 'total' => 0 );
			}
			$conds[] = "o.evento_id IN (SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (" . implode( ',', array_map( 'intval', $tt ) ) . '))';
		}
		$busca = trim( (string) $a['busca'] );
		if ( '' !== $busca ) {
			$like    = '%' . $wpdb->esc_like( $busca ) . '%';
			$conds[] = $wpdb->prepare(
				"(p.post_title LIKE %s OR o.evento_id IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('local_evento','descricao_curta') AND meta_value LIKE %s))",
				$like,
				$like
			);
		}
		$where = implode( ' AND ', $conds );
		$desc  = $a['passados'];
		$ord   = $desc ? 'DESC' : 'ASC';
		$lim   = (int) $a['limite'] > 0 ? $wpdb->prepare( ' LIMIT %d OFFSET %d', $a['limite'], max( 0, (int) $a['offset'] ) ) : '';

		if ( $a['por_evento'] ) {
			$agg   = $desc ? 'MAX' : 'MIN';
			$base  = "FROM {$t} o INNER JOIN {$wpdb->posts} p ON p.ID = o.evento_id WHERE {$where}";
			$sql   = "SELECT o.* FROM {$t} o INNER JOIN {$wpdb->posts} p ON p.ID = o.evento_id
				INNER JOIN (SELECT o.evento_id AS eid, {$agg}(o.data_inicio) AS d {$base} GROUP BY o.evento_id) x
				ON x.eid = o.evento_id AND x.d = o.data_inicio
				WHERE {$where}
				ORDER BY o.data_inicio {$ord}, o.hora_inicio {$ord}, o.id {$ord}{$lim}";
			$total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT o.evento_id) {$base}" ); // phpcs:ignore
		} else {
			$sql   = "SELECT o.* FROM {$t} o INNER JOIN {$wpdb->posts} p ON p.ID = o.evento_id WHERE {$where}
				ORDER BY o.data_inicio {$ord}, o.hora_inicio {$ord}, o.id {$ord}{$lim}";
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} o INNER JOIN {$wpdb->posts} p ON p.ID = o.evento_id WHERE {$where}" ); // phpcs:ignore
		}
		$linhas = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$itens  = array();
		$vistos = array();
		foreach ( (array) $linhas as $l ) {
			if ( $a['por_evento'] ) {
				if ( isset( $vistos[ $l->evento_id ] ) ) {
					continue;
				}
				$vistos[ $l->evento_id ] = 1;
			}
			$oc = self::montar( $l );
			if ( $oc ) {
				$itens[] = $oc;
			}
		}
		return array( 'itens' => $itens, 'total' => $total );
	}

	/**
	 * Junta os dados do evento com os da ocorrência.
	 *
	 * @param object $l Linha da tabela.
	 * @return array|null
	 */
	public static function montar( $l ) {
		$d = JPX_EV_Evento::dados( (int) $l->evento_id );
		if ( ! $d ) {
			return null;
		}
		$hoje                 = jpx_ev_hoje();
		$d['oc_id']           = (int) $l->id;
		$d['oc_inicio']       = $l->data_inicio;
		$d['oc_fim']          = $l->data_fim;
		$d['oc_status']       = $l->status;
		$d['oc_nota']         = $l->nota;
		$d['oc_hora_inicio']  = $l->hora_inicio;
		$d['oc_hora_fim']     = $l->hora_fim;
		$d['oc_local']        = $l->local ? $l->local : $d['local_nome'];
		$d['oc_local_alterado'] = (bool) $l->local;
		if ( $l->local ) {
			// Local trocado só nesta data: o endereço salvo do evento não vale.
			$d['endereco'] = '';
			$d['mapa_url'] = JPX_EV_Evento::mapa_url( $l->local, '', $d['cidade'] );
		}
		$d['oc_horario']      = jpx_ev_formatar_horario( $l->hora_inicio, $l->hora_fim, $d['dia_inteiro'], $d['hora_legado'] );
		$d['passado']         = $l->data_fim < $hoje;
		$d['hoje']            = $l->data_inicio <= $hoje && $l->data_fim >= $hoje;
		return $d;
	}

	/**
	 * Ocorrências de um evento (para "Próximas datas").
	 *
	 * @param int $id     Evento.
	 * @param int $limite Quantidade.
	 * @return array
	 */
	public static function do_evento( $id, $limite = 6 ) {
		return self::consultar(
			array(
				'evento'     => $id,
				'de'         => jpx_ev_hoje(),
				'limite'     => $limite,
				'canceladas' => true,
			)
		)['itens'];
	}

	/**
	 * Próxima ocorrência (ou a última, se já passou).
	 *
	 * @param int $id Evento.
	 * @return array|null
	 */
	public static function proxima( $id ) {
		$r = self::consultar( array( 'evento' => $id, 'de' => jpx_ev_hoje(), 'limite' => 1 ) );
		if ( $r['itens'] ) {
			return $r['itens'][0];
		}
		$r = self::consultar( array( 'evento' => $id, 'passados' => true, 'limite' => 1 ) );
		return $r['itens'] ? $r['itens'][0] : null;
	}
}
