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
                @php
                    $summary_lines = preg_split('/\r\n|\r|\n/', trim($summary));
                    $summary_items = [];
                    $summary_is_list = false;

                    foreach ($summary_lines as $summary_line) {
                        $summary_line = trim($summary_line);

                        if ($summary_line === '') {
                            continue;
                        }

                        if (preg_match('/^[-*]\s+/', $summary_line)) {
                            $summary_is_list = true;
                            $summary_line = preg_replace('/^[-*]\s+/', '', $summary_line);
                        }

                        $summary_items[] = $summary_line;
                    }
                @endphp

                @if ($summary_is_list || count($summary_items) > 1)
                    <ul class="ai-assistant-summary-list">
                        @foreach ($summary_items as $summary_item)
                            <li>{{ $summary_item }}</li>
                        @endforeach
                    </ul>
                @else
                    <div>{{ $summary }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
