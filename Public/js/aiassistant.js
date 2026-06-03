(function ($) {
    'use strict';

    if (!$) {
        return;
    }

    if (window.console && window.console.debug) {
        window.console.debug('AI Assistant UI loaded');
    }

    function draftTextToHtml(text) {
        return $('<div/>').text(text || '').html().replace(/\n/g, '<br>');
    }

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content')
            || $('.form-reply input[name="_token"]:first').val()
            || '';
    }

    function draftUrl($trigger, $panel) {
        return $trigger.data('draft-url') || $panel.data('draft-url') || '';
    }

    function ensurePanel() {
        var $panel = $('.ai-assistant-draft-panel:first');

        if ($panel.length) {
            return $panel;
        }

        var $replyBlock = $('.conv-reply-block:first');

        if (!$replyBlock.length) {
            return $();
        }

        $panel = $(
            '<div class="ai-assistant-draft-panel hidden">' +
                '<div class="ai-assistant-draft-header">' +
                    '<strong>AI Draft</strong>' +
                    '<span class="ai-assistant-draft-meta"></span>' +
                '</div>' +
                '<div class="ai-assistant-draft-status text-muted"></div>' +
                '<pre class="ai-assistant-draft-body hidden"></pre>' +
                '<div class="ai-assistant-draft-actions hidden">' +
                    '<button type="button" class="btn btn-primary btn-sm ai-assistant-insert-draft">Insert into Reply</button> ' +
                    '<button type="button" class="btn btn-default btn-sm ai-assistant-regenerate-draft">Regenerate</button>' +
                '</div>' +
                '<div class="ai-assistant-draft-notes hidden"><strong>Staff Notes</strong><ul></ul></div>' +
                '<div class="ai-assistant-draft-docs hidden"><strong>Documentation Used</strong><ul></ul></div>' +
            '</div>'
        );

        $replyBlock.append($panel);

        return $panel;
    }

    function resetPanel($panel) {
        $panel.find('.ai-assistant-draft-meta').text('');
        $panel.find('.ai-assistant-draft-status').removeClass('text-danger').addClass('text-muted').text('');
        $panel.find('.ai-assistant-draft-body').addClass('hidden').text('');
        $panel.find('.ai-assistant-draft-actions').addClass('hidden');
        $panel.find('.ai-assistant-draft-notes').addClass('hidden').find('ul').empty();
        $panel.find('.ai-assistant-draft-docs').addClass('hidden').find('ul').empty();
        $panel.removeData('draft-text');
    }

    function showReplyFormForDraft() {
        var $replyBlock = $('.conv-reply-block:first');

        if ($replyBlock.hasClass('hidden') || $replyBlock.hasClass('conv-note-block') || $replyBlock.hasClass('conv-forward-block')) {
            if (typeof window.prepareReplyForm === 'function') {
                window.prepareReplyForm();
            }
            if (typeof window.showReplyForm === 'function') {
                window.showReplyForm();
            } else {
                $('.conv-reply:first').trigger('click');
            }
        }
    }

    function renderDraft($panel, response) {
        resetPanel($panel);

        var draft = response.draft || '';
        $panel.data('draft-text', draft);
        $panel.find('.ai-assistant-draft-meta').text((response.language || 'unknown') + ' · ' + (response.confidence || 'unknown'));
        $panel.find('.ai-assistant-draft-status').text(response.documentation_status || '');
        $panel.find('.ai-assistant-draft-body').removeClass('hidden').text(draft);
        $panel.find('.ai-assistant-draft-actions').removeClass('hidden');

        if (response.staff_notes && response.staff_notes.length) {
            var $notes = $panel.find('.ai-assistant-draft-notes').removeClass('hidden').find('ul');
            $.each(response.staff_notes, function (i, note) {
                $('<li/>').text(note).appendTo($notes);
            });
        }

        if (response.retrieved_documents && response.retrieved_documents.length) {
            var $docs = $panel.find('.ai-assistant-draft-docs').removeClass('hidden').find('ul');
            $.each(response.retrieved_documents, function (i, doc) {
                var $item = $('<li/>');
                $('<span/>').text((doc.score || '') + ' · ' + (doc.title || '') + ' ').appendTo($item);
                $('<a/>').attr('href', doc.url || '#').attr('target', '_blank').text(doc.url || '').appendTo($item);
                $item.appendTo($docs);
            });
        }
    }

    function showDraftError($panel, message, detail) {
        resetPanel($panel);
        $panel.removeClass('hidden');
        $panel.find('.ai-assistant-draft-status').removeClass('text-muted').addClass('text-danger').text(message || 'Could not draft reply.');

        if (detail) {
            $panel.find('.ai-assistant-draft-body').removeClass('hidden').text(detail);
        }
    }

    function ajaxErrorMessage(xhr) {
        if (xhr.responseJSON) {
            if (xhr.responseJSON.msg) {
                return xhr.responseJSON.msg;
            }

            if (xhr.responseJSON.error && xhr.responseJSON.error.detail) {
                return xhr.responseJSON.error.detail;
            }
        }

        if (xhr.status) {
            return 'Request failed with HTTP ' + xhr.status + (xhr.statusText ? ' (' + xhr.statusText + ')' : '') + '.';
        }

        return 'Could not draft reply.';
    }

    function ajaxErrorDetail(xhr) {
        if (xhr.responseJSON && xhr.responseJSON.error) {
            var error = xhr.responseJSON.error;
            var parts = [];

            if (error.type) {
                parts.push('Type: ' + error.type);
            }

            if (error.detail) {
                parts.push('Detail: ' + error.detail);
            }

            if (parts.length) {
                return parts.join('\n');
            }
        }

        if (xhr.responseText && xhr.responseText.length < 1200) {
            return xhr.responseText;
        }

        return '';
    }

    function testCustomerContext($trigger) {
        var mailboxId = $trigger.data('mailbox-id');
        var url = $trigger.data('test-url') || '';
        var $result = $('.aiassistant-customer-context-test-result[data-mailbox-id="' + mailboxId + '"]');
        var email = $('.aiassistant-customer-context-test-email[data-mailbox-id="' + mailboxId + '"]').val() || '';
        var contextUrl = $('input[name="settings[aiassistant.customer_context_url][' + mailboxId + ']"]').val() || '';
        var secretKey = $('input[name="settings[aiassistant.customer_context_secret_key][' + mailboxId + ']"]').val() || '';
        var signatureHeader = $('select[name="settings[aiassistant.customer_context_signature_header][' + mailboxId + ']"]').val() || 'X-FREESCOUT-SIGNATURE';

        if (!url) {
            $result.removeClass('hidden alert-info').addClass('alert-danger').text('Test endpoint is not available.');
            return;
        }

        $trigger.button('loading');
        $result.removeClass('hidden alert-danger').addClass('alert-info').text('Testing...');

        $.ajax({
            method: 'POST',
            url: url,
            data: {
                _token: csrfToken(),
                mailbox_id: mailboxId,
                email: email,
                url: contextUrl,
                secret_key: secretKey,
                signature_header: signatureHeader
            },
            success: function (response) {
                var output = '';

                if (!response || response.status !== 'success') {
                    $result.removeClass('alert-info').addClass('alert-danger').text(response && response.msg ? response.msg : 'Could not test customer context URL.');
                    return;
                }

                output += 'HTTP ' + response.http_status + '\n';
                output += response.signature_header + ': ' + response.signature + '\n\n';
                output += response.raw_response || '';

                $result.removeClass('alert-danger').addClass('alert-info').text(output);
            },
            error: function (xhr) {
                $result.removeClass('alert-info').addClass('alert-danger').text(ajaxErrorMessage(xhr) + (ajaxErrorDetail(xhr) ? '\n\n' + ajaxErrorDetail(xhr) : ''));
            },
            complete: function () {
                $trigger.button('reset');
            }
        });
    }

    function generateDraft($trigger) {
        var $panel = ensurePanel();
        var url = draftUrl($trigger, $panel);

        if (!$panel.length || !url) {
            if (window.showFloatingAlert) {
                window.showFloatingAlert('error', 'AI Assistant draft UI is not available.', true);
            }
            return;
        }

        showReplyFormForDraft();
        $panel.removeClass('hidden');
        resetPanel($panel);
        $panel.find('.ai-assistant-draft-status').text('Drafting...');

        $.ajax({
            method: 'POST',
            url: url,
            data: {
                _token: csrfToken(),
                document_limit: 5
            },
            success: function (response) {
                if (!response || response.status !== 'success') {
                    var message = response && response.msg ? response.msg : 'Could not draft reply.';
                    var detail = response && response.error && response.error.detail ? response.error.detail : '';
                    showDraftError($panel, message, detail);
                    return;
                }

                renderDraft($panel, response);
            },
            error: function (xhr) {
                showDraftError($panel, ajaxErrorMessage(xhr), ajaxErrorDetail(xhr));
            }
        });
    }

    $(document).on('click', '.ai-assistant-draft-action, .ai-assistant-regenerate-draft', function (event) {
        event.preventDefault();
        event.stopPropagation();
        generateDraft($(this));
    });

    $(document).on('click', '.aiassistant-customer-context-test', function (event) {
        event.preventDefault();
        testCustomerContext($(this));
    });

    $(document).on('keydown', '.ai-assistant-draft-action', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            generateDraft($(this));
        }
    });

    $(document).on('click', '.ai-assistant-insert-draft', function (event) {
        event.preventDefault();

        var $panel = $(this).closest('.ai-assistant-draft-panel');
        var draft = $panel.data('draft-text') || '';

        if (!draft) {
            return;
        }

        showReplyFormForDraft();

        if (typeof window.setReplyBody === 'function') {
            window.setReplyBody(draftTextToHtml(draft));
        } else if ($('#body').length && $('#body').data('summernote')) {
            $('#body').summernote('code', draftTextToHtml(draft));
        } else {
            $('#body').val(draft);
        }
    });
})(window.jQuery);
