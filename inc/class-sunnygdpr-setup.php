<?php
/**
 * Setup and Settings Class for SunnyGDPR.
 *
 * @package SunnyGDPR
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SunnyGDPR_Setup {

    /**
     * Option group name.
     */
    private $option_group = 'sunnygdpr_options_group';

    /**
     * Option name in DB.
     */
    private $option_name = 'sunnygdpr_settings';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
    }

    /**
     * Enqueues admin stylesheet on the plugin settings page.
     *
     * @param string $hook_suffix Current admin page hook.
     */
    public function enqueue_styles( $hook_suffix ) {
        if ( false === strpos( $hook_suffix, 'sunnygdpr' ) ) {
            return;
        }

        wp_enqueue_style(
            'sunnygdpr-admin-css',
            plugins_url( 'assets/css/sunnygdpr-admin.css', dirname( __FILE__ ) ),
            array(),
            '1.1.0'
        );
    }

    /**
     * Get default plugin settings.
     *
     * @return array
     */
    public static function get_defaults() {
        return array(
            'enabled'          => '1',
            'banner_text'      => __( 'We use essential cookies for user authorization, secure bookings, and full website functionality, as well as basic analytics to help us measure site performance and improve our services. Read our <a href="/privacy-policy/" target="_blank" class="sunnygdpr-link">Privacy Policy</a> and <a href="/cookie-policy/" target="_blank" class="sunnygdpr-link">Cookie Policy</a> for details.', 'sunnygdpr' ),
            'accept_btn_text'  => __( 'Accept & Continue', 'sunnygdpr' ),
            'decline_btn_text' => __( 'Decline', 'sunnygdpr' ),
            'decline_redirect' => plugins_url( 'cookie-declined.html', dirname( __FILE__ ) ),
            'custom_scripts'   => '',
        );
    }

    /**
     * Get single or all settings.
     *
     * @param string $key Setting key.
     * @return mixed
     */
    public static function get_option( $key = '' ) {
        $defaults = self::get_defaults();
        $options  = get_option( 'sunnygdpr_settings', $defaults );
        $options  = wp_parse_args( $options, $defaults );

        if ( ! empty( $key ) ) {
            return isset( $options[ $key ] ) ? $options[ $key ] : null;
        }

        return $options;
    }

    /**
     * Add settings page under Settings menu.
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'SunnyGDPR Settings', 'sunnygdpr' ),
            __( 'SunnyGDPR', 'sunnygdpr' ),
            'manage_options',
            'sunnygdpr-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings, sections, and fields.
     */
    public function register_settings() {
        register_setting(
            $this->option_group,
            $this->option_name,
            array( $this, 'sanitize_settings' )
        );

        add_settings_section(
            'sunnygdpr_main_section',
            __( 'General GDPR Banner Settings', 'sunnygdpr' ),
            array( $this, 'render_section_info' ),
            'sunnygdpr-settings'
        );

        add_settings_field(
            'enabled',
            __( 'Enable Plugin', 'sunnygdpr' ),
            array( $this, 'render_enabled_field' ),
            'sunnygdpr-settings',
            'sunnygdpr_main_section'
        );

        add_settings_field(
            'banner_text',
            __( 'Banner Text', 'sunnygdpr' ),
            array( $this, 'render_banner_text_field' ),
            'sunnygdpr-settings',
            'sunnygdpr_main_section'
        );

        add_settings_field(
            'accept_btn_text',
            __( 'Accept Button Text', 'sunnygdpr' ),
            array( $this, 'render_accept_btn_field' ),
            'sunnygdpr-settings',
            'sunnygdpr_main_section'
        );

        add_settings_field(
            'decline_btn_text',
            __( 'Decline Button Text', 'sunnygdpr' ),
            array( $this, 'render_decline_btn_field' ),
            'sunnygdpr-settings',
            'sunnygdpr_main_section'
        );

        add_settings_field(
            'decline_redirect',
            __( 'Decline Redirect URL', 'sunnygdpr' ),
            array( $this, 'render_decline_redirect_field' ),
            'sunnygdpr-settings',
            'sunnygdpr_main_section'
        );

        add_settings_field(
            'custom_scripts',
            __( 'Consent-Gated Tracking Scripts', 'sunnygdpr' ),
            array( $this, 'render_custom_scripts_field' ),
            'sunnygdpr-settings',
            'sunnygdpr_main_section'
        );
    }

    /**
     * Sanitize saved settings.
     *
     * @param array $input Raw input.
     * @return array
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        $sanitized['enabled']          = isset( $input['enabled'] ) && '1' === $input['enabled'] ? '1' : '0';
        $sanitized['banner_text']      = isset( $input['banner_text'] ) ? wp_kses_post( $input['banner_text'] ) : '';
        $sanitized['accept_btn_text']  = isset( $input['accept_btn_text'] ) ? sanitize_text_field( $input['accept_btn_text'] ) : '';
        $sanitized['decline_btn_text'] = isset( $input['decline_btn_text'] ) ? sanitize_text_field( $input['decline_btn_text'] ) : '';
        $defaults                      = self::get_defaults();
        $redirect_val                  = isset( $input['decline_redirect'] ) ? sanitize_text_field( trim( $input['decline_redirect'] ) ) : '';
        $sanitized['decline_redirect'] = ! empty( $redirect_val ) ? $redirect_val : $defaults['decline_redirect'];
        
        // Raw script input handling for admin users to allow inline scripts and JS snippet HTML
        if ( current_user_can( 'unfiltered_html' ) ) {
            $sanitized['custom_scripts'] = isset( $input['custom_scripts'] ) ? trim( $input['custom_scripts'] ) : '';
        } else {
            $sanitized['custom_scripts'] = isset( $input['custom_scripts'] ) ? wp_kses_post( $input['custom_scripts'] ) : '';
        }

        return $sanitized;
    }

    /**
     * Section info description.
     */
    public function render_section_info() {
        echo '<p>' . esc_html__( 'Configure the GDPR cookie notification banner, redirect settings, and analytics scripts.', 'sunnygdpr' ) . '</p>';
    }

    /**
     * Render enabled checkbox.
     */
    public function render_enabled_field() {
        $val = self::get_option( 'enabled' );
        ?>
        <label for="sunnygdpr_enabled">
            <input type="checkbox" id="sunnygdpr_enabled" name="<?php echo esc_attr( $this->option_name . '[enabled]' ); ?>" value="1" <?php checked( '1', $val ); ?> />
            <?php esc_html_e( 'Activate cookie consent banner on frontend', 'sunnygdpr' ); ?>
        </label>
        <?php
    }

    /**
     * Render banner text textarea.
     */
    public function render_banner_text_field() {
        $val = self::get_option( 'banner_text' );
        ?>
        <textarea name="<?php echo esc_attr( $this->option_name . '[banner_text]' ); ?>" rows="5" cols="80" class="large-text"><?php echo esc_textarea( $val ); ?></textarea>
        <p class="description">
            <?php
            /* translators: %1$s: Privacy Policy URL placeholder, %2$s: Cookie Policy URL placeholder. */
            esc_html_e( 'Supports HTML links. Use placeholders %1$s for Privacy Policy URL and %2$s for Cookie Policy URL if using dynamic printf formatting.', 'sunnygdpr' );
            ?>
        </p>
        <?php
    }

    /**
     * Render accept button text input.
     */
    public function render_accept_btn_field() {
        $val = self::get_option( 'accept_btn_text' );
        ?>
        <input type="text" name="<?php echo esc_attr( $this->option_name . '[accept_btn_text]' ); ?>" value="<?php echo esc_attr( $val ); ?>" class="regular-text" />
        <?php
    }

    /**
     * Render decline button text input.
     */
    public function render_decline_btn_field() {
        $val = self::get_option( 'decline_btn_text' );
        ?>
        <input type="text" name="<?php echo esc_attr( $this->option_name . '[decline_btn_text]' ); ?>" value="<?php echo esc_attr( $val ); ?>" class="regular-text" />
        <?php
    }

    /**
     * Render decline redirect URL field.
     */
    public function render_decline_redirect_field() {
        $defaults    = self::get_defaults();
        $default_url = $defaults['decline_redirect'];
        $raw_options = get_option( $this->option_name );
        
        $input_val = ( isset( $raw_options['decline_redirect'] ) && $raw_options['decline_redirect'] !== $default_url ) ? $raw_options['decline_redirect'] : '';
        ?>
        <input type="text" name="<?php echo esc_attr( $this->option_name . '[decline_redirect]' ); ?>" value="<?php echo esc_attr( $input_val ); ?>" placeholder="<?php echo esc_url( $default_url ); ?>" class="large-text code" />
        <p class="description">
            <?php esc_html_e( 'Custom redirect URL for users who decline cookies. Leave empty to use the default cookie-declined.html file inside the plugin folder.', 'sunnygdpr' ); ?>
        </p>
        <?php
    }

    /**
     * Render custom tracking scripts textarea field.
     */
    public function render_custom_scripts_field() {
        $val = self::get_option( 'custom_scripts' );
        ?>
        <textarea name="<?php echo esc_attr( $this->option_name . '[custom_scripts]' ); ?>" rows="10" cols="80" class="large-text code"><?php echo esc_textarea( $val ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Paste full script tags here (e.g., Google Analytics, Yandex Metrika). These scripts will remain blocked until the user clicks Accept.', 'sunnygdpr' ); ?>
        </p>
        <?php
    }

    /**
     * Render settings page HTML.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <div class="sunnygdpr-admin-wrap">
                <!-- Main Settings Column -->
                <div class="sunnygdpr-main-column">
                    <form action="options.php" method="post">
                        <?php
                        settings_fields( $this->option_group );
                        do_settings_sections( 'sunnygdpr-settings' );
                        submit_button( __( 'Save Settings', 'sunnygdpr' ) );
                        ?>
                    </form>
                </div>

                <!-- Studio Showcase Sidebar -->
                <div class="sunnygdpr-sidebar">
                    <h3><?php esc_html_e( '100% Free Plugin', 'sunnygdpr' ); ?></h3>
                    <p>
                        <?php esc_html_e( 'This plugin is completely free and open-source. There are no premium versions, hidden paywalls, or locked features.', 'sunnygdpr' ); ?>
                    </p>

                    <hr class="sunnygdpr-sidebar-divider" />

                    <h3><?php esc_html_e( 'Custom Development', 'sunnygdpr' ); ?></h3>
                    <p>
                        <?php esc_html_e( 'Need custom WordPress plugin development, bespoke themes, or complex web engineering?', 'sunnygdpr' ); ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e( 'SunnyWebStudio.com', 'sunnygdpr' ); ?></strong> <?php esc_html_e( 'builds high-performance digital solutions tailored to your business goals.', 'sunnygdpr' ); ?>
                    </p>
                    <p style="margin-bottom: 0;">
                        <a href="https://sunnywebstudio.com/" target="_blank" class="button sunnygdpr-btn-block sunnygdpr-btn-brand">
                            <?php esc_html_e( 'Visit SunnyWebStudio.com', 'sunnygdpr' ); ?> &rarr;
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
}

new SunnyGDPR_Setup();