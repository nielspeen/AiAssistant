<?php

namespace Modules\AiAssistant\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;
use Modules\AiAssistant\Console\TranslateThreadsCommand;
use Modules\AiAssistant\Console\SummarizeConversationsCommand;

define('AI_ASSISTANT_MODULE', 'aiassistant');

class AiAssistantServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerFactories();
        $this->registerCommands();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->hooks();
    }

    /**
     * Module hooks.
     */
    public function hooks()
    {
        \Eventy::addFilter('stylesheets', function ($styles) {
            $styles[] = \Module::getPublicPath(AI_ASSISTANT_MODULE).'/css/aiassistant.css';
            return $styles;
        });

        \Eventy::addAction('conversations_table.preview_prepend', function ($conversation) {
            if (isset($conversation->ai_assistant) && $conversation->ai_assistant !== null) {
                $aiData = json_decode($conversation->ai_assistant, true);
                if (isset($aiData['summary'])) {
                    // Show only the first sentence of the summary
                    $firstSentence = '';
                    if (isset($aiData['summary'])) {
                        $summary = trim($aiData['summary']);
                        // Find the position of the first period followed by a space or end of string
                        if (preg_match('/^(.+?[.!?])(\s|$)/u', $summary, $matches)) {
                            $firstSentence = $matches[1];
                        } else {
                            $firstSentence = $summary;
                        }
                    }

                    echo '<span class="aiassistant-preview">' . e($firstSentence) . '</span><span style="display:none">';
                }
            }
        }, 10, 1);



        \Eventy::addAction('conversation.after_subject_block', function ($conversation) {
            if (isset($conversation->ai_assistant) && $conversation->ai_assistant !== null) {
                $aiData = json_decode($conversation->ai_assistant, true);
                if (isset($aiData['summary'])) {
                    echo View::make('aiassistant::summary', [
                        'summary' => $aiData['summary'],
                        'updated_at' => Carbon::parse($conversation->ai_assistant_updated_at)
                    ]);
                }
            }
        }, 10, 1);

    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerTranslations();
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('aiassistant.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php',
            'aiassistant'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/aiassistant');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/aiassistant';
        }, \Config::get('view.paths')), [$sourcePath]), 'aiassistant');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $this->loadJsonTranslationsFrom(__DIR__ .'/../Resources/lang');
    }

    /**
     * Register an additional directory of factories.
     * @source https://github.com/sebastiaanluca/laravel-resource-flow/blob/develop/src/Modules/ModuleServiceProvider.php#L66
     */
    public function registerFactories()
    {
        if (! app()->environment('production')) {
            app(Factory::class)->load(__DIR__ . '/../Database/factories');
        }
    }

    /**
     * Register console commands.
     *
     * @return void
     */
    public function registerCommands()
    {
        $this->commands(TranslateThreadsCommand::class);
        $this->commands(SummarizeConversationsCommand::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
