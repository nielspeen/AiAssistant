# FreeScout AI Assistant

## Features
* Summarizes conversations with more that N (configurable) messages.
* Translates threads, unless they're already in the target language.
  
## Installation

* Add your OpenAI API key to .env: ```OPENAI_API_KEY=sk-abcdef```
* Run ```php artisan freescout:clear-cache```
* Activate the AI Assistant module

## Updating

I recommend you run ```php artisan freescout:clear-cache``` after every update.