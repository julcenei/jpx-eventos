<?php
/**
 * Ativação, atualização de versão e migração dos dados do plugin antigo (Eventos JPX 1.x).
 *
 * @package JPX_Eventos
 */

defined( 'ABSPATH' ) || exit;

class JPX_EV_Migracao {

	/**
	 * Hooks.
	 */
	public static function iniciar() {
		add_action( 'admin_init', array( __CLASS__, 'verificar_versao' ) );
		add_action( 'jpx_ev_diario', array( __CLASS__, 'verificar_versao' ), 1 );
		add_action( 'admin_notices', array( __CLASS__, 'avisos' ) );
		add_action( 'admin_post_jpx_ev_dispensar', array( __CLASS__, 'dispensar_aviso' ) );
	}

	/**
	 * Ativação do plugin.
	 */
	public static function ativar() {
		delete_transient( 'jpx_ev_instalando' );
		self::instalar();
		flush_rewrite_rules( false );
	}

	/**
	 * Desativação.
	 */
	public static function desativar() {
		wp_clear_scheduled_hook( 'jpx_ev_diario' );
		flush_rewrite_rules( false );
	}

	/**
	 * Instala/atualiza estrutura e roda a migração uma vez.
	 */
	public static function instalar() {
		if ( get_transient( 'jpx_ev_instalando' ) ) {
			return;
		}
		set_transient( 'jpx_ev_instalando', 1, 5 * MINUTE_IN_SECONDS );
		JPX_EV_Tipos::registrar();
		self::desativar_legado( true );
		JPX_EV_Ocorrencias::criar_tabela();
		JPX_EV_Tipos::criar_padroes();
		if ( ! wp_next_scheduled( 'jpx_ev_diario' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 03:15' ), 'daily', 'jpx_ev_diario' );
		}
		self::migrar();
		update_option( 'jpx_eventos_db_version', JPX_EV_DB_VERSION, false );
		update_option( 'jpx_eventos_versao', JPX_EV_VERSION, false );
		update_option( 'jpx_eventos_flush', 1, false );
		delete_transient( 'jpx_ev_instalando' );
	}

	/**
	 * Roda a instalação quando a versão muda (atualização por .zip não dispara a ativação).
	 */
	public static function verificar_versao() {
		if ( (int) get_option( 'jpx_eventos_db_version' ) < JPX_EV_DB_VERSION || get_option( 'jpx_eventos_versao' ) !== JPX_EV_VERSION ) {
			self::instalar();
		}
	}

	/**
	 * Desativa o plugin antigo, se estiver ativo (os dois juntos duplicariam campos e shortcodes).
	 *
	 * @param bool $silencioso Sem aviso.
	 */
	public static function desativar_legado( $silencioso = false ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active( JPX_EV_LEGADO ) ) {
			deactivate_plugins( JPX_EV_LEGADO, true );
			update_option( 'jpx_eventos_aviso_legado', 1, false );
		}
	}

