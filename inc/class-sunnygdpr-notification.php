<?php
/**
 * Notification and Banner Frontend Renderer for SunnyGDPR.
 *
 * @package SunnyGDPR
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SunnyGDPR_Notification {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_head', array( $this, 'render_analytics_scripts' ), 1 );
        add_action( 'wp_footer', array( $this, 'render_banner' ) );
        add_action( 'send_headers', array( $this, 'prevent_caching_without_consent' ) );
    }

    /**
     * Prevent browser/CDNs from caching the page when consent is missing.
     */
    public function prevent_caching_without_consent() {
        if ( is_admin() ) {
            return;
        }

        $consent = isset( $_COOKIE['sunnygdpr_consent'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['sunnygdpr_consent'] ) ) : '';

        if ( 'accepted' !== $consent ) {
            nocache_headers();
        }
    }

    /**
     * Enqueue CSS and JS assets.
     */
    public function enqueue_assets() {
        if ( '1' !== SunnyGDPR_Setup::get_option( 'enabled' ) ) {
            return;
        }

        $plugin_url     = plugin_dir_url( dirname( __FILE__ ) );
        $plugin_version = defined( 'SUNNYGDPR_VERSION' ) ? SUNNYGDPR_VERSION : '1.0.0';

        wp_enqueue_style(
            'sunnygdpr-style',
            $plugin_url . 'assets/css/sunnygdpr-style.css',
            array(),
            $plugin_version
        );

        wp_enqueue_script(
            'sunnygdpr-script',
            $plugin_url . 'assets/js/sunnygdpr-script.js',
            array(),
            $plugin_version,
            true
        );

        $redirect_setting = SunnyGDPR_Setup::get_option( 'decline_redirect' );
        $default_url      = $plugin_url . 'cookie-declined.html';
        $declined_url     = ! empty( $redirect_setting ) ? esc_url( $redirect_setting ) : esc_url( $default_url );

        wp_localize_script(
            'sunnygdpr-script',
            'sunnygdpr_data',
            array(
                'declined_url' => $declined_url,
                'cookie_name'  => 'sunnygdpr_consent',
            )
        );
    }

    /**
     * Output tracking scripts ONLY if consent cookie equals 'accepted'.
     */
    public function render_analytics_scripts() {
        if ( is_admin() || '1' !== SunnyGDPR_Setup::get_option( 'enabled' ) ) {
            return;
        }

        // Requirement 3: No cookie — do not output tracking scripts in <head>!
        $consent = isset( $_COOKIE['sunnygdpr_consent'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['sunnygdpr_consent'] ) ) : '';

        if ( 'accepted' !== $consent ) {
            return;
        }

        $custom_scripts = SunnyGDPR_Setup::get_option( 'custom_scripts' );

        if ( empty( trim( $custom_scripts ) ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Custom scripts are sanitized during admin option save and need raw execution.
        echo wp_unslash( $custom_scripts ) . "\n";
    }

    /**
     * Render the cookie banner in the site footer ONLY if no consent cookie is present.
     */
    public function render_banner() {
        if ( '1' !== SunnyGDPR_Setup::get_option( 'enabled' ) ) {
            return;
        }

        $consent = isset( $_COOKIE['sunnygdpr_consent'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['sunnygdpr_consent'] ) ) : '';

        if ( 'accepted' === $consent ) {
            return;
        }

        $banner_text      = SunnyGDPR_Setup::get_option( 'banner_text' );
        $accept_btn_text  = SunnyGDPR_Setup::get_option( 'accept_btn_text' );
        $decline_btn_text = SunnyGDPR_Setup::get_option( 'decline_btn_text' );
        ?>
        <div id="sunnygdpr-banner" class="sunnygdpr-banner-wrapper">
            <div class="sunnygdpr-banner-content">
                <div class="sunnygdpr-banner-text">
                    <?php echo wp_kses_post( $banner_text ); ?>
                </div>
                <div class="sunnygdpr-banner-actions">
                    <button type="button" id="sunnygdpr-accept-btn" class="sunnygdpr-btn sunnygdpr-btn-accept">
                        <?php echo esc_html( $accept_btn_text ); ?>
                    </button>
                    <button type="button" id="sunnygdpr-decline-btn" class="sunnygdpr-btn sunnygdpr-btn-decline">
                        <?php echo esc_html( $decline_btn_text ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}

new SunnyGDPR_Notification();