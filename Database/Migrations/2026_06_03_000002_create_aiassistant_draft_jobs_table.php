<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAiassistantDraftJobsTable extends Migration
{
    public function up()
    {
        Schema::create('aiassistant_draft_jobs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('conversation_id');
            $table->unsignedInteger('user_id');
            $table->string('status', 50)->default('pending');
            $table->string('locale', 10)->nullable();
            $table->unsignedTinyInteger('document_limit')->default(0);
            $table->longText('result')->nullable();
            $table->string('error_type', 100)->nullable();
            $table->longText('error_message')->nullable();
            $table->longText('error_detail')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('conversation_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('aiassistant_draft_jobs');
    }
}
