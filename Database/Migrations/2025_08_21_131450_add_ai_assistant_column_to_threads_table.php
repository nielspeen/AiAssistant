<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAiAssistantColumnToThreadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->text('ai_assistant')->nullable();
            $table->timestamp('ai_assistant_updated_at')->nullable();

            $table->index('ai_assistant_updated_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropColumn('ai_assistant');
            $table->dropColumn('ai_assistant_updated_at');

            $table->dropIndex('ai_assistant_updated_at');
        });
    }
}
