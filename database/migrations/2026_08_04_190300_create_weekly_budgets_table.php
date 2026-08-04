<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->string('budget_type', 16)->default('reference');
            $table->unsignedInteger('target_minutes')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'category_id', 'week_start']);
            $table->index(['user_id', 'week_start']);
        });

        Schema::create('budget_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('budget_type', 16)->default('reference');
            $table->unsignedInteger('target_minutes')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_templates');
        Schema::dropIfExists('weekly_budgets');
    }
};
