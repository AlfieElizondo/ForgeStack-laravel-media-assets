<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_derivatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('profile', 64);
            $table->string('disk', 64);
            $table->text('storage_path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('size_bytes');
            $table->string('format', 16);
            $table->timestamps();

            $table->unique(['asset_id', 'profile']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_derivatives');
    }
};
