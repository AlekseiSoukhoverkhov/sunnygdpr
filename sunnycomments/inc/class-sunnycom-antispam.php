<?php
/**
 * Anti-Spam & Behavioral Factor Protection Class
 * Class Sunnycom_Antispam
 * 
 * All code comments are strictly in English.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sunnycom_Antispam {

    /**
     * Name of the hidden honeypot input field.
     *
     * @var string
     */
    private $honeypot_field_name = 'sunnycom_hp_website_verify';

    /**
     * Minimum time in seconds required to fill out the comment form.
     *
     * @var int
     */
    private $min_submit_time = 3;

    /**
     * Constructor to register anti-spam hooks.
     */
    public function __construct() {
        // Remove standard website URL field to prevent SEO spam
        add_filter( 'comment_form_fields', array( $this, 'remove_comment_url_field' ) );

        // Render anti-spam fields (honeypot, timestamp, math captcha) inside comment form
        add_action( 'comment_form_logged_in_after', array( $this, 'render_antispam_fields' ) );
        add_action( 'comment_form_after_fields', array( $this, 'render_antispam_fields' ) );

        // Validate incoming comment submissions
        add_filter( 'preprocess_comment', array( $this, 'validate_comment_submission' ) );
    }

    /**
     * Remove the website URL field from the comment form.
     *
     * @param array $fields
     * @return array
     */
    public function remove_comment_url_field( $fields ) {
        if ( isset( $fields['url'] ) ) {
            unset( $fields['url'] );
        }
        return $fields;
    }

    /**
     * Output hidden honeypot, timestamp, and human math captcha fields.
     */
    public function render_antispam_fields() {
        // Skip captcha for administrators
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }

        // Render time check field (current Unix timestamp)
        $current_time = time();
        echo '<input type="hidden" name="sunnycom_form_timestamp" value="' . esc_attr( $current_time ) . '" />';

        // Render invisible honeypot field for bot traps
        echo '<div style="display:none !important; visibility:hidden !important; opacity:0 !important; height:0 !important; width:0 !important; overflow:hidden !important;">';
        echo '<label for="' . esc_attr( $this->honeypot_field_name ) . '">Leave this field empty</label>';
        echo '<input type="text" name="' . esc_attr( $this->honeypot_field_name ) . '" id="' . esc_attr( $this->honeypot_field_name ) . '" value="" autocomplete="off" tabindex="-1" />';
        echo '</div>';

        // Math Captcha generation
        $num1 = wp_rand( 1, 9 );
        $num2 = wp_rand( 1, 9 );
        $sum  = $num1 + $num2;

        // Simple HMAC hash verification for mathematical security
        $hash = wp_hash( $sum . 'sunnycom_captcha_salt' );

        ?>
        <p class="comment-form-sunnycom-captcha" style="margin-top: 10px; margin-bottom: 10px;">
            <label for="sunnycom_math_captcha" style="font-weight: 600; display: block; margin-bottom: 4px;">
                <?php 
                printf( 
                    esc_html__( 'Security question: %1$d + %2$d = ?', 'sunnycomments' ), 
                    (int) $num1, 
                    (int) $num2 
                ); 
                ?>
                <span class="required" style="color: red;">*</span>
            </label>
            <input type="number" id="sunnycom_math_captcha" name="sunnycom_math_captcha" style="width: 80px; padding: 6px; border: 1px solid #ccc; border-radius: 4px;" required autocomplete="off" />
            <input type="hidden" name="sunnycom_math_captcha_hash" value="<?php echo esc_attr( $hash ); ?>" />
        </p>
        <?php
    }

    /**
     * Validate submission before processing comment.
     *
     * @param array $commentdata
     * @return array
     */
    public function validate_comment_submission( $commentdata ) {
        // Skip anti-spam checks for administrators and managers
        if ( current_user_can( 'manage_options' ) ) {
            return $commentdata;
        }

        $antispam_enabled = (int) get_option( 'sunnycom_enable_antispam', 1 );
        if ( ! $antispam_enabled ) {
            return $commentdata;
        }

        // 1. Honeypot check: If filled, redirect suspect to thank-you gateway
        if ( ! empty( $_POST[ $this->honeypot_field_name ] ) ) {
            $this->redirect_to_thanks_gateway();
        }

        // 2. Direct POST check: Verify timestamp field exists
        if ( ! isset( $_POST['sunnycom_form_timestamp'] ) ) {
            $this->redirect_to_thanks_gateway();
        }

        // 3. Time threshold check: Submission filled faster than humanly possible
        $submitted_time = (int) $_POST['sunnycom_form_timestamp'];
        if ( ( time() - $submitted_time ) < $this->min_submit_time ) {
            $this->redirect_to_thanks_gateway();
        }

        // 4. Math Captcha verification
        $user_answer = isset( $_POST['sunnycom_math_captcha'] ) ? (int) $_POST['sunnycom_math_captcha'] : null;
        $answer_hash = isset( $_POST['sunnycom_math_captcha_hash'] ) ? sanitize_text_field( $_POST['sunnycom_math_captcha_hash'] ) : '';

        if ( is_null( $user_answer ) || empty( $answer_hash ) || ! hash_equals( wp_hash( $user_answer . 'sunnycom_captcha_salt' ), $answer_hash ) ) {
            $this->redirect_to_thanks_gateway();
        }

        // 5. Zero-tolerance Link & URL Check in comment content
        $comment_content = isset( $commentdata['comment_content'] ) ? $commentdata['comment_content'] : '';
        if ( $this->contains_links( $comment_content ) ) {
            $this->redirect_to_thanks_gateway();
        }

        return $commentdata;
    }

    /**
     * Helper method to quietly purge spammer and redirect suspect to thank-you gateway.
     */
    private function redirect_to_thanks_gateway() {
        $user_id = get_current_user_id();

        // Quietly delete registered spammer account from database
        if ( $user_id && ! user_can( $user_id, 'manage_options' ) ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user( $user_id );
        }

        // Fetch a random published blog post
        $random_posts = get_posts( array(
            'posts_per_page' => 1,
            'orderby'        => 'rand',
            'post_status'    => 'publish',
            'post_type'      => 'post',
        ) );

        $target_url = ! empty( $random_posts ) ? get_permalink( $random_posts[0]->ID ) : home_url( '/' );

        // Construct target URL for isolated thank-you page
        $thanks_page_url = plugin_dir_url( dirname( __FILE__ ) ) . 'templates/thankscomment.php';
        $redirect_url    = add_query_arg( array(
            'to'    => urlencode( $target_url ),
            'delay' => 8, // Delay in seconds before sending to random article
        ), $thanks_page_url );

        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Check if text contains URLs, HTML links, or domain patterns.
     *
     * @param string $text
     * @return bool
     */
    private function contains_links( $text ) {
        if ( empty( $text ) ) {
            return false;
        }

        // Regex pattern to catch http/https, www, HTML <a> tags, bbCode [url], and popular TLDs
        $pattern = '/(https?:\/\/|ftps?:\/\/|www\.|<a\s+href=\|\[url=|[a-z0-9_\.-]+\.(com|ru|net|org|biz|info|io|co|xyz|site|online|top|club|link|tech|shop|me)\b)/i';

        return (bool) preg_match( $pattern, $text );
    }
}

// Instantiate Anti-Spam module
new Sunnycom_Antispam();