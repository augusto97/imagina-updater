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

    // API Key - Toggle edit mode
    $('#toggle_api_key_edit').on('click', function() {
        $('#api_key_hidden_wrapper').hide();
        $('#api_key_edit_wrapper').show();
        $('#api_key').focus();
        $('#api_key_description').hide();
    });

    // API Key - Cancel edit mode
    $('#cancel_api_key_edit').on('click', function() {
        $('#api_key_edit_wrapper').hide();
        $('#api_key_hidden_wrapper').show();
        $('#api_key').val(''); // Limpiar el campo
        $('#api_key_description').show();
    });
});
