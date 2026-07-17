<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('body');
            $table->foreignId('editor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['article_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_versions');
    }
};
