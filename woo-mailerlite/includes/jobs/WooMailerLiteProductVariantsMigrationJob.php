<?php

/**
 * After the support for Product Variants, we need to reset the tracked products and re-sync them to MailerLite.
 * This job will reset the tracked products and re-sync them to MailerLite.
 */
class WooMailerLiteProductVariantsMigrationJob extends WooMailerLiteAbstractJob
{
    public function handle($data = [])
    {
        WooMailerLiteLog()->info('variants:migration:handle');

        $products = WooMailerLiteProduct::tracked()->get(100);
        if ($products->hasItems()) {
            foreach ($products->items as $product) {
                $product->tracked = false;
                $product->save();
            }
        }

        if (isset(self::$jobModel)) {
            self::$jobModel->delete();
        }

        if (WooMailerLiteProduct::getTrackedProductsCount()) {
            self::dispatchSync();
        } else {
            WooMailerLiteProductSyncJob::dispatch();
            WooMailerLiteLog()->info('variants:migration:products-reset-complete');
        }
    }
}
