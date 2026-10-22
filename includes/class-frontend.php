<?php
/**
 * Front: assets, variáveis de identidade e shortcodes.
 *
 * Shortcodes:
 *   [jpx_agenda]   Agenda completa (lista + calendário, filtros, abas).
 *   [jpx_calendario] Só o calendário mensal.
 *   [jpx_eventos]  Próximos eventos (cards ou lista compacta) — home, núcleos, sidebar.
 *   [jpx_evento]   Página do evento (para templates de construtores como o Divi).
 *   Compatíveis com a versão 1.x: [lista_eventos], [eventos_home], [evento_detalhe].
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_Frontend {

	const SHORTCODES = array( 'jpx_agenda', 'jpx_calendario', 'jpx_eventos', 'jpx_evento', 'lista_eventos', 'eventos_home', 'evento_detalhe' );

	/** @var bool Já enfileirado. */
	private static $enfileirado = false;

	/**
	 * Hooks.
	 */
	public static function iniciar() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'registrar_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'talvez_enfileirar' ), 20 );
		add_shortcode( 'jpx_agenda', array( __CLASS__, 'sc_agenda' ) );
		add_shortcode( 'jpx_calendario', array( __CLASS__, 'sc_calendario' ) );
		add_shortcode( 'jpx_eventos', array( __CLASS__, 'sc_eventos' ) );
		add_shortcode( 'jpx_evento', array( __CLASS__, 'sc_evento' ) );
		add_shortcode( 'lista_eventos', array( __CLASS__, 'sc_legado_lista' ) );
		add_shortcode( 'eventos_home', array( __CLASS__, 'sc_legado_home' ) );
		add_shortcode( 'evento_detalhe', array( __CLASS__, 'sc_evento' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_arquivo' ) );
	}

	/**
	 * Registra CSS/JS.
	 */
	public static function registrar_assets() {
		wp_register_style( 'jpx-eventos', JPX_EV_URL . 'assets/css/front.css', array(), JPX_EV_VERSION );
		wp_register_script( 'jpx-eventos', JPX_EV_URL . 'assets/js/front.js', array(), JPX_EV_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	/**
	 * Enfileira cedo quando a página certamente usa o plugin (evita "piscar" do CSS).
	 */
	public static function talvez_enfileirar() {
		$usa = is_singular( JPX_EV_Tipos::CPT ) || is_post_type_archive( JPX_EV_Tipos::CPT ) || is_tax( array( JPX_EV_Tipos::TIPO, JPX_EV_Tipos::NUCLEO ) );
		if ( ! $usa && is_singular() ) {
			$post = get_post();
			if ( $post ) {
				foreach ( self::SHORTCODES as $sc ) {
					if ( has_shortcode( $post->post_content, $sc ) ) {
						$usa = true;
						break;
					}
				}
			}
		}
		if ( $usa || apply_filters( 'jpx_ev_carregar_sempre', false ) ) {
			self::enfileirar();
		}
	}

	/**
	 * Enfileira CSS/JS + variáveis.
	 */
	public static function enfileirar() {
		if ( self::$enfileirado ) {
			return;
		}
		self::$enfileirado = true;
		if ( ! wp_style_is( 'jpx-eventos', 'registered' ) ) {
			self::registrar_assets();
		}
		wp_enqueue_style( 'jpx-eventos' );
		wp_add_inline_style( 'jpx-eventos', self::css_tokens() );
		wp_enqueue_script( 'jpx-eventos' );
		wp_localize_script(
			'jpx-eventos',
			'JPX_EV',
			array(
				'rest'  => esc_url_raw( rest_url( 'jpx-eventos/v1/' ) ),
				'txt'   => array(
					'fechar'      => __( 'Fechar', 'jpx-eventos' ),
					'detalhes'    => __( 'Ver detalhes', 'jpx-eventos' ),
					'inscrever'   => __( 'Inscrever-se', 'jpx-eventos' ),
					'agenda'      => __( 'Adicionar à agenda', 'jpx-eventos' ),
					'mapa'        => __( 'Ver no mapa', 'jpx-eventos' ),
					'copiado'     => __( 'Link copiado!', 'jpx-eventos' ),
					'carregando'  => __( 'Carregando…', 'jpx-eventos' ),
					'erro'        => __( 'Não foi possível carregar. Tente de novo.', 'jpx-eventos' ),
					'evento'      => __( 'Resumo do evento', 'jpx-eventos' ),
				),
			)
		);
	}

	/**
	 * CSS com as variáveis de identidade.
	 *
	 * @return string
	 */
	public static function css_tokens() {
		$css = '';
		foreach ( jpx_ev_tokens() as $k => $v ) {
			$css .= $k . ':' . $v . ';';
		}
		return ':root{' . $css . '}';
	}

	/* ---------------------------------------------------------------------
	 * Shortcodes
	 * ------------------------------------------------------------------ */

	/**
	 * [jpx_agenda]
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public static function sc_agenda( $atts ) {
		self::enfileirar();
		$atts = shortcode_atts(
			array(
				'cabecalho'  => 'sim',
				'chamada'    => __( 'Agenda', 'jpx-eventos' ),
				'titulo'     => __( 'Próximos eventos', 'jpx-eventos' ),
				'descricao'  => '',
				'visao'      => 'lista',
				'tipo'       => '',
				'nucleo'     => '',
				'por_pagina' => jpx_ev_opcao( 'itens_por_pagina' ),
				'filtros'    => 'sim',
			),
			$atts,
			'jpx_agenda'
		);
		$e    = JPX_EV_Render::estado( $atts, $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
		$cont = JPX_EV_Render::contagens( $e );
		$hero = 'nao' !== $atts['cabecalho'];

		$toggle = '<div class="jpx-alternar" role="group" aria-label="' . esc_attr__( 'Modo de exibição', 'jpx-eventos' ) . '">'
			. '<a href="' . esc_url( add_query_arg( 'jpx_v', 'lista' ) ) . '" data-jpx-visao="lista" aria-pressed="' . ( 'lista' === $e['visao'] ? 'true' : 'false' ) . '">' . jpx_ev_icone( 'lista', 16 ) . esc_html__( 'Lista', 'jpx-eventos' ) . '</a>'
			. '<a href="' . esc_url( add_query_arg( 'jpx_v', 'cal' ) ) . '" data-jpx-visao="cal" aria-pressed="' . ( 'cal' === $e['visao'] ? 'true' : 'false' ) . '">' . jpx_ev_icone( 'calendario', 16 ) . esc_html__( 'Calendário', 'jpx-eventos' ) . '</a></div>';

		$estado_js = array(
			'visao'  => $e['visao'],
			'aba'    => $e['aba'],
			'tipo'   => $e['fixo_tipo'] ? '' : $e['tipo'],
			'nucleo' => $e['fixo_nucleo'] ? '' : $e['nucleo'],
			'q'      => $e['q'],
			'mes'    => $e['mes'],
			'pag'    => $e['pag'],
		);
		$fixos = array(
			'tipo'       => $e['fixo_tipo'] ? $e['tipo'] : '',
			'nucleo'     => $e['fixo_nucleo'] ? $e['nucleo'] : '',
			'por_pagina' => $e['por_pagina'],
		);

		ob_start();
		?>
		<div class="jpx-ev jpx-agenda<?php echo $hero ? ' com-hero' : ''; ?>" data-jpx-agenda data-estado="<?php echo jpx_ev_json_attr( $estado_js ); ?>" data-fixos="<?php echo jpx_ev_json_attr( $fixos ); ?>">
			<?php if ( $hero ) : ?>
				<section class="jpx-hero">
					<div class="jpx-hero__txt">
						<?php if ( $atts['chamada'] ) : ?>
							<span class="jpx-hero__chamada"><?php echo esc_html( $atts['chamada'] ); ?></span>
						<?php endif; ?>
						<h2 class="jpx-hero__titulo"><?php echo esc_html( $atts['titulo'] ); ?></h2>
						<?php if ( $atts['descricao'] ) : ?>
							<p class="jpx-hero__desc"><?php echo esc_html( $atts['descricao'] ); ?></p>
						<?php endif; ?>
					</div>
					<?php echo $toggle; // phpcs:ignore ?>
				</section>
			<?php endif; ?>

			<?php if ( 'nao' !== $atts['filtros'] ) : ?>
				<?php echo self::barra_filtros( $e, $hero ? '' : $toggle ); // phpcs:ignore ?>
			<?php elseif ( ! $hero ) : ?>
				<div class="jpx-barra jpx-barra--simples"><?php echo $toggle; // phpcs:ignore ?></div>
			<?php endif; ?>

			<div class="jpx-agenda__lista" data-jpx-parte="lista"<?php echo 'lista' === $e['visao'] ? '' : ' hidden'; ?>>
				<div class="jpx-abas" role="tablist" aria-label="<?php esc_attr_e( 'Período', 'jpx-eventos' ); ?>">
					<a role="tab" data-jpx-aba="prox" aria-selected="<?php echo 'prox' === $e['aba'] ? 'true' : 'false'; ?>" href="<?php echo esc_url( remove_query_arg( array( 'jpx_aba', 'jpx_pag' ) ) ); ?>"><?php esc_html_e( 'Próximos', 'jpx-eventos' ); ?> <span class="jpx-abas__n" data-jpx-n="prox"><?php echo (int) $cont['prox']; ?></span></a>
					<a role="tab" data-jpx-aba="ant" aria-selected="<?php echo 'ant' === $e['aba'] ? 'true' : 'false'; ?>" href="<?php echo esc_url( add_query_arg( 'jpx_aba', 'ant', remove_query_arg( 'jpx_pag' ) ) ); ?>"><?php esc_html_e( 'Anteriores', 'jpx-eventos' ); ?> <span class="jpx-abas__n" data-jpx-n="ant"><?php echo (int) $cont['ant']; ?></span></a>
				</div>
				<div class="jpx-agenda__resultado" data-jpx-resultado aria-live="polite">
					<?php echo 'lista' === $e['visao'] ? JPX_EV_Render::lista( $e )['html'] : ''; // phpcs:ignore ?>
				</div>
			</div>

			<div class="jpx-agenda__cal" data-jpx-parte="cal"<?php echo 'cal' === $e['visao'] ? '' : ' hidden'; ?>>
				<div data-jpx-cal aria-live="polite">
					<?php echo 'cal' === $e['visao'] ? JPX_EV_Render::calendario( $e )['html'] : ''; // phpcs:ignore ?>
				</div>
			</div>
		</div>
		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * Barra de busca + filtros.
	 *
	 * @param array  $e      Estado.
	 * @param string $toggle Alternador lista/calendário (quando não há cabeçalho).
	 * @return string
	 */
	private static function barra_filtros( $e, $toggle ) {
		$tipos   = $e['fixo_tipo'] ? array() : get_terms( array( 'taxonomy' => JPX_EV_Tipos::TIPO, 'hide_empty' => true ) );
		$nucleos = $e['fixo_nucleo'] ? array() : get_terms( array( 'taxonomy' => JPX_EV_Tipos::NUCLEO, 'hide_empty' => true ) );
		$tipos   = is_wp_error( $tipos ) ? array() : $tipos;
		$nucleos = is_wp_error( $nucleos ) ? array() : $nucleos;
		$uid     = wp_unique_id( 'jpx-' );
		ob_start();
		?>
		<div class="jpx-barra">
			<form class="jpx-barra__linha" method="get" action="" data-jpx-filtros role="search">
				<div class="jpx-busca">
					<label for="<?php echo esc_attr( $uid ); ?>-q" class="jpx-sr"><?php esc_html_e( 'Buscar evento', 'jpx-eventos' ); ?></label>
					<?php echo jpx_ev_icone( 'busca' ); // phpcs:ignore ?>
					<input id="<?php echo esc_attr( $uid ); ?>-q" type="search" name="jpx_q" value="<?php echo esc_attr( $e['q'] ); ?>" placeholder="<?php esc_attr_e( 'Buscar por palestra, curso, local…', 'jpx-eventos' ); ?>" autocomplete="off">
				</div>
				<?php if ( $nucleos ) : ?>
					<div class="jpx-select">
						<label for="<?php echo esc_attr( $uid ); ?>-n" class="jpx-sr"><?php echo esc_html( sprintf( __( 'Filtrar por %s', 'jpx-eventos' ), jpx_ev_minusc( jpx_ev_opcao( 'nucleo_singular' ) ) ) ); ?></label>
						<select id="<?php echo esc_attr( $uid ); ?>-n" name="jpx_nucleo">
							<option value=""><?php echo esc_html( sprintf( __( 'Todos os %s', 'jpx-eventos' ), jpx_ev_minusc( jpx_ev_opcao( 'nucleo_plural' ) ) ) ); ?></option>
							<?php foreach ( $nucleos as $n ) : ?>
								<option value="<?php echo esc_attr( $n->slug ); ?>" <?php selected( $e['nucleo'], $n->slug ); ?>><?php echo esc_html( $n->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>
				<input type="hidden" name="jpx_v" value="<?php echo esc_attr( $e['visao'] ); ?>">
				<noscript><button type="submit" class="jpx-btn jpx-btn--cheio"><?php esc_html_e( 'Filtrar', 'jpx-eventos' ); ?></button></noscript>
				<?php echo $toggle; // phpcs:ignore ?>
			</form>
			<?php if ( count( $tipos ) > 1 ) : ?>
				<div class="jpx-chips" role="group" aria-label="<?php esc_attr_e( 'Tipo de evento', 'jpx-eventos' ); ?>">
					<a class="jpx-chip" data-jpx-tipo="" aria-pressed="<?php echo $e['tipo'] ? 'false' : 'true'; ?>" href="<?php echo esc_url( remove_query_arg( array( 'jpx_tipo', 'jpx_pag' ) ) ); ?>"><?php esc_html_e( 'Todos', 'jpx-eventos' ); ?></a>
					<?php foreach ( $tipos as $t ) : ?>
						<a class="jpx-chip" style="<?php echo esc_attr( JPX_EV_Tipos::cores( $t )['css'] ); ?>" data-jpx-tipo="<?php echo esc_attr( $t->slug ); ?>" aria-pressed="<?php echo $e['tipo'] === $t->slug ? 'true' : 'false'; ?>" href="<?php echo esc_url( add_query_arg( 'jpx_tipo', $t->slug, remove_query_arg( 'jpx_pag' ) ) ); ?>"><span class="jpx-chip__ponto"></span><?php echo esc_html( $t->name ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * [jpx_calendario]
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public static function sc_calendario( $atts ) {
		$atts = shortcode_atts( array( 'tipo' => '', 'nucleo' => '', 'filtros' => 'nao', 'cabecalho' => 'nao' ), $atts, 'jpx_calendario' );
		return self::sc_agenda(
			array(
				'visao'     => 'calendario',
				'tipo'      => $atts['tipo'],
				'nucleo'    => $atts['nucleo'],
				'filtros'   => $atts['filtros'],
				'cabecalho' => $atts['cabecalho'],
			)
		);
	}

	/**
	 * [jpx_eventos]
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public static function sc_eventos( $atts ) {
		self::enfileirar();
		$atts = shortcode_atts(
			array(
				'quantidade'  => 4,
				'tipo'        => '',
				'nucleo'      => '',
				'layout'      => 'cards',
				'titulo'      => '',
				'link_agenda' => 'sim',
				'passados'    => 'nao',
				'vazio'       => __( 'Nenhum evento programado no momento.', 'jpx-eventos' ),
				'colunas'     => '',
				'agrupar'     => 'sim',
				'se_vazio'    => 'anteriores',
			),
			$atts,
			'jpx_eventos'
		);
		$qtd  = (int) $atts['quantidade'];
		$args = array(
			'tipo'       => $atts['tipo'],
			'nucleo'     => $atts['nucleo'],
			'por_evento' => 'nao' !== $atts['agrupar'],
			'limite'     => $qtd > 0 ? $qtd : 0,
		);
		$args += 'sim' === $atts['passados'] ? array( 'passados' => true ) : array( 'de' => jpx_ev_hoje() );
		$itens = JPX_EV_Ocorrencias::consultar( $args )['itens'];

		// Sem próximos eventos: mostra os últimos realizados, para a seção não ficar vazia.
		$aviso = '';
		if ( ! $itens && 'sim' !== $atts['passados'] && 'anteriores' === $atts['se_vazio'] ) {
			$args['passados'] = true;
			unset( $args['de'] );
			$itens = JPX_EV_Ocorrencias::consultar( $args )['itens'];
			if ( $itens ) {
				$aviso = '<p class="jpx-widget__aviso">' . esc_html( $atts['vazio'] ) . ' ' . esc_html__( 'Veja os últimos realizados:', 'jpx-eventos' ) . '</p>';
			}
		}

		$compacto = 'compacto' === $atts['layout'];
		$cols     = (int) $atts['colunas'];
		$h        = '<div class="jpx-ev jpx-widget' . ( $compacto ? ' jpx-widget--compacto' : '' ) . '">';
		if ( $atts['titulo'] || 'sim' === $atts['link_agenda'] ) {
			$h .= '<div class="jpx-widget__topo">';
			$h .= $atts['titulo'] ? '<h2 class="jpx-widget__titulo">' . esc_html( $atts['titulo'] ) . '</h2>' : '<span></span>';
			if ( 'sim' === $atts['link_agenda'] ) {
				$h .= '<a class="jpx-widget__link" href="' . esc_url( jpx_ev_url_agenda() ) . '">' . esc_html__( 'Agenda completa', 'jpx-eventos' ) . jpx_ev_icone( 'seta', 16 ) . '</a>';
			}
			$h .= '</div>';
		}
		$h .= $aviso;
		if ( ! $itens ) {
			$h .= '<p class="jpx-widget__vazio">' . esc_html( $atts['vazio'] ) . '</p>';
		} elseif ( $compacto ) {
			$h .= '<ul class="jpx-linhas">' . implode( '', array_map( array( 'JPX_EV_Render', 'linha' ), $itens ) ) . '</ul>';
		} else {
			$h .= '<div class="jpx-grade"' . ( $cols ? ' style="--jpx-cols:' . (int) $cols . '"' : '' ) . '>' . implode( '', array_map( array( 'JPX_EV_Render', 'card' ), $itens ) ) . '</div>';
		}
		return $h . '</div>';
	}

	/**
	 * [jpx_evento] / [evento_detalhe] — página do evento completa (com título).
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public static function sc_evento( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'jpx_evento' );
		$id   = (int) $atts['id'] ? (int) $atts['id'] : get_the_ID();
		if ( ! $id || JPX_EV_Tipos::CPT !== get_post_type( $id ) ) {
			return current_user_can( 'edit_posts' ) ? '<p>' . esc_html__( 'Este shortcode só funciona dentro de um evento.', 'jpx-eventos' ) . '</p>' : '';
		}
		self::enfileirar();
		return JPX_EV_Single::renderizar( $id, true );
	}

	/**
	 * [lista_eventos] da versão 1.x.
	 *
	 * @return string
	 */
	public static function sc_legado_lista() {
		return self::sc_agenda( array( 'cabecalho' => 'nao' ) );
	}

	/**
	 * [eventos_home] da versão 1.x.
	 *
	 * @return string
	 */
	public static function sc_legado_home() {
		return self::sc_eventos( array( 'quantidade' => 4, 'link_agenda' => 'nao' ) );
	}

	/**
	 * Arquivo de eventos e páginas de tipo/núcleo usam a agenda (temas clássicos).
	 *
	 * @param string $tpl Template.
	 * @return string
	 */
	public static function template_arquivo( $tpl ) {
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return $tpl;
		}
		if ( is_post_type_archive( JPX_EV_Tipos::CPT ) || is_tax( array( JPX_EV_Tipos::TIPO, JPX_EV_Tipos::NUCLEO ) ) ) {
			$proprio = locate_template( array( 'jpx-eventos/arquivo.php' ) );
			return $proprio ? $proprio : JPX_EV_DIR . 'templates/arquivo.php';
		}
		return $tpl;
	}
}
