<?php
/*
Plugin Name: Spam Defender - Email Blocker
Plugin URI: https://github.com/Raisul447/spam-defender-email-blocker
Description: Block specific email addresses from registration, login, comments, reviews, and WooCommerce checkout to reduce spam, fake orders, and unwanted activity.
Version: 1.0.0
Author: Raisul Islam Shagor
Author URI: https://shagor.dev
Requires at least: 4.8
Tested up to: 6.8
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Contributors: shagor447
Text Domain: spam-defender-email-blocker
*/

if (!defined('ABSPATH')) {
    exit;
}

class SDEF_Email_Blocker {
    private $option_name = 'sdef_email_blocker_list';
    private $per_page = 20;

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'settings_link']);

        // Validation hooks
        add_filter('registration_errors', [$this, 'block_registration'], 10, 3);
        add_filter('woocommerce_registration_errors', [$this, 'block_registration'], 10, 3);
        add_filter('woocommerce_process_login_errors', [$this, 'block_login'], 10, 3);
        add_filter('sdef_authenticate_user', [$this, 'block_login'], 10, 2);
        add_filter('preprocess_comment', [$this, 'block_comment']);
        add_action('woocommerce_checkout_process', [$this, 'block_checkout']);
    }

    public function settings_link($links) {
        $settings_link = '<a href="options-general.php?page=sdef-email-blocker">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function add_admin_menu() {
        add_options_page(
            'Block Email',
            'Block Email',
            'manage_options',
            'sdef-email-blocker',
            [$this, 'settings_page']
        );
    }

    public function get_blocked_emails() {
        return get_option($this->option_name, []);
    }

    public function settings_page() {
        $blocked = $this->get_blocked_emails();

        // Handle add
        if (isset($_POST['block_email']) && !empty($_POST['email_to_block']) && isset($_POST['sdef_block_email_nonce']) && check_admin_referer('sdef_block_email_action','sdef_block_email_nonce')) {
            $email = sanitize_email( wp_unslash( $_POST['email_to_block'] ) );
            if (is_email($email) && !in_array($email, $blocked)) {
                $blocked[] = $email;
                update_option($this->option_name, $blocked);
                echo '<div class="updated"><p>Email blocked successfully.</p></div>';
            }
        }

        // Handle unblock
        if (isset($_POST['unblock_email']) && !empty($_POST['email']) && isset($_POST['sdef_unblock_email_nonce']) && check_admin_referer('sdef_unblock_email_action','sdef_unblock_email_nonce')) {
            $email = sanitize_email( wp_unslash( $_POST['email'] ) );
            $blocked = array_diff($blocked, [$email]);
            update_option($this->option_name, $blocked);
            echo '<div class="updated"><p>Email unblocked successfully.</p></div>';
        }

        // Handle search
        $search = isset($_GET['s']) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        if ($search) {
            $blocked = array_filter($blocked, function($e) use ($search) {
                return stripos($e, $search) !== false;
            });
        }

        // Pagination
        $total_items = count($blocked);
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $this->per_page;
        $emails_page = array_slice($blocked, $offset, $this->per_page);
        $total_pages = ceil($total_items / $this->per_page);
        ?>
        <div class="wrap">
            <h1>Spam Defender - Email Blocker</h1>
            <form method="post" style="margin-bottom:20px; display:flex; gap:10px; align-items:center;">
                <?php wp_nonce_field('sdef_block_email_action', 'sdef_block_email_nonce'); ?>
                <input type="email" name="email_to_block" placeholder="Enter email to block" required style="width:300px;" />
                <button type="submit" name="block_email" class="button button-primary">Block</button>
            </form>

            <form method="get" style="margin-bottom:15px;">
                <input type="hidden" name="page" value="sdef-email-blocker" />
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search email..." />
                <button type="submit" class="button">Search</button>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Email</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($emails_page)) :
                        $serial = $offset + 1;
                        foreach ($emails_page as $email) : ?>
                            <tr>
                                <td><?php echo esc_html( $serial++ ); ?></td>
                                <td><?php echo esc_html($email); ?></td>
                                <td>
                                    <form method="post" style="display:inline;"> <?php wp_nonce_field('sdef_unblock_email_action', 'sdef_unblock_email_nonce'); ?>
                                        <input type="hidden" name="email" value="<?php echo esc_attr($email); ?>" />
                                        <button type="submit" name="unblock_email" class="button">Unblock</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                        <tr><td colspan="3">Email not found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;', 'spam-defender-email-blocker'),
                            'next_text' => __('&raquo;', 'spam-defender-email-blocker'),
                            'total' => $total_pages,
                            'current' => $page,
                        ]);
                        echo wp_kses_post( $page_links );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function is_blocked($email) {
        $blocked = $this->get_blocked_emails();
        return in_array($email, $blocked);
    }

    public function block_registration($errors, $sanitized_user_login, $user_email) {
        if ($this->is_blocked($user_email)) {
            $errors->add('email_blocked', __('You cannot use this email because it has been blocked.', 'spam-defender-email-blocker'));
        }
        return $errors;
    }

    public function block_login($user, $password) {
        if (is_wp_error($user)) {
            return $user;
        }
        if ($this->is_blocked($user->user_email)) {
            return new WP_Error('email_blocked', __('You cannot use this email because it has been blocked.', 'spam-defender-email-blocker'));
        }
        return $user;
    }

    public function block_comment($commentdata) {
        if ($this->is_blocked($commentdata['comment_author_email'])) {
            wp_die( esc_html__( 'You cannot use this email because it has been blocked.', 'spam-defender-email-blocker' ) );
        }
        return $commentdata;
    }

    public function block_checkout() {
        if ( ! empty( $_POST['billing_email'] ) && $this->is_blocked( sanitize_email( wp_unslash( $_POST['billing_email'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            wc_add_notice(
                esc_html__( 'You cannot use this email because it has been blocked.', 'spam-defender-email-blocker' ),
                'error'
            );
        }
    }
}

new SDEF_Email_Blocker();
?>
