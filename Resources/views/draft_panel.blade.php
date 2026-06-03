<div class="ai-assistant-draft-panel hidden"
     data-draft-url="{{ route('aiassistant.conversations.draft_reply', ['id' => $conversation->id]) }}">
    <div class="ai-assistant-draft-header">
        <strong>{{ __('AI Draft') }}</strong>
        <span class="ai-assistant-draft-meta"></span>
    </div>

    <div class="ai-assistant-draft-status text-muted"></div>

    <div class="ai-assistant-draft-body hidden"></div>

    <div class="ai-assistant-draft-english hidden">
        <strong>{{ __('English Translation') }}</strong>
        <div class="ai-assistant-draft-english-body"></div>
    </div>

    <div class="ai-assistant-draft-actions hidden">
        <button type="button" class="btn btn-primary btn-sm ai-assistant-insert-draft">{{ __('Insert into Reply') }}</button>
        <button type="button" class="btn btn-default btn-sm ai-assistant-regenerate-draft">{{ __('Regenerate') }}</button>
    </div>

    <div class="ai-assistant-draft-notes hidden">
        <strong>{{ __('Staff Notes') }}</strong>
        <ul></ul>
    </div>

    <div class="ai-assistant-draft-docs hidden">
        <strong>{{ __('Documentation Used') }}</strong>
        <ul></ul>
    </div>
</div>
