<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seo_data', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');

            $table->string('title')->nullable();
            $table->text('description')->nullable();

            $table->string('keywords')->nullable();
            $table->text('image_alt')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('robots')->nullable();

            $table->string('keyword')->nullable();
            $table->string('generated_keyword')->nullable();
            $table->unsignedTinyInteger('keyword_score')->nullable();

            $table->timestamps();
            $table->unique(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_data');
    }
};
