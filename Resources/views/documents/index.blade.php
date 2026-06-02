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
                        <textarea id="bulk_urls" name="urls" class="form-control" rows="6" placeholder="https://example.com/en/docs/article&#10;https://example.com/en/docs/another-article">{{ old('urls') }}</textarea>
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
                                <tr>
                                    <td>
                                        <strong>{{ $document->title }}</strong>
                                        @if (!$document->enabled)
                                            <span class="label label-default">{{ __('Disabled') }}</span>
                                        @endif
                                        <br>
                                        <a href="{{ $document->source_url }}" target="_blank" rel="noopener">{{ $document->source_url }}</a>
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
                                        <form method="POST" action="{{ route('aiassistant.documents.index_one', ['id' => $document->id]) }}" style="display:inline" onsubmit="return confirm('{{ __('Force reindex this documentation URL? This recreates embeddings even if the content is unchanged.') }}');">
                                            {{ csrf_field() }}
                                            <input type="hidden" name="force" value="1">
                                            <button type="submit" class="btn btn-xs btn-default">{{ __('Force') }}</button>
                                        </form>
                                        <a href="{{ route('aiassistant.documents.edit', ['id' => $document->id]) }}" class="btn btn-xs btn-default">{{ __('Edit') }}</a>
                                        <form method="POST" action="{{ route('aiassistant.documents.destroy', ['id' => $document->id]) }}" style="display:inline" onsubmit="return confirm('{{ __('Delete this documentation URL?') }}');">
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
                <p class="text-help margin-bottom-0">{{ __('No documentation URLs have been added yet.') }}</p>
            @endif
        </div>
    </div>

    <p>
        <a href="{{ route('settings', ['section' => 'aiassistant']) }}" class="btn btn-link">&larr; {{ __('Back to AI Assistant Settings') }}</a>
    </p>
</div>
@endsection
