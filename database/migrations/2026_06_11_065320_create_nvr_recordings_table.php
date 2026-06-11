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
        Schema::create('nvr_recordings', function (Blueprint $table) {
            $table->id();
            $table->date('recording_date')->index()->comment('Date of the recording');
            $table->tinyInteger('recording_hour')->index()->comment('Hour of day (0-23)');
            $table->unsignedBigInteger('start_time')->comment('Recording start timestamp');
            $table->unsignedBigInteger('end_time')->comment('Recording end timestamp');
            $table->unsignedInteger('duration')->comment('Recording duration in seconds');
            $table->string('process_id', 50)->nullable()->comment('NVR search process ID');
            $table->timestamps();

            $table->unique(['recording_date', 'start_time', 'end_time']);
            $table->index(['recording_date', 'recording_hour']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nvr_recordings');
    }
};
