<?php

use Illuminate\Support\Facades\Broadcast;
use Modules\Orders\Models\TenantOrder;


Broadcast::channel('orders.{orderId}', function ($user, $orderId) {
    $order = TenantOrder::find($orderId);
   // dd([$user->id , $order->user_id ] );
    return $order && (
        $order->user_id === $user->id
        // or match by phone/token if customers aren't authenticated Laravel users
    );
});