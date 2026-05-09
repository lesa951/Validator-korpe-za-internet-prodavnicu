<?php

if (!defined('ABSPATH')) {
    exit;
}

class SH_Validator_Checkout
{
    private $repository;
    private $settings;

    public function __construct(SH_Validator_Repository $repository, SH_Validator_Settings $settings)
    {
        $this->repository = $repository;
        $this->settings = $settings;
    }

    public function sh_register()
    {
        add_filter('woocommerce_checkout_fields', array($this, 'sh_customize_checkout_fields'));
        add_filter('woocommerce_checkout_posted_data', array($this, 'sh_normalize_posted_data'));
        add_action('woocommerce_after_checkout_validation', array($this, 'sh_validate_checkout_data'), 10, 2);
    }

    public function sh_customize_checkout_fields($fields)
    {
        if (!class_exists('WooCommerce')) {
            return $fields;
        }

        if (!isset($fields['billing']) || !is_array($fields['billing'])) {
            $fields['billing'] = array();
        }

        $fields['billing']['billing_city'] = array(
            'type' => 'select',
            'label' => __('Grad', 'sh-validator-korpe'),
            'required' => true,
            'class' => array('form-row-wide', 'update_totals_on_change'),
            'input_class' => array('sh-city-search', 'wc-enhanced-select'),
            'priority' => 70,
            'options' => $this->repository->sh_get_city_options(),
        );

        if (!isset($fields['billing']['billing_postcode']) || !is_array($fields['billing']['billing_postcode'])) {
            $fields['billing']['billing_postcode'] = array();
        }

        if (empty($fields['billing']['billing_postcode']['custom_attributes']) || !is_array($fields['billing']['billing_postcode']['custom_attributes'])) {
            $fields['billing']['billing_postcode']['custom_attributes'] = array();
        }

        $fields['billing']['billing_postcode']['label'] = __('Poštanski broj', 'sh-validator-korpe');
        $fields['billing']['billing_postcode']['required'] = true;
        $fields['billing']['billing_postcode']['priority'] = 71;
        $fields['billing']['billing_postcode']['type'] = 'text';
        $fields['billing']['billing_postcode']['placeholder'] = __('Generiše se automatski izborom grada', 'sh-validator-korpe');
        $fields['billing']['billing_postcode']['custom_attributes']['readonly'] = 'readonly';

        if (isset($fields['billing']['billing_phone'])) {
            if (empty($fields['billing']['billing_phone']['input_class']) || !is_array($fields['billing']['billing_phone']['input_class'])) {
                $fields['billing']['billing_phone']['input_class'] = array();
            }

            if (empty($fields['billing']['billing_phone']['custom_attributes']) || !is_array($fields['billing']['billing_phone']['custom_attributes'])) {
                $fields['billing']['billing_phone']['custom_attributes'] = array();
            }

            $fields['billing']['billing_phone']['default'] = '+381';
            $fields['billing']['billing_phone']['placeholder'] = __('unesite broj u formatu 64 123 45 67', 'sh-validator-korpe');
            $fields['billing']['billing_phone']['input_class'][] = 'sh-checkout-validator-phone';
            $fields['billing']['billing_phone']['custom_attributes']['inputmode'] = 'tel';
            $fields['billing']['billing_phone']['custom_attributes']['autocomplete'] = 'tel-national';
        }

        if (isset($fields['billing']['billing_email'])) {
            if (empty($fields['billing']['billing_email']['input_class']) || !is_array($fields['billing']['billing_email']['input_class'])) {
                $fields['billing']['billing_email']['input_class'] = array();
            }

            if (empty($fields['billing']['billing_email']['custom_attributes']) || !is_array($fields['billing']['billing_email']['custom_attributes'])) {
                $fields['billing']['billing_email']['custom_attributes'] = array();
            }

            $fields['billing']['billing_email']['placeholder'] = __('Unesite vašu e-mail adresu', 'sh-validator-korpe');
            $fields['billing']['billing_email']['input_class'][] = 'sh-checkout-validator-email';
            $fields['billing']['billing_email']['custom_attributes']['autocomplete'] = 'email';
        }

        return $fields;
    }

    public function sh_normalize_posted_data($data)
    {
        if (isset($data['billing_phone'])) {
            $data['billing_phone'] = $this->sh_normalize_phone($data['billing_phone']);
        }

        if (isset($data['billing_email'])) {
            $data['billing_email'] = trim((string) $data['billing_email']);
        }

        if (!empty($data['billing_city'])) {
            $selected_city = is_numeric($data['billing_city'])
                ? $this->repository->sh_find_city_by_id($data['billing_city'])
                : null;

            if (is_array($selected_city) && !empty($selected_city['city_name'])) {
                $data['billing_city'] = $selected_city['city_name'];
                $data['billing_postcode'] = $selected_city['postal_code'];
                return $data;
            }

            $postal_code = $this->repository->sh_find_postal_code_by_city($data['billing_city']);

            if (!empty($postal_code)) {
                $data['billing_postcode'] = $postal_code;
            }
        }

        return $data;
    }

    public function sh_validate_checkout_data($data, $errors)
    {
        if (!empty($data['billing_phone'])) {
            $normalized_phone = $this->sh_normalize_phone($data['billing_phone']);

            if ($normalized_phone === '+381' || strlen($normalized_phone) < 11) {
                $errors->add(
                    'billing_phone_invalid',
                    __('Unesite ispravan broj telefona. Primer: +381641234567', 'sh-validator-korpe')
                );
            }
        }

        if (!empty($data['billing_email'])) {
            $email_suggestion = $this->settings->sh_get_email_suggestion($data['billing_email']);

            if ($email_suggestion !== '' && strtolower($email_suggestion) !== strtolower(trim((string) $data['billing_email']))) {
                $errors->add(
                    'billing_email_typo',
                    sprintf(
                        __('Email adresa deluje pogrešno. Da li ste mislili: %s?', 'sh-validator-korpe'),
                        esc_html($email_suggestion)
                    )
                );
            }
        }

        if (!empty($data['billing_city'])) {
            $posted_postal_code = isset($data['billing_postcode']) ? (string) $data['billing_postcode'] : '';

            if ($posted_postal_code === '') {
                $errors->add(
                    'billing_postcode_invalid',
                    __('Poštanski broj nije usklađen sa izabranim gradom.', 'sh-validator-korpe')
                );
            } elseif (!$this->repository->sh_city_postal_pair_exists($data['billing_city'], $posted_postal_code)) {
                $errors->add(
                    'billing_city_invalid',
                    __('Izabrani grad nije u važećoj listi.', 'sh-validator-korpe')
                );
            }
        }
    }

    private function sh_normalize_phone($phone)
    {
        $phone = trim(wp_strip_all_tags((string) $phone));

        if ($phone === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return '';
        }

        if (strpos($digits, '00381') === 0) {
            $digits = substr($digits, 5);
        } elseif (strpos($digits, '381') === 0) {
            $digits = substr($digits, 3);
        } elseif (strpos($digits, '0') === 0) {
            $digits = ltrim($digits, '0');
        }

        $digits = ltrim($digits, '0');

        return '+381' . $digits;
    }
}
