<form class="form-horizontal margin-top" method="POST" action="">
    {{ csrf_field() }}

    <input type="hidden" name="settings[dummy]" value="1" />


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
            <div class="form-help">Minimum number of messages to start summarizing a conversation.</div>
        </div>
    </div>

    <h3 class="subheader">{{ __('Threads') }}</h3>

    <div class="form-group margin-top">
        <label for="summarize_conversation_threshold" class="col-sm-2 control-label">{{ __('Language') }}</label>
        <div class="col-sm-6">
            <select name="settings[aiassistant.translation_language]" class="form-control input-sized" id="translation_language">
                <option value="en" {{ $settings['aiassistant.translation_language'] == 'en' ? 'selected' : '' }}>English</option>
                <option value="nl" {{ $settings['aiassistant.translation_language'] == 'nl' ? 'selected' : '' }}>Dutch</option>
                <option value="de" {{ $settings['aiassistant.translation_language'] == 'de' ? 'selected' : '' }}>German</option>
                <option value="fr" {{ $settings['aiassistant.translation_language'] == 'fr' ? 'selected' : '' }}>French</option>
                <option value="es" {{ $settings['aiassistant.translation_language'] == 'es' ? 'selected' : '' }}>Spanish</option>
                <option value="it" {{ $settings['aiassistant.translation_language'] == 'it' ? 'selected' : '' }}>Italian</option>
                <option value="pt" {{ $settings['aiassistant.translation_language'] == 'pt' ? 'selected' : '' }}>Portuguese</option>
                <option value="ru" {{ $settings['aiassistant.translation_language'] == 'ru' ? 'selected' : '' }}>Russian</option>
                <option value="zh" {{ $settings['aiassistant.translation_language'] == 'zh' ? 'selected' : '' }}>Chinese</option>
            </select>
            <div class="form-help">Language to use for translations.</div>
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