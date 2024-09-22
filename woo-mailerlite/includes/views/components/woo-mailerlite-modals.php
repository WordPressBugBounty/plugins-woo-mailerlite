<!-- Create Group Modal -->
<div class="woo-ml-wizard-modal" id="wooMlWizardCreateGroupModal" role="dialog">
    <div class="woo-ml-wizard-modal-parent">
        <div class="woo-ml-wizard-modal-container">
            <div class="woo-ml-wizard-modal-content">
                <div class="woo-ml-wizard-modal-header">
                    <h2>Create new group</h2>
                    <span class="close"></span>
                </div>
                <div class="woo-ml-wizard-modal-body">
                    <div class="create-group-input">
                        <input id="wooMlCreateGroup" type="text" name="createGroup" placeholder="Enter group name">
                    </div>
                    <div class="modal-button-ml">
                        <button type="button" class="btn-secondary-ml woo-ml-close" style="margin-right: 12px;">Close</button>
                        <button id="createGroup" type="button" class="btn-primary-ml"><span class="woo-ml-button-text">Create group</span></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Create Group Modal -->

<!-- Sync Resources Modal -->
<div class="woo-ml-wizard-modal" id="wooMlSyncModal" role="dialog">
    <div class="woo-ml-wizard-modal-parent">
        <div class="woo-ml-wizard-modal-container">
            <div class="woo-ml-wizard-modal-content woo-ml-text-center">
                <div class="woo-ml-wizard-modal-header">
                    <h2>Synchronizing subscribers</h2>
                </div>
                <p style="line-height: 1.75rem; font-size: 14px; text-align: left; margin-bottom: 24px;">
                    This initial import will send product details, product categories, and customer data to MailerLite. It will also enable real-time synchronization on future updates. Please note that only subscribers who opt-in to receive email marketing will be added.
                </p>
                <div class="progress-box">
                    <div class="progress">
                        <div id="wooMlWizardProgress"></div>
                    </div>
                    <div style=" text-align: center; "><span id="progressPercentage">0%</span></div>
                </div>
                <h4>Total resources to sync: <?php echo max($total_untracked_resources ?? 0, 0); ?></h4>
                <div class="woo-ml-wizard-modal-body">
                    <div class="modal-button-ml">
                        <button onclick="document.getElementById('wooMlCancelSyncModal').style.display = 'block'" type="button" class="btn-secondary-ml">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Sync Resources Modal -->

<!-- Cancel Sync Resources Modal -->
<div class="woo-ml-wizard-modal" id="wooMlCancelSyncModal" role="dialog">
    <div class="woo-ml-wizard-modal-parent">
        <div class="woo-ml-wizard-modal-container">
            <div class="woo-ml-wizard-modal-content woo-ml-text-center">
                <div class="woo-ml-wizard-modal-header">
                    <h2>Terminate import</h2>
                </div>
                <p style="line-height: 1.75rem; font-size: 14px; text-align: left; margin-bottom: 0;">
                    Are you sure you want to stop the import process? The import will be reset, and the progress made so far will be lost. Click 'Terminate' if you wish to continue.
                </p>
                <div class="woo-ml-wizard-modal-body">
                    <div class="modal-button-ml">
                        <button onclick="document.getElementById('wooMlCancelSyncModal').style.display='none'" type="button" class="btn-secondary-ml" style="margin-right: 12px;">Close</button>
                        <button id="confirmCancelSync" type="button" class="btn-danger-ml" style="margin-right: 12px;"><span class="woo-ml-button-text">Terminate</span></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Cancel Sync Resources Modal -->

<!-- Reset Integration Modal -->
<div class="woo-ml-wizard-modal" id="wooMlResetIntegrationModal" role="dialog">
    <div class="woo-ml-wizard-modal-parent">
        <div class="woo-ml-wizard-modal-container">
            <div class="woo-ml-wizard-modal-content woo-ml-text-center">
                <div class="woo-ml-wizard-modal-header">
                    <h2>Reset integration</h2>
                </div>
                <p style="line-height: 1.75rem; font-size: 14px; text-align: left; margin-bottom: 0;">
                    Are you sure you want to reset the integration? Click 'Reset' if you wish to continue.
                </p>
                <div class="woo-ml-wizard-modal-body">
                    <div class="modal-button-ml">
                        <form id="resetIntegration" method="post">
                            <?php wp_nonce_field('ml_reset_integration'); ?>
                            <input type="hidden" name="resetIntegration"/>
                            <button onclick="document.getElementById('wooMlResetIntegrationModal').style.display='none'" type="button" class="btn-secondary-ml" style="margin-right: 12px;">Close</button>
                            <button id="resetIntegrationBtn" class="btn-danger-ml" style="margin-right: 12px;"><span class="woo-ml-button-text">Reset</span></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Reset Integration Modal -->

<!-- Reset Integration Modal -->
<div class="woo-ml-wizard-modal" id="openDebugLogModal" role="dialog">
    <div class="woo-ml-wizard-modal-parent">
        <div class="woo-ml-wizard-modal-container">
            <div class="woo-ml-wizard-modal-content woo-ml-text-center">
                <div class="woo-ml-wizard-modal-header">
                    <h2>Debug logs</h2>
                    <p style="line-height: 1.75rem; font-size: 14px; text-align: left; margin-bottom: 0;">
                        In case you have problems with our application's performance, please click 'Copy to clipboard' and share the logs with our support team using <a href="https://www.mailerlite.com/contact-us?category=integrations-api" target="_blank" class="settings-label-medium">this contact form</a>.
                    </p>
                </div>
                <div class="woo-ml-wizard-modal-body">
                    <pre style="max-height: 500px;text-wrap:balance;overflow: auto;line-height: 1.75rem; font-size: 14px; text-align: left; margin-bottom: 0;margin-top: 32px;" id="debugLogLines">
                    </pre>
                    <div class="modal-button-ml">
                        <button onclick="document.getElementById('openDebugLogModal').style.display='none'" type="button" class="btn-secondary-ml" style="margin-right: 12px;">Close</button>
                        <button id="copyDebugLogToClipboard" type="button" class="btn-primary-ml no-icon-tooltip-ml" style="margin-right: 12px;"><span class="no-icon-tooltip-ml-text">Copied</span><span class="woo-ml-button-text">Copy to clipboard</span></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Reset Integration Modal -->
