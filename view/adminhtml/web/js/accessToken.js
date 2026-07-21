define(['jquery', 'domReady!'], function ($) {
    'use strict';

    var errorMessage = 'Error generating token.';

    /**
     * @param {Object} config
     * @param {String} config.url
     * @param {String} config.fieldId
     */
    return function (config) {
        var field = document.getElementById(config.fieldId);

        if (!field) {
            return;
        }

        $.ajax({
            url: config.url,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            field.value = response.success ? response.token : errorMessage;
        }).fail(function () {
            field.value = errorMessage;
        });
    };
});
