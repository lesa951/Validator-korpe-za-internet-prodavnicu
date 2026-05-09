<?php

if (!defined('ABSPATH')) {
    exit;
}

class SH_Validator_Repository
{
    public function sh_get_table_name()
    {
        global $wpdb;

        return $wpdb->prefix . 'sh_validator_cities';
    }

    public function sh_get_cities()
    {
        global $wpdb;

        $table_name = $this->sh_get_table_name();

        return $wpdb->get_results("SELECT id, city_name, postal_code FROM {$table_name} ORDER BY city_name ASC", ARRAY_A);
    }

    public function sh_get_cities_page($page = 1, $per_page = 20, $search = '')
    {
        global $wpdb;

        $table_name = $this->sh_get_table_name();
        $page = max(1, (int) $page);
        $per_page = max(1, (int) $per_page);
        $offset = ($page - 1) * $per_page;
        $where = '';
        $params = array();

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where = 'WHERE city_name LIKE %s OR postal_code LIKE %s';
            $params[] = $like;
            $params[] = $like;
        }

        $params[] = $per_page;
        $params[] = $offset;
        $query = "SELECT id, city_name, postal_code FROM {$table_name} {$where} ORDER BY city_name ASC, postal_code ASC LIMIT %d OFFSET %d";

        return $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
    }

    public function sh_get_filtered_city_count($search = '')
    {
        global $wpdb;

        $table_name = $this->sh_get_table_name();

        if ($search === '') {
            return $this->sh_get_city_count();
        }

        $like = '%' . $wpdb->esc_like($search) . '%';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE city_name LIKE %s OR postal_code LIKE %s",
                $like,
                $like
            )
        );
    }

    public function sh_get_city_count()
    {
        global $wpdb;

        $table_name = $this->sh_get_table_name();

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    }

    public function sh_get_city_options()
    {
        $cities = $this->sh_get_cities();
        $options = array('' => __('Izaberite grad', 'sh-validator-korpe'));

        foreach ($cities as $city) {
            $options[(string) $city['id']] = sprintf(
                '%s - %s',
                $city['city_name'],
                $city['postal_code']
            );
        }

        return $options;
    }

    public function sh_get_city_postal_map()
    {
        $cities = $this->sh_get_cities();
        $map = array();

        foreach ($cities as $city) {
            $map[(string) $city['id']] = $city['postal_code'];
        }

        return $map;
    }

    public function sh_upsert_city($city_id, $city_name, $postal_code)
    {
        global $wpdb;

        $city_name = sanitize_text_field($city_name);
        $postal_code = sanitize_text_field($postal_code);
        $normalized_name = SH_Validator_Installer::sh_normalize_city_name($city_name);

        if ($city_name === '' || $postal_code === '') {
            return new WP_Error('invalid_city', __('Grad i poštanski broj su obavezni.', 'sh-validator-korpe'));
        }

        if (!preg_match('/^[0-9]{4,6}$/', $postal_code)) {
            return new WP_Error('invalid_postal_code', __('Poštanski broj mora imati 4 do 6 cifara.', 'sh-validator-korpe'));
        }

        $table_name = $this->sh_get_table_name();
        $existing_city = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE normalized_name = %s AND postal_code = %s AND id != %d LIMIT 1",
                $normalized_name,
                $postal_code,
                (int) $city_id
            ),
            ARRAY_A
        );

        if (!empty($existing_city)) {
            return new WP_Error('duplicate_city', __('Grad već postoji u listi.', 'sh-validator-korpe'));
        }

        if ($city_id > 0) {
            $updated = $wpdb->update(
                $table_name,
                array(
                    'city_name' => $city_name,
                    'postal_code' => $postal_code,
                    'normalized_name' => $normalized_name,
                ),
                array('id' => (int) $city_id),
                array('%s', '%s', '%s'),
                array('%d')
            );

            return ($updated === false)
                ? new WP_Error('city_update_failed', __('Izmena grada nije uspela.', 'sh-validator-korpe'))
                : true;
        }

        $inserted = $wpdb->insert(
            $table_name,
            array(
                'city_name' => $city_name,
                'postal_code' => $postal_code,
                'normalized_name' => $normalized_name,
            ),
            array('%s', '%s', '%s')
        );

        return ($inserted === false)
            ? new WP_Error('city_insert_failed', __('Dodavanje grada nije uspelo.', 'sh-validator-korpe'))
            : true;
    }

    public function sh_delete_city($city_id)
    {
        global $wpdb;

        $table_name = $this->sh_get_table_name();

        return false !== $wpdb->delete($table_name, array('id' => (int) $city_id), array('%d'));
    }

    public function sh_delete_cities($city_ids)
    {
        global $wpdb;

        if (!is_array($city_ids) || empty($city_ids)) {
            return 0;
        }

        $city_ids = array_values(array_filter(array_map('absint', $city_ids)));

        if (empty($city_ids)) {
            return 0;
        }

        $table_name = $this->sh_get_table_name();
        $placeholders = implode(',', array_fill(0, count($city_ids), '%d'));

        return (int) $wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE id IN ({$placeholders})", $city_ids));
    }

    public function sh_delete_all_cities()
    {
        global $wpdb;

        $table_name = $this->sh_get_table_name();

        return false !== $wpdb->query("TRUNCATE TABLE {$table_name}");
    }

    public function sh_import_city($city_name, $postal_code)
    {
        global $wpdb;

        $city_name = sanitize_text_field($city_name);
        $postal_code = sanitize_text_field($postal_code);
        $normalized_name = SH_Validator_Installer::sh_normalize_city_name($city_name);

        if ($city_name === '' || $postal_code === '') {
            return new WP_Error('invalid_import_row', __('Grad i poštanski broj su obavezni za import.', 'sh-validator-korpe'));
        }

        if (!preg_match('/^[0-9]{4,6}$/', $postal_code)) {
            return new WP_Error('invalid_import_postal_code', __('Poštanski broj mora imati 4 do 6 cifara.', 'sh-validator-korpe'));
        }

        $table_name = $this->sh_get_table_name();
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'city_name' => $city_name,
                'postal_code' => $postal_code,
                'normalized_name' => $normalized_name,
            ),
            array('%s', '%s', '%s')
        );

        if ($inserted === false) {
            return new WP_Error('import_insert_failed', __('Red nije moguće ubaciti.', 'sh-validator-korpe'));
        }

        return 'inserted';
    }

    public function sh_find_postal_code_by_city($city_name)
    {
        global $wpdb;

        $table_name = $this->sh_get_table_name();

        if (is_numeric($city_name)) {
            return $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT postal_code FROM {$table_name} WHERE id = %d LIMIT 1",
                    (int) $city_name
                )
            );
        }

        $normalized_name = SH_Validator_Installer::sh_normalize_city_name($city_name);

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT postal_code FROM {$table_name} WHERE normalized_name = %s LIMIT 1",
                $normalized_name
            )
        );
    }

    public function sh_find_city_by_id($city_id)
    {
        global $wpdb;

        $table_name = $this->sh_get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, city_name, postal_code FROM {$table_name} WHERE id = %d LIMIT 1",
                (int) $city_id
            ),
            ARRAY_A
        );
    }

    public function sh_city_postal_pair_exists($city_name, $postal_code)
    {
        global $wpdb;

        $table_name = $this->sh_get_table_name();
        $normalized_name = SH_Validator_Installer::sh_normalize_city_name($city_name);

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE normalized_name = %s AND postal_code = %s LIMIT 1",
                $normalized_name,
                sanitize_text_field($postal_code)
            )
        );
    }
}
