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

        Log::info('getPaymentsForUser;');
        /** @var User $user */
        $user = auth('sanctum')->user();

        $orderCount = $user->orders()->count(); // Əgər əlaqə qurulubsa
        Log::info('ordersCount:', ['orderCount:', $orderCount]);
        if ($orderCount < 3) {

            Log::info('if pay');
            $payments = Payment::where('tag', Payment::TAG_ODERO)->where('active', 1)->get();
        } else {
            Log::info('else pay');

            $payments = $this->repository->paymentsList($request->merge(['active' => 1])->all());
        }
        $menimpayments = PaymentResource::collection($payments);
        Log::info('menimpayments:', ['menimpayments:', $menimpayments]);
        return PaymentResource::collection($payments);
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
