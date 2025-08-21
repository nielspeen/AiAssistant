<div class="thread thread-type-ai-summary" id="thread-ai-summary" data-thread_id="ai-summary">
    <div class="thread-photo">
        <img class="person-photo" src="/modules/aiassistant/avatar.png" alt="">
    </div>
    <div class="thread-message">
        <div class="thread-header">
            <div class="thread-title">
                <div class="thread-person">
                    <strong>
                        {{ __('Summary') }}
                    </strong>
                </div>
            </div>
            <div class="thread-info">

                <a href="#thread-ai-summary" class="thread-date" data-toggle="tooltip" title=""
                    data-original-title="{{ $updated_at }}">{{ $updated_at->diffForHumans() }}</a><br>

                <span class="thread-status">
                </span>
            </div>
        </div>
        <div class="thread-body">
            <div class="thread-content" dir="auto">
                <div>{{ $summary }}</div>
            </div>
        </div>
    </div>
</div>