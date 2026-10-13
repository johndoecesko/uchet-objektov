<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// История изменений (spatie/laravel-activitylog): три миграции пакета в одной
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
            $table->index('log_name');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
