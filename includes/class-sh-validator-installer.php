<?php

if (!defined('ABSPATH')) {
    exit;
}

class SH_Validator_Installer
{
    const OPTION_EMAIL_TYPOS = 'sh_validator_email_typos';
    const OPTION_LEGACY_SYNC_DONE = 'sh_validator_legacy_sync_done';
    const OPTION_BUNDLED_CITIES_IMPORTED = 'sh_validator_bundled_cities_imported';
    const BUNDLED_CITIES_FILE = 'data/gradovi.csv';

    public static function sh_install()
    {
        self::sh_create_cities_table();
        self::sh_seed_email_typos();
        self::sh_seed_cities_if_needed();
        self::sh_mark_legacy_sync_if_possible();
    }

    public static function sh_get_default_email_typos()
    {
        return array(
    'gmai.com' => 'gmail.com',
    'gmaik.com' => 'gmail.com',
    'gmaik.rs' => 'gmail.com',
    'gmail.con' => 'gmail.com',
    'gmal.com' => 'gmail.com',
    'gmial.com' => 'gmail.com',
    'gnail.com' => 'gmail.com',
    'g-mail.com' => 'gmail.com',
    'g.mail.com' => 'gmail.com',
    'gemail.com' => 'gmail.com',
    'gimail.com' => 'gmail.com',
    'gmaile.com' => 'gmail.com',
    'gmaill.com' => 'gmail.com',
    'gmaiol.com' => 'gmail.com',
    'gmale.com' => 'gmail.com',
    'gmall.com' => 'gmail.com',
    'gmaol.com' => 'gmail.com',
    'gmaul.com' => 'gmail.com',
    'gmeil.com' => 'gmail.com',
    'gmil.com' => 'gmail.com',
    'gmmail.com' => 'gmail.com',
    'gmsil.com' => 'gmail.com',
    'gmail.co' => 'gmail.com',
    'gmail.cm' => 'gmail.com',
    'gmail.cmo' => 'gmail.com',
    'gmail.comm' => 'gmail.com',
    'gmail.om' => 'gmail.com',
    'gmailcom' => 'gmail.com',
    'gmail.rs' => 'gmail.com',
    'hotmai.com' => 'hotmail.com',
    'hotmial.com' => 'hotmail.com',
    'hotnail.com' => 'hotmail.com',
    'hormail.com' => 'hotmail.com',
    'hotmail.con' => 'hotmail.com',
    'hotmaill.com' => 'hotmail.com',
    'hotmal.com' => 'hotmail.com',
    'hotmale.com' => 'hotmail.com',
    'hotmil.com' => 'hotmail.com',
    'hotmail.co' => 'hotmail.com',
    'hotmail.cm' => 'hotmail.com',
    'hotmail.cmo' => 'hotmail.com',
    'hotmail.comm' => 'hotmail.com',
    'hotmai.rs' => 'hotmail.com',
    'icloud.con' => 'icloud.com',
    'iclaud.com' => 'icloud.com',
    'icoud.com' => 'icloud.com',
    'icloud.co' => 'icloud.com',
    'icloud.cm' => 'icloud.com',
    'icloud.cmo' => 'icloud.com',
    'icloud.comm' => 'icloud.com',
    'outllok.com' => 'outlook.com',
    'outlok.com' => 'outlook.com',
    'oulook.com' => 'outlook.com',
    'outlook.con' => 'outlook.com',
    'outlouk.com' => 'outlook.com',
    'outlook.co' => 'outlook.com',
    'outlook.cm' => 'outlook.com',
    'outlook.cmo' => 'outlook.com',
    'outlook.comm' => 'outlook.com',
    'outllook.com' => 'outlook.com',
    'outlook.rs' => 'outlook.com',
    'yaho.com' => 'yahoo.com',
    'yhoo.com' => 'yahoo.com',
    'yahho.com' => 'yahoo.com',
    'yaho.co' => 'yahoo.com',
    'yahoo.cmo' => 'yahoo.com',
    'yahoomail.com' => 'yahoo.com',
    'yahu.com' => 'yahoo.com',
    'yshoo.com' => 'yahoo.com',
    'yahoo.co' => 'yahoo.com',
    'yahoo.cm' => 'yahoo.com',
    'yahoo.con' => 'yahoo.com',
    'yahoo.comm' => 'yahoo.com',
    'yahooo.com' => 'yahoo.com',
    'ymail.con' => 'ymail.com',
    'live.con' => 'live.com',
);
    }

