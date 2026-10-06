<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Student and class violation reports sent from the app's "Báo cáo vi phạm"
   * flow, for admins to handle. Separate from cyo_school_mistake_list /
   * cyo_volunteer_daily_reports, which hold the school's own records and have
   * no reporter or status.
   */
  public function up()
  {
    Schema::create('cyo_violation_reports', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('reporter_id');
      $table->string('type', 16); // student | class
      $table->string('subject_name'); // the student's or the class's name
      $table->string('violation_type')->nullable();
      $table->date('report_date')->nullable();
      $table->text('notes')->nullable();
      $table->unsignedInteger('absences')->nullable();
      $table->boolean('cleanliness')->nullable();
      $table->boolean('uniform')->nullable();
      $table->string('status', 16)->default('pending'); // pending | reviewed | resolved | dismissed
      $table->text('admin_notes')->nullable();
      $table->unsignedBigInteger('reviewed_by')->nullable();
      $table->timestamp('reviewed_at')->nullable();
      $table->timestamps();

      $table->foreign('reporter_id')->references('id')->on('cyo_auth_accounts')->cascadeOnDelete();
      $table->foreign('reviewed_by')->references('id')->on('cyo_auth_accounts')->nullOnDelete();
      $table->index(['type', 'status']);
    });
  }

  public function down()
  {
    Schema::dropIfExists('cyo_violation_reports');
  }
};
