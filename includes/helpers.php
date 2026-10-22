<?php
/**
 * Funções utilitárias: opções, cores, datas e textos.
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Valores padrão das configurações.
 *
 * @return array
 */
function jpx_ev_opcoes_padrao() {
	return array(
		'cor_primaria'      => '#0E7C66',
		'cor_escura'        => '#111827',
		'raio'              => 16,
		'fonte_titulos'     => '',
		'fonte_texto'       => '',
		'slug'              => 'eventos',
		'nucleo_singular'   => 'Núcleo',
		'nucleo_plural'     => 'Núcleos',
		'pagina_agenda'     => 0,
		'modo_single'       => 'auto',
		'itens_por_pagina'  => 9,
		'mostrar_feriados'  => 1,
		'horizonte_meses'   => 18,
		'local_padrao'      => 0,
	);
}

/**
 * Lê uma configuração (ou todas).
 *
 * @param string|null $chave Chave.
 * @return mixed
 */
function jpx_ev_opcao( $chave = null ) {
	$salvas = get_option( 'jpx_eventos_opcoes', array() );
	$todas  = wp_parse_args( is_array( $salvas ) ? $salvas : array(), jpx_ev_opcoes_padrao() );
	if ( null === $chave ) {
		return $todas;
	}
	return isset( $todas[ $chave ] ) ? $todas[ $chave ] : null;
}

/* -------------------------------------------------------------------------
 * Cores
 * ---------------------------------------------------------------------- */

/**
 * Converte hex em [r,g,b].
 *
 * @param string $hex Cor.
 * @return int[]
 */
function jpx_ev_hex_rgb( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
		return array( 0, 0, 0 );
	}
	return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

/**
 * Converte [r,g,b] em hex.
 *
 * @param array $rgb Cor.
 * @return string
 */
function jpx_ev_rgb_hex( $rgb ) {
	$out = '#';
	foreach ( $rgb as $v ) {
		$out .= str_pad( dechex( (int) max( 0, min( 255, round( $v ) ) ) ), 2, '0', STR_PAD_LEFT );
	}
	return strtoupper( $out );
}

/**
 * Mistura duas cores.
 *
 * @param string $a Cor A.
 * @param string $b Cor B.
 * @param float  $t Proporção de B (0–1).
 * @return string
 */
function jpx_ev_mix( $a, $b, $t ) {
	$x = jpx_ev_hex_rgb( $a );
	$y = jpx_ev_hex_rgb( $b );
	$r = array();
	for ( $i = 0; $i < 3; $i++ ) {
		$r[] = $x[ $i ] + ( $y[ $i ] - $x[ $i ] ) * $t;
	}
	return jpx_ev_rgb_hex( $r );
}

/**
 * Luminância relativa (WCAG).
 *
 * @param string $hex Cor.
 * @return float
 */
function jpx_ev_luminancia( $hex ) {
	$pesos = array( 0.2126, 0.7152, 0.0722 );
	$soma  = 0;
	foreach ( jpx_ev_hex_rgb( $hex ) as $i => $v ) {
		$v     = $v / 255;
		$v     = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		$soma += $v * $pesos[ $i ];
	}
	return $soma;
}

/**
 * Contraste de uma cor contra o branco.
 *
 * @param string $hex Cor.
 * @return float
 */
function jpx_ev_contraste_branco( $hex ) {
	return 1.05 / ( jpx_ev_luminancia( $hex ) + 0.05 );
}

/**
 * Escurece a cor até ter contraste legível com texto branco (≥ 4.6:1).
 *
 * @param string $hex Cor.
 * @return string
 */
function jpx_ev_cor_forte( $hex ) {
	$c = jpx_ev_sanitizar_cor( $hex, '#0E7C66' );
	for ( $i = 0; $i < 40 && jpx_ev_contraste_branco( $c ) < 4.6; $i++ ) {
		$c = jpx_ev_mix( $c, '#000000', 0.06 );
	}
	return $c;
}

/**
 * Versão clarinha (fundo de etiquetas).
 *
 * @param string $hex Cor.
 * @param float  $t   Quanto clarear.
 * @return string
 */
