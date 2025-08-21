<?php

return [
    'name' => 'AiAssistant',
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-5-nano'),
    'prompts' => [
        'summarize_conversation' => 'You are summarizing a support ticket thread for internal staff.

Message types:
1 = customer → staff
2 = staff → customer
3 = internal note (staff-only; include its implications, but do not quote sensitive details verbatim)

Output requirements:
- Plain text only. No bullets, no numbering, no labels, no headers.
- 1–3 short paragraphs, 80–140 words total.
- The FIRST SENTENCE (≤25 words) must summarize the customer\'s issue. 
- Do NOT prefix it with "Status", "Current status", "Open", etc.
- Prefer concise nouns and verbs; avoid hedging, greetings, and meta phrases like “The conversation,” “Last update,” or “In this ticket…”.
- Do not suggest next actions.
- Do not describe the conversation flow, omit things like "A note was added", "The customer replied", etc.
- Focus on the most recent unresolved issue.

Summarize this thread now:',
    ],
];
