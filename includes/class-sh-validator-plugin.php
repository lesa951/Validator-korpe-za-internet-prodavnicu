<?php

if (!defined('ABSPATH')) {
    exit;
}

class SH_Validator_Plugin
{
    private $repository;
    private $settings;
    private $assets;
    private $checkout;

    public function __construct()
    {
        $this->repository = new SH_Validator_Repository();
        $this->settings = new SH_Validator_Settings();
        $this->assets = new SH_Validator_Assets($this->repository, $this->settings);
        $this->checkout = new SH_Validator_Checkout($this->repository, $this->settings);
    }

    public function sh_register()
    {
        add_action('admin_notices', array($this, 'sh_admin_notice_missing_woocommerce'));
        add_action('admin_menu', array($this, 'sh_register_admin_page'));
        $this->assets->sh_register();
        $this->checkout->sh_register();
    }

    public function sh_admin_notice_missing_woocommerce()
    {
        if (class_exists('WooCommerce')) {
            return;
        }

        echo '<div class="notice notice-warning"><p>Validator korpe za internet prodavnicu zahteva aktivan WooCommerce plugin.</p></div>';
    }

    public function sh_register_admin_page()
    {
        add_submenu_page(
            'woocommerce',
            __('Validator korpe za internet prodavnicu', 'sh-validator-korpe'),
            __('Validator korpe', 'sh-validator-korpe'),
            'manage_woocommerce',
            'sh-validator-korpe',
            array($this, 'sh_render_admin_page')
        );
    }

    public function sh_render_admin_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Nemate dozvolu za pristup ovoj stranici.', 'sh-validator-korpe'));
        }

        $notice = '';
        $notice_type = 'success';
        $editing_city = null;

        if (isset($_GET['action'], $_GET['city_id']) && $_GET['action'] === 'delete') {
            check_admin_referer('sh_delete_city_' . absint($_GET['city_id']));
            $deleted = $this->repository->sh_delete_city(absint($_GET['city_id']));
            $notice = $deleted
                ? __('Grad je obrisan.', 'sh-validator-korpe')
                : __('Brisanje grada nije uspelo.', 'sh-validator-korpe');
            $notice_type = $deleted ? 'success' : 'error';
        }

        if (isset($_GET['action'], $_GET['city_id']) && $_GET['action'] === 'edit') {
            $city_id = absint($_GET['city_id']);

            foreach ($this->repository->sh_get_cities() as $city) {
                if ((int) $city['id'] === $city_id) {
                    $editing_city = $city;
                    break;
                }
            }
        }

        if (isset($_POST['sh_save_city'])) {
            check_admin_referer('sh_save_city');

            $result = $this->repository->sh_upsert_city(
                isset($_POST['city_id']) ? absint($_POST['city_id']) : 0,
                isset($_POST['city_name']) ? wp_unslash($_POST['city_name']) : '',
                isset($_POST['postal_code']) ? wp_unslash($_POST['postal_code']) : ''
            );

            if (is_wp_error($result)) {
                $notice = $result->get_error_message();
                $notice_type = 'error';
            } else {
                $notice = __('Grad je uspešno sačuvan.', 'sh-validator-korpe');
                $editing_city = null;
            }
        }

        if (isset($_POST['sh_save_email_typos'])) {
            check_admin_referer('sh_save_email_typos');
            $this->settings->sh_save_email_typos_from_textarea(isset($_POST['email_typos']) ? wp_unslash($_POST['email_typos']) : '');
            $notice = __('Lista čestih email grešaka je sačuvana.', 'sh-validator-korpe');
        }

        $cities = $this->repository->sh_get_cities();
        $email_typos = $this->settings->sh_get_email_typos_for_textarea();

        include SH_VALIDATOR_PATH . 'admin/views/settings-page.php';
    }
}
