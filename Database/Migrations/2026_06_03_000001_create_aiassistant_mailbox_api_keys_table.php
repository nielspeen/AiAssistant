<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAiassistantMailboxApiKeysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('aiassistant_mailbox_api_keys', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('mailbox_id');
            $table->string('key_hash', 64)->unique();
            $table->string('key_preview', 32)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('mailbox_id');
            $table->index('enabled');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('aiassistant_mailbox_api_keys');
    }
}
