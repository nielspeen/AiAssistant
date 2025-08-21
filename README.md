# FreeScout AI Assistant

## Features
* Summarizes conversations
* Translates threads (WIP)
  
## Installation

* Add your OpenAI API key to .env: ```OPENAI_API_KEY=sk-abcdef```
* Run ```php artisan freescout:clear-cache```
* Activate the AI Assistant module

* Add the features you want to use to CRON. For example:
  ```
  *  *	* * *	www-data	php /var/www/html/artisan ai-assistant:summarize-conversations >> /dev/null
  ```
