<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const DEFAULTS = [
        'mason' => 'Mason', 'helper' => 'Helper', 'carpenter' => 'Carpenter', 'rod_binder' => 'Rod Binder',
        'electrician' => 'Electrician', 'plumber' => 'Plumber', 'painter' => 'Painter', 'tiles' => 'Tiles Fitter',
        'welder' => 'Welder', 'other' => 'Other',
    ];

    public function up(): void
    {
        Schema::create('labour_types', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 16)->unique();
            $table->string('code', 40)->unique();
            $table->string('name', 60)->unique();
            $table->decimal('default_rate', 18, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('labour_types')->insert(collect(self::DEFAULTS)->map(fn ($name, $code) => [
            'public_id' => Str::lower(Str::random(16)), 'code' => $code, 'name' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ])->values()->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('labour_types');
    }
};
