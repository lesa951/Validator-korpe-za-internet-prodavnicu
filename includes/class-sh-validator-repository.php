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
            $options[$city['city_name']] = $city['city_name'];
        }

        return $options;
    }

    public function sh_get_city_postal_map()
    {
        $cities = $this->sh_get_cities();
        $map = array();

        foreach ($cities as $city) {
            $map[$city['city_name']] = $city['postal_code'];
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
                "SELECT id FROM {$table_name} WHERE normalized_name = %s AND id != %d LIMIT 1",
                $normalized_name,
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

    public function sh_find_postal_code_by_city($city_name)
    {
        global $wpdb;

        $table_name = $this->sh_get_table_name();
        $normalized_name = SH_Validator_Installer::sh_normalize_city_name($city_name);

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT postal_code FROM {$table_name} WHERE normalized_name = %s LIMIT 1",
                $normalized_name
            )
        );
    }
}
