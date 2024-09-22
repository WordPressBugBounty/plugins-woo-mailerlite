var syncResourcesRequest = null;
var groupSaved = false;
jQuery(document).ready(function (a) {
    var c = a("#woocommerce_mailerlite_group");

    if (0 !== c.length) {
        var d = c.next(".select2-container");
        a('<span id="woo-ml-refresh-groups" class="woo-ml-icon-refresh" data-woo-ml-refresh-groups="true"></span>').insertAfter(d);
    }

    var e = !1;

    a(document).on("click", "[data-woo-ml-refresh-groups]", function (b) {
        if (b.preventDefault(), !e) {
            refreshGroups();
        }
    });

    let i = 0, j = a("#woo-ml-sync-untracked-resources-progress-bar");

    a(document).on("click", "[data-woo-ml-sync-untracked-resources]", function (c) {

        c.preventDefault();

        syncResources(this);
    });

    var syncResources = function (el) {

        var d = a(el);

        orders_tracked = d.data("woo-ml-untracked-resources-count");
        all_untracked_orders_left = d.data("woo-ml-untracked-resources-left");

        i = d.data("woo-ml-untracked-resources-cycle");

        fail = a('#woo-ml-sync-untracked-resources-fail');
        success = a("#woo-ml-sync-untracked-resources-success");

        let r = 0;

        console.log("inside the loop!");

        a.ajax({
            url: woo_ml_post.ajax_url,
            type: "post",
            beforeSend: function () {
                d.prop("disabled", !0);
                j.show();
                r++;
            },
            data: {
                action: "post_woo_ml_sync_untracked_resources",
                nonce: woo_ml_post.nonce,
            },
            async: 1,
            success: function (data) {

                var response = JSON.parse(data);

                if (response.allDone) {

                    console.log("done!");
                    console.log("Response: True");
                    d.hide();
                    j.hide();
                    fail.hide();
                    success.show();
                } else if (response.error) {

                    if (response.message) {
                        fail.html(response.message);
                    }

                    d.prop('disabled', 0);
                    j.hide();
                    fail.show();
                } else {

                    if (response.completed) {
                        let untracked = parseInt(d.data("woo-ml-untracked-resources-left")) - parseInt(response.completed);

                        d.data("woo-ml-untracked-resources-left", untracked);
                        d.html('Synchronize ' + untracked.toString() + ' untracked resources');
                    }

                    syncResources(el);
                }
            }
        });
    };

    a(document).on('click', '[data-woo-ml-reset-resources-sync]', function (event) {

        event.preventDefault();

        a(this).prop('disabled', true);
        a(this).text("Please wait. Do not close this window until the reset finishes.");

        resetResourcesSync();
    });

    var resetResourcesSync = function (cancelSync = false) {

        let data = {};
        data.action = 'post_woo_ml_reset_resources_sync';
        if(cancelSync) {
            data.cancelSync = true;
        }
        data.nonce = woo_ml_post.nonce;
        a.ajax({
            url: woo_ml_post.ajax_url,
            type: 'post',
            data: data,
            async: true,
            success: function (responseStr) {

                var response = responseStr;

                if (typeof responseStr === "string") {
                    response = parseJSON(responseStr);
                }

                if (response.allDone) {
                    window.location.reload();
                } else {

                    resetResourcesSync();
                }
            }
        });
    }

    var field = a("#woocommerce_mailerlite_api_key");

    a('<button id="woo-ml-validate-key" class="button-primary">Validate Key</button>').insertAfter(field);

    a(document).on("click", "#woo-ml-validate-key", function (b) {
        if (b.preventDefault(), !e) {
            var key = a("#woocommerce_mailerlite_api_key").val();
            a.ajax({
                url: woo_ml_post.ajax_url,
                type: "post",
                data: {
                    action: "post_woo_ml_validate_key",
                    nonce: woo_ml_post.nonce,
                    key: key
                },
                success: function(a) {
                    location.reload()
                }
            })
        }
    });

    if (a('#woocommerce_mailerlite_group').length > 0) {
        a('#woocommerce_mailerlite_group').select2();
    }

    if (a('#woocommerce_mailerlite_ignore_product_list').length > 0) {
        a('#woocommerce_mailerlite_ignore_product_list').select2();
    }

    if (a('#sync_fields').length > 0) {
        a('#sync_fields').select2({
            theme: 'mailerlite',
            closeOnSelect : false,
            allowHtml: true,
            allowClear: true,
            tags: false,
            templateSelection : function (tag, container){
                let is_default = a(tag.element).attr('ml-default');

                if (typeof is_default !== 'undefined' && is_default !== false){
                    a(container).addClass('ml-default-choice');
                    tag['ml-default'] = true;
                }
                return tag.text;
            },
        }).on('select2:unselecting', function(e){
            let is_default = a(e.params.args.data.element).attr('ml-default');

            if (typeof is_default !== 'undefined' && is_default !== false) {
                e.preventDefault();
            }
        });
    }

    var cs_field = a('#woocommerce_mailerlite_consumer_secret');

    if (0 !== cs_field.length) {
        var field_desc = cs_field.next(".description");
        field_desc.closest('tr').after(
            '<h2>Integration Details</h2>\
            <p class="section-description">Customize MailerLite integration for WooCommerce</p>');
    }

    var ml_platform = '';

    if (a('#ml_platform').length > 0) {
        ml_platform = a('#ml_platform').val();
    }

    var tracking_field = a('#woocommerce_mailerlite_popups');

    tracking_field.closest('tr').before(
        '<h2>Popups</h2>\
        <p class="section-description">Display pop-up subscribe forms created within MailerLite</p>');

    var button = a('[name="save"]');

    if (field.length !== 0 && (cs_field.length === 0 && ml_platform !== '2')) {
        button.hide();
    } else {
        button.show();
    }

    var ignored_p_field = a('#woocommerce_mailerlite_ignore_product_list');

    if (ignored_p_field.length !== 0) {
        ignored_p_field.closest('tr').before(
            '<h2>E-commerce Automations</h2>\
            <p class="section-description">Customize settings for your e-commerce automations created in MailerLite </p>'
        )
    }

    var auto_update_field = a('#woocommerce_mailerlite_auto_update_plugin');

    if (auto_update_field.length !== 0) {
        auto_update_field.closest('tr').before(
            '<h2>Plugin Updates</h2>\
            <p class="section-description">Customize settings for MailerLite plugin </p>'
        )
    }

    var refreshGroups = function () {
        var c = a(this);
        c.removeClass("error"), c.addClass("running");
        a.ajax({
            url: woo_ml_post.ajax_url,
            type: "post",
            dataType: 'JSON',
            data: {
                action: "post_woo_ml_refresh_groups",
                nonce: woo_ml_post.nonce,
            },
            success: function (res) {
                c.removeClass("running");

                let has_group = false;

                if (res.groups) {
                    a('#woocommerce_mailerlite_group').empty();

                    for (const [id, name] of Object.entries(res.groups)) {

                        if (res.current && parseInt(res.current) === parseInt(id)) {
                            has_group = true;
                        }

                        a('#woocommerce_mailerlite_group, #wooMlSubGroup').append(a('<option>', {
                            value: id,
                            text: name
                        }));
                    }
                }

                if (res.current && has_group) {
                    a('#woocommerce_mailerlite_group').val(res.current);
                }
            },
            error: function (x, status) {
                c.addClass("error");
            }
        });
    }

    var parseJSON = function (jsonStr) {
        try {
            var parsed = JSON.parse(jsonStr);

            if (parsed && typeof parsed === "object") {
                return parsed;
            }
        } catch (e) {
        }

        return false;
    }

    if (a('#woocommerce_mailerlite_api_key').length > 0) {
        refreshGroups();
    }

    (function ($) {
        var selectedGroup = null;

        if($('#selectedGroupValue').val()) {
            selectedGroup = $('#selectedGroupValue').val();
        }
        var errors = document.getElementsByClassName('woo-ml-wizard-error');
        groupSelectInput();
        $('.woo-ml-close, .close').on('click', function () {
            $('.woo-ml-wizard-modal').each(function(index, currentElement) {
                if(currentElement.style.display == 'block') {
                    currentElement.style.display = 'none';
                }
            });
        });

        $('#wooMlWizardApiKeyBtn').on('click', function (e) {

            let apiKey = $('#wooMlApiKey').val();
            if (apiKey.length == 0) {
                if(errors.length == 0 ) {
                    $('.api-key-input').after('<span id="" class="woo-ml-wizard-error">Enter API Key</span>');
                }
            } else {
                $('.woo-ml-wizard-error').remove();
                buttonLoadingState($(this));
                $.ajax({
                    url: woo_ml_post.ajax_url,
                    type: "post",
                    data: {
                        action: "post_woo_ml_validate_key",
                        nonce: woo_ml_post.nonce,
                        key: apiKey
                    },
                    success: function (a) {
                        buttonLoadingState($('#wooMlWizardApiKeyBtn'), true);
                        window.onbeforeunload = null;
                        window.location.reload();
                    },
                    error: function (b) {
                        buttonLoadingState($('#wooMlWizardApiKeyBtn'), true);
                        console.log(b);
                    },
                });

            }
        });
        $('#createGroupModal').on('click', function () {
            document.getElementById('wooMlWizardCreateGroupModal').style.display = "block";
        });

        $('#wooMlResetIntegration').on('click', function () {
            document.getElementById('wooMlResetIntegrationModal').style.display = "block";
        });

        $('#resetIntegration').on('submit', function() {
            buttonLoadingState($('#resetIntegrationBtn'));
        });

        $('#openDebugLog').on('click', function () {
            buttonLoadingState($(this));
            $.ajax({
                url: woo_ml_post.ajax_url,
                type: "post",
                data: {
                    action: "woo_ml_get_debug_log",
                    nonce: woo_ml_post.nonce
                },
                success: function (res) {

                    if(res.success) {
                        $('#debugLogLines').text(res.log.replaceAll("<br />", "\n"));
                        document.getElementById('openDebugLogModal').style.display = "block";
                    }
                    buttonLoadingState($('#openDebugLog'), true);
                    if(!res.success && res.message) {
                        $('.woo-ml-header').after(`
                                <div class="woo-ml-alert">
                                    <span class="woo-ml-closebtn"
                                          onClick="this.parentElement.style.display='none';">&times;</span>
                                    ${res.message ? res.message : "Something went wrong!"}
                                </div>
                            `);
                    }
                },
                error: function (x, status) {
                    buttonLoadingState($('#openDebugLog'), true);
                    console.log(x,status);
                }
            });

        });
        $('#skipResourceSync').on('click', function () {
            if(errors.length > 0 ) {
                $('.woo-ml-wizard-error').remove();
            }
            if(!$('#wooMlSubGroup').val()) {
                $('.select2-container--mailerlite').parent().after(`<span id="" class="woo-ml-wizard-error">Select group</span>`);
                return false;
            }
            $.ajax({
                url: woo_ml_post.ajax_url,
                type: "post",
                data: {
                    action: "woo_ml_skip_resource_sync",
                    group: $('#wooMlSubGroup').val(),
                    nonce: woo_ml_post.nonce
                },
                success: function (res) {
                    window.onbeforeunload = null;
                    location.reload();
                },
                error: function (x, status) {
                    console.log(x,status);
                }
            });
        });


        $('#createGroup').on('click', function () {
            $('.woo-ml-wizard-error').remove();
            if(!$('#wooMlCreateGroup').val()) {
                $('#wooMlCreateGroup').after(`<span id="" class="woo-ml-wizard-error">Enter group name</span>`);
                return false;
            }
            buttonLoadingState($(this));
            let modal = document.getElementById('wooMlWizardCreateGroupModal');
            modal.style.display = "block";
            $.ajax({
                url: woo_ml_post.ajax_url,
                type: "post",
                data: {
                    action: "woo_ml_create_group",
                    createGroup: $('#wooMlCreateGroup').val(),
                    nonce: woo_ml_post.nonce
                },
                success: function (res) {
                    if(res.data?.id) {
                        $('#wooMlSubGroup').append($('<option>', {
                            value: res.data.id,
                            text: res.data.name
                        }));
                        removeExistingErrors();
                        $('.woo-ml-header').after(`
                    <div class="woo-ml-alert-success">
                        <span class="woo-ml-closebtn-success"
                              onClick="this.parentElement.style.display='none';">&times;</span>
                        Group created successfully
                    </div>
                    `);
                        groupSelectInput(res.data.id);
                    } else if(res?.id) {
                        $('#wooMlSubGroup').append($('<option>', {
                            value: res.id,
                            text: res.name
                        }));
                        removeExistingErrors();
                        $('.woo-ml-header').after(`
                    <div class="woo-ml-alert-success">
                        <span class="woo-ml-closebtn-success"
                              onClick="this.parentElement.style.display='none';">&times;</span>
                        Group created successfully
                    </div>
                    `);
                        groupSelectInput(res.id);
                    }
                    if(res.error) {
                        if(errors.length > 0 ) {
                            $('.woo-ml-wizard-error').remove();
                        }
                        $('#wooMlCreateGroup').after(`<span id="" class="woo-ml-wizard-error">${res.error}</span>`);
                    } else {
                        modal.style.display = "none";
                    }
                    buttonLoadingState($('#createGroup'), true);
                },
                error: function (x, status) {
                    buttonLoadingState($('#createGroup'), true);
                    console.log(x,status);
                }
            });
        });
        $('#startImport').on('click', function () {
            if(errors.length > 0 ) {
                $('.woo-ml-wizard-error').remove();
            }
            if(!$('#wooMlSubGroup').val()) {
                $('.select2-container--mailerlite').parent().after(`<span id="" class="woo-ml-wizard-error">Select group</span>`);
                return false;
            } else if($('#consumerKey').length && !$('#consumerKey').val()) {
                $('#consumerKey').after(`<span id="" class="woo-ml-wizard-error">Enter consumer key</span>`);
                return false;
            } else if($('#consumerSecret').length && !$('#consumerSecret').val()) {
                $('#consumerSecret').after(`<span id="" class="woo-ml-wizard-error">Enter consumer secret</span>`);
                return false;
            }
            importResources();
        });

        $("input[name='disable_checkout_sync']").change(function() {
            if(this.checked) {
                $(this).parent().nextAll('.woo-ml-alert-warning-small').show();
            } else {
                $(this).parent().nextAll('.woo-ml-alert-warning-small').hide();
            }
        });

        $("input[name='checkout']").change(function() {
            let block = $(this).closest('.settings-block');
            let inputs = block.find('input, select');

            if(this.checked) {
                $('.checkout-settings-group').show();
                inputs.map(function(key, elem){
                    elem.disabled = false;
                });
            } else {
                inputs.map(function(key, elem){
                    $('.checkout-settings-group').hide();
                    if(elem.name !== 'checkout') {
                        elem.disabled = true;
                    }
                });
            }

        });

        $('#woo_ml_wizard_back_step_1').on('click', function(e) {
            window.location.href = window.location.href + '&step=1';
        });

        $('#updateSettingsForm').on('submit', function (e) {
            if(!$('#wooMlSubGroup').val()) {
                e.preventDefault();
                if(errors.length > 0 ) {
                    $('.woo-ml-wizard-error').remove();
                }
                $('.select2-container--mailerlite').parent().after(`<span id="" class="woo-ml-wizard-error mt-0-ml">Select group</span>`);

                $([document.documentElement, document.body]).animate({
                    scrollTop: $(".select2-container--mailerlite").parent().offset().top
                }, 1000);
                return false;
            }
            buttonLoadingState($("#updateSettingsBtn"));
            $(this).unbind('submit').submit();

        });
        const importResources = function(completed = 0) {
            let modal = document.getElementById('wooMlSyncModal');
            modal.style.display = "block";
            removeExistingErrors();
            if($('#consumerKey').length) {
                let error = false;
                $('.woo-ml-wizard-error').remove();
                if(!$('#consumerKey').val().startsWith('ck_')) {
                    error = true;
                    $('#consumerKey').after(`<span id="" class="woo-ml-wizard-error">Invalid consumer key</span>`);
                }
                if(!$('#consumerSecret').val().startsWith('cs_')) {
                    error = true;
                    $('#consumerSecret').after(`<span id="" class="woo-ml-wizard-error">Invalid consumer secret</span>`);
                }
                if(error) {
                    modal.style.display = "none";
                    return false;
                }
            }
            if(((selectedGroup !== $('#wooMlSubGroup').val()) || $('#consumerKey').length) && !groupSaved) {
                groupSaved = true;
                let data = {};
                if($('#consumerKey').length) {
                    data.consumer_key = $('#consumerKey').val();
                    data.consumer_secret = $('#consumerSecret').val();
                }

                data.action = "woo_ml_save_group";
                data.section = "mailerlite";
                data.group = jQuery('#wooMlSubGroup').val();
                data.nonce = woo_ml_post.nonce;
                let responseMessage = '';
                syncResourcesRequest = $.ajax({
                    url: woo_ml_post.ajax_url,
                    type: "post",
                    data: data,
                    success: function (res) {
                        if(res.error) {
                            $('.woo-ml-header').after(`
                                <div class="woo-ml-alert">
                                    <span class="woo-ml-closebtn"
                                          onClick="this.parentElement.style.display='none';">&times;</span>
                                    ${res.message ? res.message : "Something went wrong!"}
                                </div>
                            `);
                            modal.style.display = 'none';
                            return false;
                        }
                        selectedGroup = jQuery('#wooMlSubGroup').val();
                        $.ajax({
                            url: woo_ml_post.ajax_url,
                            type: "post",
                            xhr: function(){ return progressBar(completed)},
                            data: {
                                action: "post_woo_ml_sync_untracked_resources",
                                nonce: woo_ml_post.nonce,
                            },
                            success: function (res) {
                                let response = JSON.parse(res);
                                if (response.allDone) {
                                    console.log("done!");
                                    modal.style.display = "none";
                                    window.onbeforeunload = null;
                                    location.reload();
                                } else if (response.error) {
                                    let response = JSON.parse(res);
                                    if (response.message) {
                                        if (response.code === MESSAGES.ERROR_CODES.SUBSCRIBER_LIMIT_EXCEED) {
                                            responseMessage = MESSAGES.SYNC_MESSAGES.SUBSCRIBER_LIMIT_EXCEED
                                        }
                                    }
                                    removeExistingErrors();
                                    $('.woo-ml-header').after(`
                                    <div class="woo-ml-alert">
                                        <span class="woo-ml-closebtn"
                                              onClick="this.parentElement.style.display='none';">&times;</span>
                                        ${responseMessage ? responseMessage : "Something went wrong, please retry."}
                                    </div>
                                    `);
                                    modal.style.display = "none";
                                } else {
                                    importResources((response.completed ? response.completed : 0) + completed);
                                }
                            },
                            error: function (x) {
                                modal.style.display = "none";
                                console.log(x);
                            }
                        });
                    },
                    error: function (x, status) {
                        console.log(x,status);
                    }
                });
            } else {
                syncResourcesRequest = $.ajax({
                    url: woo_ml_post.ajax_url,
                    type: "post",
                    xhr: function(){ return progressBar(completed)},
                    data: {
                        action : "post_woo_ml_sync_untracked_resources",
                        nonce: woo_ml_post.nonce,
                    },
                    success: function (res) {
                        let response = JSON.parse(res);
                        if (response.allDone) {
                            console.log("done!");
                            modal.style.display = "none";
                            window.onbeforeunload = null;
                            location.reload();
                        } else if (response.error) {
                            let responseMessage = false;
                            if (response.message) {
                                if (response.code === MESSAGES.ERROR_CODES.SUBSCRIBER_LIMIT_EXCEED) {
                                    responseMessage = MESSAGES.SYNC_MESSAGES.SUBSCRIBER_LIMIT_EXCEED
                                }
                            }
                            removeExistingErrors();
                            $('.woo-ml-header').after(`
                            <div class="woo-ml-alert">
                                <span class="woo-ml-closebtn"
                                      onClick="this.parentElement.style.display='none';">&times;</span>
                                ${responseMessage ? responseMessage : "Something went wrong, please retry."}
                            </div>
                            `);
                            modal.style.display = "none";
                        } else {
                            importResources((response.completed ? response.completed : 0) + completed);
                        }
                    },
                    error: function (x) {
                        modal.style.display = "none";
                        console.log(x);
                    }
                });
            }

        };

        $('#confirmCancelSync').on('click', function () {
            buttonLoadingState($(this));
            syncResourcesRequest?.abort();
            window.location.reload();
        });

        function progressBar(completed = 0)
        {
            let xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener("progress", function (evt) {
                let percentage = ((100 * completed) / $('#totalUntrackedResources').val());
                if(percentage <= 100) {
                    $('#wooMlWizardProgress').css({
                        width:  percentage + '%'
                    });
                    $('#progressPercentage').text(Math.round(percentage) + '%');
                }
            }, false);
            return xhr;
        }



        function groupSelectInput(selected = false)
        {
            $('#wooMlSubGroup').select2({
                ajax: {
                    url: woo_ml_post.ajax_url,
                    method: "post",
                    data: function (params) {
                        var query = {
                            filter: params.term,
                            page: params.page || 1,
                            action: "post_woo_ml_refresh_groups",
                            nonce: woo_ml_post.nonce,
                        }
                        return query;
                    },
                    delay: 1000,
                    dataType: 'json',
                    processResults: function (data, params) {
                        console.log(params.page)
                        let groupSelect = [];
                        let pagination = false;
                        if (data.groups.data) {
                            for (let [key, value] of Object.entries(data.groups.data)) {
                                groupSelect.push({id : value.id, text: value.name});
                            }
                        }
                        if (data.groups.pagination?.next) {
                            const searchParams = new URL(data.groups.pagination.next).searchParams;
                            pagination = true;
                        } else if (data.groups.pagination) {
                            pagination = groupSelect.length;
                        }
                        return {
                            results: groupSelect,
                            pagination: {
                                more: pagination
                            }
                        };
                    }
                },
                theme: 'mailerlite'
            });
            if(selected) {
                $('#wooMlSubGroup').val(selected).trigger('change');
            }

        }

        $('#woo-ml-reset-integration').on('click', function () {
            $.ajax({
                url: woo_ml_post.ajax_url,
                type: "post",
                data: {
                    action: "woo_ml_reset_integration",
                    nonce: woo_ml_post.nonce,
                },
                success: function (res) {
                    window.onbeforeunload = null;
                    location.reload();
                },
                error: function (x) {
                    console.log(x);
                }
            });
        });

        const removeExistingErrors = function () {
            if (jQuery('.woo-ml-header').next().hasClass('woo-ml-alert')) {
                jQuery('.woo-ml-header').next().remove();
            }
            if (jQuery('.woo-ml-header').next().hasClass('woo-ml-alert-success')) {
                jQuery('.woo-ml-header').next().remove();
            }
        };

        function buttonLoadingState(elem, release = false)
        {
            elem.toggleClass('woo-ml-button-loading');
            if(release)
            {
                elem.attr('disabled', false);
            } else {
                elem.attr('disabled', true);
            }

        }

        $("#copyDebugLogToClipboard").click(async function(){
            $("#copyDebugLogToClipboard .no-icon-tooltip-ml-text").show();
            try {
                const text = document.getElementById("debugLogLines").textContent;
                await navigator.clipboard.writeText(text);
                console.log('Content copied to clipboard');
            } catch (err) {
                console.error('Failed to copy: ', err);
            }
            setTimeout(function() { $("#copyDebugLogToClipboard .no-icon-tooltip-ml-text").hide(); }, 2000);
        });
    })(jQuery);

});