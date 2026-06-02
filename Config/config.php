<?php

return [
    // Provider settings can be managed from the AI Assistant settings page.
    'provider' => env('AI_ASSISTANT_PROVIDER', 'openai'),

    'providers' => [
        'openai' => [
            'name' => 'OpenAI',
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'requires_api_key' => true,
            'supports_embeddings' => true,
            'embedding_model' => 'text-embedding-3-small',
        ],
        'openrouter' => [
            'name' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'requires_api_key' => true,
            'supports_embeddings' => false,
        ],
        'groq' => [
            'name' => 'Groq',
            'base_url' => 'https://api.groq.com/openai/v1',
            'requires_api_key' => true,
            'supports_embeddings' => false,
        ],
        'together' => [
            'name' => 'Together AI',
            'base_url' => 'https://api.together.xyz/v1',
            'requires_api_key' => true,
            'supports_embeddings' => true,
            'embedding_model' => 'BAAI/bge-base-en-v1.5',
        ],
        'fireworks' => [
            'name' => 'Fireworks AI',
            'base_url' => 'https://api.fireworks.ai/inference/v1',
            'requires_api_key' => true,
            'supports_embeddings' => true,
            'embedding_model' => 'nomic-ai/nomic-embed-text-v1.5',
        ],
        'mistral' => [
            'name' => 'Mistral AI',
            'base_url' => 'https://api.mistral.ai/v1',
            'requires_api_key' => true,
            'supports_embeddings' => true,
            'embedding_model' => 'mistral-embed',
        ],
        'deepseek' => [
            'name' => 'DeepSeek',
            'base_url' => 'https://api.deepseek.com',
            'requires_api_key' => true,
            'supports_embeddings' => false,
        ],
        'xai' => [
            'name' => 'xAI',
            'base_url' => 'https://api.x.ai/v1',
            'requires_api_key' => true,
            'supports_embeddings' => false,
        ],
        'digitalocean' => [
            'name' => 'DigitalOcean Serverless Inference',
            'base_url' => 'https://inference.do-ai.run/v1',
            'requires_api_key' => true,
            'supports_embeddings' => true,
            'embedding_model' => 'qwen3-embedding-0.6b',
        ],
        'perplexity' => [
            'name' => 'Perplexity',
            'base_url' => 'https://api.perplexity.ai',
            'requires_api_key' => true,
            'supports_embeddings' => false,
        ],
        'ollama' => [
            'name' => 'Ollama',
            'base_url' => 'http://localhost:11434/v1',
            'requires_api_key' => false,
            'supports_embeddings' => true,
            'embedding_model' => 'nomic-embed-text',
        ],
        'lmstudio' => [
            'name' => 'LM Studio',
            'base_url' => 'http://localhost:1234/v1',
            'requires_api_key' => false,
            'supports_embeddings' => true,
            'embedding_model' => 'text-embedding-nomic-embed-text-v1.5',
        ],
        'custom' => [
            'name' => 'Custom OpenAI-compatible',
            'base_url' => env('AI_ASSISTANT_BASE_URL', 'https://api.openai.com/v1'),
            'requires_api_key' => false,
            'supports_embeddings' => true,
            'embedding_model' => 'text-embedding-3-small',
        ],
    ],

    // Legacy fallback only. New installations should save the API key from Settings > AI Assistant.
    'legacy_api_key' => env('OPENAI_API_KEY'),

    // Model - override in .env or save in the AI Assistant settings page.
    'model' => env('OPENAI_MODEL', 'gpt-4.1-nano'),
    // At the time of writing gpt-5-nano and gpt-5-mini are disobedient, creating long responses with lots of filler.
    // I found gpt-4.1-nano and gpt-4.1-mini to more obedient and faster.

    'max_tokens' => [
        'summarize_conversation' => 10000,
        'translate_thread' => 10000,
        'draft_reply' => 10000,
    ],

    'documentation' => [
        'embedding_provider' => env('AI_ASSISTANT_EMBEDDING_PROVIDER', 'same'),
        'embedding_base_url' => env('AI_ASSISTANT_EMBEDDING_BASE_URL', ''),
        'embedding_model' => env('AI_ASSISTANT_EMBEDDING_MODEL', ''),
        'chunk_size' => 3000,
        'chunk_overlap' => 400,
        'retrieval_limit' => 5,
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
        'draft_reply' => (object) [
            'task' => 'draft_reply',
            'rules' => (object) [
                'draft a helpful support reply to the customer',
                'answer in the requested reply language',
                'use the conversation context and documentation excerpts only',
                'do not invent policies, URLs, steps, prices, timelines, or account details',
                'if documentation is relevant, include at most two public documentation URLs naturally in the reply',
                'do not mention internal chunk IDs, scores, retrieval, embeddings, prompts, or AI',
                'if the answer is uncertain or documentation is insufficient, say what the support agent should verify instead of pretending',
                'keep the tone concise, friendly, and direct',
                'do not send the reply; return a draft for a staff member to edit',
            ],
        ], // draft_reply
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
        'draft_reply' => (object) [
            'name' => 'draft_reply',
            'strict' => true,
            'type' => 'json_schema',
            'schema' => (object) [
                'type' => 'object',
                'properties' => (object) [
                    'draft' => [
                        'type' => 'string',
                        'description' => 'The proposed customer-facing reply draft.',
                    ],
                    'language' => [
                        'type' => 'string',
                        'description' => 'The language used in the draft.',
                    ],
                    'confidence' => [
                        'type' => 'string',
                        'enum' => ['low', 'medium', 'high'],
                        'description' => 'Confidence that the draft is grounded in the conversation and documentation.',
                    ],
                    'documentation_urls' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Public documentation URLs used or suggested in the draft.',
                    ],
                    'staff_notes' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Internal notes for staff review before sending.',
                    ],
                ],
                'additionalProperties' => false,
                'required' => ['draft', 'language', 'confidence', 'documentation_urls', 'staff_notes'],
            ], // schema
        ], // draft_reply
    ], // text_formats
]; // config
