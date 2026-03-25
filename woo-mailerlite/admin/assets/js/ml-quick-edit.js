jQuery(function ($) {
    const wpInlineEdit = inlineEditPost.edit;

    inlineEditPost.edit = function (id) {
        wpInlineEdit.apply(this, arguments);

        const postId = typeof id === 'object' ? parseInt(this.getId(id)) : id;

        const isIgnored = $('#ml_ignore_product_inline_' + postId)
            .find('._ml_ignore_product')
            .text()
            .trim();

        $('.inline-edit-row:visible')
            .find('input[name="ml_ignore_product"]')
            .prop('checked', isIgnored === 'yes');
    };
});