<?php

class WooMailerLiteProduct extends WooMailerLiteModel
{
    protected $casts = [
        'resource_id',
        'name',
        'price',
        'description',
        'short_description',
        'image',
        'category_ids',
        'created_at',
        'status',
        'updated_at',
        'url',
        'tracked',
        'ignored'
    ];

    protected $isResource = true;

    protected $format = [
        'tracked' => 'boolean',
        'image' => 'string',
    ];

    protected $removeEmpty = [
      'description',
      'short_description',
      'image',
    ];

    public static function tracked()
    {
        return self::setArgs([
            'metaQuery' => [
                [
                    'key' => '_woo_ml_product_tracked',
                    'value' => true,
                ]
            ]
        ]);
    }

    public static function untracked()
    {
        return self::setArgs([
            'metaQuery' => [
                'relation' => 'OR', // Ensures at least one condition matches
                [
                    'key'     => '_woo_ml_product_tracked',
                    'value'   => false,
                    'compare' => '=', // Looks for products where _woo_ml_product_tracked is explicitly false
                ],
                [
                    'key'     => '_woo_ml_product_tracked',
                    'compare' => 'NOT EXISTS', // Includes products where this key is missing
                ],
            ]
        ]);
    }

    public static function getTrackedProductsCount()
    {
        return self::tracked()->count();
    }

    public static function getUntrackedProductsCount()
    {
        return self::untracked()->count();
    }
}