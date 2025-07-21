<?php

namespace App\Repositories\BenefitsRepository;

use App\Models\Benefit;
use App\Models\SmsPayload;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BenefitsRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return Benefit::class;
    }

    /**
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function paginate(array $data = []): LengthAwarePaginator
    {
        $model = $this->model();

        return $model->when(isset($data['deleted_at']), fn($q) => $q->onlyTrashed())
            ->paginate(data_get($data, 'perPage', 10));
    }

    /**
     * @param string $smsType
     * @return Builder|Model|null
     */
    public function show(string $smsType): Builder|Model|null
    {
        return $this->model()->where('type', $smsType)->first();
    }
}
