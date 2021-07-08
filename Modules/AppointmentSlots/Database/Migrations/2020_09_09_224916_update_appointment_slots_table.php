<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateAppointmentSlotsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('appointment_slots', function (Blueprint $table) {
            $table->string('recurrence')->default('none')->after('end_time');
            $table->bigInteger('parent_appointment_slot_id')->nullable()->unsigned()->after('reserved_for_type');
            $table->foreign('parent_appointment_slot_id')->references('id')->on('appointment_slots')->onDelete('cascade');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('appointment_slots', function (Blueprint $table) {
            $table->dropForeign(['appointment_slots_parent_appointment_slot_id_foreign']);
            $table->dropColumn(['recurrence', 'parent_appointment_slot_id', 'deleted_at']);
        });
    }
}
