<?php

class WooMailerLiteAdminSyncController extends WooMailerLiteController
{
    public function sync()
    {
        $this->validate('nonce');
        $alreadyStarted = WooMailerLiteCache::get('manual_sync', false);
        if (!$alreadyStarted) {
            WooMailerLiteCache::set('manual_sync', true, 18000);
        }
        $totalUntrackedResources = WooMailerLiteCategory::getUntrackedCategoriesCount() + WooMailerLiteProduct::getUntrackedProductsCount() +  WooMailerLiteCustomer::getAll()->count();
        if ($totalUntrackedResources == 0) {
            WooMailerLiteCache::delete('manual_sync');
            return $this->response('no resources to sync', 200);
        }

        if ($alreadyStarted) {
            WooMailerLiteProductSyncJob::dispatch();
        } else {
            WooMailerLiteProductSyncJob::dispatchSync();
        }

        return $this->response('sync started', 202);
    }

    public function resetSync()
    {
        $this->validate('nonce');
        WooMailerLiteCache::delete('manual_sync');
        WooMailerLiteProductSyncResetJob::dispatchSync();
        return $this->response(['message' => 'reset sync completed', 'data' => [
            'totalUntrackedResources' => WooMailerLiteCategory::getUntrackedCategoriesCount() + WooMailerLiteProduct::getUntrackedProductsCount() +  WooMailerLiteCustomer::getAll()->count()
        ]], 202);
    }
}