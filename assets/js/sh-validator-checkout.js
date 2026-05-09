(function () {
    'use strict';

    var shConfig = window.shCheckoutValidator || {};
    var shPhonePrefix = shConfig.phonePrefix || '+381';
    var shPhonePlaceholder = shConfig.phonePlaceholder || 'unesite broj u formatu 64 123 45 67';
    var shInvalidEmailMessage = shConfig.invalidEmailMessage || 'Unesite ispravnu email adresu.';
    var shEmailSuggestionPrefix = shConfig.emailSuggestionPrefix || 'Da li ste mislili: ';
    var shEmailSuggestionSuffix = shConfig.emailSuggestionSuffix || '?';
    var shInvalidPhoneMessage = shConfig.invalidPhoneMessage || 'Unesite ispravan broj telefona. Primer: +381641234567';
    var shCityPostalMap = shConfig.cityPostalMap || {};
    var shEmailTypos = shConfig.emailTypos || {};

    function shFindField(selector) {
        return document.querySelector(selector);
    }

    function shNormalizePhone(value) {
        var digitsOnly = String(value || '').replace(/\D+/g, '');

        if (!digitsOnly) {
            return shPhonePrefix;
        }

        if (digitsOnly.indexOf('00381') === 0) {
            digitsOnly = digitsOnly.slice(5);
        } else if (digitsOnly.indexOf('381') === 0) {
            digitsOnly = digitsOnly.slice(3);
        } else if (digitsOnly.indexOf('0') === 0) {
            digitsOnly = digitsOnly.replace(/^0+/, '');
        }

        digitsOnly = digitsOnly.replace(/^0+/, '');

        return shPhonePrefix + digitsOnly;
    }

    function shEnsureFeedbackNode(field, className) {
        if (!field || !field.parentNode) {
            return null;
        }

        var existingNode = field.parentNode.querySelector('.' + className);

        if (existingNode) {
            return existingNode;
        }

        var feedbackNode = document.createElement('div');
        feedbackNode.className = className;
        feedbackNode.style.display = 'none';
        field.parentNode.appendChild(feedbackNode);

        return feedbackNode;
    }

    function shSetFeedback(node, message) {
        if (!node) {
            return;
        }

        node.textContent = message || '';
        node.style.display = message ? 'block' : 'none';
    }

    function shGetEmailSuggestion(email) {
        var normalizedEmail = String(email || '').trim().toLowerCase();

        if (!normalizedEmail || normalizedEmail.indexOf('@') === -1) {
            return '';
        }

        var parts = normalizedEmail.split('@');

        if (parts.length !== 2 || !parts[0] || !parts[1]) {
            return '';
        }

        var localPart = parts[0];
        var domainPart = parts[1];

        if (shEmailTypos[domainPart]) {
            return localPart + '@' + shEmailTypos[domainPart];
        }

        var lastDot = domainPart.lastIndexOf('.');
        var tldMap = {
            co: 'com',
            cim: 'com',
            cm: 'com',
            cn: 'com',
            comm: 'com',
            con: 'com',
            ne: 'net',
            nett: 'net',
            og: 'org',
            ogr: 'org',
            rss: 'rs',
            rsss: 'rs'
        };

        if (lastDot === -1) {
            return '';
        }

        var domainName = domainPart.slice(0, lastDot);
        var tld = domainPart.slice(lastDot + 1);

        if (tldMap[tld]) {
            return localPart + '@' + domainName + '.' + tldMap[tld];
        }

        return '';
    }

    function shValidateEmailField(field) {
        if (!field) {
            return true;
        }

        var feedbackNode = shEnsureFeedbackNode(field, 'sh-checkout-validator-email-feedback');
        var rawValue = String(field.value || '').trim();
        var normalizedValue = rawValue.toLowerCase();
        var basicEmailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        field.value = rawValue;

        if (!rawValue) {
            field.setCustomValidity('');
            shSetFeedback(feedbackNode, '');
            return true;
        }

        var suggestion = shGetEmailSuggestion(normalizedValue);

        if (suggestion && suggestion !== normalizedValue) {
            field.setCustomValidity(shEmailSuggestionPrefix + suggestion + shEmailSuggestionSuffix);
            shSetFeedback(feedbackNode, shEmailSuggestionPrefix + suggestion + shEmailSuggestionSuffix);
            return false;
        }

        if (!basicEmailPattern.test(rawValue)) {
            field.setCustomValidity(shInvalidEmailMessage);
            shSetFeedback(feedbackNode, shInvalidEmailMessage);
            return false;
        }

        field.setCustomValidity('');
        shSetFeedback(feedbackNode, '');
        return true;
    }

    function shValidatePhoneField(field) {
        if (!field) {
            return true;
        }

        var feedbackNode = shEnsureFeedbackNode(field, 'sh-checkout-validator-phone-feedback');
        field.value = shNormalizePhone(field.value);

        if (field.value === shPhonePrefix || field.value.length < 11) {
            field.setCustomValidity(shInvalidPhoneMessage);
            shSetFeedback(feedbackNode, shInvalidPhoneMessage);
            return false;
        }

        field.setCustomValidity('');
        shSetFeedback(feedbackNode, '');
        return true;
    }

    function shSyncPostalCode() {
        var cityField = shFindField('#billing_city, select[name="billing_city"]');
        var postcodeField = shFindField('#billing_postcode, input[name="billing_postcode"]');

        if (!cityField || !postcodeField) {
            return;
        }

        var selectedCity = cityField.value || '';
        postcodeField.value = shCityPostalMap[selectedCity] || '';
    }

    function shInitPhoneField(field) {
        if (!field || field.dataset.shCheckoutValidatorPhoneReady === '1') {
            return;
        }

        field.dataset.shCheckoutValidatorPhoneReady = '1';
        field.setAttribute('placeholder', shPhonePlaceholder);
        field.setAttribute('inputmode', 'tel');
        field.value = String(field.value || '').trim() ? shNormalizePhone(field.value) : shPhonePrefix;

        field.addEventListener('input', function () {
            field.value = shNormalizePhone(field.value);
            shValidatePhoneField(field);
        });

        field.addEventListener('blur', function () {
            shValidatePhoneField(field);
        });
    }

    function shInitEmailField(field) {
        if (!field || field.dataset.shCheckoutValidatorEmailReady === '1') {
            return;
        }

        field.dataset.shCheckoutValidatorEmailReady = '1';
        field.addEventListener('input', function () {
            shValidateEmailField(field);
        });
        field.addEventListener('blur', function () {
            shValidateEmailField(field);
        });
    }

    function shInitCityField(field) {
        if (!field || field.dataset.shCheckoutValidatorCityReady === '1') {
            return;
        }

        field.dataset.shCheckoutValidatorCityReady = '1';
        field.addEventListener('change', shSyncPostalCode);
        shSyncPostalCode();
    }

    function shInitValidator() {
        shInitPhoneField(shFindField('#billing_phone, input[name="billing_phone"]'));
        shInitEmailField(shFindField('#billing_email, input[name="billing_email"]'));
        shInitCityField(shFindField('#billing_city, select[name="billing_city"]'));
    }

    document.addEventListener('DOMContentLoaded', function () {
        shInitValidator();

        document.addEventListener('submit', function (event) {
            var phoneField = shFindField('#billing_phone, input[name="billing_phone"]');
            var emailField = shFindField('#billing_email, input[name="billing_email"]');

            shSyncPostalCode();

            if (phoneField && !shValidatePhoneField(phoneField)) {
                event.preventDefault();
                event.stopPropagation();
                phoneField.focus();
                return;
            }

            if (emailField && !shValidateEmailField(emailField)) {
                event.preventDefault();
                event.stopPropagation();
                emailField.focus();
            }
        }, true);

        var observer = new MutationObserver(function () {
            shInitValidator();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
})();
