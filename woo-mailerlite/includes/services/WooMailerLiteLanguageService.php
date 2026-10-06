<?php

/**
 * Resolves the shopper's language for MailerLite subscriber fields.
 *
 * The language is a server side value, so it is never read from the request
 * payload. It is captured into the session while the shopper is on the site
 * (where the multilingual context is correct) and snapshotted onto the order,
 * so requests that run without the shopper's context (gateway callbacks,
 * admin edits, later status changes) still report the right language.
 */
class WooMailerLiteLanguageService
{
    const META_KEY = '_woo_ml_language';

    public static function enabled()
    {
        return (bool) WooMailerLiteOptions::get('settings.languageField');
    }

    /**
     * Order meta, then the session, then the language of the current request.
     * Only returns null for an order whose language was never captured.
     */
    public static function resolve($order = null)
    {
        // This runs inside checkout and add to cart, so a broken multilingual
        // filter must cost the language field, never the sale.
        try {
            if ($order instanceof WC_Order) {
                $language = self::normalize($order->get_meta(self::META_KEY))
                    ?: self::normalize($order->get_meta('wpml_language'));

                if ($language) {
                    return $language;
                }

                // An order touched from wp-admin is not the shopper speaking: the
                // session and locale here are the store owner's. Send nothing
                // rather than stamp their language onto the customer.
                if (is_admin()) {
                    return null;
                }
            }

            if (isset(WC()->session)) {
                $language = self::normalize(WC()->session->get(self::META_KEY));
                if ($language) {
                    return $language;
                }
            }

            return self::current();
        } catch (\Throwable $e) {
            WooMailerLiteLog()->error('languageResolve', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Language of the request being served right now.
     */
    public static function current()
    {
        $language = self::normalize(apply_filters('wpml_current_language', null));

        if (!$language && function_exists('pll_current_language')) {
            $language = self::normalize(pll_current_language('locale'));
        }

        return $language ?: self::normalize(determine_locale());
    }

    /**
     * Remember the language while the shopper's own context is available.
     */
    public static function capture()
    {
        try {
            if (!self::enabled() || !isset(WC()->session)) {
                return;
            }

            // Ajax runs in an admin context, so its locale is the site's rather
            // than the shopper's. Let it fill a gap, never overwrite a page request.
            if (wp_doing_ajax() && self::normalize(WC()->session->get(self::META_KEY))) {
                return;
            }

            $language = self::current();
            if ($language) {
                WC()->session->set(self::META_KEY, $language);
            }
        } catch (\Throwable $e) {
            WooMailerLiteLog()->error('languageCapture', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Snapshots the language onto the order. Leaves saving to the caller so an
     * order being built or already being saved does not pay an extra write.
     */
    public static function persistToOrder($order)
    {
        try {
            if (!self::enabled() || !$order instanceof WC_Order || $order->get_meta(self::META_KEY)) {
                return;
            }

            $language = self::resolve($order);
            if ($language) {
                $order->add_meta_data(self::META_KEY, $language, true);
            }
        } catch (\Throwable $e) {
            WooMailerLiteLog()->error('languagePersistToOrder', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Adds the language to subscriber fields. Call after syncFields filtering:
     * the language field is opted into by its own setting, not by syncFields.
     */
    public static function applyTo(array $fields, $order = null)
    {
        if (!self::enabled()) {
            return $fields;
        }

        $language = self::resolve($order);
        if ($language) {
            $fields['subscriber_language'] = $language;
            $fields['language'] = $language;
        }

        return $fields;
    }

    /**
     * Keeps the get_locale() style format the API already receives, and drops
     * anything that is not a locale rather than guessing a missing region.
     */
    private static function normalize($locale)
    {
        if (!is_string($locale) || trim($locale) === '') {
            return null;
        }

        $locale = str_replace('-', '_', trim($locale));

        return preg_match('/^[a-z]{2,3}(_[a-z0-9]{2,8})*$/i', $locale) ? $locale : null;
    }
}
