<?php
/**
 * Plugin Name: Validator korpe za internet prodavnicu
 * Plugin URI: https://sasahuremovic.rs
 * Description: Dodatak za internet prodavnicu koji proverava i sređuje podatke kupca tokom procesa poručivanja.
 * Version: 1.2.5
 * Author: Saša Huremović / Orbilix
 * Author URI: https://sasahuremovic.rs
 * Text Domain: sh-validator-korpe
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SH_VALIDATOR_VERSION', '1.2.5');
define('SH_VALIDATOR_FILE', __FILE__);
define('SH_VALIDATOR_PATH', plugin_dir_path(__FILE__));
define('SH_VALIDATOR_URL', plugin_dir_url(__FILE__));

require_once SH_VALIDATOR_PATH . 'includes/class-sh-validator-installer.php';
require_once SH_VALIDATOR_PATH . 'includes/class-sh-validator-repository.php';
require_once SH_VALIDATOR_PATH . 'includes/class-sh-validator-settings.php';
require_once SH_VALIDATOR_PATH . 'includes/class-sh-validator-importer.php';
require_once SH_VALIDATOR_PATH . 'includes/class-sh-validator-assets.php';
require_once SH_VALIDATOR_PATH . 'includes/class-sh-validator-checkout.php';
require_once SH_VALIDATOR_PATH . 'includes/class-sh-validator-plugin.php';

register_activation_hook(SH_VALIDATOR_FILE, array('SH_Validator_Installer', 'sh_install'));

function sh_validator_boot()
{
    $plugin = new SH_Validator_Plugin();
    $plugin->sh_register();
}

sh_validator_boot();
