<?php

namespace App\Http\Controllers\API\v1\Dashboard\Seller;

use App\Exports\OrderExport;
use App\Helpers\ResponseError;
use App\Http\Requests\FilterParamsRequest;
use App\Http\Requests\Order\StatusUpdateRequest;
use App\Http\Requests\Order\WaiterUpdateRequest;
use App\Http\Requests\Order\DeliveryManUpdateRequest;
use App\Http\Requests\Order\StocksCalculateRequest;
use App\Http\Requests\Order\StoreRequest;
use App\Http\Requests\Order\UpdateRequest;
use App\Http\Resources\OrderResource;
use App\Imports\OrderImport;
use App\Models\Order;
use App\Models\Settings;
use App\Models\User;
use App\Repositories\DashboardRepository\DashboardRepository;
use App\Repositories\Interfaces\OrderRepoInterface;
use App\Repositories\OrderRepository\AdminOrderRepository;
use App\Services\Interfaces\OrderServiceInterface;
use App\Services\OrderService\OrderStatusUpdateService;
use App\Traits\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

// ⬇️ Cooking time üçün
use App\Jobs\AutoReadyOrderJob;
use Illuminate\Support\Facades\Log;

class OrderController extends SellerBaseController
{
    use Notification;

    public function __construct(
        private OrderRepoInterface   $orderRepository, // todo remove
        private AdminOrderRepository $adminRepository,
        private OrderServiceInterface $orderService
    ) {
        parent::__construct();
    }

    /** Paginate */
    public function paginate(FilterParamsRequest $request): JsonResponse
    {
        $filter = $request->merge(['shop_id' => $this->shop->id])->all();

        $orders    = $this->adminRepository->ordersPaginate($filter);
        $statistic = (new DashboardRepository)->orderByStatusStatistics($filter);
        $lastPage  = (new DashboardRepository)->getLastPage(
            data_get($filter, 'perPage', 10),
            $statistic,
            data_get($filter, 'status')
        );

        if (!Cache::get('tvoirifgjn.seirvjrc') || data_get(Cache::get('tvoirifgjn.seirvjrc'), 'active') != 1) {
            abort(403);
        }

        return $this->successResponse(__('errors.' . ResponseError::SUCCESS, locale: $this->language), [
            'statistic' => $statistic,
            'orders'    => OrderResource::collection($orders),
            'meta'      => [
                'current_page' => (int)data_get($filter, 'page', 1),
                'per_page'     => (int)data_get($filter, 'perPage', 10),
                'last_page'    => $lastPage,
                'total'        => (int)data_get($statistic, 'total', 0),
            ],
        ]);
    }

    /** Store */
    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ((int)data_get(Settings::where('key', 'order_auto_approved')->first(), 'value') === 1) {
            $validated['status'] = Order::STATUS_ACCEPTED;
        }

