<?php

class WooMailerLiteCustomer extends WooMailerLiteModel
{
    /**
     * @var string|null
     */
    protected $table = 'wc_customer_lookup';

    protected $casts = [
        'customer_id',
        'resource_id',
        'email',
        'create_subscriber',
        'accepts_marketing',
        'orders_count',
        'total_spent',
        'name',
        'last_name',
        'city',
        'state',
        'country',
        'zip',
        'last_order_id',
        'last_order'
    ];

    /**
     * Get all customers
     * @return mixed|WooMailerLiteCollection|null
     */
    public static function getAll($limit = 100)
    {
        $prefix = db()->prefix;

        $query = static::builder()->select("wp_wc_customer_lookup.customer_id,
        wp_wc_customer_lookup.email,
        max(wp_wc_order_stats.order_id) AS last_order_id,
        max(wp_wc_order_stats.date_created) AS last_order,
        count(DISTINCT wp_wc_order_stats.order_id) AS orders_count,
        sum(wp_wc_order_stats.total_sales) AS total_spent, 
        CASE WHEN (
                        SELECT
                            wpm.meta_value
                        FROM
                            {$prefix}postmeta wpm
                        WHERE
                            wpm.meta_key = '_woo_ml_subscribe'
                            AND wpm.post_id = max({$prefix}wc_order_stats.order_id)
                        LIMIT 1) THEN
                        TRUE
                    ELSE
                        FALSE
                    END AS create_subscriber,
                    max({$prefix}wc_order_stats.order_id) AS last_order_id,
                    max({$prefix}wc_order_stats.date_created) AS last_order,
                    count(DISTINCT ({$prefix}wc_order_stats.order_id)) AS orders_count,
                    sum(({$prefix}wc_order_stats.total_sales)) AS total_spent")
            ->join('wc_order_stats', 'wc_order_stats.customer_id', 'wc_customer_lookup.customer_id')
            ->whereIn('wc_order_stats.status', ['wc-processing', 'wc-completed']);

        if (self::builder()->customTableEnabled()) {
            $query->where('wc_order_stats.customer_id', '>', WooMailerLiteOptions::get('lastSyncedCustomer', 0));
        }

        return $query->groupBy('wc_order_stats.customer_id')
        ->orderBy('wc_order_stats.customer_id')
        ->get($limit);
    }

    public static function selectAll($sync = true)
    {
        $prefix = db()->prefix;
        $query  = static::builder()->select("*, 
        CASE WHEN (
                        SELECT
                            wpm.meta_value
                        FROM
                            {$prefix}postmeta wpm
                        WHERE
                            wpm.meta_key = '_woo_ml_subscribe'
                            AND wpm.post_id = max({$prefix}wc_order_stats.order_id)
                        LIMIT 1) THEN
                        TRUE
                    ELSE
                        FALSE
                    END AS create_subscriber,
                    max({$prefix}wc_order_stats.order_id) AS last_order_id,
                    max({$prefix}wc_order_stats.date_created) AS last_order,
                    count(DISTINCT ({$prefix}wc_order_stats.order_id)) AS orders_count,
                    sum(({$prefix}wc_order_stats.total_sales)) AS total_spent")
            ->join('wc_order_stats', 'wc_order_stats.customer_id', 'wc_customer_lookup.customer_id')
            ->whereIn('wc_order_stats.status', ['wc-processing', 'wc-completed']);
        if (self::builder()->customTableEnabled() && $sync) {
            $query->where('wc_order_stats.customer_id', '>', WooMailerLiteOptions::get('lastSyncedCustomer', 0));
            $query->groupBy('wc_order_stats.customer_id, wp_wc_order_stats.order_id')
                ->orderBy('wc_order_stats.customer_id');
        }

        return $query;
    }

    public function markTracked()
    {
        if (!$this->queryBuilder()->customTableEnabled()) {
            WooMailerLiteOptions::update('lastSyncedOrder', $this->last_order_id);
        } else {
            WooMailerLiteOptions::update('lastSyncedCustomer', $this->customer_id);
        }
    }

    public static function martUntracked()
    {
        if (!self::builder()->customTableEnabled()) {
            WooMailerLiteOptions::update('lastSyncedOrder', 0);
        } else {
            WooMailerLiteOptions::update('lastSyncedCustomer', 0);
        }
    }
}
