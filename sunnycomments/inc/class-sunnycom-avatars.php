<?php
/**
 * Custom Avatar Management Class
 * Class Sunnycom_Avatars
 * 
 * All code comments are strictly in English.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sunnycom_Avatars {

    /**
     * Total number of pre-generated local avatar images.
     *
     * @var int
     */
    private $total_avatars = 20;

    /**
     * Constructor to register hooks.
     */
    public function __construct() {
        // Force default WP comment cookies checkbox to be checked
        add_filter( 'comment_form_default_fields', array( $this, 'force_cookie_consent_checked' ) );

        // Override avatars in comment list
        add_filter( 'get_avatar', array( $this, 'override_comment_avatar' ), 10, 5 );

        // Render avatar selector in comment form
        add_action( 'comment_form_logged_in_after', array( $this, 'render_avatar_selector' ) );
        add_action( 'comment_form_after_fields', array( $this, 'render_avatar_selector' ) );

        // Process user registration and save avatar meta
        add_action( 'comment_post', array( $this, 'process_comment_avatar_and_user' ), 10, 3 );

        // Enqueue frontend scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );

        add_filter( 'comment_form_fields', array( $this, 'remove_cookie_consent_for_logged_in_users' ) );
    }

    /**
     * Remove standard WordPress cookie consent checkbox for logged-in users.
     *
     * @param array $fields
     * @return array
     */
    public function remove_cookie_consent_for_logged_in_users( $fields ) {
        if ( is_user_logged_in() && isset( $fields['cookies'] ) ) {
            unset( $fields['cookies'] );
        }
        return $fields;
    }

    /**
     * Force standard WordPress comment cookies consent checkbox to be pre-checked.
     *
     * @param array $fields
     * @return array
     */
    public function force_cookie_consent_checked( $fields ) {
        if ( isset( $fields['cookies'] ) ) {
            $fields['cookies'] = str_replace(
                '<input id="wp-comment-cookies-consent"',
                '<input checked="checked" id="wp-comment-cookies-consent"',
                $fields['cookies']
            );
        }
        return $fields;
    }

    private function get_plugin_url() {
        if ( defined( 'SUNNYCOM_PLUGIN_URL' ) ) {
            return SUNNYCOM_PLUGIN_URL;
        }
        return plugin_dir_url( dirname( __FILE__ ) );
    }

    private function get_plugin_dir() {
        if ( defined( 'SUNNYCOM_PLUGIN_DIR' ) ) {
            return SUNNYCOM_PLUGIN_DIR;
        }
        return plugin_dir_path( dirname( __FILE__ ) );
    }

    public function enqueue_frontend_scripts() {
        if ( is_singular() && comments_open() ) {
            wp_enqueue_script(
                'sunnycom-avatar-picker',
                $this->get_plugin_url() . 'assets/js/sunnycom-avatar-picker.js',
                array(),
                '1.0.1',
                true
            );
        }
    }

