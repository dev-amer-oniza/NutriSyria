<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListMyOrdersRequest;
use App\Http\Resources\OrderResource;
use App\Service\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    /**
     * List the authenticated user's orders, most recent first.
     */
    public function index(ListMyOrdersRequest $request): JsonResponse
    {
        $orders = $this->orderService->listForUser(
            userId: $request->user()->id,
            filters: $request->filters(),
        );

        return static::success(
            OrderResource::collection($orders),
            'orders.list_success'
        );
    }
}
