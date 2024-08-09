<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Seatplus\Auth\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        $this->createTables();

        $this->migrateData();

        $this->dropTables();
    }

    private function createTables(): void
    {
        Schema::create('role_memberships', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('role_id');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->morphs('entity');
            $table->boolean('can_moderate')->default(false);
            $table->string('status')->nullable()->default(null);
            $table->timestamps();
        });

        Schema::table('affiliations', function (Blueprint $table) {
            $table->unsignedInteger('role_id')->change();
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });
    }

    private function migrateData(): void
    {
        DB::table('acl_affiliations')->get()->each(function ($acl_affiliation) {
            DB::table('role_memberships')->insert([
                'role_id' => $acl_affiliation->role_id,
                'entity_type' => $acl_affiliation->affiliatable_type,
                'entity_id' => $acl_affiliation->affiliatable_id,
                'can_moderate' => $acl_affiliation->can_moderate,
                'status' => $acl_affiliation->affiliatable_type === User::class ? 'member' : null,
                'created_at' => $acl_affiliation->created_at,
                'updated_at' => $acl_affiliation->updated_at,
            ]);
        });

        DB::table('acl_members')->get()->each(function ($acl_member) {
            DB::table('role_memberships')->insert([
                'role_id' => $acl_member->role_id,
                'entity_type' => User::class,
                'entity_id' => $acl_member->user_id,
                'can_moderate' => false,
                'status' => $acl_member->status,
                'created_at' => $acl_member->created_at,
                'updated_at' => $acl_member->updated_at,
            ]);
        });
    }

    private function dropTables(): void
    {
        Schema::dropIfExists('acl_affiliations');
        Schema::dropIfExists('acl_members');
    }
};
