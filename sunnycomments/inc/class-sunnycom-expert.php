<?php
/**
 * Highlight Post Author or Administrator as Expert Class
 * Class Sunnycom_Expert
 * 
 * All code comments are strictly in English.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sunnycom_Expert {

    /**
     * Constructor to register hooks.
     */
    public function __construct() {
        // Add custom CSS class to author comments
        add_filter( 'comment_class', array( $this, 'add_author_comment_class' ), 10, 5 );

        // Prepend graphic verification icon to comment text
        add_filter( 'comment_text', array( $this, 'prepend_expert_badge' ), 10, 2 );

        // Print dynamic CSS variables based on plugin options
        add_action( 'wp_head', array( $this, 'output_dynamic_css' ) );
    }

    /**
     * Helper check to determine if comment user is post author or site administrator.
     *
     * @param object $comment
     * @return bool
     */
    private function is_expert( $comment ) {
        if ( ! $comment || empty( $comment->user_id ) ) {
            return false;
        }

        $user_id = (int) $comment->user_id;

        // Check 1: Is user the post author?
        $post = get_post( $comment->comment_post_ID );
        if ( $post && (int) $post->post_author === $user_id ) {
            return true;
        }

        // Check 2: Is user an Administrator?
        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }

        return false;
    }

    /**
     * Append .sunnycom-author-comment class to comment container.
     */
    public function add_author_comment_class( $classes, $class, $comment_id, $comment, $post_id ) {
        if ( $comment && $this->is_expert( $comment ) ) {
            $classes[] = 'sunnycom-author-comment';
        }
        return $classes;
    }

    /**
     * Prepend messenger-style graphic checkmark badge to the comment text.
     */
    public function prepend_expert_badge( $comment_text, $comment ) {
        if ( is_admin() || ! $comment ) {
            return $comment_text;
        }

        if ( $this->is_expert( $comment ) ) {
            $title_attr = esc_attr__( 'Verified Author', 'sunnycomments' );

            // Standalone graphic badge without text
            $badge_html  = '<span class="sunnycom-verified-badge" title="' . $title_attr . '">';
            $badge_html .= '<svg class="sunnycom-check-icon" viewBox="0 0 24 24" width="12" height="12"><path fill="#ffffff" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';
            $badge_html .= '</span> ';

            return $badge_html . $comment_text;
        }

        return $comment_text;
    }

    /**
     * Inject custom CSS variables into site header.
     */
    public function output_dynamic_css() {
        $bg_color     = get_option( 'sunnycom_expert_bg', '#fffdf5' );
        $border_color = get_option( 'sunnycom_expert_border', '#1e3a8a' );
        $badge_color  = get_option( 'sunnycom_expert_badge_color', '#f39c12' );
        $text_color   = get_option( 'sunnycom_expert_text_color', '#1e293b' );
        ?>
        <style id="sunnycom-dynamic-styles">
            :root {
                --sunnycom-expert-bg: <?php echo esc_html( $bg_color ); ?>;
                --sunnycom-expert-border: <?php echo esc_html( $border_color ); ?>;
                --sunnycom-expert-badge-bg: <?php echo esc_html( $badge_color ); ?>;
                --sunnycom-expert-text: <?php echo esc_html( $text_color ); ?>;
            }
        </style>
        <?php
    }
}

// Instantiate the module
new Sunnycom_Expert();