<?php
/**
 * Tela de cadastro do evento em 4 passos (O quê, Quando, Onde, Inscrição).
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_Admin_Editor {

	/**
	 * Hooks.
	 */
	public static function iniciar() {
		add_filter( 'enter_title_here', array( __CLASS__, 'placeholder_titulo' ), 10, 2 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'formulario' ) );
		add_action( 'edit_form_after_editor', array( __CLASS__, 'apos_editor' ) );
		add_action( 'add_meta_boxes_' . JPX_EV_Tipos::CPT, array( __CLASS__, 'caixas' ) );
		add_action( 'save_post_' . JPX_EV_Tipos::CPT, array( __CLASS__, 'salvar' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'post_updated_messages', array( __CLASS__, 'mensagens' ) );
	}

	/**
	 * Estamos na tela de edição de evento?
	 *
	 * @return bool
	 */
	private static function na_tela() {
		$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $s && 'post' === $s->base && JPX_EV_Tipos::CPT === $s->post_type;
	}

	/**
	 * CSS/JS.
	 */
	public static function assets() {
		if ( ! self::na_tela() ) {
			return;
		}
		wp_enqueue_style( 'jpx-ev-admin', JPX_EV_URL . 'assets/css/admin.css', array(), JPX_EV_VERSION );
		wp_add_inline_style( 'jpx-ev-admin', JPX_EV_Frontend::css_tokens() );
		wp_enqueue_script( 'jpx-ev-editor', JPX_EV_URL . 'assets/js/admin-editor.js', array(), JPX_EV_VERSION, true );
		wp_localize_script(
			'jpx-ev-editor',
			'JPX_EV_ED',
			array(
				'rest'  => esc_url_raw( rest_url( 'jpx-eventos/v1/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'meses' => array_values( jpx_ev_nomes( 'meses_curto' ) ),
				'dias'  => jpx_ev_nomes( 'dias_curto' ),
				'txt'   => array(
					'cancelar'   => __( 'Cancelar esta data', 'jpx-eventos' ),
					'alterar'    => __( 'Alterar', 'jpx-eventos' ),
					'restaurar'  => __( 'Restaurar', 'jpx-eventos' ),
					'cancelada'  => __( 'Cancelada', 'jpx-eventos' ),
					'alterada'   => __( 'Alterada', 'jpx-eventos' ),
					'local'      => __( 'Local nesta data', 'jpx-eventos' ),
					'hora'       => __( 'Horário', 'jpx-eventos' ),
					'nota'       => __( 'Aviso (opcional)', 'jpx-eventos' ),
					'ok'         => __( 'Aplicar', 'jpx-eventos' ),
					'ocorrencias' => __( 'ocorrências', 'jpx-eventos' ),
					'semNome'    => __( 'Nome do evento', 'jpx-eventos' ),
					'personalizar' => __( 'Personalizar…', 'jpx-eventos' ),
				),
			)
		);
	}

	/**
	 * Placeholder do título.
	 *
	 * @param string  $t    Texto.
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function placeholder_titulo( $t, $post ) {
		return JPX_EV_Tipos::CPT === $post->post_type ? __( 'Nome do evento', 'jpx-eventos' ) : $t;
	}

	/**
	 * Caixas laterais.
	 */
	public static function caixas() {
		add_meta_box( 'jpx_ev_previa', __( 'Como vai aparecer no site', 'jpx-eventos' ), array( __CLASS__, 'caixa_previa' ), JPX_EV_Tipos::CPT, 'side', 'high' );
		remove_meta_box( 'slugdiv', JPX_EV_Tipos::CPT, 'normal' );
	}

	/**
	 * Valores do formulário (evento existente ou novo).
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	private static function valores( $post ) {
		$novo = 'auto-draft' === $post->post_status;
		$d    = JPX_EV_Evento::dados( $post );
		if ( $novo ) {
			$data = isset( $_GET['data'] ) ? jpx_ev_sanitizar_data( sanitize_text_field( wp_unslash( $_GET['data'] ) ) ) : ''; // phpcs:ignore
			$lpad = (int) jpx_ev_opcao( 'local_padrao' );
			$d    = array_merge(
				$d,
				array(
					'inicio'      => $data ? $data : jpx_ev_hoje(),
					'fim'         => $data ? $data : jpx_ev_hoje(),
					'hora_inicio' => '19:00',
					'hora_fim'    => '21:00',
					'valor_tipo'  => 'gratuito',
					'publico'     => 'aberto',
					'local_id'    => $lpad,
				)
			);
		}
		return array( $d, $novo );
	}

	/**
	 * Qual sugestão de repetição corresponde à regra salva.
	 *
	 * @param array $d Dados.
	 * @return string
	 */
	private static function chave_repeticao( $d ) {
		if ( ! $d['recorrente'] ) {
			return 'nenhuma';
		}
		$campos = array( 'freq', 'intervalo', 'dias_semana', 'modo_mensal', 'posicao', 'dia_semana', 'dia_mes' );
		foreach ( JPX_EV_Recorrencia::sugestoes( $d['inicio'] ) as $s ) {
			$igual = true;
			foreach ( $campos as $c ) {
				if ( 'mensal' !== $d['recorrencia']['freq'] && in_array( $c, array( 'modo_mensal', 'posicao', 'dia_semana', 'dia_mes' ), true ) ) {
					continue;
				}
				if ( 'mensal' === $d['recorrencia']['freq'] && ( ( 'dia' === $d['recorrencia']['modo_mensal'] && in_array( $c, array( 'posicao', 'dia_semana' ), true ) ) || ( 'posicao' === $d['recorrencia']['modo_mensal'] && 'dia_mes' === $c ) ) ) {
					continue;
				}
				if ( $s['regra'][ $c ] != $d['recorrencia'][ $c ] ) { // phpcs:ignore Universal.Operators.StrictComparisons
					$igual = false;
					break;
				}
			}
			if ( $igual ) {
				return $s['chave'];
			}
		}
		return 'personalizar';
	}

	/**
	 * Formulário principal (logo abaixo do título).
	 *
	 * @param WP_Post $post Post.
	 */
	public static function formulario( $post ) {
		if ( JPX_EV_Tipos::CPT !== $post->post_type ) {
			return;
		}
		list( $d, $novo ) = self::valores( $post );
		wp_nonce_field( 'jpx_ev_salvar', 'jpx_ev_nonce' );

		$tipos   = get_terms( array( 'taxonomy' => JPX_EV_Tipos::TIPO, 'hide_empty' => false ) );
		$nucleos = get_terms( array( 'taxonomy' => JPX_EV_Tipos::NUCLEO, 'hide_empty' => false ) );
		$locais  = get_terms( array( 'taxonomy' => JPX_EV_Tipos::LOCAL, 'hide_empty' => false ) );
		$tipos   = is_wp_error( $tipos ) ? array() : $tipos;
		$nucleos = is_wp_error( $nucleos ) ? array() : $nucleos;
		$locais  = is_wp_error( $locais ) ? array() : $locais;

		$tipo_id   = $d['tipo'] ? $d['tipo']->term_id : 0;
		$nucleo_id = $d['nucleo'] ? $d['nucleo']->term_id : 0;
		$regra     = $d['recorrencia'];
		$chave     = self::chave_repeticao( $d );
		$sug       = JPX_EV_Recorrencia::sugestoes( $d['inicio'] );
		$local_sel = $d['local_id'] ? $d['local_id'] : ( $d['local_nome'] ? 'novo' : 0 );
		$sem_hora = ! $novo && ! $d['hora_inicio'] && ! $d['dia_inteiro'] && $d['hora_legado'];
		$dc       = jpx_ev_nomes( 'dias_curto' );
		?>
		<div class="jpx-ed" data-jpx-editor data-novo="<?php echo $novo ? '1' : '0'; ?>">

			<section class="jpx-ed__passo">
				<header><span class="jpx-ed__num">1</span><h2><?php esc_html_e( 'O quê', 'jpx-eventos' ); ?></h2><?php echo JPX_EV_Admin_Ajuda::dica( __( 'O tipo define a cor do evento no site e no calendário. O núcleo é opcional: liga o evento a um grupo e aparece no filtro da agenda.', 'jpx-eventos' ), 'passo-o-que' ); // phpcs:ignore ?></header>
				<div class="jpx-ed__linha">
					<div class="jpx-ed__grupo">
						<span class="jpx-ed__rot"><?php esc_html_e( 'Tipo', 'jpx-eventos' ); ?></span>
						<div class="jpx-ed__chips" role="radiogroup" aria-label="<?php esc_attr_e( 'Tipo de evento', 'jpx-eventos' ); ?>">
							<?php foreach ( $tipos as $t ) : $c = JPX_EV_Tipos::cores( $t ); ?>
								<label class="jpx-ed__chip" style="<?php echo esc_attr( '--c:' . $c['forte'] ); ?>"><input type="radio" name="jpx[tipo]" value="<?php echo (int) $t->term_id; ?>" data-cor="<?php echo esc_attr( $c['forte'] ); ?>" <?php checked( $tipo_id, $t->term_id ); ?>><span><i></i><?php echo esc_html( $t->name ); ?></span></label>
							<?php endforeach; ?>
							<label class="jpx-ed__chip jpx-ed__chip--fantasma"><input type="radio" name="jpx[tipo]" value="0" <?php checked( $tipo_id, 0 ); ?>><span><?php esc_html_e( 'Sem tipo', 'jpx-eventos' ); ?></span></label>
							<a class="jpx-ed__mini-link" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . JPX_EV_Tipos::TIPO . '&post_type=' . JPX_EV_Tipos::CPT ) ); ?>"><?php esc_html_e( 'Gerenciar tipos', 'jpx-eventos' ); ?></a>
						</div>
					</div>
					<div class="jpx-ed__grupo">
						<label class="jpx-ed__rot" for="jpx-nucleo"><?php echo esc_html( jpx_ev_opcao( 'nucleo_singular' ) ); ?></label>
						<select id="jpx-nucleo" name="jpx[nucleo]">
							<option value="0"><?php esc_html_e( '— Nenhum —', 'jpx-eventos' ); ?></option>
							<?php foreach ( $nucleos as $n ) : ?>
								<option value="<?php echo (int) $n->term_id; ?>" <?php selected( $nucleo_id, $n->term_id ); ?>><?php echo esc_html( $n->name ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if ( ! $nucleos ) : ?>
							<a class="jpx-ed__mini-link" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . JPX_EV_Tipos::NUCLEO . '&post_type=' . JPX_EV_Tipos::CPT ) ); ?>"><?php esc_html_e( 'Cadastrar', 'jpx-eventos' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</section>

			<section class="jpx-ed__passo">
				<header><span class="jpx-ed__num">2</span><h2><?php esc_html_e( 'Quando', 'jpx-eventos' ); ?></h2><?php echo JPX_EV_Admin_Ajuda::dica( __( 'Data e horário. Marque “Vários dias” para cursos e feiras, ou “Dia inteiro” quando não há horário.', 'jpx-eventos' ), 'passo-quando' ); // phpcs:ignore ?></header>
				<?php if ( $sem_hora ) : ?>
					<p class="jpx-ed__alerta"><?php echo esc_html( sprintf( __( 'Horário do cadastro antigo: “%s”. Escolha o horário abaixo para ele aparecer certinho no calendário.', 'jpx-eventos' ), $d['hora_legado'] ) ); ?></p>
				<?php endif; ?>
				<div class="jpx-ed__quando">
					<label class="jpx-sr" for="jpx-inicio"><?php esc_html_e( 'Data', 'jpx-eventos' ); ?></label>
					<input type="date" id="jpx-inicio" name="jpx[inicio]" value="<?php echo esc_attr( $d['inicio'] ); ?>" required>
					<span class="jpx-ed__ate" data-jpx-fim <?php echo $d['varios_dias'] ? '' : 'hidden'; ?>>
						<span><?php esc_html_e( 'até', 'jpx-eventos' ); ?></span>
						<label class="jpx-sr" for="jpx-fim"><?php esc_html_e( 'Data final', 'jpx-eventos' ); ?></label>
						<input type="date" id="jpx-fim" name="jpx[fim]" value="<?php echo esc_attr( $d['fim'] ); ?>">
					</span>
					<span class="jpx-ed__horas" data-jpx-horas <?php echo $d['dia_inteiro'] ? 'hidden' : ''; ?>>
						<span><?php esc_html_e( 'das', 'jpx-eventos' ); ?></span>
						<label class="jpx-sr" for="jpx-hi"><?php esc_html_e( 'Hora de início', 'jpx-eventos' ); ?></label>
						<input type="time" id="jpx-hi" name="jpx[hora_inicio]" value="<?php echo esc_attr( $d['hora_inicio'] ); ?>">
						<span><?php esc_html_e( 'às', 'jpx-eventos' ); ?></span>
						<label class="jpx-sr" for="jpx-hf"><?php esc_html_e( 'Hora de término', 'jpx-eventos' ); ?></label>
						<input type="time" id="jpx-hf" name="jpx[hora_fim]" value="<?php echo esc_attr( $d['hora_fim'] ); ?>">
					</span>
				</div>
				<div class="jpx-ed__opcoes">
					<label><input type="checkbox" name="jpx[varios_dias]" value="1" data-jpx-toggle="fim" <?php checked( $d['varios_dias'] ); ?>> <?php esc_html_e( 'Vários dias', 'jpx-eventos' ); ?></label>
					<label><input type="checkbox" name="jpx[dia_inteiro]" value="1" data-jpx-toggle="horas" <?php checked( $d['dia_inteiro'] ); ?>> <?php esc_html_e( 'Dia inteiro', 'jpx-eventos' ); ?></label>
				</div>

				<div class="jpx-ed__repete" data-jpx-repete-caixa <?php echo $d['varios_dias'] ? 'hidden' : ''; ?>>
					<span class="jpx-ed__rot-linha"><label class="jpx-ed__rot" for="jpx-repete"><?php esc_html_e( 'Repetição', 'jpx-eventos' ); ?></label><?php echo JPX_EV_Admin_Ajuda::dica( __( 'Para reuniões fixas: escolha uma opção pronta (ex.: todo mês na última quinta). Abaixo aparecem as próximas datas — dá para cancelar ou mudar o local de uma data só.', 'jpx-eventos' ), 'repeticao' ); // phpcs:ignore ?></span>
					<select id="jpx-repete" name="jpx[repete]" data-jpx-repete>
						<?php foreach ( $sug as $s ) : ?>
							<option value="<?php echo esc_attr( $s['chave'] ); ?>" data-regra="<?php echo jpx_ev_json_attr( $s['regra'] ); ?>" <?php selected( $chave, $s['chave'] ); ?>><?php echo esc_html( $s['rotulo'] ); ?></option>
						<?php endforeach; ?>
						<option value="personalizar" <?php selected( $chave, 'personalizar' ); ?>><?php esc_html_e( 'Personalizar…', 'jpx-eventos' ); ?></option>
					</select>
					<span class="jpx-ed__dica"><?php esc_html_e( 'As opções se ajustam sozinhas à data escolhida.', 'jpx-eventos' ); ?></span>

					<div class="jpx-ed__personalizar" data-jpx-personalizar <?php echo 'personalizar' === $chave ? '' : 'hidden'; ?>>
						<div class="jpx-ed__linha">
							<span><?php esc_html_e( 'A cada', 'jpx-eventos' ); ?></span>
							<input type="number" min="1" max="52" name="jpx[regra][intervalo]" value="<?php echo (int) $regra['intervalo']; ?>" aria-label="<?php esc_attr_e( 'Intervalo', 'jpx-eventos' ); ?>" class="jpx-ed__num-in">
							<select name="jpx[regra][freq]" aria-label="<?php esc_attr_e( 'Frequência', 'jpx-eventos' ); ?>" data-jpx-freq>
								<option value="diaria" <?php selected( $regra['freq'], 'diaria' ); ?>><?php esc_html_e( 'dia(s)', 'jpx-eventos' ); ?></option>
								<option value="semanal" <?php selected( in_array( $regra['freq'], array( 'semanal', 'nenhuma' ), true ) ); ?>><?php esc_html_e( 'semana(s)', 'jpx-eventos' ); ?></option>
								<option value="mensal" <?php selected( $regra['freq'], 'mensal' ); ?>><?php esc_html_e( 'mês(es)', 'jpx-eventos' ); ?></option>
							</select>
						</div>
						<div class="jpx-ed__linha" data-jpx-se="semanal">
							<span><?php esc_html_e( 'Nos dias', 'jpx-eventos' ); ?></span>
							<?php foreach ( $dc as $i => $nome ) : ?>
								<label class="jpx-ed__dia"><input type="checkbox" name="jpx[regra][dias_semana][]" value="<?php echo (int) $i; ?>" <?php checked( in_array( $i, $regra['dias_semana'], true ) || ( ! $regra['dias_semana'] && (int) jpx_ev_dt( $d['inicio'] )->format( 'w' ) === $i ) ); ?>><span><?php echo esc_html( $nome ); ?></span></label>
							<?php endforeach; ?>
						</div>
						<div class="jpx-ed__linha" data-jpx-se="mensal">
							<label><input type="radio" name="jpx[regra][modo_mensal]" value="posicao" <?php checked( $regra['modo_mensal'], 'posicao' ); ?>> <?php esc_html_e( 'Na', 'jpx-eventos' ); ?></label>
							<select name="jpx[regra][posicao]" aria-label="<?php esc_attr_e( 'Posição no mês', 'jpx-eventos' ); ?>">
								<?php foreach ( array( 1 => '1ª', 2 => '2ª', 3 => '3ª', 4 => '4ª', -1 => __( 'última', 'jpx-eventos' ) ) as $v => $r ) : ?>
									<option value="<?php echo (int) $v; ?>" <?php selected( $regra['posicao'], $v ); ?>><?php echo esc_html( $r ); ?></option>
								<?php endforeach; ?>
							</select>
							<select name="jpx[regra][dia_semana]" aria-label="<?php esc_attr_e( 'Dia da semana', 'jpx-eventos' ); ?>">
								<?php foreach ( jpx_ev_nomes( 'dias' ) as $i => $nome ) : ?>
									<option value="<?php echo (int) $i; ?>" <?php selected( $regra['dia_semana'], $i ); ?>><?php echo esc_html( $nome ); ?></option>
								<?php endforeach; ?>
							</select>
							<label class="jpx-ed__ou"><input type="radio" name="jpx[regra][modo_mensal]" value="dia" <?php checked( $regra['modo_mensal'], 'dia' ); ?>> <?php esc_html_e( 'ou no dia', 'jpx-eventos' ); ?></label>
							<input type="number" min="1" max="31" name="jpx[regra][dia_mes]" value="<?php echo (int) $regra['dia_mes']; ?>" class="jpx-ed__num-in" aria-label="<?php esc_attr_e( 'Dia do mês', 'jpx-eventos' ); ?>">
						</div>
					</div>

					<div class="jpx-ed__termina" data-jpx-termina <?php echo 'nenhuma' === $chave ? 'hidden' : ''; ?>>
						<span class="jpx-ed__rot"><?php esc_html_e( 'Termina', 'jpx-eventos' ); ?></span>
						<label><input type="radio" name="jpx[regra][fim]" value="nunca" <?php checked( $regra['fim'], 'nunca' ); ?>> <?php esc_html_e( 'nunca', 'jpx-eventos' ); ?></label>
						<label><input type="radio" name="jpx[regra][fim]" value="data" <?php checked( $regra['fim'], 'data' ); ?>> <?php esc_html_e( 'em', 'jpx-eventos' ); ?> <input type="date" name="jpx[regra][ate]" value="<?php echo esc_attr( $regra['ate'] ); ?>" aria-label="<?php esc_attr_e( 'Data final da repetição', 'jpx-eventos' ); ?>"></label>
						<label><input type="radio" name="jpx[regra][fim]" value="contagem" <?php checked( $regra['fim'], 'contagem' ); ?>> <?php esc_html_e( 'após', 'jpx-eventos' ); ?> <input type="number" min="1" max="500" name="jpx[regra][contagem]" value="<?php echo (int) $regra['contagem']; ?>" class="jpx-ed__num-in" aria-label="<?php esc_attr_e( 'Número de vezes', 'jpx-eventos' ); ?>"> <?php esc_html_e( 'vezes', 'jpx-eventos' ); ?></label>
					</div>

					<div class="jpx-ed__previa" data-jpx-previa <?php echo 'nenhuma' === $chave ? 'hidden' : ''; ?>>
						<div class="jpx-ed__resumo" data-jpx-resumo></div>
						<ul class="jpx-ed__datas" data-jpx-datas></ul>
					</div>
					<input type="hidden" name="jpx[excecoes]" value="<?php echo esc_attr( wp_json_encode( (object) $d['excecoes'] ) ); ?>" data-jpx-excecoes>
				</div>
			</section>

			<section class="jpx-ed__passo">
				<header><span class="jpx-ed__num">3</span><h2><?php esc_html_e( 'Onde', 'jpx-eventos' ); ?></h2><?php echo JPX_EV_Admin_Ajuda::dica( __( 'Clique num local salvo. Para um lugar novo, use “+ Outro local” e marque “Salvar” para reaproveitar depois.', 'jpx-eventos' ), 'passo-onde' ); // phpcs:ignore ?></header>
				<div class="jpx-ed__chips jpx-ed__chips--locais" role="radiogroup" aria-label="<?php esc_attr_e( 'Local', 'jpx-eventos' ); ?>">
					<?php foreach ( $locais as $l ) : ?>
						<label class="jpx-ed__chip jpx-ed__chip--local"><input type="radio" name="jpx[local_id]" value="<?php echo (int) $l->term_id; ?>" data-endereco="<?php echo esc_attr( implode( ' · ', array_filter( array( get_term_meta( $l->term_id, 'jpx_endereco', true ), get_term_meta( $l->term_id, 'jpx_cidade', true ) ) ) ) ); ?>" data-nome="<?php echo esc_attr( $l->name ); ?>" <?php checked( $local_sel, $l->term_id ); ?>><span><?php echo esc_html( $l->name ); ?></span></label>
					<?php endforeach; ?>
					<label class="jpx-ed__chip jpx-ed__chip--local jpx-ed__chip--novo"><input type="radio" name="jpx[local_id]" value="novo" <?php checked( $local_sel, 'novo' ); ?>><span><?php echo $locais ? esc_html__( '+ Outro local', 'jpx-eventos' ) : esc_html__( '+ Informar local', 'jpx-eventos' ); ?></span></label>
					<label class="jpx-ed__chip jpx-ed__chip--local jpx-ed__chip--fantasma"><input type="radio" name="jpx[local_id]" value="0" <?php checked( $local_sel, 0 ); ?>><span><?php esc_html_e( 'A definir', 'jpx-eventos' ); ?></span></label>
				</div>
				<p class="jpx-ed__dica" data-jpx-endereco></p>
				<div class="jpx-ed__novo-local" data-jpx-novo-local <?php echo 'novo' === $local_sel ? '' : 'hidden'; ?>>
					<div class="jpx-ed__grade3">
						<p><label for="jpx-ln"><?php esc_html_e( 'Nome do local', 'jpx-eventos' ); ?></label><input type="text" id="jpx-ln" name="jpx[local_nome]" value="<?php echo esc_attr( $d['local_id'] ? '' : $d['local_nome'] ); ?>" placeholder="<?php esc_attr_e( 'Ex.: Auditório da associação', 'jpx-eventos' ); ?>"></p>
						<p><label for="jpx-le"><?php esc_html_e( 'Endereço', 'jpx-eventos' ); ?></label><input type="text" id="jpx-le" name="jpx[local_endereco]" value="<?php echo esc_attr( $d['local_id'] ? '' : $d['endereco'] ); ?>" placeholder="<?php esc_attr_e( 'Rua, número, bairro', 'jpx-eventos' ); ?>"></p>
						<p><label for="jpx-lc"><?php esc_html_e( 'Cidade', 'jpx-eventos' ); ?></label><input type="text" id="jpx-lc" name="jpx[local_cidade]" value="<?php echo esc_attr( $d['local_id'] ? '' : $d['cidade'] ); ?>"></p>
					</div>
					<label class="jpx-ed__salvar-local"><input type="checkbox" name="jpx[salvar_local]" value="1" <?php checked( ! $d['local_nome'] ); ?>> <?php esc_html_e( 'Salvar este local para os próximos eventos', 'jpx-eventos' ); ?></label>
				</div>
			</section>

			<section class="jpx-ed__passo">
				<header><span class="jpx-ed__num jpx-ed__num--opcional">4</span><h2><?php esc_html_e( 'Inscrição', 'jpx-eventos' ); ?> <small><?php esc_html_e( '(opcional)', 'jpx-eventos' ); ?></small></h2><?php echo JPX_EV_Admin_Ajuda::dica( __( 'Cole o link do formulário (Google Forms, Sympla…) e o site mostra o botão “Inscrever-se”. Valor e público aparecem como selos no card.', 'jpx-eventos' ), 'passo-inscricao' ); // phpcs:ignore ?></header>
				<p>
					<label class="jpx-sr" for="jpx-insc"><?php esc_html_e( 'Link de inscrição', 'jpx-eventos' ); ?></label>
					<input type="url" id="jpx-insc" name="jpx[inscricao]" value="<?php echo esc_attr( $d['inscricao'] ); ?>" placeholder="<?php esc_attr_e( 'Cole o link do Google Forms, Sympla, WhatsApp…', 'jpx-eventos' ); ?>" class="jpx-ed__grande">
					<span class="jpx-ed__dica"><?php esc_html_e( 'Vazio = sem botão de inscrição. O botão some sozinho depois que o evento termina.', 'jpx-eventos' ); ?></span>
				</p>
				<div class="jpx-ed__linha">
					<div class="jpx-ed__grupo">
						<span class="jpx-ed__rot"><?php esc_html_e( 'Valor', 'jpx-eventos' ); ?></span>
						<div class="jpx-ed__chips" role="radiogroup" aria-label="<?php esc_attr_e( 'Valor', 'jpx-eventos' ); ?>">
							<?php foreach ( jpx_ev_valores() as $k => $r ) : ?>
								<label class="jpx-ed__chip jpx-ed__chip--neutro"><input type="radio" name="jpx[valor_tipo]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $d['valor_tipo'], $k ); ?>><span><?php echo esc_html( $r ); ?></span></label>
							<?php endforeach; ?>
							<label class="jpx-ed__chip jpx-ed__chip--fantasma"><input type="radio" name="jpx[valor_tipo]" value="" <?php checked( $d['valor_tipo'], '' ); ?>><span><?php esc_html_e( 'Não informar', 'jpx-eventos' ); ?></span></label>
						</div>
						<input type="text" name="jpx[valor]" value="<?php echo esc_attr( $d['valor'] ); ?>" data-jpx-valor placeholder="<?php esc_attr_e( 'R$ 120 · ou 1 kg de alimento', 'jpx-eventos' ); ?>" aria-label="<?php esc_attr_e( 'Valor ou contribuição', 'jpx-eventos' ); ?>" <?php echo in_array( $d['valor_tipo'], array( 'pago', 'solidario' ), true ) ? '' : 'hidden'; ?>>
					</div>
					<div class="jpx-ed__grupo">
						<span class="jpx-ed__rot"><?php esc_html_e( 'Público', 'jpx-eventos' ); ?></span>
						<div class="jpx-ed__chips" role="radiogroup" aria-label="<?php esc_attr_e( 'Público', 'jpx-eventos' ); ?>">
							<?php foreach ( jpx_ev_publicos() as $k => $r ) : ?>
								<label class="jpx-ed__chip jpx-ed__chip--neutro"><input type="radio" name="jpx[publico]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $d['publico'], $k ); ?>><span><?php echo esc_html( 'aberto' === $k ? __( 'Aberto', 'jpx-eventos' ) : $r ); ?></span></label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</section>

			<button type="button" class="jpx-ed__expandir" data-jpx-expandir aria-expanded="false">
				<span><?php esc_html_e( 'Descrição, resumo e programação', 'jpx-eventos' ); ?></span>
				<small><?php esc_html_e( 'opcional', 'jpx-eventos' ); ?> ▾</small>
			</button>
		</div>
		<?php
	}

	/**
	 * Resumo e programação (abaixo do editor de texto).
	 *
	 * @param WP_Post $post Post.
	 */
	public static function apos_editor( $post ) {
		if ( JPX_EV_Tipos::CPT !== $post->post_type ) {
			return;
		}
		list( $d ) = self::valores( $post );
		?>
		<div class="jpx-ed jpx-ed__extras" data-jpx-extras>
			<p>
				<label for="jpx-resumo"><strong><?php esc_html_e( 'Resumo', 'jpx-eventos' ); ?></strong> — <?php esc_html_e( 'aparece no card, no modal e no Google (até ~200 caracteres).', 'jpx-eventos' ); ?></label>
				<textarea id="jpx-resumo" name="jpx[resumo]" rows="2" maxlength="300"><?php echo esc_textarea( $d['resumo'] ); ?></textarea>
			</p>
			<p>
				<label for="jpx-prog"><strong><?php esc_html_e( 'Programação', 'jpx-eventos' ); ?></strong> — <?php esc_html_e( 'uma linha por item, começando pelo horário. Ex.: 18:30 Credenciamento', 'jpx-eventos' ); ?></label>
				<textarea id="jpx-prog" name="jpx[programacao]" rows="4"><?php echo esc_textarea( $d['programacao_txt'] ); ?></textarea>
			</p>
		</div>
		<?php
	}

	/**
	 * Caixa lateral: prévia do card + checklist.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function caixa_previa( $post ) {
		?>
		<div class="jpx-ed-previa" data-jpx-card>
			<div class="jpx-ed-previa__capa" data-jpx-card-capa>
				<span class="jpx-ed-previa__num" data-jpx-card-num></span>
				<span class="jpx-ed-previa__tag" data-jpx-card-tipo></span>
				<span class="jpx-ed-previa__data"><b data-jpx-card-mes></b><strong data-jpx-card-dia></strong></span>
			</div>
			<div class="jpx-ed-previa__corpo">
				<strong data-jpx-card-titulo></strong>
				<span data-jpx-card-quando></span>
				<span data-jpx-card-local></span>
			</div>
		</div>
		<ul class="jpx-ed-check">
			<li data-jpx-check="nome"><?php esc_html_e( 'Nome', 'jpx-eventos' ); ?></li>
			<li data-jpx-check="data"><?php esc_html_e( 'Data e horário', 'jpx-eventos' ); ?></li>
			<li data-jpx-check="local"><?php esc_html_e( 'Local', 'jpx-eventos' ); ?></li>
		</ul>
		<p class="jpx-ed-atalho"><?php esc_html_e( 'Atalho: Ctrl + Enter publica/atualiza.', 'jpx-eventos' ); ?></p>
		<?php
	}

	/**
	 * Salva o formulário.
	 *
	 * @param int     $id   Post.
	 * @param WP_Post $post Post.
	 */
	public static function salvar( $id, $post ) {
		if ( ! isset( $_POST['jpx_ev_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['jpx_ev_nonce'] ), 'jpx_ev_salvar' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		$f = isset( $_POST['jpx'] ) && is_array( $_POST['jpx'] ) ? wp_unslash( $_POST['jpx'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitizado em JPX_EV_Evento::salvar.

		$inicio = jpx_ev_sanitizar_data( $f['inicio'] ?? '' );
		$chave  = sanitize_key( $f['repete'] ?? 'nenhuma' );
		$regra  = array( 'freq' => 'nenhuma' );
		if ( 'personalizar' === $chave ) {
			$regra = (array) ( $f['regra'] ?? array() );
		} elseif ( 'nenhuma' !== $chave ) {
			foreach ( JPX_EV_Recorrencia::sugestoes( $inicio ) as $s ) {
				if ( $s['chave'] === $chave ) {
					$regra = $s['regra'];
				}
			}
		}
		if ( 'nenhuma' !== ( $regra['freq'] ?? 'nenhuma' ) ) {
			foreach ( array( 'fim', 'ate', 'contagem' ) as $k ) {
				$regra[ $k ] = $f['regra'][ $k ] ?? '';
			}
		}

		$local = $f['local_id'] ?? 0;
		JPX_EV_Evento::salvar(
			$id,
			array(
				'inicio'         => $inicio,
				'fim'            => $f['fim'] ?? '',
				'varios_dias'    => ! empty( $f['varios_dias'] ),
				'dia_inteiro'    => ! empty( $f['dia_inteiro'] ),
				'hora_inicio'    => $f['hora_inicio'] ?? '',
				'hora_fim'       => $f['hora_fim'] ?? '',
				'tipo'           => (int) ( $f['tipo'] ?? 0 ),
				'nucleo'         => (int) ( $f['nucleo'] ?? 0 ),
				'local_id'       => 'novo' === $local ? 0 : (int) $local,
				'local_nome'     => 'novo' === $local ? ( $f['local_nome'] ?? '' ) : '',
				'local_endereco' => 'novo' === $local ? ( $f['local_endereco'] ?? '' ) : '',
				'local_cidade'   => 'novo' === $local ? ( $f['local_cidade'] ?? '' ) : '',
				'salvar_local'   => 'novo' === $local && ! empty( $f['salvar_local'] ),
				'inscricao'      => $f['inscricao'] ?? '',
				'valor_tipo'     => $f['valor_tipo'] ?? '',
				'valor'          => $f['valor'] ?? '',
				'publico'        => $f['publico'] ?? '',
				'resumo'         => $f['resumo'] ?? '',
				'programacao'    => $f['programacao'] ?? '',
				'recorrencia'    => $regra,
				'excecoes'       => $f['excecoes'] ?? '{}',
			)
		);
	}

	/**
	 * Mensagens após salvar.
	 *
	 * @param array $m Mensagens.
	 * @return array
	 */
	public static function mensagens( $m ) {
		global $post;
		$ver = $post ? ' <a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html__( 'Ver evento', 'jpx-eventos' ) . '</a>' : '';
		$m[ JPX_EV_Tipos::CPT ] = array(
			1  => __( 'Evento atualizado.', 'jpx-eventos' ) . $ver,
			4  => __( 'Evento atualizado.', 'jpx-eventos' ),
			6  => __( 'Evento publicado! Já está na agenda.', 'jpx-eventos' ) . $ver,
			7  => __( 'Evento salvo.', 'jpx-eventos' ),
			8  => __( 'Evento enviado para revisão.', 'jpx-eventos' ),
			9  => __( 'Publicação do evento agendada.', 'jpx-eventos' ),
			10 => __( 'Rascunho do evento atualizado.', 'jpx-eventos' ),
		);
		return $m;
	}
}
