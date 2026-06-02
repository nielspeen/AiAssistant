@extends('layouts.app')

@section('title', $mode == 'create' ? __('Add Documentation URL') : __('Edit Documentation URL'))

@section('content')
<div class="container">
    <div class="section-heading">
        {{ $mode == 'create' ? __('Add Documentation URL') : __('Edit Documentation URL') }}
    </div>

    @include('partials/flash_messages')

    <div class="row-container form-container">
        <div class="row">
            <div class="col-xs-12">
                <form class="form-horizontal margin-top" method="POST" action="{{ $mode == 'create' ? route('aiassistant.documents.store') : route('aiassistant.documents.update', ['id' => $document->id]) }}">
                    {{ csrf_field() }}

                    <div class="form-group{{ $errors->has('mailbox_id') ? ' has-error' : '' }}">
                        <label for="mailbox_id" class="col-sm-2 control-label">{{ __('Mailbox') }}</label>
                        <div class="col-sm-6">
                            <select id="mailbox_id" name="mailbox_id" class="form-control input-sized" required>
                                @foreach ($mailboxes as $mailbox)
                                    <option value="{{ $mailbox->id }}" {{ old('mailbox_id', $document->mailbox_id) == $mailbox->id ? 'selected' : '' }}>{{ $mailbox->name }}</option>
                                @endforeach
                            </select>
                            @include('partials/field_error', ['field' => 'mailbox_id'])
                        </div>
                    </div>

                    <div class="form-group{{ $errors->has('source_url') ? ' has-error' : '' }}">
                        <label for="source_url" class="col-sm-2 control-label">{{ __('English URL') }}</label>
                        <div class="col-sm-6">
                            <input id="source_url" type="url" class="form-control input-sized" name="source_url" value="{{ old('source_url', $document->source_url) }}" maxlength="2048" required placeholder="https://example.com/en/docs/article">
                            <div class="form-help">{{ __('Enter the public English documentation URL. Markdown is fetched by appending .md, and the title is read from the Markdown.') }}</div>
                            @include('partials/field_error', ['field' => 'source_url'])
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="enabled" class="col-sm-2 control-label">{{ __('Enabled') }}</label>
                        <div class="col-sm-6">
                            <label class="checkbox plain">
                                <input id="enabled" type="checkbox" name="enabled" value="1" {{ old('enabled', $document->enabled) ? 'checked' : '' }}> {{ __('Use this document for retrieval') }}
                            </label>
                        </div>
                    </div>

                    @if ($document->exists)
                        <div class="form-group">
                            <label class="col-sm-2 control-label">{{ __('Derived URLs') }}</label>
                            <div class="col-sm-6">
                                @foreach ($document->localizedUrls() as $locale => $url)
                                    <div><code>{{ $locale }}</code> <a href="{{ $url }}" target="_blank" rel="noopener">{{ $url }}</a></div>
                                @endforeach
                                <div class="margin-top-5"><code>md</code> {{ $document->markdownUrl() }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="form-group margin-top-0 margin-bottom-0">
                        <div class="col-sm-6 col-sm-offset-2">
                            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                            <a href="{{ route('aiassistant.documents') }}" class="btn btn-link">{{ __('Cancel') }}</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
