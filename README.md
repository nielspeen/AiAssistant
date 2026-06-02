# FreeScout AI Assistant

## Features
* Summarizes conversations with more that N (configurable) messages.
* Translates threads, unless they're already in the target language.
  
## Installation

* Activate the AI Assistant module
* Open Settings > AI Assistant to select an OpenAI-compatible provider, base URL, API key, and model.

The module uses the OpenAI-compatible Chat Completions API. Provider presets include OpenAI, OpenRouter, Groq, Together AI, Fireworks AI, Mistral AI, DeepSeek, xAI, Perplexity, Ollama, LM Studio, and a custom option.

API keys entered in settings are stored encrypted in the database. Existing installations that still have the old `OPENAI_API_KEY` environment variable will import it automatically only when no API key has been saved yet.

## Updating

I recommend you run ```php artisan freescout:clear-cache``` after every update.
