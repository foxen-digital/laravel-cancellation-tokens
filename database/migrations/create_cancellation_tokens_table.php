<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('cancellation-tokens.table', 'cancellation_tokens'), function (Blueprint $table) {
            $table->id();
            $table->string('token', 255)->unique();
            $table->morphs('tokenable');
            $table->morphs('cancellable');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('cancellation-tokens.table', 'cancellation_tokens'));
    }
};
