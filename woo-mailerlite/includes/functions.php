<?php

use MailerLite\Includes\Shared\Api\ApiType;

function woo_ml_valid_email($email)
{

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $email = explode("@", $email);

        if (checkdnsrr(array_pop($email))) {

            return true;
        }
    }

    return false;
}

/**
 * Check whether we are on our admin pages or not
 *
 * @return bool
 */
function woo_ml_is_plugin_admin_area()
{
    $screen = get_current_screen();

    return (strpos($screen->id, 'wc-settings') !== false) ? true : false;
}

/**
 * Debug
 *
 * @param $args
 * @param bool $title
 */
function woo_ml_debug($args, $title = false)
{

    if ($title) {
        echo '<h3>' . $title . '</h3>';
    }

    echo '<pre>';
    print_r($args);
    echo '</pre>';
}

/**
 * Debug to log file
 *
 * @param $message
 */
function woo_ml_debug_log($message)
{

    if (WP_DEBUG === true && defined('WOO_ML_DEBUG') && WOO_ML_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

/**
 * MailerLite universal script for tracking visits
 * @return void
 */
function mailerlite_universal_woo_commerce()
{

    require_once WOO_MAILERLITE_DIR . 'includes/shared/api/class.woo-mailerlite-api-type.php';

    if ((int)get_option('woo_mailerlite_platform', 1) === ApiType::CLASSIC) {

        $shopUrl = home_url();
        $shopUrl = str_replace('http://', '', $shopUrl);
        $shopUrl = str_replace('https://', '', $shopUrl);

        $popups_enabled = !get_option('mailerlite_popups_disabled');
        $load = '';

        if ($popups_enabled) {
            $load = 'load';
        }

        ?>
        <!-- MailerLite Universal -->
        <script>
            (function (m, a, i, l, e, r) {
                m['MailerLiteObject'] = e;

                function f() {
                    var c = {a: arguments, q: []};
                    var r = this.push(c);
                    return "number" != typeof r ? r : f.bind(c.q);
                }

                f.q = f.q || [];
                m[e] = m[e] || f.bind(f.q);
                m[e].q = m[e].q || f.q;
                r = a.createElement(i);
                var _ = a.getElementsByTagName(i)[0];
                r.async = 1;
                r.src = l + '?v' + (~~(new Date().getTime() / 1000000));
                _.parentNode.insertBefore(r, _);
            })(window, document, 'script', 'https://static.mailerlite.com/js/universal.js', 'ml');

            window.mlsettings = window.mlsettings || {};
            window.mlsettings.shop = '<?php echo $shopUrl; ?>';
            var ml_account = ml('accounts', '<?php echo get_option("account_id"); ?>', '<?php echo get_option("account_subdomain"); ?>', '<?php echo $load; ?>');
            ml('ecommerce', 'visitor', 'woocommerce');
        </script>
        <!-- End MailerLite Universal -->
        <?php
    }

    if ((int)get_option('woo_mailerlite_platform', 1) === ApiType::CURRENT) {

        $mailerlite_popups = !((get_option('mailerlite_popups_disabled') == '1'));
        ?>
        <!-- MailerLite Universal -->
        <script>
            (function (w, d, e, u, f, l, n) {
                w[f] = w[f] || function () {
                    (w[f].q = w[f].q || [])
                        .push(arguments);
                }, l = d.createElement(e), l.async = 1, l.src = u,
                    n = d.getElementsByTagName(e)[0], n.parentNode.insertBefore(l, n);
            })
            (window, document, 'script', 'https://assets.mailerlite.com/js/universal.js', 'ml');
            ml('account', '<?php echo get_option('account_id'); ?>');
            ml('enablePopups', <?php echo $mailerlite_popups ? 'true' : 'false'; ?>);
        </script>
        <!-- End MailerLite Universal -->
        <?php
    }
}

if (get_option('account_id') && get_option('account_subdomain')) {

    add_action('wp_head', 'mailerlite_universal_woo_commerce');
}

if ((int)get_option('woo_mailerlite_platform', 1) === 2 && get_option('account_id')) {

    add_action('wp_head', 'mailerlite_universal_woo_commerce');
}