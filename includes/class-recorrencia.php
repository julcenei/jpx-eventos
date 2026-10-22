<?php
/**
 * Regras de recorrência: normalização, expansão em datas e descrição em português.
 *
 * Regra (array):
 *   freq         nenhuma|diaria|semanal|mensal
 *   intervalo    int ≥ 1
 *   dias_semana  int[] 0–6 (semanal)
 *   modo_mensal  dia|posicao (mensal)
 *   dia_mes      1–31 (mensal/dia)
 *   posicao      1–4 ou -1 = última (mensal/posicao)
 *   dia_semana   0–6 (mensal/posicao)
 *   fim          nunca|data|contagem
 *   ate          Y-m-d
 *   contagem     int
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_Recorrencia {

	const LIMITE = 800;

	/**
	 * Normaliza/sanitiza uma regra.
	 *
	 * @param mixed  $r      Regra bruta.
	 * @param string $inicio Data de início (para valores padrão).
	 * @return array
	 */
	public static function normalizar( $r, $inicio = '' ) {
		$r   = is_array( $r ) ? $r : array();
		$d   = jpx_ev_dt( $inicio );
		$dow = $d ? (int) $d->format( 'w' ) : 0;
		$out = array(
			'freq'        => in_array( $r['freq'] ?? '', array( 'diaria', 'semanal', 'mensal' ), true ) ? $r['freq'] : 'nenhuma',
			'intervalo'   => max( 1, min( 52, (int) ( $r['intervalo'] ?? 1 ) ) ),
			'dias_semana' => array(),
			'modo_mensal' => ( $r['modo_mensal'] ?? '' ) === 'dia' ? 'dia' : 'posicao',
			'dia_mes'     => max( 1, min( 31, (int) ( $r['dia_mes'] ?? ( $d ? $d->format( 'j' ) : 1 ) ) ) ),
			'posicao'     => (int) ( $r['posicao'] ?? self::posicao_no_mes( $inicio ) ),
			'dia_semana'  => isset( $r['dia_semana'] ) ? max( 0, min( 6, (int) $r['dia_semana'] ) ) : $dow,
			'fim'         => in_array( $r['fim'] ?? '', array( 'data', 'contagem' ), true ) ? $r['fim'] : 'nunca',
			'ate'         => jpx_ev_sanitizar_data( $r['ate'] ?? '' ),
			'contagem'    => max( 1, min( 500, (int) ( $r['contagem'] ?? 10 ) ) ),
		);
		if ( ! in_array( $out['posicao'], array( -1, 1, 2, 3, 4 ), true ) ) {
			$out['posicao'] = 1;
		}
		$dias = isset( $r['dias_semana'] ) ? (array) $r['dias_semana'] : array();
		foreach ( $dias as $x ) {
			$x = (int) $x;
			if ( $x >= 0 && $x <= 6 ) {
				$out['dias_semana'][] = $x;
			}
		}
		$out['dias_semana'] = array_values( array_unique( $out['dias_semana'] ) );
		sort( $out['dias_semana'] );
		if ( 'semanal' === $out['freq'] && ! $out['dias_semana'] ) {
			$out['dias_semana'] = array( $dow );
		}
		if ( 'data' === $out['fim'] && ! $out['ate'] ) {
			$out['fim'] = 'nunca';
		}
		return $out;
	}

	/**
	 * Indica se a regra repete.
	 *
	 * @param array $r Regra.
	 * @return bool
	 */
	public static function repete( $r ) {
		return is_array( $r ) && ! empty( $r['freq'] ) && 'nenhuma' !== $r['freq'];
	}

	/**
	 * Posição do dia no mês (1–4, ou -1 se for a última ocorrência daquele dia da semana).
	 *
	 * @param string $iso      Data.
	 * @param bool   $preferir_ultima Retornar -1 quando for a última (mesmo sendo a 4ª).
	 * @return int
	 */
	public static function posicao_no_mes( $iso, $preferir_ultima = false ) {
		$d = jpx_ev_dt( $iso );
		if ( ! $d ) {
			return 1;
		}
		$n = (int) ceil( (int) $d->format( 'j' ) / 7 );
		if ( $n >= 5 || ( $preferir_ultima && self::eh_ultima( $iso ) ) ) {
			return -1;
		}
		return $n;
	}

	/**
	 * A data é a última ocorrência daquele dia da semana no mês?
	 *
	 * @param string $iso Data.
	 * @return bool
	 */
	public static function eh_ultima( $iso ) {
		$d = jpx_ev_dt( $iso );
		return $d && (int) $d->modify( '+7 days' )->format( 'n' ) !== (int) $d->format( 'n' );
	}

	/**
	 * Expande a regra em datas de início (Y-m-d).
	 *
	 * @param string $inicio   Primeira data.
	 * @param array  $r        Regra normalizada.
	 * @param string $limite   Não gerar depois desta data (Y-m-d).
	 * @param string $desde    Não devolver datas anteriores a esta (elas ainda contam para "N vezes").
	 * @return string[]
	 */
	public static function expandir( $inicio, $r, $limite, $desde = '' ) {
		$ini = jpx_ev_dt( $inicio );
		if ( ! $ini ) {
			return array();
		}
		if ( ! self::repete( $r ) ) {
			return array( $inicio );
		}
		$r      = self::normalizar( $r, $inicio );
		$teto   = $limite;
		if ( 'data' === $r['fim'] && $r['ate'] && $r['ate'] < $teto ) {
			$teto = $r['ate'];
		}
		$por_contagem = 'contagem' === $r['fim'];
		$max    = $por_contagem ? $r['contagem'] : self::LIMITE;
		$datas   = array();
		$antes   = 0;
		$futuras = 0;
		$hoje    = jpx_ev_hoje();
		$guarda  = 0;

		$adicionar = function ( DateTimeImmutable $d ) use ( &$datas, &$antes, &$futuras, $hoje, $inicio, $desde, $teto, $max, $por_contagem ) {
			$iso = $d->format( 'Y-m-d' );
			if ( $iso < $inicio ) {
				return true;
			}
			if ( $iso > $teto ) {
				return false;
			}
			// "N vezes" conta desde o início; o limite de segurança conta só as datas futuras.
			if ( $por_contagem ? ( count( $datas ) + $antes >= $max ) : ( $futuras >= $max ) ) {
				return false;
			}
			if ( $iso >= $hoje ) {
				$futuras++;
			}
			if ( $desde && $iso < $desde ) {
				$antes++;
				return true;
			}
			$datas[] = $iso;
			return true;
		};

		switch ( $r['freq'] ) {
			case 'diaria':
				$d = $ini;
				while ( $guarda++ < 40000 && $adicionar( $d ) ) {
					$d = $d->modify( '+' . $r['intervalo'] . ' days' );
				}
				break;

			case 'semanal':
				$semana = $ini->modify( '-' . $ini->format( 'w' ) . ' days' );
				$segue  = true;
				while ( $segue && $guarda++ < 3000 ) {
					foreach ( $r['dias_semana'] as $dw ) {
						if ( ! $adicionar( $semana->modify( '+' . $dw . ' days' ) ) ) {
							$segue = false;
							break;
						}
					}
					$semana = $semana->modify( '+' . ( 7 * $r['intervalo'] ) . ' days' );
				}
				break;

			case 'mensal':
				$mes = $ini->modify( 'first day of this month' );
				while ( $guarda++ < 1200 ) {
					$alvo = 'dia' === $r['modo_mensal']
						? self::dia_do_mes( $mes, $r['dia_mes'] )
						: self::enesimo_dia_semana( $mes, $r['posicao'], $r['dia_semana'] );
					if ( $alvo && ! $adicionar( $alvo ) ) {
						break;
					}
					if ( $mes->format( 'Y-m-d' ) > $teto ) {
						break;
					}
					$mes = $mes->modify( '+' . $r['intervalo'] . ' months' );
				}
				break;
		}
		return $datas;
	}

	/**
	 * Dia N de um mês (null se não existir, ex.: 31 em abril).
	 *
	 * @param DateTimeImmutable $mes Primeiro dia do mês.
	 * @param int               $dia Dia.
	 * @return DateTimeImmutable|null
	 */
	private static function dia_do_mes( DateTimeImmutable $mes, $dia ) {
		if ( $dia > (int) $mes->format( 't' ) ) {
			return null;
		}
		return $mes->modify( '+' . ( $dia - 1 ) . ' days' );
	}

	/**
	 * N-ésimo dia da semana do mês (posicao -1 = último).
	 *
	 * @param DateTimeImmutable $mes Primeiro dia do mês.
	 * @param int               $pos Posição.
	 * @param int               $dow Dia da semana 0–6.
	 * @return DateTimeImmutable|null
	 */
	private static function enesimo_dia_semana( DateTimeImmutable $mes, $pos, $dow ) {
		if ( -1 === $pos ) {
			$ult  = $mes->modify( 'last day of this month' );
			$diff = ( (int) $ult->format( 'w' ) - $dow + 7 ) % 7;
			return $ult->modify( '-' . $diff . ' days' );
		}
		$diff = ( $dow - (int) $mes->format( 'w' ) + 7 ) % 7;
		$d    = $mes->modify( '+' . ( $diff + 7 * ( $pos - 1 ) ) . ' days' );
		return $d->format( 'n' ) === $mes->format( 'n' ) ? $d : null;
	}

	/**
	 * Descrição em português ("Todo mês, na última quinta-feira").
	 *
	 * @param array $r Regra normalizada.
	 * @return string
	 */
	public static function descrever( $r ) {
		if ( ! self::repete( $r ) ) {
			return __( 'Não se repete', 'jpx-eventos' );
		}
		$dn  = jpx_ev_nomes( 'dias' );
		$dp  = jpx_ev_nomes( 'dias_plural' );
		$n   = (int) $r['intervalo'];
		$ord = array( 1 => '1ª', 2 => '2ª', 3 => '3ª', 4 => '4ª', -1 => __( 'última', 'jpx-eventos' ) );
		switch ( $r['freq'] ) {
			case 'diaria':
				$t = 1 === $n ? __( 'Todo dia', 'jpx-eventos' ) : sprintf( __( 'A cada %d dias', 'jpx-eventos' ), $n );
				break;
			case 'semanal':
				if ( array( 1, 2, 3, 4, 5 ) === $r['dias_semana'] ) {
					$t = 1 === $n ? __( 'Todo dia útil (segunda a sexta)', 'jpx-eventos' ) : sprintf( __( 'A cada %d semanas, de segunda a sexta', 'jpx-eventos' ), $n );
					break;
				}
				$nomes = array_map(
					function ( $d ) use ( $dp ) {
						return $dp[ $d ];
					},
					$r['dias_semana']
				);
				$lista = count( $nomes ) > 1 ? implode( ', ', array_slice( $nomes, 0, -1 ) ) . ' e ' . end( $nomes ) : $nomes[0];
				$t     = ( 1 === $n ? __( 'Toda semana', 'jpx-eventos' ) : sprintf( __( 'A cada %d semanas', 'jpx-eventos' ), $n ) ) . ', ' . ( count( $nomes ) > 1 ? 'às ' : ( in_array( $r['dias_semana'][0], array( 0, 6 ), true ) ? 'aos ' : 'às ' ) ) . $lista;
				break;
			case 'mensal':
			default:
				$base = 1 === $n ? __( 'Todo mês', 'jpx-eventos' ) : sprintf( __( 'A cada %d meses', 'jpx-eventos' ), $n );
				if ( 'dia' === $r['modo_mensal'] ) {
					$t = $base . ', ' . sprintf( __( 'no dia %d', 'jpx-eventos' ), $r['dia_mes'] );
				} else {
					$artigo = in_array( (int) $r['dia_semana'], array( 0, 6 ), true ) ? 'no ' : 'na ';
					$ordem  = $ord[ $r['posicao'] ];
					if ( in_array( (int) $r['dia_semana'], array( 0, 6 ), true ) ) {
						$ordem = -1 === $r['posicao'] ? __( 'último', 'jpx-eventos' ) : str_replace( 'ª', 'º', $ordem );
					}
					$t = $base . ', ' . $artigo . $ordem . ' ' . $dn[ $r['dia_semana'] ];
				}
		}
		if ( 'data' === $r['fim'] && $r['ate'] ) {
			$t .= ', ' . sprintf( __( 'até %s', 'jpx-eventos' ), jpx_ev_formatar_data( $r['ate'], 'numerico' ) );
		} elseif ( 'contagem' === $r['fim'] ) {
			$t .= ', ' . sprintf( _n( '%d vez', '%d vezes', $r['contagem'], 'jpx-eventos' ), $r['contagem'] );
		}
		return $t;
	}

	/**
	 * Sugestões prontas para o campo "Repetição", a partir da data escolhida.
	 *
	 * @param string $inicio Y-m-d.
	 * @return array[] Cada item: [ 'chave' => string, 'rotulo' => string, 'regra' => array ]
	 */
	public static function sugestoes( $inicio ) {
		$d = jpx_ev_dt( $inicio );
		if ( ! $d ) {
			return array();
		}
		$dow  = (int) $d->format( 'w' );
		$pos  = self::posicao_no_mes( $inicio );
		$base = array( 'fim' => 'nunca' );
		$s    = array(
			array( 'chave' => 'nenhuma', 'regra' => array( 'freq' => 'nenhuma' ) ),
			array( 'chave' => 'semanal', 'regra' => $base + array( 'freq' => 'semanal', 'dias_semana' => array( $dow ) ) ),
		);
		if ( -1 !== $pos ) {
			$s[] = array( 'chave' => 'mensal_pos', 'regra' => $base + array( 'freq' => 'mensal', 'modo_mensal' => 'posicao', 'posicao' => $pos, 'dia_semana' => $dow ) );
		}
		if ( self::eh_ultima( $inicio ) ) {
			$s[] = array( 'chave' => 'mensal_ultima', 'regra' => $base + array( 'freq' => 'mensal', 'modo_mensal' => 'posicao', 'posicao' => -1, 'dia_semana' => $dow ) );
		}
		$s[] = array( 'chave' => 'mensal_dia', 'regra' => $base + array( 'freq' => 'mensal', 'modo_mensal' => 'dia', 'dia_mes' => (int) $d->format( 'j' ) ) );
		if ( $dow >= 1 && $dow <= 5 ) {
			$s[] = array( 'chave' => 'dias_uteis', 'regra' => $base + array( 'freq' => 'semanal', 'dias_semana' => array( 1, 2, 3, 4, 5 ) ) );
		}
		foreach ( $s as &$item ) {
			$item['regra']  = self::normalizar( $item['regra'], $inicio );
			$item['rotulo'] = self::descrever( $item['regra'] );
		}
		return $s;
	}
}
