<?php

namespace Modules\AiAssistant\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;
use App\Mailbox;
use Modules\AiAssistant\Console\DraftReplyCommand;
use Modules\AiAssistant\Console\IndexDocumentsCommand;
use Modules\AiAssistant\Console\SearchDocumentsCommand;
use Modules\AiAssistant\Console\TranslateThreadsCommand;
use Modules\AiAssistant\Console\SummarizeConversationsCommand;
use Modules\AiAssistant\Services\CustomerContextService;

require_once __DIR__.'/../vendor/autoload.php';

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
        $this->importLegacyApiKey();
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

        \Eventy::addFilter('javascripts', function ($javascripts) {
            $javascripts[] = \Module::getPublicPath(AI_ASSISTANT_MODULE).'/js/aiassistant.js';
            return $javascripts;
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

        \Eventy::addAction('conversation.action_buttons', function ($conversation, $mailbox) {
            if (auth()->user() && auth()->user()->can('view', $conversation)) {
                echo View::make('aiassistant::draft_action', [
                    'conversation' => $conversation,
                    'mailbox' => $mailbox,
                ]);
            }
        }, 20, 2);

        \Eventy::addAction('reply_form.after', function ($conversation) {
            if (auth()->user() && auth()->user()->can('view', $conversation)) {
                echo View::make('aiassistant::draft_panel', [
                    'conversation' => $conversation,
                ]);
            }
        }, 20, 1);


        \Eventy::addFilter('settings.sections', function ($sections) {
            $sections[AI_ASSISTANT_MODULE] = ['title' => __('AI Assistant'), 'icon' => 'cloud', 'order' => 600];

            return $sections;
        }, 40);


        \Eventy::addFilter('settings.section_settings', function ($settings, $section) {

            if ($section != AI_ASSISTANT_MODULE) {
                return $settings;
            }

            $settings['aiassistant.provider'] = \Option::get('aiassistant.provider', config('aiassistant.provider', 'openai'));
            $settings['aiassistant.api_key'] = \Helper::decrypt(\Option::get('aiassistant.api_key', ''));
            $settings['aiassistant.base_url'] = \Option::get('aiassistant.base_url', '');
            $settings['aiassistant.model'] = \Option::get('aiassistant.model', config('aiassistant.model'));
            $settings['aiassistant.documentation.embedding_provider'] = $this->configuredEmbeddingProvider();
            $settings['aiassistant.documentation.embedding_api_key'] = \Helper::decrypt(\Option::get('aiassistant.documentation.embedding_api_key', ''));
            $settings['aiassistant.documentation.embedding_base_url'] = \Option::get('aiassistant.documentation.embedding_base_url', '');
            $settings['aiassistant.documentation.embedding_model'] = trim(\Option::get('aiassistant.documentation.embedding_model', '')) ?: $this->defaultEmbeddingModel();
            $settings['aiassistant.documentation.chunk_size'] = \Option::get('aiassistant.documentation.chunk_size', config('aiassistant.documentation.chunk_size', 3000));
            $settings['aiassistant.documentation.chunk_overlap'] = \Option::get('aiassistant.documentation.chunk_overlap', config('aiassistant.documentation.chunk_overlap', 400));
            $settings['aiassistant.documentation.retrieval_limit'] = \Option::get('aiassistant.documentation.retrieval_limit', config('aiassistant.documentation.retrieval_limit', 5));
            $settings['aiassistant.documentation.enabled'] = $this->providerSupportsEmbeddings();
            $settings['aiassistant.summary_conversation_threshold'] = \Option::get('aiassistant.summary_conversation_threshold', 3);
            $settings['aiassistant.translation_language'] = \Option::get('aiassistant.translation_language', 'en');

            return $settings;
        }, 20, 2);

        \Eventy::addFilter('settings.section_params', function ($params, $section) {
            if ($section != AI_ASSISTANT_MODULE) {
                return $params;
            }

            $params['settings'] = [
                'aiassistant.api_key' => [
                    'safe_password' => true,
                    'encrypt' => true,
                ],
                'aiassistant.documentation.embedding_api_key' => [
                    'safe_password' => true,
                    'encrypt' => true,
                ],
            ];
            $params['template_vars'] = [
                'aiassistant_mailboxes' => Mailbox::orderBy('name')->get(),
            ];

            $params['validator_rules'] = [];

            foreach (Mailbox::select(['id'])->get() as $mailbox) {
                $params['validator_rules']['aiassistant_customer_context_urls.' . $mailbox->id] = 'nullable|url|max:2048';
                $params['validator_rules']['aiassistant_customer_context_secret_keys.' . $mailbox->id] = 'nullable|string|max:255';
                $params['validator_rules']['aiassistant_customer_context_signature_headers.' . $mailbox->id] = 'nullable|in:X-FREESCOUT-SIGNATURE,X-HELPSCOUT-SIGNATURE';
                $params['validator_rules']['aiassistant_customer_context_guidance.' . $mailbox->id] = 'nullable|string|max:6000';
            }

            return $params;
        }, 20, 2);

        \Eventy::addFilter('settings.before_save', function ($request, $section, $settings) {
            if ($section != AI_ASSISTANT_MODULE || empty($request->settings)) {
                return $request;
            }

            $settings_input = $request->settings;

            foreach ([
                'aiassistant.provider',
                'aiassistant.api_key',
                'aiassistant.base_url',
                'aiassistant.model',
                'aiassistant.documentation.embedding_provider',
                'aiassistant.documentation.embedding_api_key',
                'aiassistant.documentation.embedding_base_url',
                'aiassistant.documentation.embedding_model',
            ] as $setting) {
                if (isset($settings_input[$setting])) {
                    $settings_input[$setting] = trim($settings_input[$setting]);
                }
            }

            $customer_context_urls = $request->input('aiassistant_customer_context_urls', []);

            if (is_array($customer_context_urls)) {
                $this->saveCustomerContextSettings(
                    $customer_context_urls,
                    $request->input('aiassistant_customer_context_secret_keys', []),
                    $request->input('aiassistant_customer_context_signature_headers', []),
                    $request->input('aiassistant_customer_context_guidance', [])
                );
            }

            if (isset($settings_input['aiassistant.provider'])) {
                $settings_input['aiassistant.provider'] = $this->normalizeProvider($settings_input['aiassistant.provider']);
            }

            if (isset($settings_input['aiassistant.documentation.embedding_provider'])) {
                $settings_input['aiassistant.documentation.embedding_provider'] = $this->normalizeEmbeddingProvider($settings_input['aiassistant.documentation.embedding_provider']);
            }

            if (isset($settings_input['aiassistant.documentation.embedding_model'])) {
                $provider = $settings_input['aiassistant.documentation.embedding_provider'] ?? $this->configuredEmbeddingProvider();
                $provider = $this->resolvedEmbeddingProvider($provider);
                $settings_input['aiassistant.documentation.embedding_model'] = $this->normalizeEmbeddingModel(
                    $provider,
                    $settings_input['aiassistant.documentation.embedding_model']
                );
            }

            if (!empty($settings_input['aiassistant.base_url'])) {
                $settings_input['aiassistant.base_url'] = rtrim($settings_input['aiassistant.base_url'], '/');
            }

            if (!empty($settings_input['aiassistant.documentation.embedding_base_url'])) {
                $settings_input['aiassistant.documentation.embedding_base_url'] = rtrim($settings_input['aiassistant.documentation.embedding_base_url'], '/');
            }

            foreach ([
                'aiassistant.documentation.chunk_size' => [500, 20000],
                'aiassistant.documentation.chunk_overlap' => [0, 5000],
                'aiassistant.documentation.retrieval_limit' => [1, 20],
            ] as $setting => $bounds) {
                if (isset($settings_input[$setting])) {
                    $value = intval($settings_input[$setting]);
                    $settings_input[$setting] = max($bounds[0], min($bounds[1], $value));
                }
            }

            $request->merge(['settings' => $settings_input]);

            return $request;
        }, 20, 3);

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
        $this->registerProviderDefaults();
    }

    protected function registerProviderDefaults()
    {
        $moduleConfig = require __DIR__.'/../Config/config.php';
        $defaultProviders = $moduleConfig['providers'] ?? [];
        $providers = config('aiassistant.providers', []);

        foreach ($defaultProviders as $provider => $defaults) {
            $current = $providers[$provider] ?? [];

            foreach ($defaults as $key => $value) {
                if (in_array($key, ['supports_embeddings', 'embedding_model'])) {
                    $current[$key] = $value;
                } elseif (!array_key_exists($key, $current)) {
                    $current[$key] = $value;
                }
            }

            $providers[$provider] = $current;
        }

        config(['aiassistant.providers' => $providers]);

        $documentation = config('aiassistant.documentation', []);

        foreach (($moduleConfig['documentation'] ?? []) as $key => $value) {
            if (!array_key_exists($key, $documentation)) {
                $documentation[$key] = $value;
            }
        }

        config(['aiassistant.documentation' => $documentation]);

        $customerContext = config('aiassistant.customer_context', []);

        foreach (($moduleConfig['customer_context'] ?? []) as $key => $value) {
            if (!array_key_exists($key, $customerContext)) {
                $customerContext[$key] = $value;
            }
        }

        config(['aiassistant.customer_context' => $customerContext]);

        foreach (['max_tokens', 'prompts', 'text_formats'] as $section) {
            $current = config('aiassistant.'.$section, []);

            foreach (($moduleConfig[$section] ?? []) as $key => $value) {
                if (!isset($current[$key])) {
                    $current[$key] = $value;
                }
            }

            config(['aiassistant.'.$section => $current]);
        }
    }

    protected function importLegacyApiKey()
    {
        try {
            $storedApiKey = \Option::get('aiassistant.api_key', null, true, false);

            if ($storedApiKey !== null) {
                return;
            }

            $legacyApiKey = config('aiassistant.legacy_api_key', config('aiassistant.api_key'));

            if (!$legacyApiKey) {
                return;
            }

            $encryptedApiKey = \Helper::encrypt($legacyApiKey);

            \Option::set('aiassistant.api_key', $encryptedApiKey);
            \App\Option::$cache['aiassistant.api_key'] = $encryptedApiKey;
        } catch (\Exception $e) {
            // Ignore import failures; users can save a key from settings.
        }
    }

    protected function providerSupportsEmbeddings()
    {
        $provider = $this->resolvedEmbeddingProvider();
        $providers = config('aiassistant.providers', []);

        return !empty($providers[$provider]['supports_embeddings']);
    }

    protected function defaultEmbeddingModel()
    {
        $configured = config('aiassistant.documentation.embedding_model');

        if ($configured) {
            return $this->normalizeEmbeddingModel($this->resolvedEmbeddingProvider(), $configured);
        }

        $provider = $this->resolvedEmbeddingProvider();
        $providers = config('aiassistant.providers', []);

        return $this->normalizeEmbeddingModel($provider, $providers[$provider]['embedding_model'] ?? '');
    }

    protected function configuredProvider()
    {
        return $this->normalizeProvider(\Option::get('aiassistant.provider', config('aiassistant.provider', 'openai')));
    }

    protected function configuredEmbeddingProvider()
    {
        return $this->normalizeEmbeddingProvider(\Option::get(
            'aiassistant.documentation.embedding_provider',
            config('aiassistant.documentation.embedding_provider', 'same')
        ));
    }

    protected function resolvedEmbeddingProvider($embeddingProvider = null)
    {
        $embeddingProvider = $embeddingProvider ?: $this->configuredEmbeddingProvider();

        if ($embeddingProvider === 'same') {
            return $this->configuredProvider();
        }

        return $this->normalizeProvider($embeddingProvider);
    }

    protected function normalizeEmbeddingProvider($provider)
    {
        $provider = strtolower(trim((string) $provider));

        if ($provider === 'same') {
            return 'same';
        }

        return $this->normalizeProvider($provider);
    }

    protected function normalizeProvider($provider)
    {
        $provider = strtolower(trim((string) $provider));
        $providers = config('aiassistant.providers', []);

        if (!$provider || !array_key_exists($provider, $providers)) {
            return config('aiassistant.provider', 'openai');
        }

        return $provider;
    }

    protected function normalizeEmbeddingModel($provider, $model)
    {
        return trim((string) $model);
    }

    protected function saveCustomerContextSettings(array $urls, array $secretKeys, array $signatureHeaders, array $guidance)
    {
        $mailboxIds = array_unique(array_map('intval', array_merge(
            array_keys($urls),
            array_keys($secretKeys),
            array_keys($signatureHeaders),
            array_keys($guidance)
        )));
        $mailboxes = Mailbox::whereIn('id', $mailboxIds)->get();

        foreach ($mailboxes as $mailbox) {
            CustomerContextService::setMailboxSettings($mailbox, [
                'url' => trim((string) ($urls[$mailbox->id] ?? '')),
                'secret_key' => (string) ($secretKeys[$mailbox->id] ?? ''),
                'signature_header' => (string) ($signatureHeaders[$mailbox->id] ?? CustomerContextService::DEFAULT_SIGNATURE_HEADER),
                'guidance' => trim((string) ($guidance[$mailbox->id] ?? '')),
            ]);
        }
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
        $this->commands(DraftReplyCommand::class);
        $this->commands(IndexDocumentsCommand::class);
        $this->commands(SearchDocumentsCommand::class);
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
