<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('verification_request_source_overrides')) {
            return;
        }

        Schema::create('verification_request_source_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('verification_request_id');
            $table->string('source', 20);
            $table->timestamps();

            $table->unique('verification_request_id', 'vr_source_overrides_request_unique');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_request_source_overrides');
    }
};