	/**
	 * Migração dos eventos antigos: backup, conversão das horas e geração das ocorrências.
	 */
	public static function migrar() {
		if ( get_option( 'jpx_eventos_migracao' ) ) {
			JPX_EV_Ocorrencias::gerar_todos();
			return;
		}
		$ids = get_posts(
			array(
				'post_type'      => JPX_EV_Tipos::CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$chaves  = array( 'data_evento_inicio', 'data_evento_fim', 'evento_varios_dias', 'hora_evento', 'local_evento', 'cidade_evento', 'descricao_curta' );
		$backup  = array();
		$pend    = array();
		$conv    = 0;
		foreach ( $ids as $id ) {
			foreach ( $chaves as $k ) {
				$backup[ $id ][ $k ] = get_post_meta( $id, $k, true );
			}
			if ( '' !== get_post_meta( $id, '_jpx_hora_inicio', true ) || get_post_meta( $id, '_jpx_migrado', true ) ) {
				continue;
			}
			$txt = (string) get_post_meta( $id, 'hora_evento', true );
			$h   = jpx_ev_interpretar_hora( $txt );
			if ( $h['ok'] ) {
				update_post_meta( $id, '_jpx_hora_inicio', wp_slash( $h['inicio'] ) );
				update_post_meta( $id, '_jpx_hora_fim', wp_slash( $h['fim'] ) );
				if ( '' !== trim( $txt ) ) {
					$conv++;
				}
			} else {
				update_post_meta( $id, '_jpx_hora_inicio', wp_slash( '' ) );
				$pend[ $id ] = $txt;
			}
			if ( '' === get_post_meta( $id, '_jpx_recorrencia', true ) ) {
				update_post_meta( $id, '_jpx_recorrencia', wp_slash( array( 'freq' => 'nenhuma' ) ) );
			}
			update_post_meta( $id, '_jpx_migrado', wp_slash( 2 ) );
		}

		if ( $ids ) {
			add_option( 'jpx_eventos_backup_legado', $backup, '', 'no' );
			// Instalação vinda do plugin antigo: herda a identidade verde original.
			if ( false === get_option( 'jpx_eventos_opcoes', false ) ) {
				add_option(
					'jpx_eventos_opcoes',
					array_merge(
						jpx_ev_opcoes_padrao(),
						array(
							'cor_primaria' => '#00AB4E',
							'cor_escura'   => '#062C1B',
						)
					)
				);
			}
		}

		update_option(
			'jpx_eventos_migracao',
			array(
				'data'       => current_time( 'mysql' ),
				'total'      => count( $ids ),
				'convertidas' => $conv,
				'pendentes'  => $pend,
				'aviso'      => $ids ? 1 : 0,
			),
			false
		);
		JPX_EV_Ocorrencias::gerar_todos();
	}

	/**
	 * Avisos no painel.
	 */
	public static function avisos() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$url_disp = function ( $qual ) {
			return wp_nonce_url( admin_url( 'admin-post.php?action=jpx_ev_dispensar&qual=' . $qual ), 'jpx_ev_dispensar' );
		};
		if ( get_option( 'jpx_eventos_aviso_legado' ) ) {
			echo '<div class="notice notice-info"><p><strong>JPX Eventos:</strong> ' . esc_html__( 'o plugin antigo "Eventos JPX" foi desativado automaticamente para não duplicar campos. Seus eventos foram mantidos. O ACF não é mais necessário para os eventos.', 'jpx-eventos' ) . ' <a href="' . esc_url( $url_disp( 'legado' ) ) . '">' . esc_html__( 'Ok, entendi', 'jpx-eventos' ) . '</a></p></div>';
		}
		$m = get_option( 'jpx_eventos_migracao' );
		if ( is_array( $m ) && ! empty( $m['aviso'] ) ) {
			echo '<div class="notice notice-success"><p><strong>JPX Eventos:</strong> ';
			printf(
				/* translators: 1: total, 2: convertidas */
				esc_html__( '%1$d eventos migrados. %2$d horários escritos à mão foram convertidos automaticamente.', 'jpx-eventos' ),
				(int) $m['total'],
				(int) $m['convertidas']
			);
			echo '</p>';
			if ( ! empty( $m['pendentes'] ) ) {
				echo '<p>' . esc_html__( 'Estes horários não puderam ser entendidos e continuam aparecendo como texto. Abra cada evento e escolha o horário:', 'jpx-eventos' ) . '</p><ul style="list-style:disc;margin-left:20px">';
				foreach ( $m['pendentes'] as $id => $txt ) {
					$p = get_post( $id );
					if ( ! $p || get_post_meta( $id, '_jpx_hora_inicio', true ) || get_post_meta( $id, '_jpx_dia_inteiro', true ) ) {
						continue;
					}
					echo '<li><a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a> — “' . esc_html( '' === $txt ? __( '(vazio)', 'jpx-eventos' ) : $txt ) . '”</li>';
				}
				echo '</ul>';
			}
			echo '<p><a href="' . esc_url( $url_disp( 'migracao' ) ) . '">' . esc_html__( 'Dispensar aviso', 'jpx-eventos' ) . '</a></p></div>';
		}
	}

	/**
	 * Dispensa avisos.
	 */
	public static function dispensar_aviso() {
		check_admin_referer( 'jpx_ev_dispensar' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die();
		}
		$qual = isset( $_GET['qual'] ) ? sanitize_key( $_GET['qual'] ) : '';
		if ( 'legado' === $qual ) {
			delete_option( 'jpx_eventos_aviso_legado' );
		} elseif ( 'migracao' === $qual ) {
			$m = get_option( 'jpx_eventos_migracao' );
			if ( is_array( $m ) ) {
				$m['aviso'] = 0;
				update_option( 'jpx_eventos_migracao', $m, false );
			}
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