        $result = $this->orderService->create($validated);

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        if (!Cache::get('tvoirifgjn.seirvjrc') || data_get(Cache::get('tvoirifgjn.seirvjrc'), 'active') != 1) {
            abort(403);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_CREATED, locale: $this->language),
            $this->orderRepository->reDataOrder(data_get($result, 'data')),
        );
    }

    /** Admin tokenləri */
    public function tokens(): array
    {
        $admins = User::with([
            'roles' => fn($q) => $q->where('name', 'admin')
        ])
            ->whereHas('roles', fn($q) => $q->where('name', 'admin'))
            ->whereNotNull('firebase_token')
            ->pluck('firebase_token', 'id')
            ->toArray();

        $aTokens = [];
        foreach ($admins as $adminToken) {
            $aTokens = array_merge($aTokens, is_array($adminToken) ? array_values($adminToken) : [$adminToken]);
        }

        return [
            'tokens' => array_values(array_unique($aTokens)),
            'ids'    => array_keys($admins)
        ];
    }

    /** Show */
    public function show(int $id): JsonResponse
    {
        $order = $this->orderRepository->orderById($id, $this->shop->id);

        if ($order) {
            return $this->successResponse(
                __('errors.' . ResponseError::SUCCESS, locale: $this->language),
                $this->orderRepository->reDataOrder($order)
            );
        }

        if (!Cache::get('tvoirifgjn.seirvjrc') || data_get(Cache::get('tvoirifgjn.seirvjrc'), 'active') != 1) {
            abort(403);
        }

        return $this->onErrorResponse([
            'code'    => ResponseError::ERROR_404,
            'message' => __('errors.' . ResponseError::ORDER_NOT_FOUND, locale: $this->language)
        ]);
    }

    /** Update */
    public function update(int $id, UpdateRequest $request): JsonResponse
    {
        $result = $this->orderService->update($id, $request->all());

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_UPDATED, locale: $this->language),
            $this->orderRepository->reDataOrder(data_get($result, 'data')),
        );
    }

    /** Status update (mövcud axın) */
    public function orderStatusUpdate(int $orderId, StatusUpdateRequest $request): JsonResponse
    {
        /** @var Order $order */
        $order = Order::with(['shop.seller', 'deliveryMan', 'waiter', 'user.wallet'])
            ->where('shop_id', $this->shop->id)
            ->find($orderId);

        if (!$order) {
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_404,
                'message' => __('errors.' . ResponseError::ORDER_NOT_FOUND, locale: $this->language)
            ]);
        }

        $result = (new OrderStatusUpdateService)->statusUpdate($order, $request->input('status'));

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        if (!Cache::get('tvoirifgjn.seirvjrc') || data_get(Cache::get('tvoirifgjn.seirvjrc'), 'active') != 1) {
            abort(403);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR),
            $this->orderRepository->reDataOrder(data_get($result, 'data')),
        );
    }

    /** Deliveryman təyin etmə */
    public function orderDeliverymanUpdate(int $orderId, DeliveryManUpdateRequest $request): JsonResponse
    {
        $result = $this->orderService->updateDeliveryMan($orderId, $request->input('deliveryman'), $this->shop->id);

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_UPDATED, locale: $this->language),
            $this->orderRepository->reDataOrder(data_get($result, 'data')),
        );
    }

    /** Waiter təyin etmə */
    public function orderWaiterUpdate(int $orderId, WaiterUpdateRequest $request): JsonResponse
    {
        $result = $this->orderService->updateWaiter($orderId, $request->input('waiter_id'), $this->shop->id);

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_UPDATED, locale: $this->language),
            $this->orderRepository->reDataOrder(data_get($result, 'data')),
        );
    }

    /** Kalkulyasiya */
    public function orderStocksCalculate(StocksCalculateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['shop_id'] = $this->shop->id;

        $result = $this->orderRepository->orderStocksCalculate($validated);

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        return $this->successResponse(__('errors.' . ResponseError::SUCCESS, locale: $this->language), $result);
    }

    /** Pending transaction siyahısı */
    public function ordersPendingTransaction(FilterParamsRequest $request): JsonResponse
    {
        $filter    = $request->merge(['shop_id' => $this->shop->id])->all();
        $orders    = $this->adminRepository->ordersPendingTransaction($filter);
        $statistic = (new DashboardRepository)->orderByStatusStatistics($filter);
        $lastPage  = (new DashboardRepository)->getLastPage(
            data_get($filter, 'perPage', 10),
            $statistic,
            data_get($filter, 'status')
        );

        if (!Cache::get('tvoirifgjn.seirvjrc') || data_get(Cache::get('tvoirifgjn.seirvjrc'), 'active') != 1) {
            abort(403);
        }

        return $this->successResponse(__('errors.' . ResponseError::SUCCESS, locale: $this->language), [
            'statistic' => $statistic,
            'orders'    => OrderResource::collection($orders),
            'meta'      => [
                'current_page' => (int)data_get($filter, 'page', 1),
                'per_page'     => (int)data_get($filter, 'perPage', 10),
                'last_page'    => $lastPage,
                'total'        => (int)data_get($statistic, 'total', 0),
            ],
        ]);
    }

    /** Destroy */
    public function destroy(FilterParamsRequest $request): JsonResponse
    {
        $result = $this->orderService->destroy($request->input('ids'), $this->shop->id);

        if (count($result) > 0) {
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_400,
                'message' => __('errors.' . ResponseError::CANT_DELETE_ORDERS, [
                    'ids' => implode(', #', $result)
                ], locale: $this->language)
            ]);
        }

        return $this->successResponse('Successfully data');
    }

    /** Export */
    public function fileExport(FilterParamsRequest $request): JsonResponse
    {
        $fileName = 'export/orders.xlsx';

        try {
            $filter = $request->merge(['shop_id' => $this->shop->id, 'language' => $this->language])->all();
            Excel::store(new OrderExport($filter), $fileName, 'public', \Maatwebsite\Excel\Excel::XLSX);

            return $this->successResponse('Successfully exported', [
                'path'      => 'public/export',
                'file_name' => $fileName
            ]);
        } catch (Throwable $e) {
            $this->error($e);
            return $this->errorResponse('Error during export');
        }
    }

    /** Import */
    public function fileImport(Request $request): JsonResponse
    {
        try {
            Excel::import(new OrderImport($this->language, $this->shop->id), $request->file('file'));

            return $this->successResponse('Successfully imported');
        } catch (Throwable $e) {
            $this->error($e);
            return $this->errorResponse(
                ResponseError::ERROR_508,
                __('errors.' . ResponseError::ERROR_508, locale: $this->language) . ' | ' . $e->getMessage()
            );
        }
    }

    /* ============================================================
     |            COOKING TIME — ƏLAVƏ ACTION-LAR                 |
     ============================================================ */

    /**
     * Seller order-i qəbul edir: status=COOKING + gecikdirilmiş job (READY).
     * Body: { "prep_minutes": 1|5|10|15|20|30 }
     */
    public function startCooking(int $orderId, Request $request): JsonResponse
    {
        $minutes = (int)$request->input('prep_minutes');
        if (!in_array($minutes, [1, 5, 10, 15, 20, 30], true)) {
            return response()->json(['message' => 'Invalid minutes'], 422);
        }

        /** @var Order $order */
        $order = Order::query()
            ->where('shop_id', $this->shop->id)
            ->whereIn('status', ['new', 'accepted'])
            ->find($orderId);

        if (!$order) {
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_404,
                'message' => __('errors.' . ResponseError::ORDER_NOT_FOUND, locale: $this->language)
            ]);
        }

        $order->status               = 'cooking';
        $order->cooking_minutes      = $minutes;
        $order->cooking_started_at   = now();
        $order->cooking_deadline_at  = now()->addMinutes($minutes);
        $order->save();

        // Avtomatik READY üçün gecikdirilmiş job
        AutoReadyOrderJob::dispatch($order->id)->delay($order->cooking_deadline_at);

        // İstəyə görə user-ə “hazırlanır” push
        if (method_exists($this, 'sendNotification') && $order->user_id) {
            $this->sendNotification(
                receivers: [$order->user_id],
                message: __('Your order is being prepared'),
                title: __('Order #:id', ['id' => $order->id]),
                data: ['type' => 'order_cooking', 'order_id' => (string)$order->id, 'role' => 'user']
            );
        }

        Log::info('startCooking', ['order_id' => $order->id, 'minutes' => $minutes]);

        return $this->successResponse(__('errors.' . ResponseError::SUCCESS, locale: $this->language), [
            'order'    => $this->orderRepository->reDataOrder($order),
            'deadline' => optional($order->cooking_deadline_at)->toIso8601String(),
        ]);
    }

    /**
     * Seller cooking vaxtını dəyişir: yeni deadline + yeni gecikdirilmiş job.
     * Body: { "prep_minutes": 1|5|10|15|20|30 }
     */
    public function changeCookingTime(int $orderId, Request $request): JsonResponse
    {
        $minutes = (int)$request->input('prep_minutes');
        if (!in_array($minutes, [1, 5, 10, 15, 20, 30], true)) {
            return response()->json(['message' => 'Invalid minutes'], 422);
        }

        /** @var Order $order */
        $order = Order::query()
            ->where('shop_id', $this->shop->id)
            ->where('status', 'cooking')
            ->find($orderId);

        if (!$order) {
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_404,
                'message' => __('errors.' . ResponseError::ORDER_NOT_FOUND, locale: $this->language)
            ]);
        }

        $order->cooking_minutes     = $minutes;
        $order->cooking_deadline_at = now()->addMinutes($minutes);
        $order->save();

        // Yenilənmiş deadline üçün yeni job
        AutoReadyOrderJob::dispatch($order->id)->delay($order->cooking_deadline_at);

        Log::info('changeCookingTime', ['order_id' => $order->id, 'minutes' => $minutes]);

        return $this->successResponse(__('errors.' . ResponseError::SUCCESS, locale: $this->language), [
            'order'    => $this->orderRepository->reDataOrder($order),
            'deadline' => optional($order->cooking_deadline_at)->toIso8601String(),
        ]);
    }
}
