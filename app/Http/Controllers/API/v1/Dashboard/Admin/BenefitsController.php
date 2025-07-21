<?php

namespace App\Http\Controllers\API\v1\Dashboard\Admin;

use App\Helpers\ResponseError;
use App\Http\Controllers\Controller;
use App\Http\Requests\Benefit\StoreRequest;
use App\Http\Requests\Benefit\UpdateRequest;
use App\Repositories\BenefitsRepository\BenefitsRepository;
use App\Repositories\SmsPayloadRepository\SmsPayloadRepository;
use App\Services\BenefitsService\BenefitsService;
use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\BenefitsResource;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BenefitsController extends Controller
{
    use ApiResponse;

    public function __construct(private BenefitsService $service, private BenefitsRepository $repository)
    {
        parent::__construct();
    }


    public function index(FilterParamsRequest $request): AnonymousResourceCollection
    {
        Log::info('benefit index');
        $model = $this->repository->paginate($request->all());

        $data = BenefitsResource::collection($model);

        Log::info('data:', ['data:', $data]);
        return BenefitsResource::collection($model);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        Log::info('1111111111111111111111111111111');

        $validated = $request->validated();


        LOg::info('validate:', ['val:', $validated]);


        $result = $this->service->create($validated);


        LOg::info('result:', ['result:', $result]);


        if (!data_get($result, 'status')) {
            return data_get($result, 'params') ?
                $this->requestErrorResponse(
                    data_get($result, 'status'),
                    data_get($result, 'code'),
                    data_get($result, 'params'),
                    data_get($result, 'http', 422),
                )
                : $this->onErrorResponse($result);
        }
        Log::info('succese gedir');

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_CREATED, locale: $this->language),
            BenefitsResource::make(data_get($result, 'data'))
        );
    }


    public function show(string $smsType): JsonResponse
    {
        Log::info('benefit show');
        $model = $this->repository->show($smsType);
        Log::info('model:', ['model:', $model]);

        if (empty($model)) {
            Log::info('ifff');
            return $this->onErrorResponse([
                'code'      => ResponseError::ERROR_404,
                'message'   => __('errors.' . ResponseError::ERROR_404, locale: $this->language)
            ]);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::SUCCESS, locale: $this->language),
            BenefitsResource::make($model)
        );
    }
    public function update(string $benefitType, UpdateRequest $request): JsonResponse
    {
        Log::info('update benefit',);
        $validated = $request->validated();
        Log::info('val:', ['val:', $validated]);

        $result = $this->service->update($benefitType, $validated);

        if (!data_get($result, 'status')) {
            return data_get($result, 'params') ?
                $this->requestErrorResponse(
                    data_get($result, 'status'),
                    data_get($result, 'code'),
                    data_get($result, 'params'),
                    data_get($result, 'http', 422),
                )
                : $this->onErrorResponse($result);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_UPDATED, locale: $this->language),
            BenefitsResource::make(data_get($result, 'data'))
        );
    }

    public function destroy(FilterParamsRequest $request): JsonResponse
    {
        $result = $this->service->delete($request->input('ids', []));

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse([
                'code'      => ResponseError::ERROR_404,
                'message'   => __('errors.' . ResponseError::ERROR_404, locale: $this->language)
            ]);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_DELETED, locale: $this->language)
        );
    }


    public function dropAll(): JsonResponse
    {
        $this->service->dropAll();

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_DELETED, locale: $this->language)
        );
    }

    /**
     * @return JsonResponse
     */
    public function truncate(): JsonResponse
    {
        $this->service->truncate();

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_DELETED, locale: $this->language)
        );
    }

    public function restoreAll(): JsonResponse
    {
        $this->service->restoreAll();

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_DELETED, locale: $this->language)
        );
    }
}
