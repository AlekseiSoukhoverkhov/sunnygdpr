<?php
/**
 * Mailer Module for SunnyComments
 * Class SunnyCom_Mailer
 * 
 * All code comments are strictly in English.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SunnyCom_Mailer
 * Handles email notifications using reliable domain-based headers to pass SPF/DMARC.
 */
class SunnyCom_Mailer {

	/**
	 * Instance of this class.
	 *
	 * @var SunnyCom_Mailer|null
	 */
	private static $instance = null;

	/**
	 * Main Instance.
	 *
	 * @return SunnyCom_Mailer
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Register hooks for comment creation and moderation approval.
	 */
	private function init_hooks() {
		add_action( 'comment_post', array( $this, 'handle_new_comment' ), 10, 3 );
		add_action( 'wp_set_comment_status', array( $this, 'handle_status_change' ), 10, 2 );
		add_action( 'comment_unapproved_to_approved', array( $this, 'handle_comment_approved_object' ), 10, 1 );
	}

	/**
	 * Process notification when a comment is immediately approved on submission.
	 *
	 * @param int        $comment_id       Comment ID.
	 * @param int|string $comment_approved Approval status (1, '1', 'approved').
	 * @param array      $commentdata      Comment payload.
	 */
	public function handle_new_comment( $comment_id, $comment_approved, $commentdata ) {
		if ( 1 === (int) $comment_approved || 'approved' === $comment_approved ) {
			$this->process_notifications( $comment_id );
		}
	}

	/**
	 * Process notification when a comment status changes.
	 *
	 * @param int    $comment_id     Comment ID.
	 * @param string $comment_status New comment status.
	 */
	public function handle_status_change( $comment_id, $comment_status ) {
		if ( 'approve' === $comment_status || '1' === $comment_status ) {
			$this->process_notifications( $comment_id );
		}
	}

	/**
	 * Process notification when unapproved comment transitions to approved.
	 *
	 * @param WP_Comment $comment Comment object.
	 */
	public function handle_comment_approved_object( $comment ) {
		if ( $comment && isset( $comment->comment_ID ) ) {
			$this->process_notifications( $comment->comment_ID );
		}
	}

	/**
	 * Determine which notification to trigger based on option toggles.
	 *
	 * @param int $comment_id Comment ID.
	 */
	private function process_notifications( $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return;
		}

		// Prevent duplicate email sending
		if ( get_comment_meta( $comment->comment_ID, '_sunnycom_mail_sent', true ) ) {
			return;
		}

		$is_reply = ! empty( $comment->comment_parent );

