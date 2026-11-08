<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('customer_code', 20)->unique();
            $table->string('full_name', 160);
            $table->string('email')->nullable();
            $table->string('primary_mobile', 20)->unique();
            $table->string('alternate_mobile', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('nid_or_passport')->nullable();
            $table->string('occupation', 120)->nullable();
            $table->string('status', 20)->default('active');
            $table->string('source', 40)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('full_name');
            $table->index('alternate_mobile');
            $table->index(['status', 'deleted_at']);
            $table->index('created_by');
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('address_type', 20)->default('present');
            $table->string('address_line');
            $table->string('city', 80)->nullable();
            $table->string('district', 80)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 80)->default('Bangladesh');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('visibility', 20)->default('internal');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_documents', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('original_name');
            $table->string('disk', 30);
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_documents');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
