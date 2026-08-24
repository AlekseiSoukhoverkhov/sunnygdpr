<?php
/**
 * Core Setup & Admin Settings Class
 * Class Sunnycom_Setup
 * 
 * All code comments are strictly in English.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sunnycom_Setup {

    /**
     * Constructor to register admin hooks, filters, and settings.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Force comments closure or opening globally based on the master switch
        add_filter( 'comments_open', array( $this, 'filter_comments_open' ), 999, 2 );
    }

    /**
     * Enqueue WordPress core assets (color picker, media library) on plugin settings page.
     *
     * @param string $hook_suffix
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        if ( 'toplevel_page_sunnycomments' !== $hook_suffix ) {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_media();
    }

    /**
     * Add SunnyComments menu item to the WordPress dashboard sidebar.
     */
    public function register_admin_menu() {
        add_menu_page(
            __( 'SunnyComments Dashboard', 'sunnycomments' ),
            'SunnyComments',
            'manage_options',
            'sunnycomments',
            array( $this, 'render_admin_page' ),
            'dashicons-admin-comments',
            25
        );
    }

    /**
     * Register plugin options using WordPress Settings API.
     */
    public function register_settings() {
        // Global master switch (1 = open everywhere, 0 = closed everywhere)
        register_setting( 'sunnycom_settings_group', 'sunnycom_enable_comments_globally', array(
            'type'              => 'integer',
            'default'           => 1,
            'sanitize_callback' => 'absint',
        ) );

        register_setting( 'sunnycom_settings_group', 'sunnycom_expert_bg', array(
            'type'              => 'string',
            'default'           => '#fffdf5',
            'sanitize_callback' => 'sanitize_hex_color',
        ) );

        register_setting( 'sunnycom_settings_group', 'sunnycom_expert_border', array(
            'type'              => 'string',
            'default'           => '#1e3a8a',
            'sanitize_callback' => 'sanitize_hex_color',
        ) );

        register_setting( 'sunnycom_settings_group', 'sunnycom_expert_badge_color', array(
            'type'              => 'string',
            'default'           => '#f39c12',
            'sanitize_callback' => 'sanitize_hex_color',
        ) );

        register_setting( 'sunnycom_settings_group', 'sunnycom_expert_text_color', array(
            'type'              => 'string',
            'default'           => '#1e293b',
            'sanitize_callback' => 'sanitize_hex_color',
        ) );

        register_setting( 'sunnycom_settings_group', 'sunnycom_author_avatar_url', array(
            'type'              => 'string',
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ) );

        register_setting( 'sunnycom_settings_group', 'sunnycom_enable_threading', array(
            'type'              => 'integer',
            'default'           => 1,
            'sanitize_callback' => 'absint',
        ) );

        register_setting( 'sunnycom_settings_group', 'sunnycom_enable_antispam', array(
            'type'              => 'integer',
            'default'           => 1,
            'sanitize_callback' => 'absint',
        ) );

        // Notification settings
        register_setting( 'sunnycom_settings_group', 'sunnycom_notify_post_author', array(
            'type'              => 'integer',
            'default'           => 1,
            'sanitize_callback' => 'absint',
        ) );

        register_setting( 'sunnycom_settings_group', 'sunnycom_notify_comment_reply', array(
            'type'              => 'integer',
            'default'           => 1,
            'sanitize_callback' => 'absint',
        ) );

        // Register Super Commenter option only if module class is available
        if ( class_exists( 'SunnyCom_SuperCommenter' ) ) {
            register_setting( 'sunnycom_settings_group', 'sunnycom_enable_super_commenter', array(
                'type'              => 'integer',
                'default'           => 0,
                'sanitize_callback' => 'absint',
            ) );
        }
    }

    /**
     * Globally filter comments_open status to act as a master switch.
     *
     * @param bool $open    Whether comments are open for the post.
     * @param int  $post_id Post ID.
     * @return bool Updated comment status.
     */
    public function filter_comments_open( $open, $post_id ) {
        $post = get_post( $post_id );
        
        // Apply the master switch only to standard posts.
        // We do not want to accidentally force open comments on static pages or attachments.
        if ( ! $post || 'post' !== $post->post_type ) {
            return $open;
        }

        $global_enabled = (int) get_option( 'sunnycom_enable_comments_globally', 1 );
        
        // Force status based on the master switch
        if ( 0 === $global_enabled ) {
            return false;
        }

        return true;
    }

    /**
     * Render the main admin settings page.
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Apply settings on save
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
            $global_enabled = (int) get_option( 'sunnycom_enable_comments_globally', 1 );
            update_option( 'default_comment_status', $global_enabled === 1 ? 'open' : 'closed' );

            if ( (int) get_option( 'sunnycom_enable_threading', 1 ) === 1 ) {
                update_option( 'thread_comments', 1 );
                update_option( 'thread_comments_depth', 2 );
            }
        }

        $global_comments    = (int) get_option( 'sunnycom_enable_comments_globally', 1 );
        $bg_color           = get_option( 'sunnycom_expert_bg', '#fffdf5' );
        $border_color       = get_option( 'sunnycom_expert_border', '#1e3a8a' );
        $badge_color        = get_option( 'sunnycom_expert_badge_color', '#f39c12' );
        $text_color         = get_option( 'sunnycom_expert_text_color', '#1e293b' );
        $author_avatar      = get_option( 'sunnycom_author_avatar_url', '' );
        $threading          = get_option( 'sunnycom_enable_threading', 1 );
        $antispam           = get_option( 'sunnycom_enable_antispam', 1 );
        $notify_post_author = get_option( 'sunnycom_notify_post_author', 1 );
        $notify_reply       = get_option( 'sunnycom_notify_comment_reply', 1 );
        $super_commenter    = get_option( 'sunnycom_enable_super_commenter', 0 );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <hr />

            <form method="post" action="options.php">
                <?php
                settings_fields( 'sunnycom_settings_group' );
                do_settings_sections( 'sunnycom_settings_group' );
                ?>

                <h2><?php esc_html_e( 'General Settings', 'sunnycomments' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Master Comment Switch', 'sunnycomments' ); ?></th>
                        <td>
                            <label for="sunnycom_enable_comments_globally">
                                <input type="checkbox" id="sunnycom_enable_comments_globally" name="sunnycom_enable_comments_globally" value="1" <?php checked( 1, $global_comments ); ?> />
                                <strong><?php esc_html_e( 'Force enable comments on all blog posts', 'sunnycomments' ); ?></strong>
                            </label>
                            <p class="description"><?php esc_html_e( 'Uncheck to instantly hide and close comments across all articles on the site. Check to force open them everywhere.', 'sunnycomments' ); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Author / Admin Profile & Avatars', 'sunnycomments' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sunnycom_author_avatar_url"><?php esc_html_e( 'Custom Admin Avatar', 'sunnycomments' ); ?></label>
                        </th>
                        <td>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div id="sunnycom-avatar-preview" style="width: 64px; height: 64px; border-radius: 50%; background: #e2e8f0; overflow: hidden; display: flex; align-items: center; justify-content: center; border: 2px solid #cbd5e1;">
                                    <?php if ( ! empty( $author_avatar ) ) : ?>
                                        <img src="<?php echo esc_url( $author_avatar ); ?>" style="width:100%; height:100%; object-fit:cover;" />
                                    <?php else : ?>
                                        <span class="dashicons dashicons-admin-users" style="font-size:32px; color:#94a3b8; width:32px; height:32px;"></span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <input type="hidden" id="sunnycom_author_avatar_url" name="sunnycom_author_avatar_url" value="<?php echo esc_url( $author_avatar ); ?>" />
                                    <button type="button" class="button button-secondary" id="sunnycom-upload-avatar-btn"><?php esc_html_e( 'Upload Custom Avatar', 'sunnycomments' ); ?></button>
                                    <button type="button" class="button button-link-delete" id="sunnycom-remove-avatar-btn" style="<?php echo empty( $author_avatar ) ? 'display:none;' : ''; ?>"><?php esc_html_e( 'Remove Avatar', 'sunnycomments' ); ?></button>
                                    <p class="description"><?php esc_html_e( 'Upload your custom portrait avatar for site author/admin replies.', 'sunnycomments' ); ?></p>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Author Highlight Styling', 'sunnycomments' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sunnycom_expert_bg"><?php esc_html_e( 'Bubble Background Color', 'sunnycomments' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="sunnycom_expert_bg" name="sunnycom_expert_bg" value="<?php echo esc_attr( $bg_color ); ?>" class="sunnycom-color-field" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sunnycom_expert_border"><?php esc_html_e( 'Accent Border Color', 'sunnycomments' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="sunnycom_expert_border" name="sunnycom_expert_border" value="<?php echo esc_attr( $border_color ); ?>" class="sunnycom-color-field" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sunnycom_expert_badge_color"><?php esc_html_e( 'Verification Badge Color', 'sunnycomments' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="sunnycom_expert_badge_color" name="sunnycom_expert_badge_color" value="<?php echo esc_attr( $badge_color ); ?>" class="sunnycom-color-field" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sunnycom_expert_text_color"><?php esc_html_e( 'Text Color', 'sunnycomments' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="sunnycom_expert_text_color" name="sunnycom_expert_text_color" value="<?php echo esc_attr( $text_color ); ?>" class="sunnycom-color-field" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Comment Threading', 'sunnycomments' ); ?></th>
                        <td>
                            <label for="sunnycom_enable_threading">
                                <input type="checkbox" id="sunnycom_enable_threading" name="sunnycom_enable_threading" value="1" <?php checked( 1, $threading ); ?> />
                                <?php esc_html_e( 'Enable threaded comments and enforce 1-level depth (Question → Answer)', 'sunnycomments' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Anti-Spam & PF Protection', 'sunnycomments' ); ?></th>
                        <td>
                            <label for="sunnycom_enable_antispam">
                                <input type="checkbox" id="sunnycom_enable_antispam" name="sunnycom_enable_antispam" value="1" <?php checked( 1, $antispam ); ?> />
                                <?php esc_html_e( 'Enable Honeypot, submission timestamp checks, and block direct POST spam', 'sunnycomments' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Email Notifications', 'sunnycomments' ); ?></th>
                        <td>
                            <fieldset>
                                <label for="sunnycom_notify_post_author">
                                    <input type="checkbox" id="sunnycom_notify_post_author" name="sunnycom_notify_post_author" value="1" <?php checked( 1, $notify_post_author ); ?> />
                                    <?php esc_html_e( 'Notify post author about new comments', 'sunnycomments' ); ?>
                                </label>
                                <br />
                                <label for="sunnycom_notify_comment_reply" style="margin-top: 6px; display: inline-block;">
                                    <input type="checkbox" id="sunnycom_notify_comment_reply" name="sunnycom_notify_comment_reply" value="1" <?php checked( 1, $notify_reply ); ?> />
                                    <?php esc_html_e( 'Notify user when someone replies to their comment', 'sunnycomments' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <?php if ( class_exists( 'SunnyCom_SuperCommenter' ) ) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Super Commenter', 'sunnycomments' ); ?></th>
                        <td>
                            <label for="sunnycom_enable_super_commenter">
                                <input type="checkbox" id="sunnycom_enable_super_commenter" name="sunnycom_enable_super_commenter" value="1" <?php checked( 1, $super_commenter ); ?> />
                                <?php esc_html_e( 'Enable Super Commenter mode for Administrators', 'sunnycomments' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Allows administrators to instantly switch roles and post comments as virtual users from the frontend.', 'sunnycomments' ); ?></p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($){
            // Color Pickers
            $('.sunnycom-color-field').wpColorPicker();

            // Media Library Uploader for Admin Avatar
            var mediaUploader;
            $('#sunnycom-upload-avatar-btn').click(function(e) {
                e.preventDefault();
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                mediaUploader = wp.media.frames.file_frame = wp.media({
                    title: '<?php esc_html_e( "Select Custom Admin Avatar", "sunnycomments" ); ?>',
                    button: { text: '<?php esc_html_e( "Use this Avatar", "sunnycomments" ); ?>' },
                    multiple: false
                });
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#sunnycom_author_avatar_url').val(attachment.url);
                    $('#sunnycom-avatar-preview').html('<img src="' + attachment.url + '" style="width:100%; height:100%; object-fit:cover;" />');
                    $('#sunnycom-remove-avatar-btn').show();
                });
                mediaUploader.open();
            });

            $('#sunnycom-remove-avatar-btn').click(function(e){
                e.preventDefault();
                $('#sunnycom_author_avatar_url').val('');
                $('#sunnycom-avatar-preview').html('<span class="dashicons dashicons-admin-users" style="font-size:32px; color:#94a3b8; width:32px; height:32px;"></span>');
                $(this).hide();
            });
        });
        </script>
        <?php
    }
}

// Instantiate the setup class
new Sunnycom_Setup();