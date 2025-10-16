<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\ShopPaymentMethod
 *
 * @property int $id
 * @property int $shop_id
 * @property int $payment_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Shop|null $shop
 * @property-read Payment|null $payment
 * @method static Builder|ShopPaymentMethod newModelQuery()
 * @method static Builder|ShopPaymentMethod newQuery()
 * @method static Builder|ShopPaymentMethod query()
 * @method static Builder|ShopPaymentMethod whereShopId($value)
 * @method static Builder|ShopPaymentMethod wherePaymentId($value)
 * @method static Builder|ShopPaymentMethod whereCreatedAt($value)
 * @method static Builder|ShopPaymentMethod whereUpdatedAt($value)
 * @method static Builder|ShopPaymentMethod whereDeletedAt($value)
 * @mixin Eloquent
 */
class ShopPaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}

