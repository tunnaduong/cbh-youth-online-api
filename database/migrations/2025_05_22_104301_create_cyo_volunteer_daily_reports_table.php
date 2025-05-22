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
        Schema::create('cyo_volunteer_daily_reports', function (Blueprint $table) {
            $table->integer('id', true);
            $table->unsignedBigInteger('class_id')->nullable()->index('fk_class_id_v');
            $table->unsignedBigInteger('volunteer_id')->nullable()->index('fk_volunteer_id');
            $table->integer('absent')->nullable();
            $table->boolean('cleanliness')->nullable()->comment('1=sạch, 0=bẩn');
            $table->boolean('uniform')->nullable()->comment('1=đủ, 0=thiếu');
            $table->unsignedBigInteger('mistake_id')->nullable()->index('fk_mistake_id');
            $table->mediumText('note')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cyo_volunteer_daily_reports');
    }
};