function jpx_ev_cor_suave( $hex, $t = 0.9 ) {
	return jpx_ev_mix( $hex, '#FFFFFF', $t );
}

/**
 * Sanitiza uma cor hex.
 *
 * @param string $cor    Valor.
 * @param string $padrao Fallback.
 * @return string
 */
function jpx_ev_sanitizar_cor( $cor, $padrao = '' ) {
	$cor = sanitize_hex_color( (string) $cor );
	return $cor ? strtoupper( $cor ) : $padrao;
}

/**
 * Tokens de identidade (variáveis CSS) calculados a partir das configurações.
 *
 * @return array
 */
function jpx_ev_tokens() {
	$o       = jpx_ev_opcao();
	$prim    = jpx_ev_sanitizar_cor( $o['cor_primaria'], '#0E7C66' );
	$ink     = jpx_ev_sanitizar_cor( $o['cor_escura'], '#111827' );
	$raio    = max( 0, min( 32, (int) $o['raio'] ) );
	$muted   = jpx_ev_mix( $ink, '#FFFFFF', 0.38 );
	for ( $i = 0; $i < 30 && jpx_ev_contraste_branco( $muted ) < 4.8; $i++ ) {
		$muted = jpx_ev_mix( $muted, $ink, 0.12 );
	}
	$fonte = function ( $f ) {
		$f = trim( preg_replace( '/[^\p{L}\p{N} ,\-]/u', '', (string) $f ) );
		return '' === $f ? 'inherit' : "'" . $f . "', system-ui, sans-serif";
	};
	return array(
		'--jpx-primary' => $prim,
		'--jpx-strong'  => jpx_ev_cor_forte( $prim ),
		'--jpx-soft'    => jpx_ev_cor_suave( $prim, 0.88 ),
		'--jpx-hero-accent' => jpx_ev_mix( $prim, '#FFFFFF', 0.35 ),
		'--jpx-ink'     => $ink,
		'--jpx-text'    => jpx_ev_mix( $ink, '#FFFFFF', 0.12 ),
		'--jpx-muted'   => $muted,
		'--jpx-line'    => jpx_ev_mix( $ink, '#FFFFFF', 0.88 ),
		'--jpx-line-2'  => jpx_ev_mix( $ink, '#FFFFFF', 0.93 ),
		'--jpx-bg'      => jpx_ev_mix( $ink, '#FFFFFF', 0.965 ),
		'--jpx-chip'    => jpx_ev_mix( $ink, '#FFFFFF', 0.92 ),
		'--jpx-r'       => $raio . 'px',
		'--jpx-r-sm'    => max( 6, round( $raio * 0.6 ) ) . 'px',
		'--jpx-r-lg'    => ( $raio + 6 ) . 'px',
		'--jpx-font-h'  => $fonte( $o['fonte_titulos'] ),
		'--jpx-font-b'  => $fonte( $o['fonte_texto'] ),
	);
}

/* -------------------------------------------------------------------------
 * Datas
 * ---------------------------------------------------------------------- */

/**
 * Hoje no fuso do site (Y-m-d).
 *
 * @return string
 */
function jpx_ev_hoje() {
	return apply_filters( 'jpx_ev_hoje', wp_date( 'Y-m-d' ) );
}

/**
 * Nomes de meses e dias.
 *
 * @param string $qual meses|meses_curto|dias|dias_curto.
 * @return string[]
 */
