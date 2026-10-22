<?php
/**
 * Marcação HTML de todas as peças do front (cards, agenda, calendário, página do evento).
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_Render {

	/** @var string URL da página da agenda (quando o HTML é gerado via REST). */
	public static $base = '';

	/**
	 * Link com parâmetros, relativo à página da agenda.
	 *
	 * @param array $args    Parâmetros a definir.
	 * @param array $remover Parâmetros a remover.
	 * @return string
	 */
	public static function link( $args, $remover = array() ) {
		$url = self::$base ? self::$base : remove_query_arg( array() );
		if ( $remover ) {
			$url = remove_query_arg( $remover, $url );
		}
		return add_query_arg( $args, $url );
	}

	/* ---------------------------------------------------------------------
	 * Peças pequenas
	 * ------------------------------------------------------------------ */

	/**
	 * Capa do evento: imagem destacada ou capa gerada (cor do tipo + dia).
	 *
	 * @param array  $oc  Ocorrência.
	 * @param string $tam Tamanho da imagem.
	 * @return string
	 */
	public static function capa( $oc, $tam = 'medium_large' ) {
		if ( $oc['thumb_id'] ) {
			$img = wp_get_attachment_image(
				$oc['thumb_id'],
				$tam,
				false,
				array(
					'class'    => 'jpx-capa__img',
					'loading'  => 'lazy',
					'decoding' => 'async',
					'alt'      => '',
				)
			);
			if ( $img ) {
				return '<div class="jpx-capa jpx-capa--foto">' . $img . '</div>';
			}
		}
		$dia = jpx_ev_dt( $oc['oc_inicio'] ?? $oc['inicio'] );
		return '<div class="jpx-capa jpx-capa--gerada"><span class="jpx-capa__num" aria-hidden="true">' . esc_html( $dia ? $dia->format( 'd' ) : '' ) . '</span></div>';
	}

	/**
	 * Bloco de data (MÊS / DIA).
	 *
	 * @param string $iso Data.
	 * @param bool   $com_dia_semana Mostrar dia da semana.
	 * @return string
	 */
	public static function bloco_data( $iso, $com_dia_semana = false ) {
		$d = jpx_ev_dt( $iso );
		if ( ! $d ) {
			return '';
		}
		$mc = jpx_ev_nomes( 'meses_curto' );
		$dc = jpx_ev_nomes( 'dias_curto' );
		$h  = '<span class="jpx-data" aria-hidden="true"><span class="jpx-data__mes">' . esc_html( jpx_ev_maiusc( $mc[ (int) $d->format( 'n' ) ] ) ) . '</span><span class="jpx-data__dia">' . esc_html( $d->format( 'd' ) ) . '</span>';
		if ( $com_dia_semana ) {
			$h .= '<span class="jpx-data__sem">' . esc_html( $dc[ (int) $d->format( 'w' ) ] ) . '</span>';
		}
		return $h . '</span>';
	}

	/**
	 * Etiqueta do tipo.
	 *
	 * @param array $oc Ocorrência.
	 * @return string
	 */
	public static function tag_tipo( $oc ) {
		if ( ! $oc['tipo_nome'] ) {
			return '';
		}
		return '<span class="jpx-tag"><span class="jpx-tag__ponto"></span>' . esc_html( $oc['tipo_nome'] ) . '</span>';
	}

	/**
	 * Data curta + horário de uma ocorrência.
	 *
	 * @param array $oc Ocorrência.
	 * @return string
	 */
	public static function texto_quando( $oc ) {
		$ano  = substr( $oc['oc_inicio'], 0, 4 ) !== wp_date( 'Y' );
		$data = $oc['oc_fim'] !== $oc['oc_inicio']
			? jpx_ev_formatar_periodo( $oc['oc_inicio'], $oc['oc_fim'] ) . ( $ano ? ' de ' . substr( $oc['oc_fim'], 0, 4 ) : '' )
			: jpx_ev_formatar_data( $oc['oc_inicio'], $ano ? 'curto_ano' : 'curto' );
		return $oc['oc_horario'] ? $data . ' · ' . $oc['oc_horario'] : $data;
	}

	/**
	 * Data longa de uma ocorrência.
	 *
	 * @param array $oc Ocorrência.
	 * @return string
	 */
	public static function texto_data_longa( $oc ) {
		return $oc['oc_fim'] !== $oc['oc_inicio']
			? jpx_ev_formatar_periodo( $oc['oc_inicio'], $oc['oc_fim'], true )
			: jpx_ev_formatar_data( $oc['oc_inicio'], 'longo' );
	}

	/**
	 * Local + cidade.
	 *
	 * @param array $oc Ocorrência.
	 * @return string
	 */
	public static function texto_local( $oc ) {
		$l = isset( $oc['oc_local'] ) ? $oc['oc_local'] : $oc['local_nome'];
		return implode( ' · ', array_filter( array( $l, $oc['cidade'] ) ) );
	}

	/**
	 * URL do evento apontando para uma data específica (recorrentes).
	 *
	 * @param array $oc Ocorrência.
	 * @return string
	 */
	public static function url_ocorrencia( $oc ) {
		return $oc['recorrente'] ? add_query_arg( 'data', $oc['oc_inicio'], $oc['url'] ) : $oc['url'];
	}

	/**
	 * Dados do modal (JSON no atributo data-jpx-evento).
	 *
	 * @param array $oc Ocorrência.
	 * @return array
	 */
	public static function dados_modal( $oc ) {
		$dia = jpx_ev_dt( $oc['oc_inicio'] );
		return array(
			't'   => $oc['titulo'],
			'u'   => self::url_ocorrencia( $oc ),
			'tp'  => $oc['tipo_nome'],
			'c'   => $oc['cores']['cor'] ? $oc['cores']['forte'] : '',
			'cs'  => $oc['cores']['cor'] ? $oc['cores']['suave'] : '',
			'd'   => $dia ? $dia->format( 'd' ) : '',
			'dl'  => self::texto_data_longa( $oc ),
			'h'   => $oc['oc_horario'],
			'l'   => self::texto_local( $oc ),
			'm'   => $oc['mapa_url'],
			'n'   => $oc['nucleo_nome'],
			'r'   => $oc['recorrencia_txt'],
			'rs'  => wp_html_excerpt( $oc['resumo'] ? $oc['resumo'] : wp_strip_all_tags( get_post_field( 'post_content', $oc['id'] ) ), 220, '…' ),
			'v'   => $oc['passado'] ? __( 'Encerrado', 'jpx-eventos' ) : $oc['valor_rotulo'],
			'p'   => $oc['publico_rotulo'],
			'i'   => ( ! $oc['passado'] && $oc['inscricao'] ) ? $oc['inscricao'] : '',
			'g'   => JPX_EV_ICS::url_google( $oc ),
			'ics' => JPX_EV_ICS::url_evento( $oc['id'], $oc['oc_inicio'] ),
			'nt'  => $oc['oc_nota'],
		);
	}

	/* ---------------------------------------------------------------------
	 * Cards
	 * ------------------------------------------------------------------ */

	/**
	 * Card padrão.
	 *
	 * @param array $oc Ocorrência.
	 * @return string
	 */
	public static function card( $oc ) {
		$url   = self::url_ocorrencia( $oc );
		$class = 'jpx-card' . ( $oc['passado'] ? ' is-passado' : '' ) . ( $oc['thumb_id'] ? ' tem-foto' : '' );
		ob_start();
		?>
		<article class="<?php echo esc_attr( $class ); ?>" style="<?php echo esc_attr( $oc['cores']['css'] ); ?>">
			<div class="jpx-card__capa">
				<?php echo self::capa( $oc ); // phpcs:ignore ?>
				<div class="jpx-card__tags">
					<?php echo $oc['passado'] ? '<span class="jpx-tag jpx-tag--escura">' . esc_html__( 'Encerrado', 'jpx-eventos' ) . '</span>' : self::tag_tipo( $oc ); // phpcs:ignore ?>
					<?php if ( $oc['recorrente'] && ! $oc['passado'] ) : ?>
						<span class="jpx-tag jpx-tag--vidro"><?php echo jpx_ev_icone( 'repetir', 13 ); // phpcs:ignore ?><?php esc_html_e( 'Recorrente', 'jpx-eventos' ); ?></span>
					<?php endif; ?>
				</div>
				<?php echo self::bloco_data( $oc['oc_inicio'] ); // phpcs:ignore ?>
			</div>
			<div class="jpx-card__corpo">
				<h3 class="jpx-card__titulo"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $oc['titulo'] ); ?></a></h3>
				<ul class="jpx-meta">
					<li><?php echo jpx_ev_icone( 'relogio', 15 ); // phpcs:ignore ?><span><?php echo esc_html( self::texto_quando( $oc ) ); ?></span></li>
					<?php if ( self::texto_local( $oc ) ) : ?>
						<li><?php echo jpx_ev_icone( 'local', 15 ); // phpcs:ignore ?><span class="jpx-trunc"><?php echo esc_html( self::texto_local( $oc ) ); ?></span></li>
					<?php endif; ?>
					<?php if ( $oc['nucleo_nome'] ) : ?>
						<li><?php echo jpx_ev_icone( 'pessoas', 15 ); // phpcs:ignore ?><span class="jpx-trunc"><?php echo esc_html( $oc['nucleo_nome'] ); ?></span></li>
					<?php endif; ?>
				</ul>
				<div class="jpx-card__rodape">
					<div class="jpx-badges">
						<?php if ( ! $oc['passado'] && $oc['valor_curto'] ) : ?>
							<span class="jpx-badge jpx-badge--cor"><?php echo esc_html( $oc['valor_curto'] ); ?></span>
						<?php endif; ?>
						<?php if ( $oc['publico_curto'] ) : ?>
							<span class="jpx-badge"><?php echo esc_html( $oc['publico_curto'] ); ?></span>
						<?php endif; ?>
					</div>
					<a class="jpx-card__ir" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Ver evento: %s', 'jpx-eventos' ), $oc['titulo'] ) ); ?>"><?php echo jpx_ev_icone( 'seta', 18 ); // phpcs:ignore ?></a>
				</div>
			</div>
		</article>
		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * Card de destaque (próximo evento).
	 *
	 * @param array $oc Ocorrência.
	 * @return string
	 */
	public static function destaque( $oc ) {
		$url  = self::url_ocorrencia( $oc );
		$dias = (int) jpx_ev_dt( jpx_ev_hoje() )->diff( jpx_ev_dt( $oc['oc_inicio'] ) )->format( '%r%a' );
		if ( $oc['hoje'] ) {
			$quando = __( 'acontece hoje', 'jpx-eventos' );
		} elseif ( 1 === $dias ) {
			$quando = __( 'amanhã', 'jpx-eventos' );
		} else {
			$quando = sprintf( __( 'em %d dias', 'jpx-eventos' ), $dias );
		}
		$resumo = $oc['resumo'] ? $oc['resumo'] : wp_html_excerpt( wp_strip_all_tags( get_post_field( 'post_content', $oc['id'] ) ), 200, '…' );
		ob_start();
		?>
		<article class="jpx-destaque<?php echo $oc['thumb_id'] ? ' tem-foto' : ''; ?>" style="<?php echo esc_attr( $oc['cores']['css'] ); ?>">
			<div class="jpx-destaque__capa">
				<?php echo self::capa( $oc, 'large' ); // phpcs:ignore ?>
				<div class="jpx-card__tags">
					<?php echo self::tag_tipo( $oc ); // phpcs:ignore ?>
					<span class="jpx-tag jpx-tag--vidro"><?php echo esc_html( sprintf( __( 'Próximo evento · %s', 'jpx-eventos' ), $quando ) ); ?></span>
				</div>
				<?php echo self::bloco_data( $oc['oc_inicio'], true ); // phpcs:ignore ?>
			</div>
			<div class="jpx-destaque__corpo">
				<h3 class="jpx-destaque__titulo"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $oc['titulo'] ); ?></a></h3>
				<?php if ( $resumo ) : ?>
					<p class="jpx-destaque__resumo"><?php echo esc_html( $resumo ); ?></p>
				<?php endif; ?>
				<ul class="jpx-meta jpx-meta--grande">
					<li><?php echo jpx_ev_icone( 'calendario' ); // phpcs:ignore ?><span><?php echo esc_html( self::texto_data_longa( $oc ) ); ?></span></li>
					<?php if ( $oc['oc_horario'] ) : ?>
						<li><?php echo jpx_ev_icone( 'relogio' ); // phpcs:ignore ?><span><?php echo esc_html( $oc['oc_horario'] ); ?></span></li>
					<?php endif; ?>
					<?php if ( self::texto_local( $oc ) ) : ?>
						<li><?php echo jpx_ev_icone( 'local' ); // phpcs:ignore ?><span><?php echo esc_html( self::texto_local( $oc ) ); ?></span></li>
					<?php endif; ?>
					<?php if ( $oc['recorrente'] ) : ?>
						<li><?php echo jpx_ev_icone( 'repetir' ); // phpcs:ignore ?><span><?php echo esc_html( $oc['recorrencia_txt'] ); ?></span></li>
					<?php endif; ?>
				</ul>
				<?php if ( $oc['valor_rotulo'] || $oc['publico_rotulo'] ) : ?>
					<div class="jpx-badges">
						<?php if ( $oc['valor_rotulo'] ) : ?>
							<span class="jpx-badge jpx-badge--cor"><?php echo esc_html( $oc['valor_rotulo'] ); ?></span>
						<?php endif; ?>
						<?php if ( $oc['publico_rotulo'] ) : ?>
							<span class="jpx-badge"><?php echo esc_html( $oc['publico_rotulo'] ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<div class="jpx-acoes">
					<?php if ( $oc['inscricao'] ) : ?>
						<a class="jpx-btn jpx-btn--cheio" href="<?php echo esc_url( $oc['inscricao'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Inscrever-se', 'jpx-eventos' ); ?><?php echo jpx_ev_icone( 'externo', 16 ); // phpcs:ignore ?></a>
					<?php endif; ?>
					<a class="jpx-btn jpx-btn--borda" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Ver detalhes', 'jpx-eventos' ); ?></a>
				</div>
			</div>
		</article>
		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * Linha compacta (widgets, página de núcleos).
	 *
	 * @param array $oc Ocorrência.
	 * @return string
	 */
	public static function linha( $oc ) {
		$meta = array_filter( array( $oc['tipo_nome'], $oc['oc_fim'] !== $oc['oc_inicio'] ? jpx_ev_formatar_periodo( $oc['oc_inicio'], $oc['oc_fim'] ) : '', $oc['oc_horario'], isset( $oc['oc_local'] ) ? $oc['oc_local'] : '' ) );
		return '<li class="jpx-linha' . ( $oc['passado'] ? ' is-passado' : '' ) . '" style="' . esc_attr( $oc['cores']['css'] ) . '"><a href="' . esc_url( self::url_ocorrencia( $oc ) ) . '">'
			. self::bloco_data( $oc['oc_inicio'] )
			. '<span class="jpx-linha__txt"><span class="jpx-linha__titulo">' . esc_html( $oc['titulo'] ) . '</span><span class="jpx-linha__meta">' . esc_html( implode( ' · ', $meta ) ) . '</span></span></a></li>';
	}

	/* ---------------------------------------------------------------------
	 * Agenda (lista)
	 * ------------------------------------------------------------------ */

	/**
	 * Estado da agenda a partir dos atributos e da URL.
	 *
	 * @param array $atts  Atributos do shortcode.
	 * @param array $fonte Parâmetros (normalmente $_GET).
	 * @return array
	 */
	public static function estado( $atts, $fonte ) {
		$g = function ( $k, $padrao = '' ) use ( $fonte ) {
			return isset( $fonte[ $k ] ) ? sanitize_text_field( wp_unslash( $fonte[ $k ] ) ) : $padrao;
		};
		$mes = $g( 'jpx_mes' );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $mes ) ) {
			$mes = wp_date( 'Y-m' );
		}
		$visao = $g( 'jpx_v', 'calendario' === $atts['visao'] ? 'cal' : 'lista' );
		return array(
			'visao'      => in_array( $visao, array( 'lista', 'cal' ), true ) ? $visao : 'lista',
			'aba'        => 'ant' === $g( 'jpx_aba' ) ? 'ant' : 'prox',
			'tipo'       => $atts['tipo'] ? $atts['tipo'] : sanitize_title( $g( 'jpx_tipo' ) ),
			'nucleo'     => $atts['nucleo'] ? $atts['nucleo'] : sanitize_title( $g( 'jpx_nucleo' ) ),
			'q'          => mb_substr( $g( 'jpx_q' ), 0, 80 ),
			'mes'        => $mes,
			'pag'        => max( 1, (int) $g( 'jpx_pag', 1 ) ),
			'por_pagina' => max( 1, min( 48, (int) $atts['por_pagina'] ) ),
			'fixo_tipo'  => (bool) $atts['tipo'],
			'fixo_nucleo' => (bool) $atts['nucleo'],
		);
	}

	/**
	 * Contagens das abas.
	 *
	 * @param array $e Estado.
	 * @return array
	 */
	public static function contagens( $e ) {
		$base = array( 'tipo' => $e['tipo'], 'nucleo' => $e['nucleo'], 'busca' => $e['q'], 'por_evento' => true, 'limite' => 1 );
		return array(
			'prox' => JPX_EV_Ocorrencias::consultar( $base + array( 'de' => jpx_ev_hoje() ) )['total'],
			'ant'  => JPX_EV_Ocorrencias::consultar( $base + array( 'passados' => true ) )['total'],
		);
	}

	/**
	 * Conteúdo da aba (destaque + grade + "carregar mais"), ou só os cards de uma página extra.
	 *
	 * @param array $e        Estado.
	 * @param bool  $so_cards Página extra (append).
	 * @return array{html:string,mais:bool}
	 */
	public static function lista( $e, $so_cards = false ) {
		$prox  = 'prox' === $e['aba'];
		$pp    = $e['por_pagina'];
		$extra = $prox ? 1 : 0;
		$lim   = 1 === $e['pag'] ? $pp + $extra : $pp;
		$off   = 1 === $e['pag'] ? 0 : $extra + ( $e['pag'] - 1 ) * $pp;
		$args  = array(
			'tipo'       => $e['tipo'],
			'nucleo'     => $e['nucleo'],
			'busca'      => $e['q'],
			'por_evento' => true,
			'limite'     => $lim,
			'offset'     => $off,
		);
		$args += $prox ? array( 'de' => jpx_ev_hoje() ) : array( 'passados' => true );
		$r     = JPX_EV_Ocorrencias::consultar( $args );
		$itens = $r['itens'];
		$mais  = $r['total'] > $off + count( $itens );

		if ( $so_cards ) {
			return array( 'html' => implode( '', array_map( array( __CLASS__, 'card' ), $itens ) ), 'mais' => $mais );
		}

		$h = '';
		if ( ! $itens ) {
			$h .= self::vazio( $e );
		} else {
			if ( $prox && 1 === $e['pag'] ) {
				$h .= self::destaque( array_shift( $itens ) );
			}
			if ( $itens ) {
				$h .= '<div class="jpx-grade">' . implode( '', array_map( array( __CLASS__, 'card' ), $itens ) ) . '</div>';
			}
		}
		$h .= '<div class="jpx-mais"' . ( $mais ? '' : ' hidden' ) . '><a class="jpx-btn jpx-btn--borda" data-jpx-mais href="' . esc_url( self::link( array( 'jpx_pag' => $e['pag'] + 1 ) ) ) . '">' . esc_html__( 'Carregar mais eventos', 'jpx-eventos' ) . '</a></div>';
		return array( 'html' => $h, 'mais' => $mais );
	}

	/**
	 * Estado vazio.
	 *
	 * @param array $e Estado.
	 * @return string
	 */
	public static function vazio( $e ) {
		$filtrado = ( $e['tipo'] && ! $e['fixo_tipo'] ) || ( $e['nucleo'] && ! $e['fixo_nucleo'] ) || $e['q'];
		$h        = '<div class="jpx-vazio"><span class="jpx-vazio__ico">' . jpx_ev_icone( 'calendario', 26 ) . '</span>';
		if ( 'ant' === $e['aba'] ) {
			$h .= '<h3>' . esc_html__( 'Nenhum evento anterior encontrado', 'jpx-eventos' ) . '</h3>';
			return $h . '</div>';
		}
		if ( $filtrado ) {
			$h .= '<h3>' . esc_html__( 'Nenhum evento encontrado', 'jpx-eventos' ) . '</h3><p>' . esc_html__( 'Não há eventos programados para esse filtro. Tente outra busca ou veja os eventos já realizados.', 'jpx-eventos' ) . '</p>';
			$h .= '<a class="jpx-btn jpx-btn--borda" data-jpx-aba="ant" href="' . esc_url( self::link( array( 'jpx_aba' => 'ant' ), array( 'jpx_pag' ) ) ) . '">' . esc_html__( 'Ver eventos anteriores', 'jpx-eventos' ) . '</a>';
			return $h . '</div>';
		}
		$h .= '<h3>' . esc_html__( 'Nenhum evento programado no momento', 'jpx-eventos' ) . '</h3><p>' . esc_html__( 'Novos eventos aparecem aqui assim que forem publicados. Enquanto isso, veja os últimos realizados:', 'jpx-eventos' ) . '</p>';
		$ult = JPX_EV_Ocorrencias::consultar( array( 'passados' => true, 'por_evento' => true, 'limite' => 3, 'tipo' => $e['tipo'], 'nucleo' => $e['nucleo'] ) )['itens'];
		if ( $ult ) {
			$h .= '<ul class="jpx-linhas">' . implode( '', array_map( array( __CLASS__, 'linha' ), $ult ) ) . '</ul>';
		}
		return $h . '</div>';
	}

	/* ---------------------------------------------------------------------
	 * Calendário mensal
	 * ------------------------------------------------------------------ */

	/**
	 * Calendário de um mês.
	 *
	 * @param array $e Estado (mes, tipo, nucleo, q).
	 * @return array{html:string,titulo:string}
	 */
	public static function calendario( $e ) {
		$prim = jpx_ev_dt( $e['mes'] . '-01' );
		if ( ! $prim ) {
			$prim = jpx_ev_dt( wp_date( 'Y-m' ) . '-01' );
		}
		$ult   = $prim->modify( 'last day of this month' );
		$de    = $prim->format( 'Y-m-d' );
		$ate   = $ult->format( 'Y-m-d' );
		$hoje  = jpx_ev_hoje();
		$r     = JPX_EV_Ocorrencias::consultar( array( 'de' => $de, 'ate' => $ate, 'tipo' => $e['tipo'], 'nucleo' => $e['nucleo'], 'busca' => $e['q'] ) );
		$porDia = array();
		foreach ( $r['itens'] as $oc ) {
			$a     = jpx_ev_dt( $oc['oc_inicio'] );
			$b     = jpx_ev_dt( $oc['oc_fim'] );
			$total = (int) $a->diff( $b )->days + 1;
			for ( $i = 0, $d = $a; $d <= $b; $i++, $d = $d->modify( '+1 day' ) ) {
				$iso = $d->format( 'Y-m-d' );
				if ( $iso < $de || $iso > $ate ) {
					continue;
				}
				$porDia[ $iso ][] = array( $oc, $total > 1 ? ( $i + 1 ) . '/' . $total : '' );
			}
		}
		$fer    = JPX_EV_Feriados::entre( $de, $ate );
		$titulo = jpx_ev_formatar_data( $de, 'mes_ano' );
		$ant    = $prim->modify( '-1 month' )->format( 'Y-m' );
		$prox   = $prim->modify( '+1 month' )->format( 'Y-m' );
		$dc     = jpx_ev_nomes( 'dias_curto' );
		$link   = function ( $m ) {
			return esc_url( self::link( array( 'jpx_mes' => $m, 'jpx_v' => 'cal' ) ) );
		};

		ob_start();
		?>
		<div class="jpx-cal" data-mes="<?php echo esc_attr( $prim->format( 'Y-m' ) ); ?>">
			<div class="jpx-cal__topo">
				<h3 class="jpx-cal__titulo"><?php echo esc_html( ucfirst( jpx_ev_nomes( 'meses' )[ (int) $prim->format( 'n' ) ] ) ); ?> <span><?php echo esc_html( $prim->format( 'Y' ) ); ?></span></h3>
				<nav class="jpx-cal__nav" aria-label="<?php esc_attr_e( 'Navegar entre meses', 'jpx-eventos' ); ?>">
					<a class="jpx-btn jpx-btn--borda jpx-btn--p" data-jpx-mes="<?php echo esc_attr( wp_date( 'Y-m' ) ); ?>" href="<?php echo $link( wp_date( 'Y-m' ) ); // phpcs:ignore ?>"><?php esc_html_e( 'Hoje', 'jpx-eventos' ); ?></a>
					<a class="jpx-btn-ico" data-jpx-mes="<?php echo esc_attr( $ant ); ?>" href="<?php echo $link( $ant ); // phpcs:ignore ?>" aria-label="<?php esc_attr_e( 'Mês anterior', 'jpx-eventos' ); ?>"><?php echo jpx_ev_icone( 'esq' ); // phpcs:ignore ?></a>
					<a class="jpx-btn-ico" data-jpx-mes="<?php echo esc_attr( $prox ); ?>" href="<?php echo $link( $prox ); // phpcs:ignore ?>" aria-label="<?php esc_attr_e( 'Próximo mês', 'jpx-eventos' ); ?>"><?php echo jpx_ev_icone( 'dir' ); // phpcs:ignore ?></a>
				</nav>
			</div>
			<div class="jpx-cal__grade">
				<div class="jpx-cal__semana" aria-hidden="true">
					<?php foreach ( $dc as $n ) : ?>
						<span><?php echo esc_html( jpx_ev_maiusc( $n ) ); ?></span>
					<?php endforeach; ?>
				</div>
				<div class="jpx-cal__dias">
					<?php
					$vazios = (int) $prim->format( 'w' );
					for ( $i = 0; $i < $vazios; $i++ ) {
						echo '<div class="jpx-cal__dia is-fora" aria-hidden="true"></div>';
					}
					for ( $d = $prim; $d <= $ult; $d = $d->modify( '+1 day' ) ) {
						$iso   = $d->format( 'Y-m-d' );
						$dow   = (int) $d->format( 'w' );
						$lista = isset( $porDia[ $iso ] ) ? $porDia[ $iso ] : array();
						$cls   = 'jpx-cal__dia';
						$cls  .= ( 0 === $dow || 6 === $dow ) ? ' is-fds' : '';
						$cls  .= isset( $fer[ $iso ] ) ? ' is-feriado' : '';
						$cls  .= $iso === $hoje ? ' is-hoje' : '';
						$cls  .= $iso < $hoje ? ' is-passado' : '';
						$cls  .= $lista ? ' tem-evento' : '';
						echo '<div class="' . esc_attr( $cls ) . '"' . ( $iso === $hoje ? ' data-vazio="' . esc_attr__( 'Nenhum evento hoje', 'jpx-eventos' ) . '"' : '' ) . '>';
						echo '<div class="jpx-cal__cab"><span class="jpx-cal__num">' . esc_html( $d->format( 'j' ) ) . '</span><span class="jpx-cal__rotulo">' . esc_html( jpx_ev_formatar_data( $iso, 'dia_semana_mes' ) ) . '</span>';
						if ( isset( $fer[ $iso ] ) ) {
							echo '<span class="jpx-cal__feriado">' . esc_html( $fer[ $iso ] ) . '</span>';
						}
						echo '</div>';
						foreach ( $lista as $par ) {
							list( $oc, $parte ) = $par;
							echo '<a class="jpx-mini' . ( $oc['passado'] ? ' is-passado' : '' ) . '" style="' . esc_attr( $oc['cores']['css'] ) . '" href="' . esc_url( self::url_ocorrencia( $oc ) ) . '" data-jpx-evento="' . jpx_ev_json_attr( self::dados_modal( $oc ) ) . '">';
							echo '<span class="jpx-mini__faixa"><span>' . esc_html( jpx_ev_maiusc( $oc['tipo_nome'] ? $oc['tipo_nome'] : __( 'Evento', 'jpx-eventos' ) ) ) . '</span>' . ( $parte ? '<span>' . esc_html( $parte ) . '</span>' : '' ) . '</span>';
							echo '<span class="jpx-mini__corpo">' . ( $oc['oc_horario'] ? '<span class="jpx-mini__hora">' . esc_html( $oc['oc_horario'] ) . '</span>' : '' ) . '<span class="jpx-mini__titulo">' . esc_html( $oc['titulo'] ) . '</span></span>';
							echo '</a>';
						}
						echo '</div>';
					}
					$resto = ( 7 - ( ( $vazios + (int) $ult->format( 'j' ) ) % 7 ) ) % 7;
					for ( $i = 0; $i < $resto; $i++ ) {
						echo '<div class="jpx-cal__dia is-fora" aria-hidden="true"></div>';
					}
					?>
				</div>
			</div>
			<?php if ( ! $r['itens'] ) : ?>
				<p class="jpx-cal__vazio"><?php echo esc_html( sprintf( __( 'Nenhum evento em %s.', 'jpx-eventos' ), jpx_ev_minusc( $titulo ) ) ); ?></p>
			<?php endif; ?>
			<div class="jpx-cal__legenda">
				<?php
				$tipos = get_terms( array( 'taxonomy' => JPX_EV_Tipos::TIPO, 'hide_empty' => true ) );
				if ( ! is_wp_error( $tipos ) ) {
					foreach ( $tipos as $t ) {
						echo '<span style="' . esc_attr( JPX_EV_Tipos::cores( $t )['css'] ) . '"><i></i>' . esc_html( $t->name ) . '</span>';
					}
				}
				if ( $fer ) {
					echo '<span class="is-feriado"><i></i>' . esc_html__( 'Feriado', 'jpx-eventos' ) . '</span>';
				}
				?>
			</div>
		</div>
		<?php
		return array( 'html' => trim( ob_get_clean() ), 'titulo' => $titulo );
	}

	/* ---------------------------------------------------------------------
	 * Página do evento
	 * ------------------------------------------------------------------ */

	/**
	 * Ocorrência exibida na página do evento (?data= ou a próxima).
	 *
	 * @param int $id Evento.
	 * @return array|null
	 */
	public static function ocorrencia_da_pagina( $id ) {
		$data = isset( $_GET['data'] ) ? jpx_ev_sanitizar_data( sanitize_text_field( wp_unslash( $_GET['data'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $data ) {
			$r = JPX_EV_Ocorrencias::consultar( array( 'evento' => $id, 'de' => $data, 'ate' => $data, 'limite' => 1, 'canceladas' => true ) );
			if ( $r['itens'] ) {
				return $r['itens'][0];
			}
		}
		$oc = JPX_EV_Ocorrencias::proxima( $id );
		if ( ! $oc ) {
			// Evento não publicado (pré-visualização): monta a partir dos dados.
			$d = JPX_EV_Evento::dados( $id );
			if ( ! $d ) {
				return null;
			}
			$oc = JPX_EV_Ocorrencias::montar(
				(object) array(
					'id'          => 0,
					'evento_id'   => $id,
					'data_inicio' => $d['inicio'],
					'data_fim'    => $d['fim'],
					'hora_inicio' => $d['hora_inicio'],
					'hora_fim'    => $d['hora_fim'],
					'status'      => 'ativa',
					'local'       => '',
					'nota'        => '',
				)
			);
		}
		return $oc;
	}

	/**
	 * Página do evento.
	 *
	 * @param int    $id          Evento.
	 * @param bool   $com_titulo  Incluir título, trilha e capa (modo shortcode/construtor).
	 * @param string $conteudo    HTML do conteúdo (descrição).
	 * @return string
	 */
	public static function single( $id, $com_titulo, $conteudo ) {
		$oc = self::ocorrencia_da_pagina( $id );
		if ( ! $oc ) {
			return $conteudo;
		}
		$cancelada = 'cancelada' === $oc['oc_status'];
		$agenda    = jpx_ev_url_agenda();
		ob_start();
		?>
		<div class="jpx-ev jpx-single<?php echo $com_titulo ? ' com-titulo' : ''; ?>" style="<?php echo esc_attr( $oc['cores']['css'] ); ?>">
			<?php if ( $com_titulo ) : ?>
				<nav class="jpx-trilha" aria-label="<?php esc_attr_e( 'Você está em', 'jpx-eventos' ); ?>">
					<a href="<?php echo esc_url( $agenda ); ?>"><?php esc_html_e( 'Agenda', 'jpx-eventos' ); ?></a>
					<?php if ( $oc['tipo'] && ! is_wp_error( get_term_link( $oc['tipo'] ) ) ) : ?>
						<span aria-hidden="true">/</span><a href="<?php echo esc_url( get_term_link( $oc['tipo'] ) ); ?>"><?php echo esc_html( $oc['tipo_nome'] ); ?></a>
					<?php endif; ?>
					<span aria-hidden="true">/</span><span aria-current="page"><?php echo esc_html( wp_html_excerpt( $oc['titulo'], 60, '…' ) ); ?></span>
				</nav>
				<header class="jpx-single__capa<?php echo $oc['thumb_id'] ? ' tem-foto' : ''; ?>">
					<?php echo self::capa( $oc, 'full' ); // phpcs:ignore ?>
					<div class="jpx-single__capa-txt">
						<div class="jpx-card__tags">
							<?php echo self::tag_tipo( $oc ); // phpcs:ignore ?>
							<?php if ( $oc['nucleo_nome'] ) : ?>
								<span class="jpx-tag jpx-tag--vidro"><?php echo esc_html( $oc['nucleo_nome'] ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( ! $oc['thumb_id'] ) : ?>
							<h1 class="jpx-single__titulo"><?php echo esc_html( $oc['titulo'] ); ?></h1>
						<?php endif; ?>
					</div>
				</header>
				<?php if ( $oc['thumb_id'] ) : ?>
					<h1 class="jpx-single__titulo jpx-single__titulo--fora"><?php echo esc_html( $oc['titulo'] ); ?></h1>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $cancelada ) : ?>
				<div class="jpx-aviso jpx-aviso--erro"><?php echo esc_html( sprintf( __( 'A edição de %s foi cancelada.', 'jpx-eventos' ), jpx_ev_formatar_data( $oc['oc_inicio'], 'dia_mes' ) ) ); ?> <?php echo esc_html( $oc['oc_nota'] ); ?></div>
			<?php elseif ( $oc['oc_local_alterado'] || $oc['oc_nota'] ) : ?>
				<div class="jpx-aviso"><?php echo esc_html( trim( ( $oc['oc_local_alterado'] ? sprintf( __( 'Atenção: nesta data o encontro será em %s.', 'jpx-eventos' ), $oc['oc_local'] ) : '' ) . ' ' . $oc['oc_nota'] ) ); ?></div>
			<?php endif; ?>

			<div class="jpx-single__corpo">
				<div class="jpx-single__principal">
					<?php if ( $oc['resumo'] ) : ?>
						<p class="jpx-single__lead"><?php echo esc_html( $oc['resumo'] ); ?></p>
					<?php endif; ?>
					<?php if ( trim( wp_strip_all_tags( $conteudo ) ) || false !== strpos( $conteudo, '<img' ) ) : ?>
						<div class="jpx-single__conteudo"><?php echo $conteudo; // phpcs:ignore -- conteúdo do post já filtrado. ?></div>
					<?php endif; ?>

					<?php if ( $oc['programacao'] ) : ?>
						<section class="jpx-secao">
							<h2><?php esc_html_e( 'Programação', 'jpx-eventos' ); ?></h2>
							<ol class="jpx-prog">
								<?php foreach ( $oc['programacao'] as $item ) : ?>
									<li><span class="jpx-prog__hora"><?php echo esc_html( $item[0] ); ?></span><span><?php echo esc_html( $item[1] ); ?></span></li>
								<?php endforeach; ?>
							</ol>
						</section>
					<?php endif; ?>

					<?php
					if ( $oc['recorrente'] ) :
						$datas = JPX_EV_Ocorrencias::do_evento( $id, 6 );
						if ( $datas ) :
							?>
							<section class="jpx-secao">
								<h2><?php esc_html_e( 'Próximas datas', 'jpx-eventos' ); ?></h2>
								<p class="jpx-secao__sub"><?php echo esc_html( $oc['recorrencia_txt'] . ( $oc['oc_horario'] ? ', ' . $oc['horario'] : '' ) ); ?></p>
								<ul class="jpx-datas">
									<?php foreach ( $datas as $i => $x ) : ?>
										<?php
										$cls  = 'cancelada' === $x['oc_status'] ? ' is-cancelada' : ( $x['oc_inicio'] === $oc['oc_inicio'] ? ' is-atual' : '' );
										$nota = 'cancelada' === $x['oc_status'] ? __( 'Cancelada', 'jpx-eventos' ) : ( $x['oc_local_alterado'] ? sprintf( __( 'Local: %s', 'jpx-eventos' ), $x['oc_local'] ) : ( 0 === $i ? __( 'próxima', 'jpx-eventos' ) : '' ) );
										?>
										<li class="<?php echo esc_attr( trim( $cls ) ); ?>">
											<a href="<?php echo esc_url( add_query_arg( 'data', $x['oc_inicio'], $oc['url'] ) ); ?>"><strong><?php echo esc_html( jpx_ev_formatar_data( $x['oc_inicio'], 'curto' ) ); ?></strong><?php echo $nota ? '<span>' . esc_html( $nota ) . '</span>' : ''; ?></a>
										</li>
									<?php endforeach; ?>
								</ul>
							</section>
							<?php
						endif;
					endif;
					?>

					<?php if ( $oc['oc_local'] || $oc['endereco'] ) : ?>
						<section class="jpx-secao">
							<h2><?php esc_html_e( 'Local', 'jpx-eventos' ); ?></h2>
							<div class="jpx-local">
								<?php if ( $oc['mapa_url'] ) : ?>
									<div class="jpx-mapa" data-jpx-mapa="<?php echo esc_attr( 'https://www.google.com/maps?q=' . rawurlencode( implode( ', ', array_filter( array( $oc['oc_local'], $oc['endereco'], $oc['cidade'] ) ) ) ) . '&output=embed' ); ?>">
										<button type="button" class="jpx-btn jpx-btn--branco"><?php echo jpx_ev_icone( 'local' ); // phpcs:ignore ?><?php esc_html_e( 'Carregar mapa', 'jpx-eventos' ); ?></button>
									</div>
								<?php endif; ?>
								<div class="jpx-local__txt">
									<div>
										<strong><?php echo esc_html( $oc['oc_local'] ); ?></strong>
										<span><?php echo esc_html( implode( ' · ', array_filter( array( $oc['endereco'], $oc['cidade'] ) ) ) ); ?></span>
									</div>
									<?php if ( $oc['mapa_url'] ) : ?>
										<a href="<?php echo esc_url( $oc['mapa_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Abrir no Google Maps', 'jpx-eventos' ); ?></a>
									<?php endif; ?>
								</div>
							</div>
						</section>
					<?php endif; ?>

					<?php if ( $oc['nucleo'] ) : ?>
						<section class="jpx-secao">
							<h2><?php esc_html_e( 'Organização', 'jpx-eventos' ); ?></h2>
							<div class="jpx-org" style="<?php echo esc_attr( JPX_EV_Tipos::cores( $oc['nucleo'] )['css'] ); ?>">
								<span class="jpx-org__ico"><?php echo jpx_ev_icone( 'pessoas', 24 ); // phpcs:ignore ?></span>
								<div><strong><?php echo esc_html( $oc['nucleo_nome'] ); ?></strong><span><?php esc_html_e( 'Veja outros eventos', 'jpx-eventos' ); ?></span></div>
								<?php if ( $oc['nucleo_url'] ) : ?>
									<a href="<?php echo esc_url( $oc['nucleo_url'] ); ?>"><?php echo esc_html( sprintf( __( 'Ver %s', 'jpx-eventos' ), jpx_ev_minusc( jpx_ev_opcao( 'nucleo_singular' ) ) ) ); ?></a>
								<?php endif; ?>
							</div>
						</section>
					<?php endif; ?>
				</div>

				<aside class="jpx-single__lateral">
					<div class="jpx-painel">
						<div class="jpx-painel__quando">
							<?php echo self::bloco_data( $oc['oc_inicio'], true ); // phpcs:ignore ?>
							<div>
								<strong><?php echo esc_html( self::texto_data_longa( $oc ) ); ?></strong>
								<?php if ( $oc['oc_horario'] ) : ?>
									<span><?php echo esc_html( $oc['oc_hora_fim'] ? sprintf( __( '%1$s às %2$s', 'jpx-eventos' ), $oc['oc_hora_inicio'], $oc['oc_hora_fim'] ) : $oc['oc_horario'] ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<ul class="jpx-painel__lista">
							<?php if ( self::texto_local( $oc ) ) : ?>
								<li><?php echo jpx_ev_icone( 'local' ); // phpcs:ignore ?><span><?php echo esc_html( self::texto_local( $oc ) ); ?></span></li>
							<?php endif; ?>
							<?php if ( $oc['valor_rotulo'] ) : ?>
								<li><?php echo jpx_ev_icone( 'etiqueta' ); // phpcs:ignore ?><span><?php echo esc_html( $oc['valor_rotulo'] ); ?></span></li>
							<?php endif; ?>
							<?php if ( $oc['publico_rotulo'] ) : ?>
								<li><?php echo jpx_ev_icone( 'pessoas' ); // phpcs:ignore ?><span><?php echo esc_html( $oc['publico_rotulo'] ); ?></span></li>
							<?php endif; ?>
							<?php if ( $oc['recorrente'] ) : ?>
								<li><?php echo jpx_ev_icone( 'repetir' ); // phpcs:ignore ?><span><?php echo esc_html( $oc['recorrencia_txt'] ); ?></span></li>
							<?php endif; ?>
						</ul>
						<?php if ( $oc['passado'] ) : ?>
							<p class="jpx-painel__encerrado"><?php esc_html_e( 'Este evento já aconteceu.', 'jpx-eventos' ); ?></p>
						<?php elseif ( $cancelada ) : ?>
							<p class="jpx-painel__encerrado"><?php esc_html_e( 'Esta data foi cancelada.', 'jpx-eventos' ); ?></p>
						<?php else : ?>
							<?php if ( $oc['inscricao'] ) : ?>
								<a class="jpx-btn jpx-btn--cheio jpx-btn--bloco" href="<?php echo esc_url( $oc['inscricao'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Inscrever-se', 'jpx-eventos' ); ?><?php echo jpx_ev_icone( 'externo', 16 ); // phpcs:ignore ?></a>
							<?php endif; ?>
							<div class="jpx-painel__bloco">
								<span class="jpx-painel__rot"><?php esc_html_e( 'Adicionar à agenda', 'jpx-eventos' ); ?></span>
								<div class="jpx-painel__agendas">
									<a class="jpx-btn jpx-btn--borda jpx-btn--p" href="<?php echo esc_url( JPX_EV_ICS::url_google( $oc ) ); ?>" target="_blank" rel="noopener">Google</a>
									<a class="jpx-btn jpx-btn--borda jpx-btn--p" href="<?php echo esc_url( JPX_EV_ICS::url_outlook( $oc ) ); ?>" target="_blank" rel="noopener">Outlook</a>
									<a class="jpx-btn jpx-btn--borda jpx-btn--p" href="<?php echo esc_url( JPX_EV_ICS::url_evento( $id, $oc['recorrente'] ? '' : $oc['oc_inicio'] ) ); ?>">.ics</a>
								</div>
							</div>
						<?php endif; ?>
						<div class="jpx-painel__compartilhar">
							<span class="jpx-painel__rot"><?php esc_html_e( 'Compartilhar', 'jpx-eventos' ); ?></span>
							<div>
								<a class="jpx-btn-ico jpx-btn-ico--cinza" href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( $oc['titulo'] . ' — ' . self::texto_quando( $oc ) . ' ' . $oc['url'] ) ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Compartilhar no WhatsApp', 'jpx-eventos' ); ?>"><?php echo jpx_ev_icone( 'mensagem' ); // phpcs:ignore ?></a>
								<button type="button" class="jpx-btn-ico jpx-btn-ico--cinza" data-jpx-compartilhar="<?php echo esc_attr( $oc['url'] ); ?>" data-titulo="<?php echo esc_attr( $oc['titulo'] ); ?>" hidden aria-label="<?php esc_attr_e( 'Compartilhar', 'jpx-eventos' ); ?>"><?php echo jpx_ev_icone( 'compartilhar' ); // phpcs:ignore ?></button>
								<button type="button" class="jpx-btn-ico jpx-btn-ico--cinza" data-jpx-copiar="<?php echo esc_attr( $oc['url'] ); ?>" aria-label="<?php esc_attr_e( 'Copiar link', 'jpx-eventos' ); ?>"><?php echo jpx_ev_icone( 'link' ); // phpcs:ignore ?></button>
							</div>
						</div>
					</div>
				</aside>
			</div>

			<?php
			$rel = self::relacionados( $oc );
			if ( $rel ) :
				?>
				<section class="jpx-relacionados">
					<div class="jpx-relacionados__topo">
						<h2><?php esc_html_e( 'Outros eventos', 'jpx-eventos' ); ?></h2>
						<a href="<?php echo esc_url( $agenda ); ?>"><?php esc_html_e( 'Ver agenda completa', 'jpx-eventos' ); ?></a>
					</div>
					<ul class="jpx-linhas jpx-linhas--grade"><?php echo implode( '', array_map( array( __CLASS__, 'linha' ), $rel ) ); // phpcs:ignore ?></ul>
				</section>
			<?php endif; ?>
		</div>
		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * Até 3 próximos eventos, priorizando o mesmo tipo.
	 *
	 * @param array $oc Ocorrência atual.
	 * @return array
	 */
	public static function relacionados( $oc ) {
		$base = array( 'de' => jpx_ev_hoje(), 'por_evento' => true, 'limite' => 3, 'excluir' => $oc['id'] );
		$a    = $oc['tipo'] ? JPX_EV_Ocorrencias::consultar( $base + array( 'tipo' => $oc['tipo']->term_id ) )['itens'] : array();
		if ( count( $a ) < 3 ) {
			$ids = wp_list_pluck( $a, 'id' );
			foreach ( JPX_EV_Ocorrencias::consultar( array_merge( $base, array( 'limite' => 6 ) ) )['itens'] as $x ) {
				if ( count( $a ) >= 3 ) {
					break;
				}
				if ( ! in_array( $x['id'], $ids, true ) ) {
					$a[] = $x;
				}
			}
		}
		return $a;
	}
}