		if ( $is_reply ) {
			if ( (int) get_option( 'sunnycom_notify_comment_reply', 1 ) === 1 ) {
				$this->send_reply_notification( $comment );
			}
		} else {
			if ( (int) get_option( 'sunnycom_notify_post_author', 1 ) === 1 ) {
				$this->send_post_author_notification( $comment );
			}
		}
	}

	/**
	 * Switch WPML language context based on post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null Previous language code or null.
	 */
	private function switch_language_for_post( $post_id ) {
		$previous_lang = null;
		
		// Check if WPML API is available
		if ( function_exists( 'wpml_get_language_information' ) && has_filter( 'wpml_current_language' ) ) {
			$lang_info = wpml_get_language_information( null, $post_id );
			
			if ( ! is_wp_error( $lang_info ) && ! empty( $lang_info['language_code'] ) ) {
				$post_lang     = $lang_info['language_code'];
				$previous_lang = apply_filters( 'wpml_current_language', null );

				if ( $post_lang !== $previous_lang ) {
					do_action( 'wpml_switch_language', $post_lang );
				}
			}
		}

		return $previous_lang;
	}

	/**
	 * Restore WPML language context.
	 *
	 * @param string|null $previous_lang Previous language code.
	 */
	private function restore_language( $previous_lang ) {
		if ( $previous_lang ) {
			do_action( 'wpml_switch_language', $previous_lang );
		}
	}

	/**
	 * Generate bulletproof headers matching host domain to bypass SPF/DMARC restrictions.
	 *
	 * @param string $reply_name  Author name for Reply-To header.
	 * @param string $reply_email Author email for Reply-To header.
	 * @return array List of constructed headers.
	 */
	private function build_mail_headers( $reply_name = '', $reply_email = '' ) {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$domain    = wp_parse_url( home_url(), PHP_URL_HOST );
		
		// Fallback for IP/local domains
		if ( empty( $domain ) ) {
			$domain = 'localhost';
		}

		$from_email = 'wordpress@' . $domain;

		$headers = array(
			'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ),
			'From: ' . $site_name . ' <' . $from_email . '>',
		);

		if ( ! empty( $reply_email ) && is_email( $reply_email ) ) {
			$headers[] = 'Reply-To: ' . sanitize_text_field( $reply_name ) . ' <' . sanitize_email( $reply_email ) . '>';
		}

		return $headers;
	}

	/**
	 * Send email notification to the post author when a new comment is published.
	 *
	 * @param WP_Comment $comment Comment object.
	 */
	private function send_post_author_notification( $comment ) {
		$post = get_post( $comment->comment_post_ID );
		if ( ! $post ) {
			return;
		}

		$author = get_userdata( $post->post_author );
		if ( ! $author || empty( $author->user_email ) ) {
			return;
		}

		$recipient_email = trim( $author->user_email );

		// Do not notify if the post author is commenting on their own post
		if ( strtolower( $recipient_email ) === strtolower( trim( $comment->comment_author_email ) ) ) {
			return;
		}

		// Skip system dummy emails from Super Commenter
		if ( stristr( $comment->comment_author_email, '@sunnycomments.local' ) && strtolower( $recipient_email ) === strtolower( trim( $comment->comment_author_email ) ) ) {
			return;
		}

		// Switch language context to post language for WPML
		$prev_lang = $this->switch_language_for_post( $post->ID );

		$site_name    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$comment_link = get_comment_link( $comment );

		$subject = sprintf(
			/* translators: 1: Site name, 2: Post title */
			__( '[%1$s] New comment on "%2$s"', 'sunnycomments' ),
			$site_name,
			$post->post_title
		);

		$message  = '<div style="font-family: Arial, sans-serif; font-size: 15px; color: #333333; line-height: 1.6; max-width: 600px;">';
		$message .= '<p>' . sprintf( __( 'Hello, %s!', 'sunnycomments' ), esc_html( $author->display_name ) ) . '</p>';
		$message .= '<p>' . sprintf( __( '<strong>%s</strong> left a new comment on your post <strong>"%s"</strong>:', 'sunnycomments' ), esc_html( $comment->comment_author ), esc_html( $post->post_title ) ) . '</p>';

		$message .= '<blockquote style="background: #f1f5f9; border-left: 4px solid #0284c7; margin: 15px 0; padding: 12px 16px; font-style: italic;">';
		$message .= nl2br( esc_html( $comment->comment_content ) );
		$message .= '</blockquote>';

		$message .= '<p style="margin-top: 20px;">';
		$message .= '<a href="' . esc_url( $comment_link ) . '" style="background: #0284c7; color: #ffffff; padding: 10px 18px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">' . __( 'View Comment', 'sunnycomments' ) . '</a>';
		$message .= '</p>';

		$message .= '<hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0 15px 0;" />';
		$message .= '<p style="font-size: 12px; color: #94a3b8;">' . sprintf( __( 'You received this notification as the author of "%s".', 'sunnycomments' ), esc_html( $post->post_title ) ) . '</p>';
		$message .= '</div>';

		$headers = $this->build_mail_headers( $comment->comment_author, $comment->comment_author_email );

		$sent = wp_mail( $recipient_email, $subject, $message, $headers );
		if ( $sent ) {
			update_comment_meta( $comment->comment_ID, '_sunnycom_mail_sent', 1 );
		}

		// Restore original language context
		$this->restore_language( $prev_lang );
	}

	/**
	 * Send email notification to parent comment author when replied to.
	 *
	 * @param WP_Comment $comment Comment reply object.
	 */
	private function send_reply_notification( $comment ) {
		$parent_comment = get_comment( $comment->comment_parent );
		if ( ! $parent_comment ) {
			return;
		}

		$recipient_email = trim( $parent_comment->comment_author_email );

		// Validate recipient email
		if ( empty( $recipient_email ) || ! is_email( $recipient_email ) ) {
			return;
		}

		// Skip dummy recipient emails generated in Super Commenter mode
		if ( stristr( $recipient_email, '@sunnycomments.local' ) ) {
			return;
		}

		// Do not send if user is replying to their own comment
		if ( strtolower( $recipient_email ) === strtolower( trim( $comment->comment_author_email ) ) ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );

		// Switch language context to post language for WPML
		$prev_lang = $this->switch_language_for_post( $post->ID );

		$site_name    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$comment_link = get_comment_link( $comment );

		$subject = sprintf(
			/* translators: 1: Site name, 2: Post title */
			__( '[%1$s] New reply to your comment on "%2$s"', 'sunnycomments' ),
			$site_name,
			$post->post_title
		);

		$message  = '<div style="font-family: Arial, sans-serif; font-size: 15px; color: #333333; line-height: 1.6; max-width: 600px;">';
		$message .= '<p>' . sprintf( __( 'Hello, %s!', 'sunnycomments' ), esc_html( $parent_comment->comment_author ) ) . '</p>';
		$message .= '<p>' . sprintf( __( '<strong>%s</strong> replied to your comment on <strong>"%s"</strong>:', 'sunnycomments' ), esc_html( $comment->comment_author ), esc_html( $post->post_title ) ) . '</p>';

		$message .= '<blockquote style="background: #f1f5f9; border-left: 4px solid #0284c7; margin: 15px 0; padding: 12px 16px; font-style: italic;">';
		$message .= nl2br( esc_html( $comment->comment_content ) );
		$message .= '</blockquote>';

		$message .= '<p style="margin-top: 20px;">';
		$message .= '<a href="' . esc_url( $comment_link ) . '" style="background: #0284c7; color: #ffffff; padding: 10px 18px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">' . __( 'View and Reply', 'sunnycomments' ) . '</a>';
		$message .= '</p>';

		$message .= '<hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0 15px 0;" />';
		$message .= '<p style="font-size: 12px; color: #94a3b8;">' . sprintf( __( 'You received this notification because you commented on %s.', 'sunnycomments' ), esc_html( $site_name ) ) . '</p>';
		$message .= '</div>';

		$headers = $this->build_mail_headers( $comment->comment_author, $comment->comment_author_email );

		$sent = wp_mail( $recipient_email, $subject, $message, $headers );
		if ( $sent ) {
			update_comment_meta( $comment->comment_ID, '_sunnycom_mail_sent', 1 );
		}

		// Restore original language context
		$this->restore_language( $prev_lang );
	}
}

// Initialize mailer module
SunnyCom_Mailer::get_instance();