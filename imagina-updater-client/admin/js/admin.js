/**
 * Imagina Updater Client - Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Select/Deselect all plugins
    $('#select_all_plugins').on('change', function() {
        $('.plugin_checkbox').prop('checked', $(this).prop('checked'));
    });

    // Update select all checkbox based on individual checkboxes
    $('.plugin_checkbox').on('change', function() {
        var total = $('.plugin_checkbox').length;
        var checked = $('.plugin_checkbox:checked').length;

        $('#select_all_plugins').prop('checked', total === checked);
    });

    // API Key - Show/hide new API key field
    $('#change_api_key').on('change', function() {
        if ($(this).is(':checked')) {
            $('#new_api_key_wrapper').slideDown();
            $('#api_key').focus();
        } else {
            $('#new_api_key_wrapper').slideUp();
            $('#api_key').val(''); // Limpiar el campo
        }
    });
});
