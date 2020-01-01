<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAffiliationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('affiliations', function (Blueprint $table) {
            $table->bigInteger('role_id')->unsigned();
            $table->bigInteger('character_id')->nullable();
            $table->bigInteger('corporation_id')->nullable();
            $table->bigInteger('alliance_id')->nullable();
            $table->enum('type', ['allowed','inverse', 'forbidden']);
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('affiliations');
    }
}
