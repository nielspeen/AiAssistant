<form class="form-horizontal margin-top" method="POST" action="">
    {{ csrf_field() }}

    <input type="hidden" name="settings[dummy]" value="1" />


    <div class="form-group margin-top">
        <div class="col-sm-6 col-sm-offset-2">
            <a href="{{ route('aiassistant.documents') }}" class="btn btn-bordered">{{ __('Manage Documentation') }}</a>
        </div>
    </div>


    <h3 class="subheader">{{ __('Provider') }}</h3>

    <div class="form-group margin-top">
        <label for="aiassistant_provider" class="col-sm-2 control-label">{{ __('Provider') }}</label>
        <div class="col-sm-6">
            <select name="settings[aiassistant.provider]" class="form-control input-sized" id="aiassistant_provider">
                @foreach (config('aiassistant.providers', []) as $provider_key => $provider)
                    <option value="{{ $provider_key }}" {{ $settings['aiassistant.provider'] == $provider_key ? 'selected' : '' }}>{{ $provider['name'] }}</option>
                @endforeach
            </select>
            <div class="form-help">{{ __('Select a provider that supports the OpenAI-compatible Chat Completions API.') }}</div>
        </div>
    </div>

    <div class="form-group">
        <label for="aiassistant_api_key" class="col-sm-2 control-label">{{ __('API Key') }}</label>
        <div class="col-sm-6">
            <input id="aiassistant_api_key" type="password" class="form-control input-sized" name="settings[aiassistant.api_key]" value="{{ \Helper::safePassword($settings['aiassistant.api_key']) }}" autocomplete="new-password">
            <div class="form-help">{{ __('Leave the masked value unchanged to keep the current key. Some local providers do not require a key.') }}</div>
        </div>
    </div>

    <div class="form-group">
        <label for="aiassistant_base_url" class="col-sm-2 control-label">{{ __('Base URL') }}</label>
        <div class="col-sm-6">
            <input id="aiassistant_base_url" type="url" class="form-control input-sized" name="settings[aiassistant.base_url]" value="{{ $settings['aiassistant.base_url'] }}" placeholder="{{ config('aiassistant.providers.openai.base_url') }}">
            <div class="form-help">{{ __('Optional. Leave blank to use the selected provider default.') }}</div>
        </div>
    </div>

    <div class="form-group">
        <label for="aiassistant_model" class="col-sm-2 control-label">{{ __('Model') }}</label>
        <div class="col-sm-6">
            <input id="aiassistant_model" type="text" class="form-control input-sized" name="settings[aiassistant.model]" value="{{ $settings['aiassistant.model'] }}" maxlength="255">
            <div class="form-help">{{ __('Enter the model identifier from the selected provider.') }}</div>
        </div>
    </div>


    <h3 class="subheader">{{ __('Documentation') }}</h3>

    @if (!$settings['aiassistant.documentation.enabled'])
        <div class="form-group">
            <div class="col-sm-8 col-sm-offset-2">
                <div class="alert alert-warning margin-bottom-0">
                    {{ __('The selected documentation embedding provider does not support embeddings, so documentation search and reply drafting with documentation are disabled. Summaries and translations will continue to work.') }}
                </div>
            </div>
        </div>
    @endif

    <div class="form-group">
        <label for="aiassistant_embedding_provider" class="col-sm-2 control-label">{{ __('Embedding Provider') }}</label>
        <div class="col-sm-6">
            <select name="settings[aiassistant.documentation.embedding_provider]" class="form-control input-sized" id="aiassistant_embedding_provider">
                <option value="same" {{ $settings['aiassistant.documentation.embedding_provider'] == 'same' ? 'selected' : '' }}>{{ __('Same as AI Provider') }}</option>
                @foreach (config('aiassistant.providers', []) as $provider_key => $provider)
                    <option value="{{ $provider_key }}" {{ $settings['aiassistant.documentation.embedding_provider'] == $provider_key ? 'selected' : '' }}>{{ $provider['name'] }}</option>
                @endforeach
            </select>
            <div class="form-help">{{ __('Choose a separate provider for documentation embeddings, or reuse the AI provider.') }}</div>
        </div>
    </div>

    <div class="form-group">
        <label for="aiassistant_embedding_api_key" class="col-sm-2 control-label">{{ __('Embedding API Key') }}</label>
        <div class="col-sm-6">
            <input id="aiassistant_embedding_api_key" type="password" class="form-control input-sized" name="settings[aiassistant.documentation.embedding_api_key]" value="{{ \Helper::safePassword($settings['aiassistant.documentation.embedding_api_key']) }}" autocomplete="new-password">
            <div class="form-help">{{ __('Leave blank when reusing the AI provider key. Leave the masked value unchanged to keep the current key.') }}</div>
        </div>
    </div>

    <div class="form-group">
        <label for="aiassistant_embedding_base_url" class="col-sm-2 control-label">{{ __('Embedding Base URL') }}</label>
        <div class="col-sm-6">
            <input id="aiassistant_embedding_base_url" type="url" class="form-control input-sized" name="settings[aiassistant.documentation.embedding_base_url]" value="{{ $settings['aiassistant.documentation.embedding_base_url'] }}" placeholder="{{ config('aiassistant.providers.digitalocean.base_url') }}">
            <div class="form-help">{{ __('Optional. Leave blank to use the selected embedding provider default.') }}</div>
        </div>
    </div>

    <div class="form-group">
        <label for="aiassistant_embedding_model" class="col-sm-2 control-label">{{ __('Embedding Model') }}</label>
        <div class="col-sm-6">
            <input id="aiassistant_embedding_model" type="text" class="form-control input-sized" name="settings[aiassistant.documentation.embedding_model]" value="{{ $settings['aiassistant.documentation.embedding_model'] }}" maxlength="255">
            <div class="form-help">{{ __('DigitalOcean default: qwen3-embedding-0.6b.') }}</div>
        </div>
    </div>

    <div class="form-group">
        <label for="aiassistant_chunk_size" class="col-sm-2 control-label">{{ __('Chunk Size') }}</label>
        <div class="col-sm-6">
            <input id="aiassistant_chunk_size" type="number" class="form-control input-sized" name="settings[aiassistant.documentation.chunk_size]" value="{{ $settings['aiassistant.documentation.chunk_size'] }}" min="500" max="20000">
        </div>
    </div>

    <div class="form-group">
        <label for="aiassistant_chunk_overlap" class="col-sm-2 control-label">{{ __('Chunk Overlap') }}</label>
        <div class="col-sm-6">
            <input id="aiassistant_chunk_overlap" type="number" class="form-control input-sized" name="settings[aiassistant.documentation.chunk_overlap]" value="{{ $settings['aiassistant.documentation.chunk_overlap'] }}" min="0" max="5000">
        </div>
    </div>

    <div class="form-group">
        <label for="aiassistant_retrieval_limit" class="col-sm-2 control-label">{{ __('Retrieval Limit') }}</label>
        <div class="col-sm-6">
            <input id="aiassistant_retrieval_limit" type="number" class="form-control input-sized" name="settings[aiassistant.documentation.retrieval_limit]" value="{{ $settings['aiassistant.documentation.retrieval_limit'] }}" min="1" max="20">
        </div>
    </div>


    <h3 class="subheader">{{ __('Customer Context') }}</h3>

    <div class="descr-block">
        <p>{{ __('Optionally configure a mailbox-specific URL that receives the customer email addresses when AI Assistant drafts a reply. The URL must accept JSON and return JSON.') }}</p>
    </div>

    @if (!empty($aiassistant_mailboxes) && count($aiassistant_mailboxes))
        @foreach ($aiassistant_mailboxes as $mailbox)
            @php
                $customerContextSettings = \Modules\AiAssistant\Services\CustomerContextService::getMailboxSettings($mailbox);
                $urlField = 'aiassistant_customer_context_urls.' . $mailbox->id;
                $secretField = 'aiassistant_customer_context_secret_keys.' . $mailbox->id;
                $signatureHeaderField = 'aiassistant_customer_context_signature_headers.' . $mailbox->id;
                $guidanceField = 'aiassistant_customer_context_guidance.' . $mailbox->id;
                $urlInputId = 'aiassistant_customer_context_url_' . $mailbox->id;
                $secretInputId = 'aiassistant_customer_context_secret_key_' . $mailbox->id;
                $signatureHeaderInputId = 'aiassistant_customer_context_signature_header_' . $mailbox->id;
                $guidanceInputId = 'aiassistant_customer_context_guidance_' . $mailbox->id;
            @endphp
            <div class="form-group{{ $errors->has($urlField) ? ' has-error' : '' }}">
                <label for="{{ $urlInputId }}" class="col-sm-2 control-label">{{ $mailbox->name }}</label>
                <div class="col-sm-6">
                    <input id="{{ $urlInputId }}" type="url" class="form-control input-sized-lg" name="aiassistant_customer_context_urls[{{ $mailbox->id }}]" value="{{ old($urlField, $customerContextSettings['url']) }}" maxlength="2048" placeholder="https://example.com/freescout/customer-context">
                    <div class="form-help">{{ __('Callback URL') }}: {{ $mailbox->email }}</div>
                    @include('partials/field_error', ['field' => $urlField])
                </div>
            </div>

            <div class="form-group{{ $errors->has($secretField) ? ' has-error' : '' }}">
                <label for="{{ $secretInputId }}" class="col-sm-2 control-label">{{ __('Secret Key') }}</label>
                <div class="col-sm-6">
                    <input id="{{ $secretInputId }}" type="text" class="form-control input-sized-lg" name="aiassistant_customer_context_secret_keys[{{ $mailbox->id }}]" value="{{ old($secretField, $customerContextSettings['secret_key']) }}" maxlength="255">
                    <div class="form-help">{{ __('The secret key used to generate a signature header. This can be used to verify the authenticity of the request.') }}</div>
                    @include('partials/field_error', ['field' => $secretField])
                </div>
            </div>

            <div class="form-group{{ $errors->has($signatureHeaderField) ? ' has-error' : '' }}">
                <label for="{{ $signatureHeaderInputId }}" class="col-sm-2 control-label">{{ __('Signature Header') }}</label>
                <div class="col-sm-6">
                    <select id="{{ $signatureHeaderInputId }}" class="form-control input-sized" name="aiassistant_customer_context_signature_headers[{{ $mailbox->id }}]">
                        <option value="X-FREESCOUT-SIGNATURE" {{ old($signatureHeaderField, $customerContextSettings['signature_header']) == 'X-FREESCOUT-SIGNATURE' ? 'selected' : '' }}>X-FREESCOUT-SIGNATURE</option>
                        <option value="X-HELPSCOUT-SIGNATURE" {{ old($signatureHeaderField, $customerContextSettings['signature_header']) == 'X-HELPSCOUT-SIGNATURE' ? 'selected' : '' }}>X-HELPSCOUT-SIGNATURE</option>
                    </select>
                    <div class="form-help">{{ __('Select the signature header to use. This is used to verify the authenticity of the request. Select X-HELPSCOUT-SIGNATURE if you are migrating from HelpScout.') }}</div>
                    @include('partials/field_error', ['field' => $signatureHeaderField])
                </div>
            </div>

            <div class="form-group{{ $errors->has($guidanceField) ? ' has-error' : '' }}">
                <label for="{{ $guidanceInputId }}" class="col-sm-2 control-label">{{ __('Reply Guidance') }}</label>
                <div class="col-sm-6">
                    <textarea id="{{ $guidanceInputId }}" class="form-control input-sized-lg" name="aiassistant_customer_context_guidance[{{ $mailbox->id }}]" rows="6" maxlength="6000" placeholder="{{ __('Example: We sell hosting services. Customers may ask about renewals, invoices, domains, and account access. In customer context, service_expiry means the date paid service ends.') }}">{{ old($guidanceField, $customerContextSettings['guidance']) }}</textarea>
                    <div class="form-help">{{ __('Optional mailbox-specific background for drafting replies. Explain who you are, what customers buy, important terminology, field meanings, and preferred reply style.') }}</div>
                    @include('partials/field_error', ['field' => $guidanceField])
                </div>
            </div>

            <div class="form-group">
                <label for="aiassistant_customer_context_test_email_{{ $mailbox->id }}" class="col-sm-2 control-label">{{ __('Test') }}</label>
                <div class="col-sm-6">
                    <div class="input-group input-sized-lg">
                        <input id="aiassistant_customer_context_test_email_{{ $mailbox->id }}" type="email" class="form-control aiassistant-customer-context-test-email" data-mailbox-id="{{ $mailbox->id }}" placeholder="{{ __('Customer email address') }}">
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default aiassistant-customer-context-test" data-mailbox-id="{{ $mailbox->id }}" data-test-url="{{ route('aiassistant.customer_context.test') }}" data-loading-text="{{ __('Testing') }}...">{{ __('Test') }}</button>
                        </span>
                    </div>
                    <div class="form-help">{{ __('Sends a signed test request using the URL, secret key, and signature header currently shown above.') }}</div>
                    <pre class="alert alert-info hidden aiassistant-customer-context-test-result" data-mailbox-id="{{ $mailbox->id }}"></pre>
                </div>
            </div>
        @endforeach
    @else
        <div class="form-group">
            <div class="col-sm-8 col-sm-offset-2">
                <div class="alert alert-info margin-bottom-0">
                    {{ __('Create a mailbox before configuring customer context URLs.') }}
                </div>
            </div>
        </div>
    @endif


    <h3 class="subheader">{{ __('Conversations') }}</h3>

    <div class="form-group margin-top">
        <label for="summarize_conversation_threshold" class="col-sm-2 control-label">{{ __('Summary Start') }}</label>
        <div class="col-sm-6">
            <select name="settings[aiassistant.summary_conversation_threshold]" class="form-control input-sized" id="summary_conversation_threshold">
                <option value="0" {{ $settings['aiassistant.summary_conversation_threshold'] == 0 ? 'selected' : '' }}>0</option>
                <option value="1" {{ $settings['aiassistant.summary_conversation_threshold'] == 1 ? 'selected' : '' }}>1</option>
                <option value="2" {{ $settings['aiassistant.summary_conversation_threshold'] == 2 ? 'selected' : '' }}>2</option>
                <option value="3" {{ $settings['aiassistant.summary_conversation_threshold'] == 3 ? 'selected' : '' }}>3</option>
                <option value="4" {{ $settings['aiassistant.summary_conversation_threshold'] == 4 ? 'selected' : '' }}>4</option>
                <option value="5" {{ $settings['aiassistant.summary_conversation_threshold'] == 5 ? 'selected' : '' }}>5</option>
                <option value="6" {{ $settings['aiassistant.summary_conversation_threshold'] == 6 ? 'selected' : '' }}>6</option>
                <option value="7" {{ $settings['aiassistant.summary_conversation_threshold'] == 7 ? 'selected' : '' }}>7</option>
                <option value="8" {{ $settings['aiassistant.summary_conversation_threshold'] == 8 ? 'selected' : '' }}>8</option>
                <option value="9" {{ $settings['aiassistant.summary_conversation_threshold'] == 9 ? 'selected' : '' }}>9</option>
                <option value="10" {{ $settings['aiassistant.summary_conversation_threshold'] == 10 ? 'selected' : '' }}>10</option>
            </select>
            <div class="form-help">Create summaries when a conversation has more than {{ $settings['aiassistant.summary_conversation_threshold'] }} messages.</div>
        </div>
    </div>

    <h3 class="subheader">{{ __('Threads') }}</h3>

    <div class="form-group margin-top">
        <label for="summarize_conversation_threshold" class="col-sm-2 control-label">{{ __('Language') }}</label>
        <div class="col-sm-6">
            <select name="settings[aiassistant.translation_language]" class="form-control input-sized" id="translation_language">
                <option value="en" {{ $settings['aiassistant.translation_language'] == 'en' ? 'selected' : '' }}>English</option>
                <hr>
                <option value="ms-Arab" {{ $settings['aiassistant.translation_language'] == 'ms-Arab' ? 'selected' : '' }}>Bahasa Melayu (Arab)</option>
                <option value="ms-Latn" {{ $settings['aiassistant.translation_language'] == 'ms-Latn' ? 'selected' : '' }}>Bahasa Melayu (Latin)</option>
                <option value="de" {{ $settings['aiassistant.translation_language'] == 'de' ? 'selected' : '' }}>Deutsch</option>
                <option value="es" {{ $settings['aiassistant.translation_language'] == 'es' ? 'selected' : '' }}>Español</option>
                <option value="fr" {{ $settings['aiassistant.translation_language'] == 'fr' ? 'selected' : '' }}>Français</option>
                <option value="el-monoton" {{ $settings['aiassistant.translation_language'] == 'el-monoton' ? 'selected' : '' }}>Greek (Monotonic)</option>
                <option value="el-polyton" {{ $settings['aiassistant.translation_language'] == 'el-polyton' ? 'selected' : '' }}>Greek (Polytonic)</option>
                <option value="it" {{ $settings['aiassistant.translation_language'] == 'it' ? 'selected' : '' }}>Italiano</option>
                <option value="mt" {{ $settings['aiassistant.translation_language'] == 'mt' ? 'selected' : '' }}>Malti</option>
                <option value="nl" {{ $settings['aiassistant.translation_language'] == 'nl' ? 'selected' : '' }}>Nederlands</option>
                <option value="no" {{ $settings['aiassistant.translation_language'] == 'no' ? 'selected' : '' }}>Norsk</option>
                <option value="pl" {{ $settings['aiassistant.translation_language'] == 'pl' ? 'selected' : '' }}>Polski</option>
                <option value="pt-PT" {{ $settings['aiassistant.translation_language'] == 'pt-PT' ? 'selected' : '' }}>Português (PT)</option>
                <option value="pt-BR" {{ $settings['aiassistant.translation_language'] == 'pt-BR' ? 'selected' : '' }}>Português (BR)</option>
                <option value="ro" {{ $settings['aiassistant.translation_language'] == 'ro' ? 'selected' : '' }}>Română</option>
                <option value="sk" {{ $settings['aiassistant.translation_language'] == 'sk' ? 'selected' : '' }}>Slovenčina</option>
                <option value="sl" {{ $settings['aiassistant.translation_language'] == 'sl' ? 'selected' : '' }}>Slovenščina</option>
                <option value="sv" {{ $settings['aiassistant.translation_language'] == 'sv' ? 'selected' : '' }}>Svenska</option>
                <option value="th" {{ $settings['aiassistant.translation_language'] == 'th' ? 'selected' : '' }}>ไทย</option>
                <option value="tr" {{ $settings['aiassistant.translation_language'] == 'tr' ? 'selected' : '' }}>Türkçe</option>
                <option value="uk" {{ $settings['aiassistant.translation_language'] == 'uk' ? 'selected' : '' }}>Українська</option>
                <option value="vi" {{ $settings['aiassistant.translation_language'] == 'vi' ? 'selected' : '' }}>Tiếng Việt</option>
                <option value="ru" {{ $settings['aiassistant.translation_language'] == 'ru' ? 'selected' : '' }}>Русский</option>
                <option value="ja" {{ $settings['aiassistant.translation_language'] == 'ja' ? 'selected' : '' }}>日本語</option>
                <option value="zh-Hans" {{ $settings['aiassistant.translation_language'] == 'zh-Hans' ? 'selected' : '' }}>简体中文</option>
                <option value="zh-Hant" {{ $settings['aiassistant.translation_language'] == 'zh-Hant' ? 'selected' : '' }}>繁體中文</option>
            </select>
            <div class="form-help">Translate threads to the selected language.</div>
        </div>
    </div>


    <div class="form-group margin-top-0 margin-bottom-0">
        <div class="col-sm-6 col-sm-offset-2">
            <button type="submit" class="btn btn-primary">
                {{ __('Save') }}
            </button>
        </div>
    </div>

</form>