    private static function sh_create_cities_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sh_validator_cities';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            city_name varchar(191) NOT NULL,
            postal_code varchar(20) NOT NULL,
            normalized_name varchar(191) NOT NULL,
            PRIMARY KEY (id),
            KEY normalized_name (normalized_name),
            KEY postal_code (postal_code)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        self::sh_drop_legacy_unique_city_index($table_name);
    }

    private static function sh_drop_legacy_unique_city_index($table_name)
    {
        global $wpdb;

        $index = $wpdb->get_row($wpdb->prepare("SHOW INDEX FROM {$table_name} WHERE Key_name = %s", 'normalized_name'), ARRAY_A);

        if (is_array($index) && isset($index['Non_unique']) && (int) $index['Non_unique'] === 0) {
            $wpdb->query("ALTER TABLE {$table_name} DROP INDEX normalized_name");
            $wpdb->query("ALTER TABLE {$table_name} ADD INDEX normalized_name (normalized_name)");
        }
    }

    private static function sh_seed_email_typos()
    {
        $default_typos = self::sh_get_default_email_typos();
        $saved_typos = get_option(self::OPTION_EMAIL_TYPOS, array());

        if (!is_array($saved_typos) || empty($saved_typos)) {
            update_option(self::OPTION_EMAIL_TYPOS, $default_typos);
            return;
        }

        update_option(self::OPTION_EMAIL_TYPOS, array_merge($default_typos, $saved_typos));
    }

    private static function sh_seed_cities_if_needed()
    {
        $count = self::sh_get_current_city_count();
        $bundled_row_count = self::sh_get_bundled_csv_row_count();

        if (!get_option(self::OPTION_BUNDLED_CITIES_IMPORTED) && $bundled_row_count > 0 && $count < $bundled_row_count) {
            if (self::sh_seed_cities_from_bundled_file()) {
                return;
            }
        }

        if ($count > 0) {
            return;
        }

        if (self::sh_seed_cities_from_bundled_file()) {
            return;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'sh_validator_cities';

        $legacy_rows = self::sh_get_legacy_city_rows_from_database();

        if (empty($legacy_rows)) {
            $legacy_rows = self::sh_get_fallback_city_rows();
        }

        foreach ($legacy_rows as $row) {
            if (empty($row['city_name']) || empty($row['postal_code'])) {
                continue;
            }

            $wpdb->insert(
                $table_name,
                array(
                    'city_name' => $row['city_name'],
                    'postal_code' => $row['postal_code'],
                    'normalized_name' => self::sh_normalize_city_name($row['city_name']),
                ),
                array('%s', '%s', '%s')
            );
        }
    }

    private static function sh_seed_cities_from_bundled_file()
    {
        $bundled_file = SH_VALIDATOR_PATH . self::BUNDLED_CITIES_FILE;

        if (!file_exists($bundled_file) || !is_readable($bundled_file)) {
            return false;
        }

        $repository = new SH_Validator_Repository();
        $importer = new SH_Validator_Importer($repository);
        $result = $importer->sh_import_from_file_path($bundled_file, 'csv', true);

        if (is_wp_error($result)) {
            return false;
        }

        $imported_count = (int) $result['inserted'] + (int) $result['updated'];

        if ($imported_count > 0) {
            update_option(self::OPTION_BUNDLED_CITIES_IMPORTED, 'yes');
            return true;
        }

        return false;
    }

    private static function sh_get_bundled_csv_row_count()
    {
        $bundled_file = SH_VALIDATOR_PATH . self::BUNDLED_CITIES_FILE;

        if (!file_exists($bundled_file) || !is_readable($bundled_file)) {
            return 0;
        }

        $rows = file($bundled_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return is_array($rows) ? count($rows) : 0;
    }

    public static function sh_maybe_sync_legacy_cities()
    {
        if (get_option(self::OPTION_LEGACY_SYNC_DONE)) {
            return;
        }

        $legacy_rows = self::sh_get_legacy_city_rows_from_database();

        if (empty($legacy_rows)) {
            return;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'sh_validator_cities';

        foreach ($legacy_rows as $row) {
            if (empty($row['city_name']) || empty($row['postal_code'])) {
                continue;
            }

            $wpdb->replace(
                $table_name,
                array(
                    'city_name' => $row['city_name'],
                    'postal_code' => $row['postal_code'],
                    'normalized_name' => self::sh_normalize_city_name($row['city_name']),
                ),
                array('%s', '%s', '%s')
            );
        }

        self::sh_mark_legacy_sync_if_possible();
    }

    private static function sh_get_legacy_city_rows_from_database()
    {
        global $wpdb;

        $legacy_table = $wpdb->prefix . 'gradovi';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_table));

        if ($exists !== $legacy_table) {
            return array();
        }

        $rows = $wpdb->get_results("SELECT grad, postanski_broj FROM {$legacy_table} ORDER BY grad ASC", ARRAY_A);

        if (!is_array($rows)) {
            return array();
        }

        return array_map(
            static function ($row) {
                return array(
                    'city_name' => isset($row['grad']) ? sanitize_text_field($row['grad']) : '',
                    'postal_code' => isset($row['postanski_broj']) ? sanitize_text_field($row['postanski_broj']) : '',
                );
            },
            $rows
        );
    }

    private static function sh_get_current_city_count()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sh_validator_cities';

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    }

    private static function sh_mark_legacy_sync_if_possible()
    {
        $legacy_rows = self::sh_get_legacy_city_rows_from_database();

        if (!empty($legacy_rows)) {
            update_option(self::OPTION_LEGACY_SYNC_DONE, 'yes');
        }
    }

    private static function sh_get_fallback_city_rows()
    {
        return array(
            array('city_name' => 'BEOGRAD', 'postal_code' => '11000'),
            array('city_name' => 'NOVI SAD', 'postal_code' => '21000'),
            array('city_name' => 'NIŠ', 'postal_code' => '18000'),
            array('city_name' => 'KRAGUJEVAC', 'postal_code' => '34000'),
            array('city_name' => 'SUBOTICA', 'postal_code' => '24000'),
        );
    }

    public static function sh_normalize_city_name($city_name)
    {
        $city_name = remove_accents(wp_strip_all_tags((string) $city_name));
        $city_name = strtoupper($city_name);
        $city_name = preg_replace('/\s+/', ' ', $city_name);

        return trim((string) $city_name);
    }
}
