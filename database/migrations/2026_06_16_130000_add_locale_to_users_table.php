<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Per-user UI locale as an ISO 639-1 code (e.g. en, de); null = fall back to the
            // global language setting / app default. Regional variants (de-CH) are out of scope.
            $table->string('locale', 2)->nullable()->after('last_login_source');
        });
    }
};
