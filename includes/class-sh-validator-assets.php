<?php

if (!defined('ABSPATH')) {
    exit;
}

class SH_Validator_Assets
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
        add_action('wp_enqueue_scripts', array($this, 'sh_enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'sh_enqueue_admin_assets'));
    }

    public function sh_enqueue_frontend_assets()
    {
        if (!class_exists('WooCommerce') || !function_exists('is_checkout') || !is_checkout()) {
            return;
        }

        if (function_exists('is_order_received_page') && is_order_received_page()) {
            return;
        }

        $script_dependencies = array('jquery');
        $style_dependencies = array();

        if (wp_script_is('selectWoo', 'registered')) {
            wp_enqueue_script('selectWoo');
            $script_dependencies[] = 'selectWoo';
        } elseif (wp_script_is('select2', 'registered')) {
            wp_enqueue_script('select2');
            $script_dependencies[] = 'select2';
        }

        if (wp_style_is('select2', 'registered')) {
            wp_enqueue_style('select2');
            $style_dependencies[] = 'select2';
        }

        wp_enqueue_style(
            'sh-validator-korpe',
            SH_VALIDATOR_URL . 'assets/css/sh-validator-checkout.css',
            $style_dependencies,
            SH_VALIDATOR_VERSION
        );

        wp_enqueue_script(
            'sh-validator-korpe',
            SH_VALIDATOR_URL . 'assets/js/sh-validator-checkout.js',
            $script_dependencies,
            SH_VALIDATOR_VERSION,
            true
        );

        wp_localize_script(
            'sh-validator-korpe',
            'shCheckoutValidator',
            array(
                'phonePrefix' => '+381',
                'phonePlaceholder' => __('unesite broj u formatu 64 123 45 67', 'sh-validator-korpe'),
                'invalidEmailMessage' => __('Unesite ispravnu email adresu.', 'sh-validator-korpe'),
                'emailSuggestionPrefix' => __('Da li ste mislili: ', 'sh-validator-korpe'),
                'emailSuggestionSuffix' => __('?', 'sh-validator-korpe'),
                'chooseCityLabel' => __('Izaberite grad', 'sh-validator-korpe'),
                'invalidPhoneMessage' => __('Unesite ispravan broj telefona. Primer: +381641234567', 'sh-validator-korpe'),
                'cityPostalMap' => $this->repository->sh_get_city_postal_map(),
                'emailTypos' => $this->settings->sh_get_email_typos(),
            )
        );
    }

    public function sh_enqueue_admin_assets($hook_suffix)
    {
        if ($hook_suffix !== 'woocommerce_page_sh-validator-korpe') {
            return;
        }

        wp_enqueue_style(
            'sh-validator-korpe',
            SH_VALIDATOR_URL . 'assets/css/sh-validator-checkout.css',
            array(),
            SH_VALIDATOR_VERSION
        );
    }
}
