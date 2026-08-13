<?php

class WooMailerLiteCartCleanupJob extends WooMailerLiteAbstractJob
{
    public function handle($data = [])
    {
        $cutoff = date('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS);
        $carts = WooMailerLiteCart::where('created_at', '<', $cutoff)->get($this->resourceLimit);

        if ($carts->hasItems()) {
            foreach ($carts->items as $cart) {
                $cart->delete();
            }
        }

        if (isset(self::$jobModel)) {
            self::$jobModel->delete();
        }

        if ($carts->count() === $this->resourceLimit) {
            self::dispatch();
        }
    }
}
