<?php

namespace Modules\AiAssistant\Providers;

require_once __DIR__.'/../vendor/autoload.php';

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
                if (isset($aiData['one_liner'])) {
                    echo '<span class="ai-assistant-badge">AI</span> <span class="ai-assistant-preview">' . $aiData['one_liner'] . '</span><span style="display:none">';
                }
            }
        }, 10, 1);

        \Eventy::addAction('conversation.before_threads', function ($conversation) {
            if (isset($conversation->ai_assistant) && $conversation->ai_assistant !== null) {
                $aiData = json_decode($conversation->ai_assistant, true);
                if (isset($aiData['summary']) && trim($aiData['summary']) != '') {
                    echo View::make('aiassistant::summary', [
                        'summary' => $aiData['summary'],
                        'updated_at' => Carbon::parse($conversation->ai_assistant_updated_at)
                    ]);
                }
            }
        }, 10, 1);

        \Eventy::addAction('thread.before_body', function ($thread, $loop, $threads, $conversation, $mailbox) {
            if (isset($thread->ai_assistant) && $thread->ai_assistant !== null) {
                $aiData = json_decode($thread->ai_assistant, true);
                if (isset($aiData['translation']) && trim($aiData['translation']) != '') {
                    echo View::make('aiassistant::translation', [
                        'translation' => $aiData['translation'],
                        'updated_at' => Carbon::parse($thread->ai_assistant_updated_at)
                    ]);
                }
            }
        }, 20, 5);


        \Eventy::addFilter('settings.sections', function ($sections) {
            $sections[AI_ASSISTANT_MODULE] = ['title' => __('AI Assistant'), 'icon' => 'cloud', 'order' => 600];

            return $sections;
        }, 40);


        \Eventy::addFilter('settings.section_settings', function ($settings, $section) {

            if ($section != AI_ASSISTANT_MODULE) {
                return $settings;
            }

            $settings['aiassistant.summary_conversation_threshold'] = \Option::get('aiassistant.summary_conversation_threshold', 3);
            $settings['aiassistant.translation_language'] = \Option::get('aiassistant.translation_language', 'en');

            return $settings;
        }, 20, 2);

        \Eventy::addFilter('settings.view', function ($view, $section) {
            if ($section != AI_ASSISTANT_MODULE) {
                return $view;
            } else {
                return 'aiassistant::settings';
            }
        }, 20, 2);


        // On settings save
        \Eventy::addFilter('settings.after_save', function ($response, $request, $section, $settings) {
            if ($section != AI_ASSISTANT_MODULE) {
                return $response;
            }

            $settings = $request->settings ?: [];

            $summary_conversation_threshold = intval($settings['aiassistant.summary_conversation_threshold']);
            $translation_language = $settings['aiassistant.translation_language'];

            \Option::set('aiassistant.summary_conversation_threshold', $summary_conversation_threshold);
            \Option::set('aiassistant.translation_language', $translation_language);
            \Session::flash('flash_success_floating', __('Settings updated'));

            return $response;
        }, 20, 4);


        \Eventy::addFilter('schedule', function ($schedule) {
            $schedule->command('ai-assistant:summarize-conversations')
                ->cron('* * * * *')
                ->withoutOverlapping($expires_at = 60 /* minutes */);
            $schedule->command('ai-assistant:translate-threads')
                ->cron('* * * * *')
                ->withoutOverlapping($expires_at = 60 /* minutes */);

            return $schedule;
        });


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
