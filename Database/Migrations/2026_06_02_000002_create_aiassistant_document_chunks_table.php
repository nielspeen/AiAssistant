<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAiassistantDocumentChunksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('aiassistant_document_chunks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('document_id');
            $table->unsignedInteger('chunk_index');
            $table->longText('content');
            $table->string('content_hash', 64)->nullable();
            $table->unsignedInteger('token_count')->nullable();
            $table->longText('embedding')->nullable();
            $table->string('embedding_model')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();

            $table->index('document_id');
            $table->index('content_hash');
            $table->index('embedding_model');
            $table->index(['document_id', 'chunk_index']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('aiassistant_document_chunks');
    }
}
