<script>
    var billie_config_data = {
        'session_id': '<?php echo $billie_session; ?>',
        'merchant_name': '<?php echo get_bloginfo( 'name' ); ?>'
    };
    var checkBillieMount = false;
    var checkBillieGender = false;
    var billie_order_data<?php echo ' = ' . json_encode( $billie_order_data, JSON_PRETTY_PRINT ); ?>;

    function billieBlock() {
        jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table').block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
    }

    function billieUnblock() {
        jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table').unblock();
        checkBillieMount = false;
    }

    function isGatewayBillie(currentGateway) {
        return currentGateway === 'billie';
    }

    function getStreetAndHousenumber(fullStreet) {
        const split = fullStreet.split(' ');
        if(/^\d+[a-zA-Z]*$/.test(split[split.length - 1]) === false) {
            return [fullStreet, ''];
        }

        let name = split[0];
        for (var i = 1; i < split.length - 1; i++) {
            name += " " + split[i];
        }
        return [name, split[split.length - 1]];
    }

    function checkGender(salutation) {
        document.querySelectorAll('input[name="billing_gender"]').forEach((element) => {
            if (element.getAttribute('checked')) {
                salutation = element.value;
            }
        });
    }

    function prepare_billie_order_data() {
        let allRequiredFields = true;
        let salutation = 'm';
        checkGender(salutation)

        billie_order_data.debtor_person = {
            "salutation": salutation,
            "first_name": jQuery('#billing_first_name').val(),
            "last_name": jQuery('#billing_last_name').val(),
            "phone_number": jQuery('#billing_phone').val(),
            "email": jQuery('#billing_email').val(),
        };

        let billie_company_name = jQuery('#billing_company').val();
        if (billie_company_name === '') {
            billie_company_name = billie_order_data.debtor_person.last_name;
        }

        let address_street, address_house_number;
        const houseNumberField = jQuery('input[name="billie_house_number_billing"]');
        const streetField = jQuery('input[name="billie_street_number_billing"]');
        if (streetField.size() > 0 && houseNumberField.size() > 0) {
            address_street = jQuery('input[name="billie_street_number_billing"]').val();
            address_house_number = jQuery('input[name="billie_house_number_billing"]').val();
        } else {
            [address_street, address_house_number] = getStreetAndHousenumber(jQuery('#billing_address_1').val());
        }

        let address_city = jQuery('#billing_city').val();
        let address_postal_code = jQuery('#billing_postcode').val();

        let address_country = jQuery('#billing_country').val();
        let address_addition = jQuery('#billing_address_2').val();

        billie_order_data.debtor_company = {
            "name": billie_company_name,
            "established_customer": false,
            "address_street": address_street,
            "address_house_number": address_house_number,
            "address_city": address_city,
            "address_postal_code": address_postal_code,
            "address_country": address_country,
            "address_addition": address_addition
        };

        let use_shipping = jQuery('#ship-to-different-address-checkbox').is(":checked");
        let optionalFields = {};
        if (use_shipping) {
            let delivery_address_street, delivery_address_house_number;
            const houseNumberShippingField = jQuery('input[name="billie_house_number_shipping"]');
            const streetShippingField = jQuery('input[name="billie_street_number_shipping"]');
            if (streetShippingField.size() > 0 && houseNumberShippingField.size() > 0) {
                delivery_address_street = jQuery('input[name="billie_street_number_shipping"]').val();
                delivery_address_house_number = jQuery('input[name="billie_house_number_shipping"]').val();
            } else {
                [delivery_address_street, delivery_address_house_number] = getStreetAndHousenumber(jQuery('#shipping_address_1').val());
            }

            billie_order_data.delivery_address = {
                "street": delivery_address_street,
                "house_number": delivery_address_house_number,
                "city": jQuery('#shipping_city').val(),
                "postal_code": jQuery('#shipping_postcode').val(),
                "country": jQuery('#shipping_country').val(),
                "addition": jQuery('#shipping_address_2').val()
            };
            optionalFields.delivery_address = {
                'house_number': jQuery('#shipping_address_1'),
                'addition': jQuery('#shipping_address_2'),
            };
        } else {
            billie_order_data.delivery_address = {
                "street": address_street,
                "house_number": address_house_number,
                "city": address_city,
                "postal_code": address_postal_code,
                "country": address_country,
                "addition": address_addition
            };
            optionalFields.delivery_address = {
                'house_number': jQuery('#billing_address_1'),
                'addition': jQuery('#billing_address_2'),
            };
        }

        optionalFields.debtor_company = {
            'address_addition': jQuery('#billing_address_2'),
        };
        optionalFields.debtor_person = {
            'phone_number': jQuery('#billing_phone'),
        };

        let ignoreFields = [
            'address_addition',
            'addition',
            'phone_number',
            'address_house_number',
            'house_number',
        ];

        const notRequiredProps = [
            'address_house_number',
            'house_number'
        ];

        for (let key in billie_order_data) {
            // skip loop if the property is from prototype
            if (!billie_order_data.hasOwnProperty(key)) continue;

            let obj = billie_order_data[key];
            for (let prop in obj) {
                // skip loop if the property is from prototype
                if (!obj.hasOwnProperty(prop)) continue;
                let propName = prop;

                if (ignoreFields.includes(propName)) {
                    const object = optionalFields[key];
                    if (typeof object !== 'undefined') {
                        if (typeof object[propName] !== 'undefined') {
                            if (checkIfParentRequired(object[propName])) {
                                const index = ignoreFields.indexOf(propName);
                                ignoreFields.splice(index, 1);
                            }
                        }
                    }
                }

                if (!ignoreFields.includes(propName) && !notRequiredProps.includes(propName)) {
                    if (
                        typeof obj[propName] === 'undefined' || obj[propName] === '' ||
                        (propName === 'postal_code' && obj[propName].length !== 5) ||
                        (propName === 'address_postal_code' && obj[propName].length !== 5)
                    ) {
                        allRequiredFields = false;
                    }

                    if (use_shipping) {
                        const company_field = jQuery('#shipping_company');
                        if (checkIfParentRequired(company_field) && company_field.val() === '') {
                            allRequiredFields = false;
                        }

                        const salutation_field = jQuery('#shipping_salutation');
                        if (checkIfParentRequired(salutation_field) && salutation_field.val() === '') {
                            allRequiredFields = false;
                        }
                    }

                    const company_field = jQuery('#billing_company');
                    if (checkIfParentRequired(company_field) && company_field.val() === '') {
                        allRequiredFields = false;
                    }

                    const salutation_field = jQuery('#billing_salutation');
                    if (checkIfParentRequired(salutation_field) && salutation_field.val() === '') {
                        allRequiredFields = false;
                    }
                }
            }
        }

        const requiredCheckboxes = document.querySelectorAll('.validate-required input[type="checkbox"]');
        requiredCheckboxes.forEach((checkbox) => {
            if (typeof checkbox != 'undefined') {
                if (!checkbox.checked) {
                    allRequiredFields = false;
                }
            }
        });

        return allRequiredFields;
    }

    function checkIfParentRequired(element) {
        const parentParentElement = element.parent().parent();
        return parentParentElement.hasClass('validate-required');
    }

    function payWithBillie() {
        if (checkBillieMount) {
            return;
        }
        checkBillieMount = true;

        if (prepare_billie_order_data()) {
            BillieCheckoutWidget.mount({
                billie_config_data: billie_config_data,
                billie_order_data: billie_order_data
            })
            .then(function success(ao) {
                jQuery.post('<?php echo \Billie\Plugin::get_callback_url( 'ajax-billie-success' ); ?>', ao, function () {
                    jQuery('form.woocommerce-checkout').off('checkout_place_order');
                    if (jQuery('#confirm-order-flag').length !== 0) {
                        jQuery('#confirm-order-flag').val('');
                    }

                    jQuery('#place_order').parents('form').submit();
                });
                billieBlock()

                return true;
            })
            .catch(function failure(err) {
                jQuery.post('<?php echo \Billie\Plugin::get_callback_url( 'ajax-billie-error' ); ?>', err, function () {
                    jQuery(document.body).trigger('wc_update_cart');
                    jQuery(document.body).trigger('update_checkout');
                });
                console.log('Error occurred', err);
                billieUnblock();

                location.reload();

                return false;
            });

            return false;
        }

        return true;
    }

    function setBillieGenderFromGermanized(germanGender) {
        if(germanGender === '2') {
            jQuery('#billie_billing_gender_male').attr('checked', false);
            jQuery('#billie_billing_gender_female').attr('checked', true);
        } else {
            jQuery('#billie_billing_gender_male').attr('checked', true);
            jQuery('#billie_billing_gender_female').attr('checked', false);
        }
    }

    function setBillieGenderFromF4(gender) {
        if(gender === 'mrs') {
            jQuery('#billie_billing_gender_male').attr('checked', false);
            jQuery('#billie_billing_gender_female').attr('checked', true);
        } else {
            jQuery('#billie_billing_gender_male').attr('checked', true);
            jQuery('#billie_billing_gender_female').attr('checked', false);
        }
    }

    jQuery(document).ready(function () {
        jQuery(document.body).on('checkout_error', function () {
            let error_count = jQuery('.woocommerce-error li').length;

            jQuery('.woocommerce-error li').each(function () {
                let error_text = jQuery(this).text();
                if (error_text.includes('error_confirmation')) {
                    jQuery(this).css('display', 'none');

                    if (error_count === 1) {
                        jQuery(this).parent().css('display', 'none');

                        if (isGatewayBillie(jQuery('input[name=payment_method]:checked').val())) {
                            jQuery('html, body').stop();
                        }
                    }
                }
            });

            if (error_count === 1 || error_count === 0) {
                let result = true;
                if (isGatewayBillie(jQuery('input[name=payment_method]:checked').val())) {
                    billieBlock();
                    result = payWithBillie();
                    jQuery('html, body').stop();
                }

                if (result === true) billieUnblock();
            }
        });

        jQuery('form.woocommerce-checkout')
            .on('change', function () {
                if (isGatewayBillie(jQuery('input[name=payment_method]:checked').val())) {
                    prepare_billie_order_data();
                }
            })
            .on('checkout_place_order', function () {
                if (isGatewayBillie(jQuery('input[name=payment_method]:checked').val())) {
                    if (jQuery('#confirm-order-flag').length === 0) {
                        jQuery('form.woocommerce-checkout').append('<input type="hidden" id="confirm-order-flag" name="confirm-order-flag" value="1">');
                    }
                } else if (jQuery('#confirm-order-flag').length === 1) {
                    jQuery('#confirm-order-flag').val(0);
                }

                return true;
            }
        );

        const germanized_title_field = jQuery('#billing_title_field');
        const f4_title_field = jQuery('#billing_salutation_field');

        if(germanized_title_field.size() > 0) {
            setBillieGenderFromGermanized(jQuery('#billing_title').children("option:selected").val());
            jQuery('#billie_gender_fields').hide();
            germanized_title_field.on('change', function (event) {
                setBillieGenderFromGermanized(jQuery('#billing_title').children("option:selected").val());
            });
        }

        if(f4_title_field.size() > 0) {
            setBillieGenderFromF4(jQuery('#billing_salutation').children("option:selected").val());
            jQuery('#billie_gender_fields').hide();
            f4_title_field.on('change', function (event) {
                setBillieGenderFromF4(jQuery('#billing_salutation').children("option:selected").val());
            });
        }
    });
</script>
<style>
    #checkout_billie_logo {
        max-height: 2em;
    }
</style>
<p>
	<?php if ( ! isset( $this->settings['hide_logo'] ) || $this->settings['hide_logo'] === 'no' ): ?>
        <img id="checkout_billie_logo" src="<?php echo plugin_dir_url( __DIR__ ); ?>/billie_logo_large.svg" alt="Billie">
	<?php endif; ?>
	<?php echo nl2br( $this->method_description ); ?>
</p>
<?php if ( ! isset( $company ) || $company === '' ): ?>
    <p class="error">
		<?php _e( 'This payment option is only available for company customers.', 'billie' ); ?>
    </p>
<?php endif; ?>
<fieldset id="billie_gender_fields">
    <p class="form-row form-row-wide">
		<?php _e( 'Salutation', 'billie' ) ?>: <br>
        <label for="billie_billing_gender_female">
            <input type="radio" required id="billie_billing_gender_female" name="billing_gender" value="f">
			<?php _e( 'Female', 'billie' ) ?>
        </label>
        <label for="billie_billing_gender_male">
            <input type="radio" required id="billie_billing_gender_male" name="billing_gender" value="m">
			<?php _e( 'Male', 'billie' ) ?>
        </label>
    </p>
</fieldset>


