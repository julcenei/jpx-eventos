<?php
/**
 * Calendários: arquivo .ics (evento e agenda assinável) e links Google/Outlook.
 *
 * URLs:
 *   ?jpx_ics=123            Evento (todas as próximas datas).
 *   ?jpx_ics=123&data=Y-m-d Uma data específica.
 *   ?jpx_ics=agenda         Agenda inteira (assinável; aceita &tipo= e &nucleo=).
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_ICS {

	/**
	 * Hooks.
	 */
	public static function iniciar() {
		add_action( 'template_redirect', array( __CLASS__, 'servir' ) );
	}

	/**
	 * URL do .ics de um evento.
	 *
	 * @param int    $id   Evento.
	 * @param string $data Data específica (opcional).
	 * @return string
	 */
	public static function url_evento( $id, $data = '' ) {
		$args = array( 'jpx_ics' => (int) $id );
		if ( $data ) {
			$args['data'] = $data;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * URL da agenda assinável.
	 *
	 * @param array $filtros tipo/nucleo.
	 * @return string
	 */
	public static function url_agenda( $filtros = array() ) {
		return add_query_arg( array_merge( array( 'jpx_ics' => 'agenda' ), array_filter( $filtros ) ), home_url( '/' ) );
	}

	/**
	 * Início e fim (DateTimeImmutable no fuso do site) de uma ocorrência.
	 *
	 * @param array $oc Ocorrência.
	 * @return array{0:DateTimeImmutable,1:DateTimeImmutable,2:bool} [inicio, fim, dia_inteiro]
	 */
	private static function intervalo( $oc ) {
		$tz   = wp_timezone();
		$todo = $oc['dia_inteiro'] || ! $oc['oc_hora_inicio'];
		if ( $todo ) {
			$a = DateTimeImmutable::createFromFormat( '!Y-m-d', $oc['oc_inicio'], $tz );
			$b = DateTimeImmutable::createFromFormat( '!Y-m-d', $oc['oc_fim'], $tz )->modify( '+1 day' );
			return array( $a, $b, true );
		}
		$a = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $oc['oc_inicio'] . ' ' . $oc['oc_hora_inicio'], $tz );
		if ( $oc['oc_hora_fim'] ) {
			$b = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $oc['oc_fim'] . ' ' . $oc['oc_hora_fim'], $tz );
		} else {
			$b = $a->modify( '+2 hours' );
		}
		if ( $b <= $a ) {
			$b = $a->modify( '+1 hour' );
		}
		return array( $a, $b, false );
	}

	/**
	 * Link "Adicionar ao Google Agenda".
	 *
	 * @param array $oc Ocorrência.
	 * @return string
	 */
	public static function url_google( $oc ) {
		list( $a, $b, $todo ) = self::intervalo( $oc );
		$utc   = new DateTimeZone( 'UTC' );
		$datas = $todo
			? $a->format( 'Ymd' ) . '/' . $b->format( 'Ymd' )
			: $a->setTimezone( $utc )->format( 'Ymd\THis\Z' ) . '/' . $b->setTimezone( $utc )->format( 'Ymd\THis\Z' );
		return add_query_arg(
			array(
				'action'   => 'TEMPLATE',
				'text'     => rawurlencode( $oc['titulo'] ),
				'dates'    => $datas,
				'details'  => rawurlencode( wp_html_excerpt( $oc['resumo'], 300, '…' ) . "\n\n" . $oc['url'] ),
				'location' => rawurlencode( implode( ', ', array_filter( array( $oc['oc_local'] ?? $oc['local_nome'], $oc['endereco'], $oc['cidade'] ) ) ) ),
			),
			'https://calendar.google.com/calendar/render'
		);
	}

	/**
	 * Link "Adicionar ao Outlook".
	 *
	 * @param array $oc Ocorrência.
	 * @return string
	 */
	public static function url_outlook( $oc ) {
		list( $a, $b, $todo ) = self::intervalo( $oc );
		$args = array(
			'path'     => '/calendar/action/compose',
			'rru'      => 'addevent',
			'subject'  => rawurlencode( $oc['titulo'] ),
			'startdt'  => rawurlencode( $todo ? $a->format( 'Y-m-d' ) : $a->format( 'c' ) ),
			'enddt'    => rawurlencode( $todo ? $b->format( 'Y-m-d' ) : $b->format( 'c' ) ),
			'location' => rawurlencode( implode( ', ', array_filter( array( $oc['oc_local'] ?? $oc['local_nome'], $oc['endereco'], $oc['cidade'] ) ) ) ),
			'body'     => rawurlencode( $oc['url'] ),
		);
		if ( $todo ) {
			$args['allday'] = 'true';
		}
		return add_query_arg( $args, 'https://outlook.live.com/calendar/0/deeplink/compose' );
	}

	/**
	 * Responde às URLs .ics.
	 */
	public static function servir() {
		if ( ! isset( $_GET['jpx_ics'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$alvo = sanitize_key( wp_unslash( $_GET['jpx_ics'] ) ); // phpcs:ignore
		$nome = 'agenda';
		if ( 'agenda' === $alvo ) {
			$itens = JPX_EV_Ocorrencias::consultar(
				array(
					'de'     => wp_date( 'Y-m-d', strtotime( '-30 days' ) ),
					'ate'    => wp_date( 'Y-m-d', strtotime( '+12 months' ) ),
					'tipo'   => isset( $_GET['tipo'] ) ? sanitize_title( wp_unslash( $_GET['tipo'] ) ) : '', // phpcs:ignore
					'nucleo' => isset( $_GET['nucleo'] ) ? sanitize_title( wp_unslash( $_GET['nucleo'] ) ) : '', // phpcs:ignore
					'limite' => 500,
				)
			)['itens'];
			$titulo = get_bloginfo( 'name' ) . ' — ' . __( 'Agenda', 'jpx-eventos' );
		} else {
			$id = (int) $alvo;
			if ( ! $id || 'publish' !== get_post_status( $id ) || JPX_EV_Tipos::CPT !== get_post_type( $id ) ) {
				status_header( 404 );
				exit;
			}
			$data = isset( $_GET['data'] ) ? jpx_ev_sanitizar_data( sanitize_text_field( wp_unslash( $_GET['data'] ) ) ) : ''; // phpcs:ignore
			$args = $data ? array( 'evento' => $id, 'de' => $data, 'ate' => $data, 'limite' => 1 ) : array( 'evento' => $id, 'de' => jpx_ev_hoje(), 'limite' => 100 );
			$itens = JPX_EV_Ocorrencias::consultar( $args )['itens'];
			if ( ! $itens ) {
				$p     = JPX_EV_Ocorrencias::proxima( $id );
				$itens = $p ? array( $p ) : array();
			}
			$titulo = get_the_title( $id );
			$nome   = sanitize_title( $titulo );
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$utc  = new DateTimeZone( 'UTC' );
		$l    = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Jotapix//JPX Eventos ' . JPX_EV_VERSION . '//PT-BR',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . self::esc( $titulo ),
			'X-WR-TIMEZONE:' . wp_timezone_string(),
		);
		foreach ( $itens as $oc ) {
			list( $a, $b, $todo ) = self::intervalo( $oc );
			$l[] = 'BEGIN:VEVENT';
			$l[] = 'UID:jpx-' . $oc['id'] . '-' . str_replace( '-', '', $oc['oc_inicio'] ) . '@' . $host;
			$l[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
			if ( $todo ) {
				$l[] = 'DTSTART;VALUE=DATE:' . $a->format( 'Ymd' );
				$l[] = 'DTEND;VALUE=DATE:' . $b->format( 'Ymd' );
			} else {
				$l[] = 'DTSTART:' . $a->setTimezone( $utc )->format( 'Ymd\THis\Z' );
				$l[] = 'DTEND:' . $b->setTimezone( $utc )->format( 'Ymd\THis\Z' );
			}
			$l[] = 'SUMMARY:' . self::esc( $oc['titulo'] );
			$loc = implode( ', ', array_filter( array( $oc['oc_local'], $oc['endereco'], $oc['cidade'] ) ) );
			if ( $loc ) {
				$l[] = 'LOCATION:' . self::esc( $loc );
			}
			$l[] = 'DESCRIPTION:' . self::esc( trim( $oc['resumo'] . "\n\n" . $oc['url'] ) );
			$l[] = 'URL:' . esc_url_raw( $oc['url'] );
			if ( $oc['tipo_nome'] ) {
				$l[] = 'CATEGORIES:' . self::esc( $oc['tipo_nome'] );
			}
			$l[] = 'END:VEVENT';
		}
		$l[] = 'END:VCALENDAR';

		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: ' . ( 'agenda' === $alvo ? 'inline' : 'attachment' ) . '; filename="' . ( $nome ? $nome : 'evento' ) . '.ics"' );
		echo implode( "\r\n", array_map( array( __CLASS__, 'dobrar' ), $l ) ) . "\r\n"; // phpcs:ignore
		exit;
	}

	/**
	 * Escapa texto para iCalendar.
	 *
	 * @param string $t Texto.
	 * @return string
	 */
	private static function esc( $t ) {
		$t = wp_strip_all_tags( html_entity_decode( (string) $t, ENT_QUOTES, 'UTF-8' ) );
		return str_replace( array( '\\', ';', ',', "\r\n", "\n" ), array( '\\\\', '\;', '\,', '\n', '\n' ), $t );
	}

	/**
	 * Quebra linhas com mais de 75 bytes (RFC 5545).
	 *
	 * @param string $linha Linha.
	 * @return string
	 */
	private static function dobrar( $linha ) {
		if ( strlen( $linha ) <= 75 ) {
			return $linha;
		}
		$out = '';
		$cur = '';
		foreach ( preg_split( '//u', $linha, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
			if ( strlen( $cur . $ch ) > ( '' === $out ? 75 : 74 ) ) {
				$out .= ( '' === $out ? '' : "\r\n " ) . $cur;
				$cur  = '';
			}
			$cur .= $ch;
		}
		return $out . ( '' === $out ? '' : "\r\n " ) . $cur;
	}
}
