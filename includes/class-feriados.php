<?php
/**
 * Feriados nacionais do Brasil (fixos + Sexta-feira Santa).
 * Use o filtro `jpx_ev_feriados` para incluir feriados municipais.
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_Feriados {

	/**
	 * Feriados de um ano: [ 'Y-m-d' => 'Nome' ].
	 *
	 * @param int $ano Ano.
	 * @return array
	 */
	public static function do_ano( $ano ) {
		$ano = (int) $ano;
		$f   = array(
			"$ano-01-01" => __( 'Confraternização Universal', 'jpx-eventos' ),
			"$ano-04-21" => __( 'Tiradentes', 'jpx-eventos' ),
			"$ano-05-01" => __( 'Dia do Trabalho', 'jpx-eventos' ),
			"$ano-09-07" => __( 'Independência', 'jpx-eventos' ),
			"$ano-10-12" => __( 'Nossa Senhora Aparecida', 'jpx-eventos' ),
			"$ano-11-02" => __( 'Finados', 'jpx-eventos' ),
			"$ano-11-15" => __( 'Proclamação da República', 'jpx-eventos' ),
			"$ano-12-25" => __( 'Natal', 'jpx-eventos' ),
		);
		if ( $ano >= 2024 ) {
			$f[ "$ano-11-20" ] = __( 'Consciência Negra', 'jpx-eventos' );
		}
		$pascoa = self::pascoa( $ano );
		if ( $pascoa ) {
			$f[ $pascoa->modify( '-2 days' )->format( 'Y-m-d' ) ] = __( 'Sexta-feira Santa', 'jpx-eventos' );
		}
		return apply_filters( 'jpx_ev_feriados', $f, $ano );
	}

	/**
	 * Feriados entre duas datas.
	 *
	 * @param string $de  Y-m-d.
	 * @param string $ate Y-m-d.
	 * @return array
	 */
	public static function entre( $de, $ate ) {
		if ( ! jpx_ev_opcao( 'mostrar_feriados' ) ) {
			return array();
		}
		$out = array();
		for ( $a = (int) substr( $de, 0, 4 ); $a <= (int) substr( $ate, 0, 4 ); $a++ ) {
			foreach ( self::do_ano( $a ) as $d => $n ) {
				if ( $d >= $de && $d <= $ate ) {
					$out[ $d ] = $n;
				}
			}
		}
		return $out;
	}

	/**
	 * Domingo de Páscoa (algoritmo de Meeus/Jones/Butcher).
	 *
	 * @param int $ano Ano.
	 * @return DateTimeImmutable|null
	 */
	private static function pascoa( $ano ) {
		$a = $ano % 19;
		$b = intdiv( $ano, 100 );
		$c = $ano % 100;
		$d = intdiv( $b, 4 );
		$e = $b % 4;
		$f = intdiv( $b + 8, 25 );
		$g = intdiv( $b - $f + 1, 3 );
		$h = ( 19 * $a + $b - $d - $g + 15 ) % 30;
		$i = intdiv( $c, 4 );
		$k = $c % 4;
		$l = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
		$m = intdiv( $a + 11 * $h + 22 * $l, 451 );
		$mes = intdiv( $h + $l - 7 * $m + 114, 31 );
		$dia = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;
		return jpx_ev_dt( sprintf( '%04d-%02d-%02d', $ano, $mes, $dia ) );
	}
}
