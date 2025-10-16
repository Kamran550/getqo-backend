<?php

namespace App\Http\Controllers\API\v1\Rest;

use App\Helpers\ResponseError;
use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use App\Repositories\PaymentRepository\PaymentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class PaymentController extends RestBaseController
{
    private PaymentRepository $repository;

    /**
     * @param PaymentRepository $repository
     */
    public function __construct(PaymentRepository $repository)
    {
        parent::__construct();

        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @param FilterParamsRequest $request
     * @return AnonymousResourceCollection
     */
    public function index(FilterParamsRequest $request): AnonymousResourceCollection
    {

        Log::info('index payment;');
        $payments = $this->repository->paymentsList($request->merge(['active' => 1])->all());
        $pay = PaymentResource::collection($payments);
        Log::info('pay:',['pay:',$pay]);
        return PaymentResource::collection($payments);
    }


    public function getPaymentsForUser(FilterParamsRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = auth('sanctum')->user();

        $orderCount = $user->orders()->count(); // Əgər əlaqə qurulubsa
        if ($orderCount < 3) {

            $payments = Payment::where('tag', Payment::TAG_ODERO)->where('active', 1)->get();
        } else {
            $payments = $this->repository->paymentsList($request->merge(['active' => 1])->all());
        }
        return PaymentResource::collection($payments);
    }


    public function getPaymentsForUser2(FilterParamsRequest $request): JsonResponse
    {
        Log::info('getPaymentsForUser2');
        /** @var User $user */
        $user = auth('sanctum')->user();

        $orderCount = $user->orders()->count();

        Log::info('orderCount:', ['orderCount:', $orderCount]);


        $payments = Payment::where('active', 1)
            ->get()
            ->sortBy(function ($payment) {
                if ($payment->tag === 'odero') return 0;
                if ($payment->tag === 'cash') return 1;
                if ($payment->tag === 'wallet') return 2;
                return 99;
            })
            ->values();

        Log::info('paymentler:', ['pay:', $payments]);

        return response()->json([
            'data' => PaymentResource::collection($payments),
            'test' => [
                'order_count' => $orderCount
            ]
        ]);
    }

    /**
     * Get payments for specific shop based on shop_payment_methods table
     * Returns only active payment methods configured for the shop
     * 
     * @param FilterParamsRequest $request
     * @return JsonResponse
     */
    public function getPaymentsForUser3(FilterParamsRequest $request): JsonResponse
    {
        Log::info('getPaymentsForUser3');
        
        /** @var User $user */
        $user = auth('sanctum')->user();
        
        $orderCount = $user->orders()->count();
        
        Log::info('orderCount:', ['orderCount:', $orderCount]);
        
        // Get shop_id from request
        $shopId = $request->input('shop_id');
        
        if (!$shopId) {
            return response()->json([
                'status' => false,
                'message' => 'shop_id is required',
            ], 400);
        }
        
        // Find shop and load payment methods
        $shop = Shop::with(['shopPaymentMethods.payment' => function($query) {
            $query->where('active', 1);
        }])->find($shopId);
        
        Log::info('shop:', ['shop:', $shop]);
        
        if (!$shop) {
            return response()->json([
                'status' => false,
                'message' => 'Shop not found',
            ], 404);
        }
        
        // Check if shop has configured payment methods
        if ($shop->shopPaymentMethods->isEmpty()) {
            // Shop has no payment methods configured - return all active payments (old logic)
            Log::info('Shop has no payment methods configured, using default payments');
            
            $payments = Payment::where('active', 1)
                ->get()
                ->sortBy(function ($payment) {
                    if ($payment->tag === 'odero') return 0;
                    if ($payment->tag === 'cash') return 1;
                    if ($payment->tag === 'wallet') return 2;
                    return 99;
                })
                ->values();
        } else {
            // Shop has configured payment methods - use shop-specific payments
            Log::info('Shop has configured payment methods');
            
            $payments = $shop->shopPaymentMethods
                ->pluck('payment')
                ->filter() // Remove null values (inactive payments)
                ->sortBy(function ($payment) {
                    if ($payment->tag === 'odero') return 0;
                    if ($payment->tag === 'cash') return 1;
                    if ($payment->tag === 'wallet') return 2;
                    return 99;
                })
                ->values();
        }
        
        Log::info('final payments:', ['shop_id' => $shopId, 'payments_count' => $payments->count()]);
        
        return response()->json([
            'data' => PaymentResource::collection($payments),
            'test' => [
                'order_count' => $orderCount,
                'shop_id' => $shopId,
                'using_shop_specific_payments' => !$shop->shopPaymentMethods->isEmpty()
            ]
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        /** @var Payment $payment */
        $payment = $this->repository->paymentDetails($id);

        if (!$payment || !$payment->active) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        return $this->successResponse(__('web.payment_found'), PaymentResource::make($payment));
    }
}