/**
     * Render graphic avatar selector inside comment form.
     */
    public function render_avatar_selector() {
        $cookie_name     = 'sunnycom_user_avatar';
        $saved_in_cookie = isset( $_COOKIE[ $cookie_name ] ) ? sanitize_file_name( $_COOKIE[ $cookie_name ] ) : '';
        
        $current_user_id  = get_current_user_id();
        $has_saved_avatar = false;
        $default_avatar   = ''; // Изначально пусто, если аватар еще не выбирался

        if ( $current_user_id ) {
            $user_avatar = get_user_meta( $current_user_id, 'sunnycom_user_avatar', true );
            if ( ! empty( $user_avatar ) ) {
                $default_avatar   = $user_avatar;
                $has_saved_avatar = true;
            }
        } elseif ( ! empty( $saved_in_cookie ) ) {
            $avatar_path = $this->get_plugin_dir() . 'assets/img/avatars/' . $saved_in_cookie;
            if ( file_exists( $avatar_path ) ) {
                $default_avatar   = $saved_in_cookie;
                $has_saved_avatar = true;
            }
        }

        $current_avatar_url = $has_saved_avatar ? $this->get_plugin_url() . 'assets/img/avatars/' . $default_avatar : '';

        // Если это админ с активным Super Commenter — скрываем по умолчанию до клика по галочке
        $is_admin_sc_active = current_user_can( 'manage_options' ) && (int) get_option( 'sunnycom_enable_super_commenter', 0 ) === 1;
        $wrapper_class      = $is_admin_sc_active ? 'sunnycom-avatar-picker-wrapper sunnycom-sc-admin-hidden' : 'sunnycom-avatar-picker-wrapper';
        
        $preview_class = ( $has_saved_avatar && ! $is_admin_sc_active ) ? 'sunnycom-selected-preview' : 'sunnycom-selected-preview sunnycom-hidden';
        $grid_class    = ( $has_saved_avatar && ! $is_admin_sc_active ) ? 'sunnycom-picker-container sunnycom-hidden' : 'sunnycom-picker-container';
        ?>
        <div class="<?php echo esc_attr( $wrapper_class ); ?>" id="sunnycom_avatar_picker_main_wrapper">
            
            <div class="<?php echo esc_attr( $preview_class ); ?>" id="sunnycom_avatar_toggle_trigger">
                <span class="sunnycom-preview-label">
                    <?php esc_html_e( 'Your comment avatar:', 'sunnycomments' ); ?>
                </span>
                <div class="sunnycom-preview-avatar-wrapper">
                    <img id="sunnycom_current_avatar_img" src="<?php echo esc_url( $current_avatar_url ); ?>" alt="<?php esc_attr_e( 'Avatar', 'sunnycomments' ); ?>" />
                </div>
            </div>

            <div id="sunnycom_avatar_picker_grid_wrapper" class="<?php echo esc_attr( $grid_class ); ?>">
                <label class="sunnycom-picker-label">
                    <?php esc_html_e( 'Choose your comment avatar:', 'sunnycomments' ); ?> <span style="color: #e53e3e;">*</span>
                </label>

                <!-- Сообщение об ошибке, если забыли выбрать -->
                <div id="sunnycom_avatar_error_msg" class="sunnycom-hidden" style="color: #e53e3e; font-size: 13px; font-weight: 600; margin-bottom: 8px;">
                    <?php esc_html_e( 'Please select an avatar to publish your comment!', 'sunnycomments' ); ?>
                </div>

                <div class="sunnycom-avatar-picker-grid">
                    <?php for ( $i = 1; $i <= $this->total_avatars; $i++ ) : 
                        $filename  = sprintf( 'avatar-%d.png', $i );
                        $url       = $this->get_plugin_url() . 'assets/img/avatars/' . $filename;
                        $is_active = ( $filename === $default_avatar ) ? 'active' : '';
                    ?>
                        <div class="sunnycom-picker-item <?php echo $is_active; ?>" data-avatar="<?php echo esc_attr( $filename ); ?>">
                            <img src="<?php echo esc_url( $url ); ?>" alt="Avatar <?php echo $i; ?>" loading="lazy" />
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <input type="hidden" name="sunnycom_selected_avatar" id="sunnycom_selected_avatar" value="<?php echo esc_attr( $default_avatar ); ?>" />
        </div>
        
        <style>
            .sunnycom-sc-admin-hidden {
                display: none !important;
            }
        </style>
        <?php
    }

    /**
     * Process avatar saving and handle guest user registration using standard WP consent checkbox.
     *
     * @param int $comment_id
     * @param int|string $comment_approved
     * @param array $commentdata
     */
    public function process_comment_avatar_and_user( $comment_id, $comment_approved, $commentdata ) {
        $selected_avatar = isset( $_POST['sunnycom_selected_avatar'] ) ? sanitize_file_name( $_POST['sunnycom_selected_avatar'] ) : 'avatar-1.png';

        add_comment_meta( $comment_id, '_sunnycom_avatar', $selected_avatar, true );

        $user_id = get_current_user_id();
        $consent_given = isset( $_POST['wp-comment-cookies-consent'] );

        if ( ! $user_id && $consent_given ) {
            $email = sanitize_email( $commentdata['comment_author_email'] );
            $name  = sanitize_text_field( $commentdata['comment_author'] );

            if ( is_email( $email ) ) {
                $existing_user = get_user_by( 'email', $email );

                if ( ! $existing_user ) {
                    $username = sanitize_user( current( explode( '@', $email ) ), true );
                    if ( username_exists( $username ) ) {
                        $username .= '_' . wp_rand( 100, 999 );
                    }

                    $random_password = wp_generate_password( 12, false );
                    $new_user_id     = wp_create_user( $username, $random_password, $email );

                    if ( ! is_wp_error( $new_user_id ) ) {
                        $user_id = $new_user_id;

                        wp_update_user( array(
                            'ID'           => $user_id,
                            'display_name' => $name,
                        ) );

                        wp_new_user_notification( $user_id, null, 'user' );

                        wp_update_comment( array(
                            'comment_ID' => $comment_id,
                            'user_id'    => $user_id,
                        ) );

                        wp_set_current_user( $user_id );
                        wp_set_auth_cookie( $user_id, true );
                    }
                } else {
                    $user_id = $existing_user->ID;
                }
            }
        }

        if ( $user_id ) {
            update_user_meta( $user_id, 'sunnycom_user_avatar', $selected_avatar );
        } else {
            setcookie( 'sunnycom_user_avatar', $selected_avatar, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
        }
    }

/**
     * Override comment and user avatar output everywhere.
     */
    public function override_comment_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
        $user_id = 0;
        $comment = null;

        // 1. Extract User ID or Comment Object based on what WordPress passes
        if ( is_numeric( $id_or_email ) ) {
            $user_id = (int) $id_or_email;
        } elseif ( is_object( $id_or_email ) ) {
            if ( isset( $id_or_email->comment_ID ) ) {
                $comment = $id_or_email;
                $user_id = (int) $comment->user_id;
            } elseif ( isset( $id_or_email->ID ) ) {
                $user_id = (int) $id_or_email->ID;
            } elseif ( isset( $id_or_email->user_id ) ) {
                $user_id = (int) $id_or_email->user_id;
            }
        } elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
            $user = get_user_by( 'email', $id_or_email );
            if ( $user ) {
                $user_id = (int) $user->ID;
            }
        }

        // 2. Try to get saved avatar from user meta
        $saved_avatar = '';
        if ( $user_id ) {
            $saved_avatar = get_user_meta( $user_id, 'sunnycom_user_avatar', true );
        }

        // 3. Fallback to comment meta if it is a guest comment
        if ( empty( $saved_avatar ) && $comment && is_a( $comment, 'WP_Comment' ) ) {
            $saved_avatar = get_comment_meta( $comment->comment_ID, '_sunnycom_avatar', true );
        }

        // 4. If still empty, check if it is a comment to apply default, otherwise return core avatar
        if ( empty( $saved_avatar ) ) {
            if ( $comment ) {
                $saved_avatar = 'avatar-1.png';
            } else {
                return $avatar; 
            }
        }

        // 5. Check if user is expert (admin/author)
        if ( $this->is_expert( $user_id, $comment ) ) {
            $custom_author_avatar = get_option( 'sunnycom_author_avatar_url', '' );
            if ( ! empty( $custom_author_avatar ) ) {
                return sprintf(
                    '<img alt="%1$s" src="%2$s" class="avatar avatar-%3$d photo sunnycom-custom-avatar sunnycom-author-avatar" height="%3$d" width="%3$d" loading="lazy" />',
                    esc_attr( $alt ),
                    esc_url( $custom_author_avatar ),
                    (int) $size
                );
            }
        }

        // 6. Return our custom plugin avatar
        $avatar_url = $this->get_plugin_url() . 'assets/img/avatars/' . $saved_avatar;

        return sprintf(
            '<img alt="%1$s" src="%2$s" class="avatar avatar-%3$d photo sunnycom-custom-avatar" height="%3$d" width="%3$d" loading="lazy" />',
            esc_attr( $alt ),
            esc_url( $avatar_url ),
            (int) $size
        );
    }

    /**
     * Check if the given user or comment author is an expert.
     */
    private function is_expert( $user_id, $comment = null ) {
        if ( empty( $user_id ) ) {
            return false;
        }

        // Check global admin rights
        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }

        // Check post author rights if a comment context exists
        if ( $comment && isset( $comment->comment_post_ID ) ) {
            $post = get_post( $comment->comment_post_ID );
            if ( $post && (int) $post->post_author === (int) $user_id ) {
                return true;
            }
        }

        return false;
    }
}

new Sunnycom_Avatars();