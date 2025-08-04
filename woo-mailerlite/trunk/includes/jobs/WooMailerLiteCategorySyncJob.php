<?php

class WooMailerLiteCategorySyncJob extends WooMailerLiteAbstractJob
{
    public function handle($data = [])
    {
        $categories = WooMailerLiteCategory::untracked()->get(100);

        if (!$categories->hasItems()) {
            self::$jobModel->delete();
            WooMailerLiteCustomerSyncJob::dispatch($data);
            return;
        }

        $importCategories = [];

        foreach ($categories->items as $category) {
            error_log('Category: ' . $category->name);

            $importCategories[] = [
                'name' => $category->name,
                'resource_id' => (string)$category->resource_id,
            ];

            $category->tracked = true;
            $category->save();
        }

        if (!empty($importCategories)) {
            WooMailerLiteApi::client()->importCategories($importCategories);
        }

        if (static::$jobModel) {
            static::$jobModel->delete();
        }

        if (WooMailerLiteCategory::getUntrackedCategoriesCount()) {
            static::dispatch($data);
        } else {
            WooMailerLiteCustomerSyncJob::dispatch($data);
        }
    }
}