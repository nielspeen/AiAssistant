<?php

namespace Modules\AiAssistant\Console;

use Illuminate\Console\Command;

class TranslateThreadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai-assistant:translate-threads';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Translate threads';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $apiKey = \Option::get('aiassistant.api_key');
        if (!$apiKey) {
            $this->error('API key is not set');
            return;
        }

        $this->info('API key: ' . $apiKey);

    }
}
