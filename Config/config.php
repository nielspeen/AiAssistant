<?php

return [
    // OpenAI API key - specify in .env
    'api_key' => env('OPENAI_API_KEY'),

    // OpenAI model - override in .env if desired
    'model' => env('OPENAI_MODEL', 'gpt-4.1-nano'),
    // At the time of writing gpt-5-nano and gpt-5-mini are disobedient, creating long responses with lots of filler.
    // I found gpt-4.1-nano and gpt-4.1-mini to more obedient and faster.

    'max_tokens' => [
        'summarize_conversation' => 10000,
        'translate_thread' => 10000,
    ],

    // PROMPTS

    'prompts' => [
        'summarize_conversation' => (object) [
            'task' => 'summarize_conversation',
            'rules' => (object) ['80-100 words', '1-2 sentences', 'Do not use lead-ins like "Subject shows", "The latest thread", "The email", etc.', 'focus on newest unresolved issue', 'english language', 'do not describe the layout of the conversation, just the content', 'state facts only, do not draw conclusions'],
            'message_types' => (object) [
                'customer_to_staff' => 1,
                'staff_to_customer' => 2,
                'internal_note' => 3,
            ],
            'few_shots' => [
                (object) [
                    'bad' => 'The latest issue is that Joe could not login. This has been resolved now.',
                    'good' => 'Joe could not login. This has been resolved now.',
                ],
                (object) [
                    'bad' => 'The issue is that Alice wants her account to be canceled.',
                    'good' => 'Alice wants her account to be canceled.',
                ],
                (object) [
                    'bad' => 'The conversation centers on a payment notification that was received.',
                    'good' => 'A payment notification was received.',
                ],
                (object) [
                    'bad' => 'Latest activity shows a payment notification was sent confirming a USD 1,103.78 payment',
                    'good' => 'Payment has been confirmed.',
                ],
                (object) [
                    'bad' => 'The email, titled Payment Completed, informs us that the transaction succeeded',
                    'good' => 'The transaction succeeded.',
                ],
                (object) [
                    'bad' => 'The most recent thread is about pizza.',
                    'good' => 'Pizza.',
                ],
                (object) [
                    'bad' => 'The body text is "hello"',
                    'good' => 'Joe says hello.',
                ],
            ], // few_shots
        ], // summarize_conversation

        'translate_thread' => (object) [
            'task' => 'translate_thread',
            'rules' => (object) [
                'translate to: ' . \Option::get('aiassistant.translation_language', 'en'),
                'do not change the content of the thread',
                'do not add any additional information',
                'if the thread is already entirely in the target language, set same_language=true and leave translation empty.'
            ],
        ], // translate_thread
    ], // prompts

    // TEXT FORMATS

    'text_formats' => [
        'summarize_conversation' => (object) [
            'name' => 'summary',
            'strict' => true,
            'type' => 'json_schema',
            'schema' => (object) [
                'type' => 'object',
                'properties' => (object) [
                    'one_liner' => [
                        'type' => 'string',
                        'description' => 'A concise one-liner summary of the conversation. Max 25 words.',
                    ], // one_liner
                    'summary' => [
                        'type' => 'string',
                        'description' => 'A concise summary of the conversation. Max 100 words.',
                    ], // summary
                ], // properties
                'additionalProperties' => false,
                'required' => ['one_liner', 'summary'],
            ], // schema
        ], // summarize_conversation
        'translate_thread' => (object) [
            'name' => 'translation',
            'strict' => true,
            'type' => 'json_schema',
            'schema' => (object) [
                'type' => 'object',
                'properties' => (object) [
                    'translation' => [
                        'type' => 'string',
                        'description' => 'The translated thread.',
                    ],
                    'same_language' => [
                        'type' => 'boolean',
                        'description' => 'Whether the thread is already in the target language.',
                    ],
                    'detected_language' => [
                        'type' => 'string',
                        'description' => 'The language of the thread.',
                    ],
                ],
                'additionalProperties' => false,
                'required' => ['translation', 'same_language', 'detected_language'],
            ], // schema
        ], // translate_thread
    ], // text_formats
]; // config
