<?php

class WooMailerLiteCustomerSyncJob extends WooMailerLiteAbstractJob
{
    public function handle($data = [])
    {
        $customers = WooMailerLiteCustomer::getAll(100);
        if ($customers->hasItems()) {
            foreach ($customers->items as $customer) {
                $customer->markTracked();
            }
            $response = WooMailerLiteApi::client()->syncCustomers($customers->items);

            if ($response->success) {
                static::$jobModel->delete();
            }
            if (WooMailerLiteCustomer::getAll()) {
                self::dispatch($data);
            } else {
                WooMailerLiteCache::delete('manual_sync');
                self::$jobModel->delete();
            }
        }
        self::$jobModel->delete();
    }
}