<?php

namespace App\Http\Controllers\API\v1\Rest;

use App\Helpers\ResponseError;
use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
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
