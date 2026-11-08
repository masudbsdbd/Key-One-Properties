<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['project_id', 'code']);
        });

        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('floor_number');
            $table->string('label', 60);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['building_id', 'floor_number']);
        });

        Schema::create('flats', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('building_id')->constrained();
            $table->foreignId('floor_id')->constrained();
            $table->string('flat_no', 20);
            $table->string('flat_type', 40)->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->unsignedTinyInteger('balconies')->nullable();
            $table->string('facing', 20)->nullable();
            $table->decimal('saleable_area', 12, 2);
            $table->string('area_unit', 10)->default('sqft');
            $table->decimal('list_price', 18, 2)->nullable();
            $table->string('status', 20)->default('available');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['building_id', 'flat_no']);
            $table->index(['project_id', 'status']);
            $table->index(['floor_id', 'status']);
        });

        Schema::create('flat_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_price', 18, 2)->nullable();
            $table->decimal('new_price', 18, 2)->nullable();
            $table->string('reason', 255);
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('effective_at');
            $table->timestamps();

            $table->index(['flat_id', 'effective_at']);
        });

        Schema::create('flat_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('reason', 255)->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('changed_at');

            $table->index(['flat_id', 'changed_at']);
        });

        Schema::create('flat_media', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flat_media');
        Schema::dropIfExists('flat_status_history');
        Schema::dropIfExists('flat_price_history');
        Schema::dropIfExists('flats');
        Schema::dropIfExists('floors');
        Schema::dropIfExists('buildings');
    }
};
