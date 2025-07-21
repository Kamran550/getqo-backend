<?php

namespace App\Services\CouponService;

use App\Helpers\ResponseError;
use App\Models\Coupon;
use App\Models\Shop;
use App\Services\CoreService;
use App\Traits\SetTranslations;
use Exception;
use Illuminate\Support\Facades\Log;

class CouponService extends CoreService
{
    use SetTranslations;

    protected function getModelClass(): string
    {
        return Coupon::class;
    }

    public function create(array $data): array
    {
        try {
            Log::info('coupon servicedeki data:', ['data:', $data]);
            $coupon = $this->model()->create($data);

            $this->setTranslations($coupon, $data);

            if ($coupon && data_get($data, 'images.0')) {
                $coupon->update(['img' => data_get($data, 'images.0')]);
                $coupon->uploads(data_get($data, 'images'));
            }

            return ['status' => true, 'code' => ResponseError::NO_ERROR, 'data' => $coupon];
        } catch (Exception $e) {
            $this->error($e);
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_502,
                'message' => __('errors.' . $e->getMessage(), locale: $this->language)
            ];
        }
    }

    public function createForAdmin(array $data): array
    {
        try {
            $target_type = data_get($data, 'target_type');

            /** @var \Illuminate\Database\Eloquent\Collection $shops */
            $shopsQuery = Shop::query();

            if ($target_type === 'shop') {
                $shopsQuery->where('type', 'shop');
            } else if ($target_type === 'restaurant') {
                $shopsQuery->where('type', 'restaurant');
            }

            $shops = $shopsQuery->get();

            if ($shops->isEmpty()) {
                return [
                    'status' => false,
                    'code'   => ResponseError::ERROR_404,
                    'message' => __('errors.shops_not_found', locale: $this->language),
                ];
            }

            unset($data['type']);

            $createdCoupons = [];

            foreach ($shops as $shop) {
                $couponData = $data;
                $couponData['shop_id'] = $shop->id;

                // Loglamaq üçün
                Log::info('coupon yaradilir shop üçün:', ['shop_id' => $shop->id, 'data' => $couponData]);

                $result = $this->create($couponData);

                if (data_get($result, 'status')) {
                    $createdCoupons[] = data_get($result, 'data');
                }
            }

            return [
                'status' => true,
                'code'   => ResponseError::NO_ERROR,
                'data'   => $createdCoupons,
            ];
        } catch (Exception $e) {
            $this->error($e);
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_502,
                'message' => __('errors.' . $e->getMessage(), locale: $this->language)
            ];
        }
    }

    /**
     * @param Coupon $coupon
     * @param array $data
     * @return array
     */
    public function update(Coupon $coupon, array $data): array
    {
        try {
            $coupon->update($data);

            $this->setTranslations($coupon, $data);

            if (data_get($data, 'images.0')) {
                $coupon->galleries()->delete();
                $coupon->update(['img' => data_get($data, 'images.0')]);
                $coupon->uploads(data_get($data, 'images'));
            }

            return ['status' => true, 'code' => ResponseError::NO_ERROR, 'data' => $coupon];
        } catch (Exception $e) {
            $this->error($e);
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_502,
                'message' => __('errors.' . ResponseError::ERROR_502, locale: $this->language)
            ];
        }
    }

    public function updateForAdmin(Coupon $coupon, array $data): array
    {
        try {
            $coupons = Coupon::where('name', $coupon->name)->get();
            Log::info('coupons:', ["coupons:", $coupons]);

            foreach ($coupons as $item) {
                $item->update($data);
                Log::info('salam');
                $this->setTranslations($item, $data);
                Log::info('salam2');


                // Şəkil varsa
                if (data_get($data, 'images.0')) {
                    $item->galleries()->delete();
                    $item->update(['img' => data_get($data, 'images.0')]);
                    $item->uploads(data_get($data, 'images'));
                }
            }

            return ['status' => true, 'code' => ResponseError::NO_ERROR, 'data' => $coupons->first()];
        } catch (Exception $e) {
            $this->error($e);
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_502,
                'message' => __('errors.' . ResponseError::ERROR_502, locale: $this->language)
            ];
        }
    }
}
