# FreeScout AI Assistant

## Features
* Summarizes conversations with more that N (configurable) messages.
* Translates threads, unless they're already in the target language.
  
## Installation

* Activate the AI Assistant module
* Open Settings > AI Assistant to select an OpenAI-compatible provider, base URL, API key, and model.

The module uses the OpenAI-compatible Chat Completions API. Provider presets include OpenAI, OpenRouter, Groq, Together AI, Fireworks AI, Mistral AI, DeepSeek, xAI, Perplexity, Ollama, LM Studio, and a custom option.

API keys entered in settings are stored encrypted in the database. Existing installations that still have the old `OPENAI_API_KEY` environment variable will import it automatically only when no API key has been saved yet.

## Customer context URLs

Settings > AI Assistant can store an optional customer context URL, secret key, and signature header for each mailbox. When AI Assistant drafts a reply for a conversation in that mailbox, it sends a `POST` request with a JSON body:

```json
{
  "event": "draft_reply_context",
  "mailbox": {
    "id": 1,
    "name": "Support",
    "email": "support@example.com"
  },
  "conversation": {
    "id": 123,
    "number": 456,
    "subject": "Billing question",
    "customer_email": "customer@example.com"
  },
  "customer": {
    "id": 789,
    "name": "Customer Name",
    "emails": ["customer@example.com", "billing@example.com"]
  },
  "emails": ["customer@example.com", "billing@example.com"]
}
```

The request is signed the same way as the CustomApp module:

```php
$signature = base64_encode(hash_hmac('sha1', $rawJsonRequestBody, $secretKey, true));
```

The signature is sent in the configured header. The default header is:

```http
X-FREESCOUT-SIGNATURE: <signature>
```

You can also choose `X-HELPSCOUT-SIGNATURE` for HelpScout-compatible integrations.

Each mailbox can also have optional reply guidance. Use this to explain your business, what customers buy, field meanings in your customer context JSON, and any reply style preferences. This guidance is stored in FreeScout and added to the draft prompt; it is not sent to the customer context URL.

The URL should return a JSON object. Keep it concise and include only information useful to a support reply. For example:

```json
{
  "summary": "Customer is on the Pro plan. The next renewal is 2026-07-15.",
  "facts": [
    "Customer prefers invoices by email.",
    "Recent order A10045 has shipped."
  ],
  "customer": {
    "plan": "Pro",
    "status": "active"
  },
  "recent_orders": [
    {
      "id": "A10045",
      "status": "shipped"
    }
  ],
  "internal_notes": "Do not expose internal IDs unless needed."
}
```

The module passes this JSON through as customer context. It does not apply domain-specific meaning to your fields, so put important facts in explicit text such as `summary`, `facts`, or `reply_hints` when the raw data may be ambiguous. The model is instructed to use the context only when relevant and to avoid exposing private/internal metadata unnecessarily.

## Updating

I recommend you run ```php artisan freescout:clear-cache``` after every update.