function jpx_ev_nomes( $qual ) {
	switch ( $qual ) {
		case 'meses':
			return array( 1 => __( 'janeiro', 'jpx-eventos' ), __( 'fevereiro', 'jpx-eventos' ), __( 'março', 'jpx-eventos' ), __( 'abril', 'jpx-eventos' ), __( 'maio', 'jpx-eventos' ), __( 'junho', 'jpx-eventos' ), __( 'julho', 'jpx-eventos' ), __( 'agosto', 'jpx-eventos' ), __( 'setembro', 'jpx-eventos' ), __( 'outubro', 'jpx-eventos' ), __( 'novembro', 'jpx-eventos' ), __( 'dezembro', 'jpx-eventos' ) );
		case 'meses_curto':
			return array( 1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez' );
		case 'dias':
			return array( __( 'domingo', 'jpx-eventos' ), __( 'segunda-feira', 'jpx-eventos' ), __( 'terça-feira', 'jpx-eventos' ), __( 'quarta-feira', 'jpx-eventos' ), __( 'quinta-feira', 'jpx-eventos' ), __( 'sexta-feira', 'jpx-eventos' ), __( 'sábado', 'jpx-eventos' ) );
		case 'dias_plural':
			return array( __( 'domingos', 'jpx-eventos' ), __( 'segundas-feiras', 'jpx-eventos' ), __( 'terças-feiras', 'jpx-eventos' ), __( 'quartas-feiras', 'jpx-eventos' ), __( 'quintas-feiras', 'jpx-eventos' ), __( 'sextas-feiras', 'jpx-eventos' ), __( 'sábados', 'jpx-eventos' ) );
		case 'dias_curto':
		default:
			return array( 'dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb' );
	}
}

/**
 * Cria DateTimeImmutable (meia-noite, fuso do site) a partir de Y-m-d.
 *
 * @param string $ymd Data.
 * @return DateTimeImmutable|null
 */
function jpx_ev_dt( $ymd ) {
	if ( ! is_string( $ymd ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
		return null;
	}
	$d = DateTimeImmutable::createFromFormat( '!Y-m-d', $ymd, wp_timezone() );
	return ( $d && $d->format( 'Y-m-d' ) === $ymd ) ? $d : null;
}

/**
 * Converte 'Ymd' (formato antigo do ACF) em 'Y-m-d'.
 *
 * @param string $v Valor.
 * @return string
 */
function jpx_ev_ymd_para_iso( $v ) {
	$v = preg_replace( '/\D/', '', (string) $v );
	if ( 8 !== strlen( $v ) ) {
		return '';
	}
	$iso = substr( $v, 0, 4 ) . '-' . substr( $v, 4, 2 ) . '-' . substr( $v, 6, 2 );
	return jpx_ev_dt( $iso ) ? $iso : '';
}

/**
 * Converte 'Y-m-d' em 'Ymd'.
 *
 * @param string $iso Data.
 * @return string
 */
function jpx_ev_iso_para_ymd( $iso ) {
	return $iso ? str_replace( '-', '', $iso ) : '';
}

/**
 * Formata uma data.
 *
 * @param string $iso     Y-m-d.
 * @param string $formato curto|longo|dia_mes|numerico|mes_ano.
 * @return string
 */
function jpx_ev_formatar_data( $iso, $formato = 'curto' ) {
	$d = jpx_ev_dt( $iso );
	if ( ! $d ) {
		return '';
	}
	$dia  = (int) $d->format( 'j' );
	$mes  = (int) $d->format( 'n' );
	$ano  = $d->format( 'Y' );
	$dsem = (int) $d->format( 'w' );
	$mn   = jpx_ev_nomes( 'meses' );
	$mc   = jpx_ev_nomes( 'meses_curto' );
	$dn   = jpx_ev_nomes( 'dias' );
	$dc   = jpx_ev_nomes( 'dias_curto' );
	switch ( $formato ) {
		case 'longo':
			return ucfirst( $dn[ $dsem ] ) . ', ' . $dia . ' de ' . $mn[ $mes ] . ' de ' . $ano;
		case 'dia_mes':
			return $dia . ' de ' . $mn[ $mes ];
		case 'dia_semana_mes':
			return ucfirst( $dn[ $dsem ] ) . ', ' . $dia . ' de ' . $mn[ $mes ];
		case 'numerico':
			return $d->format( 'd/m/Y' );
		case 'mes_ano':
			return ucfirst( $mn[ $mes ] ) . ' ' . $ano;
		case 'curto_ano':
			return ucfirst( $dc[ $dsem ] ) . ', ' . $dia . ' de ' . $mc[ $mes ] . ' de ' . $ano;
		case 'curto':
		default:
			return ucfirst( $dc[ $dsem ] ) . ', ' . $dia . ' de ' . $mc[ $mes ];
	}
}

/**
 * Período (vários dias) em texto.
 *
 * @param string $ini   Y-m-d.
 * @param string $fim   Y-m-d.
 * @param bool   $longo Usar nomes completos.
 * @return string
 */
function jpx_ev_formatar_periodo( $ini, $fim, $longo = false ) {
	if ( ! $fim || $fim === $ini ) {
		return jpx_ev_formatar_data( $ini, $longo ? 'longo' : 'curto' );
	}
	$a  = jpx_ev_dt( $ini );
	$b  = jpx_ev_dt( $fim );
	if ( ! $a || ! $b ) {
		return '';
	}
	$mn = jpx_ev_nomes( $longo ? 'meses' : 'meses_curto' );
	$ma = $mn[ (int) $a->format( 'n' ) ];
	$mb = $mn[ (int) $b->format( 'n' ) ];
	$txt = $a->format( 'Y' ) !== $b->format( 'Y' )
		? $a->format( 'j' ) . ' de ' . $ma . ' de ' . $a->format( 'Y' ) . ' a ' . $b->format( 'j' ) . ' de ' . $mb
		: ( $ma === $mb ? $a->format( 'j' ) . ' a ' . $b->format( 'j' ) . ' de ' . $mb : $a->format( 'j' ) . ' de ' . $ma . ' a ' . $b->format( 'j' ) . ' de ' . $mb );
	if ( $longo ) {
		$txt .= ' de ' . $b->format( 'Y' );
	}
	return $txt;
}

/**
 * Faixa de horário em texto.
 *
 * @param string $ini        HH:MM.
 * @param string $fim        HH:MM.
 * @param bool   $dia_todo   Dia inteiro.
 * @param string $texto_legado Texto antigo não convertido.
 * @return string
 */
function jpx_ev_formatar_horario( $ini, $fim = '', $dia_todo = false, $texto_legado = '' ) {
	if ( $dia_todo ) {
		return __( 'Dia inteiro', 'jpx-eventos' );
	}
	if ( $ini && $fim ) {
		return $ini . '–' . $fim;
	}
	if ( $ini ) {
		return $ini;
	}
	return (string) $texto_legado;
}

/**
 * Interpreta horários escritos à mão ("19 horas", "8h às 10h", "19:30").
 *
 * @param string $texto Texto livre.
 * @return array{inicio:string,fim:string,ok:bool}
 */
function jpx_ev_interpretar_hora( $texto ) {
	$t = strtolower( remove_accents( trim( (string) $texto ) ) );
	$r = array( 'inicio' => '', 'fim' => '', 'ok' => false );
	if ( '' === $t ) {
		$r['ok'] = true;
		return $r;
	}
	preg_match_all( '/(\d{1,2})\s*(?::|h(?:oras?|rs?|s)?|\s*horas?)\s*(\d{2})?(?!\d)/', $t, $m, PREG_SET_ORDER );
	if ( ! $m && preg_match( '/^\s*(?:as\s*)?(\d{1,2})\s*$/', $t, $s ) ) {
		$m = array( array( $s[0], $s[1], '' ) );
	}
	$horas = array();
	foreach ( $m as $x ) {
		$h  = (int) $x[1];
		$mi = isset( $x[2] ) && '' !== $x[2] ? (int) $x[2] : 0;
		if ( $h > 23 || $mi > 59 ) {
			continue;
		}
		$horas[] = sprintf( '%02d:%02d', $h, $mi );
	}
	if ( ! $horas ) {
		return $r;
	}
	$r['inicio'] = $horas[0];
	$r['fim']    = isset( $horas[1] ) && $horas[1] > $horas[0] ? $horas[1] : '';
	$r['ok']     = true;
	return $r;
}

/**
 * Sanitiza HH:MM.
 *
 * @param string $v Valor.
 * @return string
 */
function jpx_ev_sanitizar_hora( $v ) {
	$v = trim( (string) $v );
	if ( preg_match( '/^(\d{1,2}):(\d{2})/', $v, $m ) && (int) $m[1] < 24 && (int) $m[2] < 60 ) {
		return sprintf( '%02d:%02d', $m[1], $m[2] );
	}
	return '';
}

/**
 * Sanitiza Y-m-d.
 *
 * @param string $v Valor.
 * @return string
 */
function jpx_ev_sanitizar_data( $v ) {
	$v = trim( (string) $v );
	if ( preg_match( '#^(\d{2})/(\d{2})/(\d{4})$#', $v, $m ) ) {
		$v = $m[3] . '-' . $m[2] . '-' . $m[1];
	}
	return jpx_ev_dt( $v ) ? $v : '';
}

/* -------------------------------------------------------------------------
 * Rótulos
 * ---------------------------------------------------------------------- */

/**
 * Opções de valor.
 *
 * @return array
 */
function jpx_ev_valores() {
	return array(
		'gratuito' => __( 'Gratuito', 'jpx-eventos' ),
		'solidario' => __( 'Ingresso solidário', 'jpx-eventos' ),
		'pago'     => __( 'Pago', 'jpx-eventos' ),
	);
}

/**
 * Opções de público.
 *
 * @return array
 */
function jpx_ev_publicos() {
	return apply_filters(
		'jpx_ev_publicos',
		array(
			'aberto'     => __( 'Aberto ao público', 'jpx-eventos' ),
			'associados' => __( 'Associados', 'jpx-eventos' ),
			'nucleados'  => __( 'Nucleados', 'jpx-eventos' ),
		)
	);
}

/**
 * URL da página da agenda (configurada) ou do arquivo do tipo de post.
 *
 * @return string
 */
function jpx_ev_url_agenda() {
	$id = (int) jpx_ev_opcao( 'pagina_agenda' );
	if ( $id && get_post_status( $id ) ) {
		return get_permalink( $id );
	}
	$arq = get_post_type_archive_link( 'evento' );
	return $arq ? $arq : home_url( '/' );
}

/**
 * Atributo HTML com JSON seguro.
 *
 * @param mixed $dados Dados.
 * @return string
 */
function jpx_ev_json_attr( $dados ) {
	return esc_attr( wp_json_encode( $dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
}

/**
 * Minúsculas/maiúsculas com acentos, sem exigir a extensão mbstring.
 *
 * @param string $t Texto.
 * @return string
 */
function jpx_ev_minusc( $t ) {
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $t, 'UTF-8' ) : strtolower( (string) $t );
}

/**
 * Maiúsculas.
 *
 * @param string $t Texto.
 * @return string
 */
function jpx_ev_maiusc( $t ) {
	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( (string) $t, 'UTF-8' ) : strtoupper( (string) $t );
}

/**
 * Lista de slugs separada por vírgula, sanitizada ("palestra, curso" → "palestra,curso").
 *
 * @param mixed $v Valor.
 * @return string
 */
function jpx_ev_slugs( $v ) {
	return implode( ',', array_filter( array_map( 'sanitize_title', explode( ',', (string) $v ) ) ) );
}

/**
 * Ícones SVG (traço).
 *
 * @param string $nome  Nome.
 * @param int    $tam   Tamanho.
 * @return string
 */
function jpx_ev_icone( $nome, $tam = 18 ) {
	$p = array(
		'calendario' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
		'cal_mais'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M12 14v4M10 16h4"/>',
		'relogio'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'local'      => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'pessoas'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
		'repetir'    => '<path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
		'busca'      => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
		'esq'        => '<path d="M15 18l-6-6 6-6"/>',
		'dir'        => '<path d="M9 18l6-6-6-6"/>',
		'fechar'     => '<path d="M18 6L6 18M6 6l12 12"/>',
		'seta'       => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'lista'      => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
		'etiqueta'   => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1.5"/>',
		'externo'    => '<path d="M15 3h6v6M10 14L21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		'mensagem'   => '<path d="M21 11.5a8.4 8.4 0 0 1-12.4 7.4L3 21l2.1-5.4A8.4 8.4 0 1 1 21 11.5z"/>',
		'compartilhar' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/>',
		'link'       => '<path d="M10 13a5 5 0 0 0 7.1 0l3-3a5 5 0 0 0-7.1-7.1l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.1 0l-3 3a5 5 0 0 0 7.1 7.1l1.7-1.7"/>',
		'check'      => '<path d="M5 12l5 5L20 7"/>',
	);
	if ( ! isset( $p[ $nome ] ) ) {
		return '';
	}
	return '<svg class="jpx-ico" width="' . (int) $tam . '" height="' . (int) $tam . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p[ $nome ] . '</svg>';
}
