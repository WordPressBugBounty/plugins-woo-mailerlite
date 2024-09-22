jQuery(document).ready(function ($) {

    /**
     * Output refresh groups icon
     */
    var settingGroups = $( '#woocommerce_mailerlite_group');

    if ( settingGroups.length !== 0 ) {

        var settingGroupsSelectContainer = settingGroups.next('.select2-container');

        $( '<span id="woo-ml-refresh-groups" class="woo-ml-icon-refresh" data-woo-ml-refresh-groups="true"></span>' ).insertAfter( settingGroupsSelectContainer );
    }

    /**
     * Handle refresh groups
     */
    var refreshGroupsRunning = false;

    $(document).on('click', '[data-woo-ml-refresh-groups]', function (event) {

        event.preventDefault();

        if ( refreshGroupsRunning ) // don't do anything if an AJAX request is running
            return;

        var refreshIcon = $(this);

        refreshIcon.removeClass('error');
        refreshIcon.addClass('running');
        refreshGroupsRunning = true;

        // Request
        jQuery.ajax({
            url: woo_ml_post.ajax_url,
            type: 'post',
            data: {
                action: 'post_woo_ml_refresh_groups'
            },
            success: function (response) {

                // Hide form if success
                if ( response.indexOf( "success" ) >= 0 ) {
                    //console.log('done!');
                    refreshIcon.removeClass('running');
                }

                if ( response ) {
                    // Redirect page in order to show refreshed list
                    location.reload();
                } else {
                    // Something went wrong
                    refreshIcon.addClass('error');
                }

                refreshGroupsRunning = false;
            }

        });
    });


    /**
     * Resets the re-synchronization for all orders
     */
    $(document).on('click', '[data-woo-ml-reset-orders-sync]', function(event) {

        event.preventDefault();

        jQuery.ajax({
            url: woo_ml_post.ajax_url,
            type: 'post',
            data: {
                action: 'post_woo_ml_reset_orders_sync'
            },
            async: false, // In order not to break our while due to async actions
            success: function (response) {

                window.location.reload();
            }

        });
    });

    /**
     * Synchronize untracked orders
     */
    var syncUntrackedOrdersRunning = false;
    var untrackedOrdersCount = 0;
    var untrackedOrdersLeft = 0;
    var untrackedOrdersCycle = 0;
    var untrackedOrdersProgressBar = $('#woo-ml-sync-untracked-orders-progress-bar');
    var untrackedOrdersProgress = 0;

    $(document).on('click', '[data-woo-ml-sync-untracked-orders]', function (event) {

        event.preventDefault();

        if ( syncUntrackedOrdersRunning ) // don't do anything if an AJAX request is running
            return;

        syncUntrackedOrdersRunning = true;

        console.log( 'Synchronize untracked orders' );

        // Elements
        var button = $(this);

        button.prop( 'disabled', true );
        untrackedOrdersProgressBar.show();

        // Prepare cycling
        untrackedOrdersCount = button.data('woo-ml-untracked-orders-count');
        untrackedOrdersLeft = button.data('woo-ml-untracked-orders-left');
        untrackedOrdersCycle = button.data('woo-ml-untracked-orders-cycle');

        // Debugging
        //untrackedOrdersCount = 20;
        //untrackedOrdersLeft = untrackedOrdersCount;

        console.log( 'untrackedOrdersCount >> ' + untrackedOrdersCount );
        console.log( 'untrackedOrdersLeft >> ' + untrackedOrdersLeft );
        console.log( 'untrackedOrdersCycle >> ' + untrackedOrdersCycle );

        while ( untrackedOrdersLeft > 0 ) {

            console.log( 'inside the loop!' );

                // Request
                jQuery.ajax({
                    url: woo_ml_post.ajax_url,
                    type: 'post',
                    data: {
                        action: 'post_woo_ml_sync_untracked_resources'
                    },
                    async: false, // In order not to break our while due to async actions
                    success: function (response) {

                        // Hide form if success
                        if ( response.indexOf( "success" ) >= 0 ) {
                            console.log('done!');
                        }

                        if ( response ) {
                            // Redirect page in order to show refreshed list
                            console.log('Response: True');
                            updateUntrackedOrdersProgress();
                        } else {
                            // Something went wrong
                            console.log('Response: False');
                        }

                        //refreshGroupsRunning = false;
                    }

                });
        }

        console.log( 'loop finished!' );

        button.hide();
        untrackedOrdersProgressBar.hide();
        $('#woo-ml-sync-untracked-orders-success').show();

    });


    /**
     * Synchronize untracked products
     */
    var syncUntrackedProductsRunning = false;
    var untrackedProductsCount = 0;
    var untrackedProductsLeft = 0;
    var untrackedProductsCycle = 0;
    var untrackedProductsProgressBar = $('#woo-ml-sync-untracked-products-progress-bar');
    var untrackedProductsProgress = 0;

    $(document).on('click', '[data-woo-ml-sync-untracked-products]', function (event) {

        event.preventDefault();

        if ( syncUntrackedProductsRunning ) // don't do anything if an AJAX request is running
            return;

        syncUntrackedProductsRunning = true;

        console.log( 'Synchronize untracked products' );

        // Elements
        var button = $(this);

        button.prop( 'disabled', true );
        untrackedProductsProgressBar.show();

        // Prepare cycling
        untrackedProductsCount = button.data('woo-ml-untracked-products-count');
        untrackedProductsLeft = button.data('woo-ml-untracked-products-left');
        untrackedProductsCycle = button.data('woo-ml-untracked-products-cycle');

        console.log( 'untrackedProductsCount >> ' + untrackedProductsCount );
        console.log( 'untrackedProductsLeft >> ' + untrackedProductsLeft );
        console.log( 'untrackedProductsCycle >> ' + untrackedProductsCycle );

        while ( untrackedProductsLeft > 0 ) {

            console.log( 'inside the loop! (products)' );

            // Request
            jQuery.ajax({
                url: woo_ml_post.ajax_url,
                type: 'post',
                data: {
                    action: 'post_woo_ml_sync_untracked_products'
                },
                async: false, // In order not to break our while due to async actions
                success: function (response) {

                    // Hide form if success
                    if ( response.indexOf( "success" ) >= 0 ) {
                        console.log('done!');
                    }

                    if ( response ) {
                        // Redirect page in order to show refreshed list
                        console.log('Response: True');
                        updateUntrackedProductsProgress();
                    } else {
                        // Something went wrong
                        console.log('Response: False');
                    }

                    //refreshGroupsRunning = false;
                }

            });
        }

        console.log( 'loop finished!' );

        button.hide();
        untrackedProductsProgressBar.hide();
        $('#woo-ml-sync-untracked-products-success').show();

    });

    function updateUntrackedOrdersProgress() {

        // Reduce orders left count
        untrackedOrdersLeft -= untrackedOrdersCycle;

        // Calculate progress
        untrackedOrdersProgress = ( ( 100 * ( untrackedOrdersCount - untrackedOrdersLeft ) ) / untrackedOrdersCount );

        // Update progress bar
        var progressBarInnerElement = untrackedOrdersProgressBar.find('div');

        progressBarInnerElement.css( 'width', untrackedOrdersProgress + '%' );
    }

    function updateUntrackedProductsProgress() {

        // Reduce orders left count
        untrackedProductsLeft -= untrackedProductsCycle;

        // Calculate progress
        untrackedProductsProgress = ( ( 100 * ( untrackedProductsCount - untrackedProductsLeft ) ) / untrackedProductsCount );

        // Update progress bar
        var progressBarInnerElement = untrackedProductsProgressBar.find('div');

        progressBarInnerElement.css( 'width', untrackedProductsProgress + '%' );
    }

});