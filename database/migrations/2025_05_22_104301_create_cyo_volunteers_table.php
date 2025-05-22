<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cyo_volunteers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index('fk_user_id_volunteers');
            $table->string('full_name');
            $table->enum('gender', ['male', 'female', 'other']);
            $table->date('date_of_birth');
            $table->unsignedBigInteger('class_id')->index('class_id');
            $table->string('contact_number', 20)->nullable();
            $table->string('email')->nullable()->unique('email');
            $table->dateTime('join_date')->nullable()->useCurrent();
            $table->enum('status', ['active', 'inactive', 'archived'])->nullable()->default('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_volunteers');
    }
};
