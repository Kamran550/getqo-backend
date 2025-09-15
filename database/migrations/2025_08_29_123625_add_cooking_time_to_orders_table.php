<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCookingTimeToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('cooking_time')
                ->nullable()
                ->after('status');

            $table->timestamp('cooking_started_at')
                ->nullable()
                ->after('cooking_time');

            $table->timestamp('ready_at')
                ->nullable()
                ->after('cooking_started_at');
            $table->boolean('courier_search_started')
                ->default(false)
                ->after('cooking_started_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn(['cooking_time', 'cooking_started_at', 'ready_at', 'courier_search_started']);
            });
        });
    }
}
