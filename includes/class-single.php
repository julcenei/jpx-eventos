<?php
/**
 * Página do evento: modo automático (injeta no conteúdo) e dados estruturados (Schema.org).
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_Single {

	/** @var bool Evita recursão ao aplicar the_content dentro do próprio bloco. */
	private static $renderizando = false;

	/**
	 * Hooks.
	 */
	public static function iniciar() {
		add_filter( 'the_content', array( __CLASS__, 'filtrar_conteudo' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'schema' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Monta a página do evento.
	 *
	 * @param int  $id         Evento.
	 * @param bool $com_titulo Com título/capa (shortcode).
	 * @return string
	 */
	public static function renderizar( $id, $com_titulo ) {
		if ( self::$renderizando ) {
			return '';
		}
		self::$renderizando = true;
		$conteudo = apply_filters( 'the_content', get_post_field( 'post_content', $id ) );
		$html     = JPX_EV_Render::single( $id, $com_titulo, $conteudo );
		self::$renderizando = false;
		return $html;
	}

	/**
	 * Modo automático: substitui o conteúdo do evento pelo layout completo (sem título — o tema já mostra).
	 *
	 * @param string $conteudo Conteúdo.
	 * @return string
	 */
	public static function filtrar_conteudo( $conteudo ) {
		if ( self::$renderizando || 'auto' !== jpx_ev_opcao( 'modo_single' ) ) {
			return $conteudo;
		}
		if ( ! is_singular( JPX_EV_Tipos::CPT ) || ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id() ) {
			return $conteudo;
		}
		JPX_EV_Frontend::enfileirar();
		self::$renderizando = true;
		$html = JPX_EV_Render::single( get_the_ID(), false, $conteudo );
		self::$renderizando = false;
		return $html;
	}

	/**
	 * Classe no body.
	 *
	 * @param array $c Classes.
	 * @return array
	 */
	public static function body_class( $c ) {
		if ( is_singular( JPX_EV_Tipos::CPT ) ) {
			$c[] = 'jpx-evento-single';
		}
		return $c;
	}

	/**
	 * JSON-LD Event.
	 */
	public static function schema() {
		if ( ! is_singular( JPX_EV_Tipos::CPT ) ) {
			return;
		}
		$id = get_queried_object_id();
		$oc = JPX_EV_Render::ocorrencia_da_pagina( $id );
		if ( ! $oc ) {
			return;
		}
		$tz  = wp_timezone();
		$iso = function ( $data, $hora ) use ( $tz ) {
			if ( ! $hora ) {
				return $data;
			}
			$d = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $data . ' ' . $hora, $tz );
			return $d ? $d->format( 'c' ) : $data;
		};
		$online = $oc['oc_local'] && preg_match( '/^online$/i', trim( $oc['oc_local'] ) );
		$s      = array(
			'@context'            => 'https://schema.org',
			'@type'               => 'Event',
			'name'                => $oc['titulo'],
			'url'                 => $oc['url'],
			'startDate'           => $iso( $oc['oc_inicio'], $oc['oc_hora_inicio'] ),
			'endDate'             => $iso( $oc['oc_fim'], $oc['oc_hora_fim'] ? $oc['oc_hora_fim'] : $oc['oc_hora_inicio'] ),
			'eventStatus'         => 'cancelada' === $oc['oc_status'] ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => $online ? 'https://schema.org/OnlineEventAttendanceMode' : 'https://schema.org/OfflineEventAttendanceMode',
			'organizer'           => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
		);
		$desc = $oc['resumo'] ? $oc['resumo'] : wp_html_excerpt( wp_strip_all_tags( get_post_field( 'post_content', $id ) ), 300, '…' );
		if ( $desc ) {
			$s['description'] = $desc;
		}
		if ( $oc['thumb_id'] ) {
			$img = wp_get_attachment_image_url( $oc['thumb_id'], 'full' );
			if ( $img ) {
				$s['image'] = array( $img );
			}
		}
		if ( $online ) {
			$s['location'] = array( '@type' => 'VirtualLocation', 'url' => $oc['inscricao'] ? $oc['inscricao'] : $oc['url'] );
		} elseif ( $oc['oc_local'] || $oc['cidade'] ) {
			$s['location'] = array(
				'@type'   => 'Place',
				'name'    => $oc['oc_local'] ? $oc['oc_local'] : $oc['cidade'],
				'address' => array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $oc['endereco'],
					'addressLocality' => $oc['cidade'],
					'addressCountry'  => 'BR',
				),
			);
		}
		if ( 'gratuito' === $oc['valor_tipo'] || ( 'pago' === $oc['valor_tipo'] && preg_match( '/(\d+[.,]?\d*)/', str_replace( '.', '', $oc['valor'] ), $mm ) ) ) {
			$s['offers'] = array(
				'@type'         => 'Offer',
				'price'         => 'gratuito' === $oc['valor_tipo'] ? '0' : str_replace( ',', '.', $mm[1] ),
				'priceCurrency' => 'BRL',
				'url'           => $oc['inscricao'] ? $oc['inscricao'] : $oc['url'],
				'availability'  => 'https://schema.org/InStock',
			);
		}
		$s = apply_filters( 'jpx_ev_schema', $s, $oc );
		echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $s, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n"; // phpcs:ignore
	}
}
