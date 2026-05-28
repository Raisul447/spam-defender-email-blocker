<?php
/*
Plugin Name: Spam Defender - Email Blocker
Plugin URI: https://raisul.dev/projects/spam-defender-email-blocker-wordpress-plugin
Description: Block specific email addresses from registration, login, comments, reviews, and WooCommerce checkout to reduce spam, fake orders, and unwanted activity.
Version: 1.0.4
Author: Raisul Islam Shagor
Author URI: https://raisul.dev
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('wp_ajax_sdef_check_email', [$this, 'ajax_check_email']);
        add_action('wp_ajax_nopriv_sdef_check_email', [$this, 'ajax_check_email']);

        // Validation hooks
        add_filter('registration_errors', [$this, 'block_registration'], 10, 3);
        add_filter('woocommerce_registration_errors', [$this, 'block_registration'], 10, 3);
        add_filter('woocommerce_process_login_errors', [$this, 'block_login'], 10, 3);
        add_filter('sdef_authenticate_user', [$this, 'block_login'], 10, 2);
        add_filter('preprocess_comment', [$this, 'block_comment']);
        add_action('comment_form_before', [$this, 'display_comment_error']);
        add_action('woocommerce_checkout_process', [$this, 'block_checkout']);
    }

    public function settings_link($links) {
        $settings_link = '<a href="options-general.php?page=sdef-email-blocker">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function enqueue_admin_assets($hook) {
        if ($hook === 'settings_page_sdef-email-blocker') {
            wp_enqueue_style('sdef-admin-style', plugins_url('includes/admin.css', __FILE__), [], '1.0.4');
            wp_enqueue_script('sdef-admin-script', plugins_url('includes/admin.js', __FILE__), [], '1.0.4', true);
        }
    }

    public function enqueue_frontend_assets() {
        if (is_single()) {
            wp_enqueue_script('sdef-frontend-script', plugins_url('includes/frontend.js', __FILE__), [], '1.0.4', true);
            wp_localize_script('sdef-frontend-script', 'sdef_vars', [
                'ajax_url'  => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('sdef_check_email_nonce'),
                'error_msg' => __('You cannot use this email because it has been blocked.', 'spam-defender-email-blocker')
            ]);
        }
    }

    public function ajax_check_email() {
        check_ajax_referer('sdef_check_email_nonce', 'nonce');
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        if (is_email($email) && $this->is_blocked($email)) {
            wp_send_json_success(['blocked' => true]);
        } else {
            wp_send_json_success(['blocked' => false]);
        }
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
        $total_blocked_count = count($blocked);
        $messages = [];

        // Handle add
        if (isset($_POST['block_email']) && !empty($_POST['email_to_block']) && isset($_POST['sdef_block_email_nonce']) && check_admin_referer('sdef_block_email_action','sdef_block_email_nonce')) {
            $input = sanitize_textarea_field( wp_unslash( $_POST['email_to_block'] ) );
            // Split input by commas, new lines, carriage returns or spaces
            $raw_emails = preg_split('/[\s,\n\r]+/', $input);
            $added_count = 0;
            $invalid_count = 0;
            $duplicate_count = 0;

            foreach ($raw_emails as $raw_email) {
                $raw_email = trim($raw_email);
                if (empty($raw_email)) {
                    continue;
                }
                $email = sanitize_email($raw_email);
                if (is_email($email)) {
                    if (!in_array($email, $blocked)) {
                        $blocked[] = $email;
                        $added_count++;
                    } else {
                        $duplicate_count++;
                    }
                } else {
                    $invalid_count++;
                }
            }

            if ($added_count > 0) {
                update_option($this->option_name, $blocked);
                $total_blocked_count = count($blocked); // Update count
                $messages[] = [
                    'type' => 'success',
                    'text' => sprintf(
                        /* translators: %d: number of emails */
                        _n('%d email blocked successfully.', '%d emails blocked successfully.', $added_count, 'spam-defender-email-blocker'),
                        $added_count
                    )
                ];
            }

            if ($invalid_count > 0) {
                $messages[] = [
                    'type' => 'danger',
                    'text' => sprintf(
                        /* translators: %d: number of invalid emails */
                        _n('%d invalid email address skipped.', '%d invalid email addresses skipped.', $invalid_count, 'spam-defender-email-blocker'),
                        $invalid_count
                    )
                ];
            }

            if ($duplicate_count > 0) {
                $messages[] = [
                    'type' => 'warning',
                    'text' => sprintf(
                        /* translators: %d: number of duplicate emails */
                        _n('%d already blocked email skipped.', '%d already blocked emails skipped.', $duplicate_count, 'spam-defender-email-blocker'),
                        $duplicate_count
                    )
                ];
            }

            if ($added_count === 0 && $invalid_count === 0 && $duplicate_count === 0) {
                $messages[] = ['type' => 'danger', 'text' => __('No email addresses found to block.', 'spam-defender-email-blocker')];
            }
        }

        // Handle unblock
        if (isset($_POST['unblock_email']) && !empty($_POST['email']) && isset($_POST['sdef_unblock_email_nonce']) && check_admin_referer('sdef_unblock_email_action','sdef_unblock_email_nonce')) {
            $email = sanitize_email( wp_unslash( $_POST['email'] ) );
            if (in_array($email, $blocked)) {
                $blocked = array_diff($blocked, [$email]);
                update_option($this->option_name, $blocked);
                $total_blocked_count = count($blocked); // Update count
                $messages[] = ['type' => 'success', 'text' => __('Email unblocked successfully.', 'spam-defender-email-blocker')];
            }
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

        // Toast Messages Output
        if (!empty($messages)) {
            echo '<div class="sdef-alert-container">';
            foreach ($messages as $msg) {
                if ($msg['type'] === 'success') {
                    $class = 'sdef-alert-success';
                    $icon = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>';
                } elseif ($msg['type'] === 'warning') {
                    $class = 'sdef-alert-warning';
                    $icon = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>';
                } else {
                    $class = 'sdef-alert-danger';
                    $icon = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>';
                }
                
                echo '<div class="sdef-alert ' . esc_attr($class) . '">';
                echo '<div class="sdef-alert-icon">' . wp_kses($icon, [
                    'svg'  => [
                        'viewbox' => true,
                        'fill'    => true,
                    ],
                    'path' => [
                        'fill-rule' => true,
                        'd'         => true,
                        'clip-rule' => true,
                    ],
                ]) . '</div>';
                echo '<div class="sdef-alert-content"><p class="sdef-alert-message">' . esc_html($msg['text']) . '</p></div>';
                echo '<button type="button" class="sdef-alert-close"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg></button>';
                echo '</div>';
            }
            echo '</div>';
        }
        ?>
        <div class="wrap sdef-admin-dashboard">
            <div class="sdef-header">
                <div class="sdef-brand">
                    <div class="sdef-brand-logo">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                    </div>
                    <div class="sdef-brand-info">
                        <h1>Spam Defender - Email Blocker</h1>
                        <p>Protect WooCommerce registration, login, comments, and checkout from spam emails</p>
                    </div>
                </div>
                <div class="sdef-stats">
                    <div class="sdef-stat-card">
                        <span class="sdef-stat-label">Total Blocked</span>
                        <span class="sdef-stat-val"><?php echo esc_html($total_blocked_count); ?></span>
                    </div>
                    <?php if ($search !== '') : ?>
                        <div class="sdef-stat-card">
                            <span class="sdef-stat-label">Matches</span>
                            <span class="sdef-stat-val"><?php echo esc_html(count($blocked)); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sdef-dashboard-body">
                <div class="sdef-card">
                    <h2 class="sdef-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                        Block New Email
                    </h2>
                    <form method="post">
                        <?php wp_nonce_field('sdef_block_email_action', 'sdef_block_email_nonce'); ?>
                        <div class="sdef-form-group">
                            <label for="email_to_block">Email Address(es)</label>
                            <div class="sdef-input-wrapper sdef-input-wrapper-textarea">
                                <span class="sdef-input-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                        <polyline points="22,6 12,13 2,6"></polyline>
                                    </svg>
                                </span>
                                <textarea id="email_to_block" name="email_to_block" class="sdef-control sdef-textarea" placeholder="example@spamdomain.com&#10;spam@domain.com, test@spam.com" required></textarea>
                            </div>
                            <span style="font-size: 11px; color: var(--sdef-text-muted); display: block; margin-top: 6px;">Separate multiple emails with commas, spaces, or a new line.</span>
                        </div>
                        <button type="submit" name="block_email" class="sdef-btn sdef-btn-primary sdef-btn-block">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px; height:18px;">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            Block Email
                        </button>
                    </form>
                </div>

                <div class="sdef-card">
                    <h2 class="sdef-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                        </svg>
                        Blocked Email Directory
                    </h2>

                    <form method="get" class="sdef-search-wrapper">
                        <input type="hidden" name="page" value="sdef-email-blocker" />
                        <div class="sdef-input-wrapper">
                            <span class="sdef-input-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                            </span>
                            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" class="sdef-control" placeholder="Search blocked emails..." />
                        </div>
                        <button type="submit" class="sdef-btn sdef-btn-search">Search</button>
                        <?php if ($search !== '') : ?>
                            <a href="options-general.php?page=sdef-email-blocker" class="sdef-btn sdef-btn-search" style="text-decoration:none;">Clear</a>
                        <?php endif; ?>
                    </form>

                    <div class="sdef-table-container">
                        <table class="sdef-table">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>Email Address</th>
                                    <th style="width: 100px;">Status</th>
                                    <th style="text-align: right; width: 120px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($emails_page)) :
                                    $serial = $offset + 1;
                                    foreach ($emails_page as $email) : 
                                        $initial = strtoupper(substr($email, 0, 1));
                                        if (!preg_match('/[A-Z0-9]/', $initial)) {
                                            $initial = '@';
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html($serial++); ?></td>
                                            <td>
                                                <div class="sdef-email-col">
                                                    <div class="sdef-email-avatar"><?php echo esc_html($initial); ?></div>
                                                    <span class="sdef-email-text"><?php echo esc_html($email); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="sdef-badge sdef-badge-danger">Blocked</span>
                                            </td>
                                            <td style="text-align: right;">
                                                <form method="post" class="sdef-unblock-form" style="display:inline;">
                                                    <?php wp_nonce_field('sdef_unblock_email_action', 'sdef_unblock_email_nonce'); ?>
                                                    <input type="hidden" name="email" value="<?php echo esc_attr($email); ?>" />
                                                    <button type="submit" name="unblock_email" class="sdef-btn sdef-btn-unblock">Unblock</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; else : ?>
                                    <tr>
                                        <td colspan="4">
                                            <div class="sdef-empty-state">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <line x1="8" y1="12" x2="16" y2="12"></line>
                                                </svg>
                                                <p>No blocked emails found.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <?php if ($total_pages > 1) : ?>
                            <div class="sdef-pagination">
                                <div class="sdef-pagination-info">
                                    Showing <?php echo esc_html($offset + 1); ?>-<?php echo esc_html(min($offset + $this->per_page, $total_items)); ?> of <?php echo esc_html($total_items); ?> entries
                                </div>
                                <div class="sdef-pagination-links">
                                    <?php
                                    $page_links = paginate_links([
                                        'base' => add_query_arg('paged', '%#%'),
                                        'format' => '',
                                        'prev_text' => __('&lsaquo;', 'spam-defender-email-blocker'),
                                        'next_text' => __('&rsaquo;', 'spam-defender-email-blocker'),
                                        'total' => $total_pages,
                                        'current' => $page,
                                    ]);
                                    echo wp_kses_post( $page_links );
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
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
            $is_review = isset($commentdata['comment_type']) && $commentdata['comment_type'] === 'review';

            if (defined('DOING_AJAX') && DOING_AJAX) {
                wp_send_json_error(__('You cannot use this email because it has been blocked.', 'spam-defender-email-blocker'), 400);
            }

            if ($is_review && function_exists('wc_add_notice')) {
                wc_add_notice(__('You cannot use this email because it has been blocked.', 'spam-defender-email-blocker'), 'error');
                $referrer = wp_get_referer();
                if ($referrer) {
                    wp_safe_redirect($referrer);
                    exit;
                }
            }

            $referrer = wp_get_referer();
            if ($referrer) {
                $referrer = add_query_arg('sdef_comment_error', 'blocked', $referrer);
                wp_safe_redirect($referrer);
                exit;
            } else {
                wp_die(esc_html__('You cannot use this email because it has been blocked.', 'spam-defender-email-blocker'));
            }
        }
        return $commentdata;
    }

    public function display_comment_error() {
        if (isset($_GET['sdef_comment_error']) && $_GET['sdef_comment_error'] === 'blocked') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<div class="sdef-comment-error" style="background-color: #ffe4e6; color: #f43f5e; border-left: 4px solid #f43f5e; padding: 12px 16px; border-radius: 8px; font-weight: 500; font-family: sans-serif; margin-bottom: 20px; font-size: 14px;">';
            echo esc_html__('You cannot use this email because it has been blocked.', 'spam-defender-email-blocker');
            echo '</div>';
        }
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
