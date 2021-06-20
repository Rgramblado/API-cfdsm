<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCurrentOperationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('current_operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('market_id');
            $table->timestamp('init_time')->nullable();
            $table->boolean('is_long');
            $table->integer('leverage');
            $table->decimal('margin', 20, 10);
            $table->decimal('weight', 20, 10);
            $table->decimal('entry_price', 20, 10);
            $table->decimal('liquidation_price', 20, 10);
            $table->string('limit_price')->nullable();
            $table->timestamps();
        });

       
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('current_operations');
    }
}
