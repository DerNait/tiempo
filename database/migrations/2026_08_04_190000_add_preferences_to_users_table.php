<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->default('America/Guatemala')->after('password');
            $table->unsignedTinyInteger('week_starts_on')->default(1)->after('timezone');
            $table->boolean('audit_mode_enabled')->default(false)->after('week_starts_on');
            $table->timestamp('audit_started_at')->nullable()->after('audit_mode_enabled');
            $table->unsignedSmallInteger('audit_days')->default(7)->after('audit_started_at');
            $table->timestamp('onboarded_at')->nullable()->after('audit_days');
            $table->string('accent_color', 16)->default('#a855f7')->after('onboarded_at');
            $table->foreignId('rainmeter_priority_category_id')->nullable()->after('accent_color');
            $table->foreignId('rainmeter_leak_category_id')->nullable()->after('rainmeter_priority_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'timezone',
                'week_starts_on',
                'audit_mode_enabled',
                'audit_started_at',
                'audit_days',
                'onboarded_at',
                'accent_color',
                'rainmeter_priority_category_id',
                'rainmeter_leak_category_id',
            ]);
        });
    }
};
