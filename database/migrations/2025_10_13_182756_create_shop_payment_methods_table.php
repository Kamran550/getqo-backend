<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShopPaymentMethodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_payment_methods', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('shop_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            
            $table->foreignId('payment_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Unique constraint - bir shop eyni payment-i 1 dəfə əlavə edə bilər
            $table->unique(['shop_id', 'payment_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shop_payment_methods');
    }
}
