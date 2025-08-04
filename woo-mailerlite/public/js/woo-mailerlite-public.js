jQuery(document).ready(function(a) {
    const allowedInputs = ['billing_email', 'billing_first_name', 'email', 'billing_last_name', 'woo_ml_subscribe', 'billing-first_name', 'billing-last_name', 'shipping-first_name', 'shipping-last_name'];
    let email = null;
    let firstName = null;
    let lastName = null;
    let foundEmail = false;
    triggerAddEvents();
    if (document.querySelector('[data-block-name="woocommerce/checkout"]')) {
        window.mailerlitePublicJsCaptured = false
        return
    } else {
        window.mailerlitePublicJsCaptured = true
    }

    var execute;
    if (wooMailerLitePublicData.checkboxSettings.preselect) {
        jQuery('#woo_ml_subscribe').prop('checked', true);
    }


    const interval = setInterval(() => {
        const emailInput = document.getElementById('email');
        if (emailInput) {
            triggerAddEvents()
            clearInterval(interval);
        }
    }, 100);
    function triggerAddEvents() {

        allowedInputs.forEach((val, key) => {
            if (!foundEmail && val.match('email')) {
                email = document.querySelector('#' + val)
                if (email) {
                    foundEmail = true;
                }
            }

            if (val.match('first_name')) {
                if (document.querySelector('#' + val)) {
                    firstName = document.querySelector('#' + val);
                }
            }
            if (val.match('last_name')) {
                if (document.querySelector('#' + val)) {
                    lastName = document.querySelector('#' + val);
                }
            }
        })

        const signup = document.querySelector('#woo_ml_subscribe');
        if (email !== null && !email.form?.querySelector('#woo_ml_subscribe')) {
            if (document.getElementById('woo_ml_subscribe')) {
                return false;
            }
            const checkboxWrapper = document.createElement('div');
            checkboxWrapper.className = 'woo-ml-subscribe-wrapper';
            checkboxWrapper.style.marginTop = '15px';
            const wooMlCheckoutCheckbox = document.createElement('input');
            wooMlCheckoutCheckbox.setAttribute('id', 'woo_ml_subscribe');
            wooMlCheckoutCheckbox.setAttribute('type', 'checkbox');
            wooMlCheckoutCheckbox.setAttribute('value', wooMailerLitePublicData.checkboxSettings.preselect ? 1 : 0);
            wooMlCheckoutCheckbox.setAttribute('checked', wooMailerLitePublicData.checkboxSettings.preselect ? 'checked' : '');


            if (!wooMailerLitePublicData.checkboxSettings.hidden) {
                const label = document.createElement('label');
                label.htmlFor = 'woo-ml-subscribe-checkbox';
                label.textContent = wooMailerLitePublicData.checkboxSettings.label ?? 'Yes, I want to receive your newsletter.';
                checkboxWrapper.appendChild(wooMlCheckoutCheckbox);
                checkboxWrapper.appendChild(label);
                // Insert the container after the email field’s wrapper
                // email.closest('p').insertAdjacentElement('afterend', container);
            }
            const wrapper = email.closest('div') ?? email;
            wrapper.parentNode.insertBefore(checkboxWrapper, wrapper.nextSibling);
            // email.insertAdjacentElement('afterend', wooMlCheckoutCheckbox);

            triggerAddEvents();
        }

        if (email !== null) {
            email.addEventListener('change', (event) => {
                validateMLSub(event);
            });
        }

        if (firstName !== null) {
            firstName.addEventListener('change', (event) => {

                if(firstName.value.length > 0) {
                    validateMLSub(event);
                }
            });
        }

        if (lastName !== null) {
            lastName.addEventListener('change', (event) => {
                if(lastName.value.length > 0) {
                    validateMLSub(event);
                }
            });
        }

        if (signup !== null) {
            a(document).on('change', signup, function(event) {
                if (event.target.id === 'woo_ml_subscribe') {
                    validateMLSub(event);
                }
            });
        }
    }

    function validateMLSub(e) {
        if(email !== null && email.value.length > 0) {
            checkoutMLSub(e);
        }
    }

    function checkoutMLSub(e) {
        clearTimeout(execute);
        execute = setTimeout(() => {
            if (!allowedInputs.includes(e.target.id)) {
                return false;
            }
            /** set cookie before sending request to server
             * since multiple checkout update requests can be sent
             * and server cookies won't get updated, so send the saved
             * cookie as a request parameter
             **/

            if (!getCookie('mailerlite_checkout_token')) {
                var now = new Date();
                now.setTime(now.getTime() + 48 * 3600 * 1000);
                document.cookie = `mailerlite_checkout_token=${(+new Date).toString()}; expires=${now.toUTCString()}; path=/`;
            }

            const accept_marketing = document.querySelector('#woo_ml_subscribe').checked;

            jQuery.ajax({
                url: wooMailerLitePublicData.ajaxUrl,
                type: "post",
                data: {
                    action: "woo_mailerlite_set_cart_email",
                    email: email.value ?? null,
                    signup: accept_marketing,
                    language: wooMailerLitePublicData.language,
                    name: firstName.value ?? '',
                    last_name: lastName.value ?? '',
                    cookie_mailerlite_checkout_token:getCookie('mailerlite_checkout_token')
                }
            });
        }, 2);
    }
});

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        return parts.pop().split(';').shift()
    }
    return null;
}