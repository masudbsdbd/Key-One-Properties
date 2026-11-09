<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type', 20)->default('staff')->index();
        });

        foreach (['investor' => 'investors', 'customer' => 'customers'] as $type => $table) {
            Schema::create("{$type}_user_links", function (Blueprint $t) use ($type, $table) {
                $t->id();
                $t->char('public_id', 16)->unique();
                $t->foreignId('user_id')->constrained();
                $t->foreignId("{$type}_id")->constrained($table);
                $t->boolean('is_active')->default(true);
                $t->timestamp('verified_at')->nullable();
                $t->foreignId('verified_by')->nullable()->constrained('users');
                $t->timestamp('revoked_at')->nullable();
                $t->foreignId('revoked_by')->nullable()->constrained('users');
                $t->foreignId('created_by')->constrained('users');
                $t->timestamps();

                $t->unique(['user_id', "{$type}_id"]);
                $t->index(["{$type}_id", 'is_active']);
            });
        }

        Schema::create('portal_access_grants', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('portal_type', 20);
            $table->string('resource_type', 20);
            $table->unsignedBigInteger('resource_id');
            $table->string('capability', 80);
            $table->foreignId('granted_by')->constrained('users');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['user_id', 'portal_type', 'resource_type', 'resource_id'], 'pag_user_resource_idx');
            $table->index(['capability', 'revoked_at']);
        });

        Schema::create('portal_access_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('portal_type', 20);
            $table->string('action', 40);
            $table->string('resource_type', 30)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('result', 20);
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->boolean('portal_visible')->default(false)->after('file_size');
        });
    }

    public function down(): void
    {
        Schema::table('attachments', fn (Blueprint $table) => $table->dropColumn('portal_visible'));
        Schema::dropIfExists('portal_access_audits');
        Schema::dropIfExists('portal_access_grants');
        Schema::dropIfExists('customer_user_links');
        Schema::dropIfExists('investor_user_links');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_type']);
            $table->dropColumn('account_type');
        });
    }
};
