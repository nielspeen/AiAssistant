@extends('layouts.app')

@section('title', __('AI Assistant Documentation'))

@section('content')
<div class="container">
    <div class="flexy-container">
        <div class="flexy-item">
            <span class="heading">{{ __('AI Assistant Documentation') }}</span>
        </div>
        <div class="flexy-item margin-left">
            <a href="{{ route('aiassistant.documents.create') }}" class="btn btn-bordered">{{ __('Add Documentation URL') }}</a>
        </div>
        <div class="flexy-item margin-left">
            <form method="POST" action="{{ route('aiassistant.documents.index_all') }}" style="display:inline">
                {{ csrf_field() }}
                <button type="submit" class="btn btn-primary">{{ __('Index Pending/Changed') }}</button>
            </form>
        </div>
        <div class="flexy-item margin-left">
            <form method="POST" action="{{ route('aiassistant.documents.index_all') }}" style="display:inline" onsubmit="return confirm('{{ __('Force reindex all documentation? This recreates embeddings for every enabled document.') }}');">
                {{ csrf_field() }}
                <input type="hidden" name="force" value="1">
                <button type="submit" class="btn btn-bordered">{{ __('Force Reindex All') }}</button>
            </form>
        </div>
        <div class="flexy-block"></div>
    </div>

    @include('partials/flash_messages')

    <div class="panel panel-default margin-top">
        <div class="panel-heading">
            <strong>{{ __('Bulk Add URLs') }}</strong>
        </div>
        <div class="panel-body">
            <form class="form-horizontal" method="POST" action="{{ route('aiassistant.documents.bulk_store') }}">
                {{ csrf_field() }}

                <div class="form-group{{ $errors->has('mailbox_id') ? ' has-error' : '' }}">
                    <label for="bulk_mailbox_id" class="col-sm-2 control-label">{{ __('Mailbox') }}</label>
                    <div class="col-sm-6">
                        <select id="bulk_mailbox_id" name="mailbox_id" class="form-control input-sized" required>
                            @foreach ($mailboxes as $mailbox)
                                <option value="{{ $mailbox->id }}" {{ old('mailbox_id') == $mailbox->id ? 'selected' : '' }}>{{ $mailbox->name }}</option>
                            @endforeach
                        </select>
                        @include('partials/field_error', ['field' => 'mailbox_id'])
                    </div>
                </div>

                <div class="form-group{{ $errors->has('urls') ? ' has-error' : '' }}">
                    <label for="bulk_urls" class="col-sm-2 control-label">{{ __('URLs') }}</label>
                    <div class="col-sm-8">
                        <textarea id="bulk_urls" name="urls" class="form-control" rows="6" placeholder="https://example.com/en/docs/article&#10;https://example.com/en/docs/another-article">{{ old('urls', '') }}</textarea>
                        <div class="form-help">{{ __('Paste one English documentation URL per line. Existing URLs for the selected mailbox are refreshed.') }}</div>
                        @include('partials/field_error', ['field' => 'urls'])
                    </div>
                </div>

                <div class="form-group margin-bottom-0">
                    <div class="col-sm-8 col-sm-offset-2">
                        <button type="submit" class="btn btn-primary">{{ __('Add URLs') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="panel panel-default margin-top">
        <div class="panel-heading">
            <strong>{{ __('Documentation API') }}</strong>
        </div>
        <div class="panel-body">
            @if (!$apiKeyStorageReady)
                <div class="alert alert-warning">
                    {{ __('Documentation API key storage is not ready. Run module migrations before generating mailbox API keys.') }}
                </div>
            @endif

            <p class="text-help">
                {{ __('Generate a mailbox-specific key for websites or build jobs that need to push Markdown directly into this mailbox documentation index.') }}
            </p>

            <div class="table-responsive">
                <table class="table table-condensed margin-bottom-0">
                    <thead>
                        <tr>
                            <th>{{ __('Mailbox') }}</th>
                            <th>{{ __('Key') }}</th>
                            <th>{{ __('Last Used') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mailboxes as $mailbox)
                            @php
                                $apiKey = $apiKeys->get($mailbox->id);
                            @endphp
                            <tr>
                                <td>{{ $mailbox->name }}</td>
                                <td>
                                    @if ($apiKey)
                                        <code>{{ $apiKey->key_preview }}</code>
                                    @else
                                        <span class="text-help">{{ __('Not generated') }}</span>
                                    @endif
                                </td>
                                <td>{{ $apiKey && $apiKey->last_used_at ? $apiKey->last_used_at->format('Y-m-d H:i') : '-' }}</td>
                                <td class="text-right">
                                    @if ($apiKeyStorageReady)
                                        <form method="POST" action="{{ route('aiassistant.documents.api_keys.issue', ['mailbox_id' => $mailbox->id]) }}" style="display:inline" onsubmit="return confirm('{{ $apiKey ? __('Regenerate this documentation API key? Existing integrations using the old key will stop working.') : __('Generate a documentation API key for this mailbox?') }}');">
                                            {{ csrf_field() }}
                                            <button type="submit" class="btn btn-xs btn-default">{{ $apiKey ? __('Regenerate') : __('Generate') }}</button>
                                        </form>
                                        @if ($apiKey)
                                            <form method="POST" action="{{ route('aiassistant.documents.api_keys.revoke', ['mailbox_id' => $mailbox->id]) }}" style="display:inline" onsubmit="return confirm('{{ __('Revoke this documentation API key? Existing integrations using it will stop working.') }}');">
                                                {{ csrf_field() }}
                                                <button type="submit" class="btn btn-xs btn-link text-danger">{{ __('Revoke') }}</button>
                                            </form>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="margin-top">
                <div class="form-help">{{ __('Endpoint') }}: <code>POST {{ route('aiassistant.api.documents.store') }}</code></div>
                <pre class="margin-top-5">curl -X POST "{{ route('aiassistant.api.documents.store') }}" \
  -H "Authorization: Bearer YOUR_MAILBOX_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "identifier": "setup/android",
    "content": "# Android setup\n\nMarkdown content...",
    "public_url": "https://docs.example.com/en/setup/android",
    "localized_urls": {
      "en": "https://docs.example.com/en/setup/android",
      "ja": "https://docs.example.com/ja/setup/android",
      "zh": "https://docs.example.com/zh/setup/android",
      "ko": "https://docs.example.com/ko/setup/android"
    }
  }'</pre>
            </div>
        </div>
    </div>

    <div class="panel panel-default margin-top">
        <div class="panel-body">
            @if ($documents->count())
                <div class="table-responsive">
                    <table class="table table-condensed table-striped margin-bottom-0">
                        <thead>
                            <tr>
                                <th>{{ __('Title') }}</th>
                                <th>{{ __('Mailbox') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Chunks') }}</th>
                                <th>{{ __('Last Indexed') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($documents as $document)
                                @php
                                    $metadata = $document->metadata();
                                    $isPrivateApiDocument = $document->source_type == \Modules\AiAssistant\Models\Document::SOURCE_TYPE_API && strpos($document->source_url, 'api://') === 0;
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $document->title }}</strong>
                                        @if (!$document->enabled)
                                            <span class="label label-default">{{ __('Disabled') }}</span>
                                        @endif
                                        <br>
                                        @if ($isPrivateApiDocument)
                                            <span class="text-help">{{ __('API document') }}: {{ $metadata['api_identifier'] ?? $document->source_url }}</span>
                                        @else
                                            <a href="{{ $document->source_url }}" target="_blank" rel="noopener">{{ $document->source_url }}</a>
                                            @if ($document->source_type == \Modules\AiAssistant\Models\Document::SOURCE_TYPE_API)
                                                <span class="label label-info">{{ __('API') }}</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td>{{ $document->mailbox ? $document->mailbox->name : '#' . $document->mailbox_id }}</td>
                                    <td>
                                        <span class="label label-{{ $document->status == 'failed' ? 'danger' : ($document->status == 'indexed' ? 'success' : 'default') }}">
                                            {{ ucfirst($document->status) }}
                                        </span>
                                        @if ($document->last_error)
                                            <div class="text-danger margin-top-5">{{ $document->last_error }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $document->chunks_count }}</td>
                                    <td>{{ $document->last_indexed_at ? $document->last_indexed_at->format('Y-m-d H:i') : '-' }}</td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('aiassistant.documents.index_one', ['id' => $document->id]) }}" style="display:inline">
                                            {{ csrf_field() }}
                                            <button type="submit" class="btn btn-xs btn-primary">{{ __('Reindex') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('aiassistant.documents.index_one', ['id' => $document->id]) }}" style="display:inline" onsubmit="return confirm('{{ __('Force reindex this documentation item? This recreates embeddings even if the content is unchanged.') }}');">
                                            {{ csrf_field() }}
                                            <input type="hidden" name="force" value="1">
                                            <button type="submit" class="btn btn-xs btn-default">{{ __('Force') }}</button>
                                        </form>
                                        @if ($document->source_type == \Modules\AiAssistant\Models\Document::SOURCE_TYPE_URL)
                                            <a href="{{ route('aiassistant.documents.edit', ['id' => $document->id]) }}" class="btn btn-xs btn-default">{{ __('Edit') }}</a>
                                        @endif
                                        <form method="POST" action="{{ route('aiassistant.documents.destroy', ['id' => $document->id]) }}" style="display:inline" onsubmit="return confirm('{{ __('Delete this documentation item?') }}');">
                                            {{ csrf_field() }}
                                            <button type="submit" class="btn btn-xs btn-link text-danger">{{ __('Delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-help margin-bottom-0">{{ __('No documentation has been added yet.') }}</p>
            @endif
        </div>
    </div>

    <p>
        <a href="{{ route('settings', ['section' => 'aiassistant']) }}" class="btn btn-link">&larr; {{ __('Back to AI Assistant Settings') }}</a>
    </p>
</div>
@endsection
