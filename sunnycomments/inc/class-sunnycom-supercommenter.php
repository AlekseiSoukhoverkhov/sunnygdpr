<?php
/**
 * Super Commenter Module
 *
 * @package SunnyComments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SunnyCom_SuperCommenter {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_hooks();
	}

	private function init_hooks() {
		add_action( 'init', array( $this, 'init_module' ) );
	}

	public function init_module() {
		if ( ! $this->is_super_commenter_active() ) {
			return;
		}

		add_action( 'comment_form_top', array( $this, 'render_super_commenter_bar' ) );
		add_filter( 'preprocess_comment', array( $this, 'process_super_comment' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_filter( 'get_avatar', array( $this, 'render_virtual_avatar' ), 10, 5 );
	}

	public function is_super_commenter_active() {
		if ( ! is_user_logged_in() || ! current_user_can( 'administrator' ) ) {
			return false;
		}

		$is_enabled = (int) get_option( 'sunnycom_enable_super_commenter', 0 );
		return 1 === $is_enabled;
	}

public function enqueue_frontend_assets() {
		// Важно: эта проверка должна быть строго ВНУТРИ этой функции, 
		// чтобы не вызывать ошибку "is_single was called incorrectly"
		if ( ! is_singular() || ! comments_open() ) {
			return;
		}

		wp_enqueue_media();

		// Получаем URL корня плагина (выходим из папки inc/ нативными средствами WP)
		$plugin_root_url = plugin_dir_url( dirname( __FILE__ ) );

		wp_enqueue_style(
			'sunnycom-super-commenter-style',
			$plugin_root_url . 'assets/css/sunnycom.css',
			array(),
			'1.0.1'
		);

		wp_enqueue_script(
			'sunnycom-super-commenter-script',
			$plugin_root_url . 'assets/js/supercommentator.js',
			array( 'jquery', 'wp-util' ), 
			'1.0.1',
			true
		);
	}

	public function render_super_commenter_bar() {
		global $post;

		$nonce            = wp_create_nonce( 'super_commenter_action' );
		$post_date_iso    = $post ? get_post_time( 'Y-m-d\TH:i', false, $post ) : '';
		$current_date_iso = current_time( 'Y-m-d\TH:i' );
		?>
		<div id="sunnycom-super-bar" class="sunnycom-super-bar">
			<div class="sunnycom-super-bar-header">
				<span class="sunnycom-super-bar-title">
					&#9889; Super Commenter
				</span>
				
				<div class="sunnycom-super-field">
					<label for="sunnycom-custom-date">Date/Time:</label>
					<input 
						type="datetime-local" 
						id="sunnycom-custom-date" 
						name="super_commenter_date" 
						data-post-date="<?php echo esc_attr( $post_date_iso ); ?>"
						min="<?php echo esc_attr( $post_date_iso ); ?>" 
						max="<?php echo esc_attr( $current_date_iso ); ?>" 
					/>
				</div>

				<label class="sunnycom-super-toggle-label">
					<input type="checkbox" id="sunnycom-toggle-virtual-mode" name="is_super_commenter_virtual" value="1" />
					Switch to Virtual User
				</label>
			</div>

			<input type="hidden" name="super_commenter_nonce" value="<?php echo esc_attr( $nonce ); ?>" />

			<!-- Virtual User Section: Visible only when checkbox is active -->
			<div class="sunnycom-super-controls">
				<div class="sunnycom-super-field">
					<label for="sunnycom-virtual-name">Virtual Name:</label>
					<input type="text" id="sunnycom-virtual-name" name="super_commenter_name" placeholder="e.g. Alex" />
				</div>

				<div class="sunnycom-super-field">
					<label>Avatar:</label>
					<div id="sunnycom-sc-avatar-preview" class="sunnycom-avatar-preview-box">
						<span class="sunnycom-avatar-placeholder">&#128100;</span>
					</div>
					<input type="hidden" id="sunnycom-virtual-avatar" name="super_commenter_avatar" value="" />
					<button type="button" id="sunnycom-sc-select-avatar-btn" class="button button-small sunnycom-btn-select">
						Select / Upload Image
					</button>
					<button type="button" id="sunnycom-sc-remove-avatar-btn" class="sunnycom-btn-remove" style="display:none;">
						&times; Remove
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	public function process_super_comment( $commentdata ) {
		if ( ! $this->is_super_commenter_active() ) {
			return $commentdata;
		}

		if ( isset( $_POST['super_commenter_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['super_commenter_nonce'] ) ), 'super_commenter_action' ) ) {
				return $commentdata;
			}
		} else {
			return $commentdata;
		}

		if ( isset( $_POST['is_super_commenter_virtual'] ) && '1' === $_POST['is_super_commenter_virtual'] ) {
			$virtual_name   = ! empty( $_POST['super_commenter_name'] ) ? sanitize_text_field( wp_unslash( $_POST['super_commenter_name'] ) ) : 'Anonymous';
			$virtual_avatar = ! empty( $_POST['super_commenter_avatar'] ) ? esc_url_raw( wp_unslash( $_POST['super_commenter_avatar'] ) ) : '';

			$commentdata['user_id']            = 0;
			$commentdata['comment_author']     = $virtual_name;
			$commentdata['comment_author_url'] = '';

			$hash = md5( $virtual_name . microtime() );
			$commentdata['comment_author_email'] = 'sc_' . substr( $hash, 0, 8 ) . '@sunnycomments.local';

			add_filter( 'pre_comment_approved', '__return_true' );

			add_action( 'comment_post', function( $comment_id ) use ( $virtual_avatar ) {
				if ( ! empty( $virtual_avatar ) ) {
					update_comment_meta( $comment_id, '_super_commenter_avatar', $virtual_avatar );
				}
				update_comment_meta( $comment_id, '_is_super_commenter', 1 );
			} );
		}

		if ( ! empty( $_POST['super_commenter_date'] ) ) {
			$input_date = sanitize_text_field( wp_unslash( $_POST['super_commenter_date'] ) );
			$timestamp  = strtotime( $input_date );

			if ( $timestamp ) {
				$post_id        = isset( $commentdata['comment_post_ID'] ) ? (int) $commentdata['comment_post_ID'] : 0;
				$post_timestamp = $post_id ? get_post_time( 'U', false, $post_id ) : 0;
				$parent_id      = isset( $commentdata['comment_parent'] ) ? (int) $commentdata['comment_parent'] : 0;
				
				$min_timestamp = $post_timestamp;

				if ( $parent_id > 0 ) {
					$parent_comment = get_comment( $parent_id );
					if ( $parent_comment ) {
						$min_timestamp = strtotime( $parent_comment->comment_date );
					}
				}

				$current_time = current_time( 'timestamp' );

				if ( $timestamp >= $min_timestamp && $timestamp <= $current_time ) {
					$formatted_date                  = date( 'Y-m-d H:i:s', $timestamp );
					$commentdata['comment_date']     = $formatted_date;
					$commentdata['comment_date_gmt'] = get_gmt_from_date( $formatted_date );
				}
			}
		}

		return $commentdata;
	}

	public function render_virtual_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
		$comment = null;

		if ( is_numeric( $id_or_email ) ) {
			$comment = get_comment( $id_or_email );
		} elseif ( is_object( $id_or_email ) && ! empty( $id_or_email->comment_ID ) ) {
			$comment = $id_or_email;
		}

		if ( $comment ) {
			$custom_avatar = get_comment_meta( $comment->comment_ID, '_super_commenter_avatar', true );
			if ( ! empty( $custom_avatar ) ) {
				return sprintf(
					'<img alt="%s" src="%s" class="avatar avatar-%d photo" height="%d" width="%d" />',
					esc_attr( $alt ),
					esc_url( $custom_avatar ),
					(int) $size,
					(int) $size,
					(int) $size
				);
			}
		}

		return $avatar;
	}
}

SunnyCom_SuperCommenter::get_instance();