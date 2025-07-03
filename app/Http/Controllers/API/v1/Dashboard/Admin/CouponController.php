<?php

namespace App\Http\Controllers\API\v1\Dashboard\Admin;

use App\Helpers\ResponseError;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coupon\StoreRequest;
use App\Http\Requests\Coupon\StoreRequestForAdmin;
use App\Http\Requests\Coupon\UpdateRequestForAdmin;
use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Repositories\CouponRepository\CouponRepository;
use App\Services\CouponService\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class CouponController extends AdminBaseController
{
    private CouponRepository $couponRepository;
    private CouponService $couponService;

    /**
     * @param CouponRepository $couponRepository
     * @param CouponService $couponService
     */
    public function __construct(CouponRepository $couponRepository, CouponService $couponService)
    {
        parent::__construct();
        $this->couponRepository = $couponRepository;
        $this->couponService    = $couponService;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Log::info('salam index');
        $coupons = $this->couponRepository->couponsList($request->all());

        return CouponResource::collection($coupons);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function paginate(Request $request): AnonymousResourceCollection
    {
        Log::info('paginate');
        $coupons = $this->couponRepository->couponsPaginateForAdmin($request->all());
        Log::info('coupons:', ['coupons:', $coupons]);
        return CouponResource::collection($coupons);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreRequest $request
     * @return JsonResponse
     */
    public function store(StoreRequestForAdmin $request): JsonResponse
    {
        Log::info('admin coupon store', ['store:', $request->validated()]);
        $validated = $request->validated();

        $result = $this->couponService->createForAdmin($validated);
        Log::info('admin coupon result', ['result:', $result]);

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        $coupons = data_get($result, 'data');

        // Bir və ya çoxlu array ayırd et
        if (is_array($coupons) && count($coupons) > 1) {
            $resource = CouponResource::collection(collect($coupons));
        } elseif (is_array($coupons) && count($coupons) === 1) {
            $resource = CouponResource::make($coupons[0]);
        } else {
            // fallback
            $resource = $coupons;
        }

        Log::info('myCouponResource:', ["myCoupon:" => $resource]);

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_CREATED, locale: $this->language),
            $resource
        );
    }

    /**
     * Display the specified resource.
     *
     * @param Coupon $coupon
     * @return JsonResponse
     */
    public function show(Coupon $coupon): JsonResponse
    {
        LOg::info('show');
        $coupon->load([
            'translation' => fn($q) => $q->where('locale', $this->language)->select('id', 'coupon_id', 'locale', 'title'),
            'translations'
        ]);

        return $this->successResponse(
            __('errors.' . ResponseError::SUCCESS, locale: $this->language),
            CouponResource::make($coupon)
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Coupon $coupon
     * @param StoreRequest $request
     * @return JsonResponse
     */
    public function update(Coupon $coupon, UpdateRequestForAdmin $request): JsonResponse
    {
        $validated = $request->validated();
        Log::info('validated:', ["validated:", $validated]);

        $result = $this->couponService->updateForAdmin($coupon, $validated);

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_UPDATED, locale: $this->language),
            CouponResource::make(data_get($result, 'data'))
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param FilterParamsRequest $request
     * @return JsonResponse
     */
    // public function destroy(FilterParamsRequest $request): JsonResponse
    // {
    //     Log::info('destroy');
    //     $coupons = Coupon::whereIn('id', $request->input('ids', []))
    //         ->get();

    //     foreach ($coupons as $coupon) {
    //         $coupon->forceDelete();
    //     }

    //     return $this->successResponse(
    //         __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_DELETED, locale: $this->language)
    //     );
    // }
    public function destroy(FilterParamsRequest $request): JsonResponse
    {
        Log::info('destroy request', $request->all());

        $ids = $request->input('ids', []);
        Log::info(['ids:', ['ids:', $ids]]);

        if (empty($ids)) {
            return $this->onErrorResponse([
                'code' => ResponseError::ERROR_400,
                'message' => __('errors.invalid_request', locale: $this->language)
            ]);
        }

        // 1️⃣ Gələn id-lərin kuponlarını tap
        $coupons = Coupon::whereIn('id', $ids)->get();
        Log::info(['coupons:', ['coupons:', $coupons]]);


        if ($coupons->isEmpty()) {
            return $this->onErrorResponse([
                'code' => ResponseError::ERROR_404,
                'message' => __('errors.not_found', locale: $this->language)
            ]);
        }

        // 2️⃣ Onların *name*-lərini yığ (unikal)
        $names = $coupons->pluck('name')->unique();
        Log::info(['names:', ['names:', $names]]);


        // 3️⃣ Bu adlara uyğun bütün kuponları sil
        Coupon::whereIn('name', $names)->forceDelete();

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_DELETED, locale: $this->language)
        );
    }
}
