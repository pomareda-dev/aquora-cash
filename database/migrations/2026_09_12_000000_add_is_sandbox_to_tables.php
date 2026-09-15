<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->boolean('is_sandbox')->default(false)->after('is_projected');
            $table->index(['user_id', 'is_sandbox']);
        });

        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->boolean('is_sandbox')->default(false)->after('active');
            $table->index(['user_id', 'is_sandbox']);
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->boolean('is_sandbox')->default(false)->after('closed_at');
            $table->index(['user_id', 'is_sandbox']);
        });

        Schema::table('goal_contributions', function (Blueprint $table) {
            $table->boolean('is_sandbox')->default(false)->after('notes');
            $table->index(['goal_id', 'is_sandbox']);
        });
    }

    public function down(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_sandbox']);
            $table->dropColumn('is_sandbox');
        });

        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_sandbox']);
            $table->dropColumn('is_sandbox');
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_sandbox']);
            $table->dropColumn('is_sandbox');
        });

        Schema::table('goal_contributions', function (Blueprint $table) {
            $table->dropIndex(['goal_id', 'is_sandbox']);
            $table->dropColumn('is_sandbox');
        });
    }
};
