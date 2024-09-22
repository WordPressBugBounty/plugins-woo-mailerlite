(function($) {

    $('#the-list').on('click', '.editinline', function(){

        // extract metadata and put it as the value for the custom field form
        inlineEditPost.revert();

        // get post id
        var post_id = $(this).closest('tr').attr('id');
        post_id = post_id.replace("post-", "");

        // get ignore value
        var ignore_enabled = $( '#ml_ignore_product_inline_' + post_id + ' #_ml_ignore_product').text();

        $('#ml_ignore_product').prop('checked', false);

        // set checked if value is yes
        if (ignore_enabled === 'yes') {

            $('#ml_ignore_product').prop('checked', true);
        }
    });
})(jQuery);