<?php

trait WooMailerLiteProductVariantsTrait
{
    private function getProductVariants($resourceId)
    {
        $product = wc_get_product($resourceId);
        if (!$product || !$product->is_type('variable')) {
            return [];
        }

        $variants = [];
        foreach ($product->get_children() as $variantId) {
            $variant = wc_get_product($variantId);
            if (!$variant) {
                continue;
            }

            $variants[] = [
                'resource_id' => (string) $variant->get_id(),
                'name' => $variant->get_name(),
                'price' => (float) $variant->get_price(),
                'price_type' => 'fixed',
                'attributes' => $this->formatVariantAttributes($variant),
                'image_url' => $this->getVariantImageUrl($variant)
            ];
        }

        return $variants;
    }

    private function getVariantImageUrl($variant)
    {
        if ($variant->get_image_id()) {
            return wp_get_attachment_image_url($variant->get_image_id(), 'full');
        }

        return null;
    }

    private function formatVariantAttributes($variant)
    {
        $attributes = [];
        foreach ($variant->get_attributes() as $key => $value) {
            $attributes[str_replace('pa_', '', $key)] = $value;
        }

        return $attributes;
    }
}
