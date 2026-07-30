<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliations', function (Blueprint $table): void {
            // AffiliationResolver filters affiliations by role and morph type on every permission
            // check and enumeration; the table had no index. `type` is deliberately omitted — it is
            // a native enum compared via `type::text = ?`, which a btree on the column can't serve,
            // and it only has three low-selectivity values anyway.
            $table->index(['role_id', 'affiliatable_type', 'affiliatable_id'], 'affiliations_resolver_index');
        });
    }
};
