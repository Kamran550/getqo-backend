<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'cooking_minutes')) {
                $table->unsignedSmallInteger('cooking_minutes')->nullable()->after('status');
            }
            if (!Schema::hasColumn('orders', 'cooking_started_at')) {
                $table->dateTime('cooking_started_at')->nullable()->after('cooking_minutes');
            }
            if (!Schema::hasColumn('orders', 'cooking_deadline_at')) {
                $table->dateTime('cooking_deadline_at')->nullable()->after('cooking_started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'cooking_minutes')) {
                $table->dropColumn('cooking_minutes');
            }
            if (Schema::hasColumn('orders', 'cooking_started_at')) {
                $table->dropColumn('cooking_started_at');
            }
            if (Schema::hasColumn('orders', 'cooking_deadline_at')) {
                $table->dropColumn('cooking_deadline_at');
            }
        });
    }
};
