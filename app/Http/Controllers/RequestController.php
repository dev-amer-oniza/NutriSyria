<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRestaurantRequest;
use App\Services\RequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RequestController extends Controller
{
    public function __construct(private RequestService $service)
    {
    }
    /**
     * Register as a Owner
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws ValidationException
     */
    public function restaurantRequest(StoreRestaurantRequest $request): JsonResponse
    {

        $validated = $request->validated();

        $ownerRequest = $this->service->makeRestaurantRequest($validated);

        return self::success(
            data: [
                'request_id' => $ownerRequest->id ?? null,
                'restaurant_name' => $validated['restaurant_name'],
                'status' => 'pending',
                'address' => $validated['address'],
                'phone'   => $validated['restaurant_phone'],

            ],
            message: 'Your request has been successfully submitted. We will contact you soon.',
            status: 201
        );
    }
}
