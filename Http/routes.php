<?php

Route::group([
    'middleware' => ['open'],
], function () {
    Route::post('/ai-assistant/api/documents', 'Modules\AiAssistant\Http\Controllers\ApiDocumentController@store')->name('aiassistant.api.documents.store');
});

Route::group([
    'middleware' => ['web', 'auth'],
], function () {
    Route::post('/ai-assistant/conversations/{id}/draft-reply', 'Modules\AiAssistant\Http\Controllers\DraftReplyController@store')->name('aiassistant.conversations.draft_reply');
});

Route::group([
    'middleware' => ['web', 'auth', 'roles'],
    'roles' => ['admin'],
], function () {
    Route::post('/ai-assistant/customer-context/test', 'Modules\AiAssistant\Http\Controllers\CustomerContextController@test')->name('aiassistant.customer_context.test');
    Route::post('/ai-assistant/customer-context/{mailbox_id}', 'Modules\AiAssistant\Http\Controllers\CustomerContextController@update')->name('aiassistant.customer_context.update');
    Route::get('/ai-assistant/documents', 'Modules\AiAssistant\Http\Controllers\DocumentController@index')->name('aiassistant.documents');
    Route::post('/ai-assistant/documents/index', 'Modules\AiAssistant\Http\Controllers\DocumentController@indexAll')->name('aiassistant.documents.index_all');
    Route::post('/ai-assistant/documents/bulk', 'Modules\AiAssistant\Http\Controllers\DocumentController@bulkStore')->name('aiassistant.documents.bulk_store');
    Route::post('/ai-assistant/documents/api-keys/{mailbox_id}', 'Modules\AiAssistant\Http\Controllers\DocumentController@issueApiKey')->name('aiassistant.documents.api_keys.issue');
    Route::post('/ai-assistant/documents/api-keys/{mailbox_id}/revoke', 'Modules\AiAssistant\Http\Controllers\DocumentController@revokeApiKey')->name('aiassistant.documents.api_keys.revoke');
    Route::get('/ai-assistant/documents/create', 'Modules\AiAssistant\Http\Controllers\DocumentController@create')->name('aiassistant.documents.create');
    Route::post('/ai-assistant/documents', 'Modules\AiAssistant\Http\Controllers\DocumentController@store')->name('aiassistant.documents.store');
    Route::get('/ai-assistant/documents/{id}/edit', 'Modules\AiAssistant\Http\Controllers\DocumentController@edit')->name('aiassistant.documents.edit');
    Route::post('/ai-assistant/documents/{id}', 'Modules\AiAssistant\Http\Controllers\DocumentController@update')->name('aiassistant.documents.update');
    Route::post('/ai-assistant/documents/{id}/index', 'Modules\AiAssistant\Http\Controllers\DocumentController@indexOne')->name('aiassistant.documents.index_one');
    Route::post('/ai-assistant/documents/{id}/delete', 'Modules\AiAssistant\Http\Controllers\DocumentController@destroy')->name('aiassistant.documents.destroy');
});
