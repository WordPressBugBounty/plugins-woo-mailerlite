<?php

namespace MailerLite\Includes\Classes\Process;

use MailerLite\Includes\Classes\Singleton;

class CheckoutProcess extends Singleton
{
    /**
     * Sets checkout for user session when clicking on Return to checkout email
     * woo_ml_reload_checkout
     * @return void
     */
    public function reloadCheckout()
    {
        if ( ! function_exists('WC')) {

            return false;
        }

        if ( ! is_object(WC()->session)) {

            return false;
        }

        if (isset($_GET['ml_checkout'])) {
            //$checkout_id = substr($_GET['ml_checkout'], 0, strpos($_GET['ml_checkout'], "?"));
            $checkout_id = $_GET['ml_checkout'];
            $checkout    = $this->getSavedCheckout($checkout_id);

            if ($checkout && ! empty($checkout->cart_content)) {
                WC()->session->set('cart', unserialize($checkout->cart_content));
                @setcookie('mailerlite_checkout_token', $checkout->checkout_id, time() + 172800, '/');
                @setcookie('mailerlite_checkout_email', $checkout->email, time() + 172800, '/');
            }
        }
    }

    /**
     * Remove Checkout
     * woo_ml_remove_checkout
     *
     * @param $email
     *
     * @return void
     */
    public function removeCheckout($email)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mailerlite_checkouts';

        $wpdb->delete($table, array('email' => $email));
    }

    /**
     * Get saved checkout id by email
     * woo_ml_get_saved_checkout_id_by_email
     *
     * @param $email
     *
     * @return null
     */
    public function getSavedCheckoutIdByEmail($email)
    {
        $checkout = $this->getSavedCheckoutByEmail($email);
        if ( ! empty($checkout)) {
            return $checkout->checkout_id;
        }

        return null;
    }

    /**
     * Get saved checkout by email
     * woo_ml_get_saved_checkout_by_email
     *
     * @param $email
     *
     * @return array|object|\stdClass|null
     */
    public function getSavedCheckoutByEmail($email)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mailerlite_checkouts';

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE email = %s ORDER BY time DESC LIMIT 1",
            $email));
    }

    /**
     * Get saved checkout
     * woo_ml_get_saved_checkout
     *
     * @param string $checkout_id
     *
     * @return array
     */
    public function getSavedCheckout($checkout_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mailerlite_checkouts';

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE checkout_id = %s", $checkout_id));
    }

    /**
     * Insert/update/delete checkout entry from the table
     * woo_ml_save_or_update_checkout
     *
     * @param string $checkout_id
     * @param string $customer_email
     * @param array $cart
     *
     * @return void
     */
    public function saveOrUpdateCheckout($checkout_id, $customer_email, $cart)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mailerlite_checkouts';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $this->createMailerliteCheckoutsTable();
        }

        $checkout = $this->getSavedCheckout($checkout_id);
        if ( ! empty($checkout) && ! empty($cart)) {
            $wpdb->query($wpdb->prepare("UPDATE $table 
                SET email = %s, cart_content = %s
                WHERE checkout_id = %s", $customer_email, serialize($cart), $checkout_id)
            );
        } elseif ( ! empty($checkout) && empty($cart)) {
            $wpdb->delete($table, array('checkout_id' => $checkout_id));
        } else {
            $wpdb->insert(
                $table,
                array(
                    'time'         => current_time('mysql'),
                    'checkout_id'  => $checkout_id,
                    'email'        => $customer_email,
                    'cart_content' => serialize($cart)
                )
            );
        }
    }

    /**
     * Drop mailerlite checkouts table
     * woo_ml_drop_mailerlite_checkouts_table
     * @return void
     */
    public function dropMailerliteCheckoutsTable()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mailerlite_checkouts';
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }

    /**
     * Intial creation of mailerlite_checkouts table
     * woo_ml_create_mailerlite_checkouts_table
     * @return void
     */
    public function createMailerliteCheckoutsTable()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mailerlite_checkouts';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table(
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		checkout_id varchar(55) NOT NULL,
		email text NOT NULL,
		cart_content text DEFAULT '' NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Preparing checkout data for api
     * woo_ml_get_checkout_data
     *
     * @param $cookie_email
     *
     * @return array
     */
    public function getCheckoutData(
        $cookie_email = null,
        $subscribe = null,
        $language = null,
        $subscriber_fields = null
    ) {
        if ( ! function_exists('WC')) {
            return false;
        }

        if (empty(WC()->cart)) {
            WC()->frontend_includes();

            if ( ! did_action('woocommerce_load_cart_from_session') && function_exists('wc_load_cart')) {
                wc_load_cart();
            }
        }

        if ( ! $cookie_email) {
            $cookie_email = $_COOKIE['mailerlite_checkout_email'] ?? $cookie_email;
        }

        if ( $subscribe === null ) {
            $subscribe = (bool) ($_COOKIE['mailerlite_accepts_marketing'] ?? false);
        }

        $cart           = WC()->cart;
        $cart_items     = $cart->get_cart();
        $customer       = $cart->get_customer();
        $customer_email = $customer->get_email();
        if ( ! $customer_email) {
            $customer_email = $cookie_email;
        }

        // check if email was updated recently in checkout
        if (filter_var($cookie_email, FILTER_VALIDATE_EMAIL) && $customer_email !== $cookie_email) {
            $customer_email = $cookie_email;
        }

        $checkout_data = [];
        if ( ! empty($customer_email)) {
            $line_items = [];
            $total      = 0;

            foreach ($cart_items as $key => $value) {
                $subtotal = intval($value['quantity']) * floatval($value['data']->get_price('edit'));

                $line_item    = [
                    'key'           => $key,
                    'line_subtotal' => $subtotal,
                    'line_total'    => $subtotal,
                    'product_id'    => $value['product_id'],
                    'quantity'      => $value['quantity'],
                    'variation'     => $value['variation'],
                    'variation_id'  => $value['variation_id']
                ];
                $line_items[] = $line_item;

                $total += $subtotal;
            }

            if ( !isset($_COOKIE['mailerlite_checkout_token']) && !isset($_POST['cookie_mailerlite_checkout_token'])) {
                if (!isset($_SESSION['mailerlite_checkout_token'])) {
                    $_SESSION['mailerlite_checkout_token'] = floor(microtime(true) * 1000);
                }
                @setcookie('mailerlite_checkout_token', $_SESSION['mailerlite_checkout_token'], time() + 172800, '/');
                $checkout_id = $_SESSION['mailerlite_checkout_token'];
            } else {
                $checkout_id = $_COOKIE['mailerlite_checkout_token'] ?? $_POST['cookie_mailerlite_checkout_token'];
            }

            $shop_checkout_url = wc_get_checkout_url();
            $checkout_url      = $shop_checkout_url . '?ml_checkout=' . $checkout_id;

            $checkout_data = [
                'id'                     => $checkout_id,
                'email'                  => $customer_email,
                'line_items'             => $line_items,
                'abandoned_checkout_url' => $checkout_url,
                'total_price'            => $total,
                'created_at'             => date('Y-m-d h:m:s')
            ];

            if ($subscribe === true) {
                $checkout_data['subscribe'] = true;
            }

            if ( ! empty($language)) {
                $checkout_data['language'] = $language;
            }

            if ( ! empty($subscriber_fields)) {
                $checkout_data['subscriber_fields'] = $subscriber_fields;
            }

            $this->saveOrUpdateCheckout($checkout_id, $customer_email, $cart_items);
        }

        return $checkout_data;
    }
}