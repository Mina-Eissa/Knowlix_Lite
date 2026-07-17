<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attachable_id');
            $table->string('attachable_type');
            $table->foreignId('uploader_id')->constrained('users')->restrictOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index(['attachable_id', 'attachable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
