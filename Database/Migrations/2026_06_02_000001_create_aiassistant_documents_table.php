<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAiassistantDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('aiassistant_documents', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('mailbox_id');
            $table->string('title');
            $table->string('source_type', 50)->default('url');
            $table->string('source_url', 2048);
            $table->string('source_identifier')->nullable();
            $table->string('canonical_locale', 10)->default('en');
            $table->longText('localized_urls')->nullable();
            $table->longText('content')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->string('status', 50)->default('pending');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_indexed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();

            $table->index('mailbox_id');
            $table->index('source_type');
            $table->index('source_identifier');
            $table->index('content_hash');
            $table->index('status');
            $table->index('enabled');
            $table->index('last_indexed_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('aiassistant_documents');
    }
}
