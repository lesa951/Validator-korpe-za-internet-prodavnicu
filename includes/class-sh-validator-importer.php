<?php

if (!defined('ABSPATH')) {
    exit;
}

class SH_Validator_Importer
{
    private $repository;

    public function __construct(SH_Validator_Repository $repository)
    {
        $this->repository = $repository;
    }

    public function sh_import_from_uploaded_file($uploaded_file, $replace_existing = false)
    {
        if (empty($uploaded_file['tmp_name']) || !is_uploaded_file($uploaded_file['tmp_name'])) {
            return new WP_Error('missing_file', __('Fajl za import nije izabran.', 'sh-validator-korpe'));
        }

        $extension = strtolower((string) pathinfo((string) $uploaded_file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, array('csv', 'json', 'xml', 'xlsx'), true)) {
            return new WP_Error('invalid_file_type', __('Podržani formati su CSV, JSON, XML i XLSX.', 'sh-validator-korpe'));
        }

        return $this->sh_import_from_file_path($uploaded_file['tmp_name'], $extension, $replace_existing);
    }

    public function sh_import_from_file_path($file_path, $extension = '', $replace_existing = false)
    {
        if (!is_string($file_path) || $file_path === '' || !file_exists($file_path) || !is_readable($file_path)) {
            return new WP_Error('missing_file', __('Fajl za import nije pronađen ili nije čitljiv.', 'sh-validator-korpe'));
        }

        if ($extension === '') {
            $extension = strtolower((string) pathinfo($file_path, PATHINFO_EXTENSION));
        }

        if (!in_array($extension, array('csv', 'json', 'xml', 'xlsx'), true)) {
            return new WP_Error('invalid_file_type', __('Podržani formati su CSV, JSON, XML i XLSX.', 'sh-validator-korpe'));
        }

        $rows = $this->sh_parse_file($file_path, $extension);

        if (is_wp_error($rows)) {
            return $rows;
        }

        if (empty($rows)) {
            return new WP_Error('empty_import', __('U fajlu nema važećih redova za import.', 'sh-validator-korpe'));
        }

        if ($replace_existing) {
            $this->repository->sh_delete_all_cities();
        }

        $result = array(
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array(),
        );

        foreach ($rows as $index => $row) {
            $city_name = isset($row['city_name']) ? $row['city_name'] : '';
            $postal_code = isset($row['postal_code']) ? $row['postal_code'] : '';
            $import_result = $this->repository->sh_import_city($city_name, $postal_code);

            if (is_wp_error($import_result)) {
                $result['skipped']++;
                $result['errors'][] = sprintf(
                    __('Red %1$d: %2$s', 'sh-validator-korpe'),
                    $index + 1,
                    $import_result->get_error_message()
                );
                continue;
            }

            if ($import_result === 'updated') {
                $result['updated']++;
            } elseif ($import_result === 'inserted') {
                $result['inserted']++;
            } else {
                $result['skipped']++;
            }
        }

        return $result;
    }

    private function sh_parse_file($file_path, $extension)
    {
        switch ($extension) {
            case 'csv':
                return $this->sh_parse_csv($file_path);
            case 'json':
                return $this->sh_parse_json($file_path);
            case 'xml':
                return $this->sh_parse_xml($file_path);
            case 'xlsx':
                return $this->sh_parse_xlsx($file_path);
        }

        return new WP_Error('unsupported_format', __('Format fajla nije podržan.', 'sh-validator-korpe'));
    }

    private function sh_parse_csv($file_path)
    {
        $handle = fopen($file_path, 'r');

        if (!$handle) {
            return new WP_Error('csv_open_failed', __('CSV fajl nije moguće otvoriti.', 'sh-validator-korpe'));
        }

        $rows = array();
        $headers = array();
        $is_first_row = true;
        $delimiter = ',';
        $headerless_csv = false;

        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if ($is_first_row) {
                $delimiter = $this->sh_detect_csv_delimiter($data);

                if ($delimiter !== ',') {
                    rewind($handle);
                    $data = fgetcsv($handle, 0, $delimiter);
                }

                $headers = $this->sh_normalize_headers($data);
                $headerless_csv = !$this->sh_headers_contain_city_and_postal_code($headers);

                if ($headerless_csv) {
                    $headers = array('grad', 'postanski_broj');
                    $row = $this->sh_map_row_by_headers($headers, $data);

                    if (!empty($row)) {
                        $rows[] = $row;
                    }
                }

                $is_first_row = false;
                continue;
            }

            if ($delimiter !== ',') {
                $data = $this->sh_reparse_csv_line($data, $delimiter);
            }

            $row = $this->sh_map_row_by_headers($headers, $data);

            if (!empty($row)) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return $rows;
    }

    private function sh_parse_json($file_path)
    {
        $contents = file_get_contents($file_path);

        if (!is_string($contents) || $contents === '') {
            return new WP_Error('json_read_failed', __('JSON fajl nije moguće pročitati.', 'sh-validator-korpe'));
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            return new WP_Error('json_invalid', __('JSON fajl nije validan.', 'sh-validator-korpe'));
        }

        if (isset($decoded['cities']) && is_array($decoded['cities'])) {
            $decoded = $decoded['cities'];
        } elseif (isset($decoded['items']) && is_array($decoded['items'])) {
            $decoded = $decoded['items'];
        }

        $rows = array();

        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $row = $this->sh_extract_row_from_assoc($item);

            if (!empty($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function sh_parse_xml($file_path)
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($file_path);

        if (!$xml instanceof SimpleXMLElement) {
            return new WP_Error('xml_invalid', __('XML fajl nije validan.', 'sh-validator-korpe'));
        }

        $rows = array();
        $nodes = $xml->xpath('//*[local-name()="city" or local-name()="item" or local-name()="row"]');

        if (empty($nodes)) {
            $nodes = array($xml);
        }

        foreach ($nodes as $node) {
            $item = json_decode(wp_json_encode($node), true);

            if (!is_array($item)) {
                continue;
            }

            $row = $this->sh_extract_row_from_assoc($item);

            if (!empty($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function sh_parse_xlsx($file_path)
    {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('xlsx_zip_missing', __('XLSX import zahteva PHP ZipArchive ekstenziju.', 'sh-validator-korpe'));
        }

        $zip = new ZipArchive();
        $opened = $zip->open($file_path);

        if ($opened !== true) {
            return new WP_Error('xlsx_open_failed', __('XLSX fajl nije moguće otvoriti.', 'sh-validator-korpe'));
        }

        $shared_strings = $this->sh_parse_xlsx_shared_strings($zip);
        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!is_string($sheet_xml) || $sheet_xml === '') {
            return new WP_Error('xlsx_sheet_missing', __('XLSX fajl nema prvi radni list.', 'sh-validator-korpe'));
        }

        $sheet = simplexml_load_string($sheet_xml);

        if (!$sheet instanceof SimpleXMLElement) {
            return new WP_Error('xlsx_invalid', __('XLSX fajl nije validan.', 'sh-validator-korpe'));
        }

        $sheet_rows = $sheet->xpath('/*[local-name()="worksheet"]/*[local-name()="sheetData"]/*[local-name()="row"]');

        if (empty($sheet_rows)) {
            return new WP_Error('xlsx_invalid', __('XLSX fajl ne sadrži čitljive redove.', 'sh-validator-korpe'));
        }

        $rows = array();
        $headers = array();
        $is_first_row = true;

        foreach ($sheet_rows as $row) {
            $values = array();
            $cells = $row->xpath('./*[local-name()="c"]');

            foreach ($cells as $cell) {
                $column_index = $this->sh_get_xlsx_column_index((string) $cell['r']);
                $values[$column_index] = $this->sh_get_xlsx_cell_value($cell, $shared_strings);
            }

            ksort($values);
            $values = array_values($values);

            if ($is_first_row) {
                $headers = $this->sh_normalize_headers($values);
                $is_first_row = false;
                continue;
            }

            $mapped_row = $this->sh_map_row_by_headers($headers, $values);

            if (!empty($mapped_row)) {
                $rows[] = $mapped_row;
            }
        }

        return $rows;
    }

    private function sh_parse_xlsx_shared_strings(ZipArchive $zip)
    {
        $shared_strings_xml = $zip->getFromName('xl/sharedStrings.xml');

        if (!is_string($shared_strings_xml) || $shared_strings_xml === '') {
            return array();
        }

        $shared_strings = simplexml_load_string($shared_strings_xml);

        if (!$shared_strings instanceof SimpleXMLElement) {
            return array();
        }

        $values = array();

        foreach ($shared_strings->xpath('/*[local-name()="sst"]/*[local-name()="si"]') as $item) {
            $text_nodes = $item->xpath('./*[local-name()="t"]');

            if (!empty($text_nodes)) {
                $values[] = (string) $text_nodes[0];
                continue;
            }

            $text = '';

            foreach ($item->xpath('./*[local-name()="r"]/*[local-name()="t"]') as $run_text) {
                $text .= (string) $run_text;
            }

            $values[] = $text;
        }

        return $values;
    }

    private function sh_get_xlsx_cell_value(SimpleXMLElement $cell, $shared_strings)
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            $inline_text = $cell->xpath('./*[local-name()="is"]/*[local-name()="t"]');

            if (!empty($inline_text)) {
                return trim((string) $inline_text[0]);
            }
        }

        $value_nodes = $cell->xpath('./*[local-name()="v"]');
        $value = !empty($value_nodes) ? (string) $value_nodes[0] : '';

        if ($type === 's') {
            $index = (int) $value;

            return isset($shared_strings[$index]) ? trim((string) $shared_strings[$index]) : '';
        }

        return trim($value);
    }

    private function sh_get_xlsx_column_index($cell_reference)
    {
        $column_letters = preg_replace('/[^A-Z]/', '', strtoupper((string) $cell_reference));
        $index = 0;

        for ($i = 0; $i < strlen($column_letters); $i++) {
            $index = ($index * 26) + (ord($column_letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }

    private function sh_normalize_headers($headers)
    {
        return array_map(
            static function ($header) {
                $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);

                return sanitize_key(remove_accents($header));
            },
            $headers
        );
    }

    private function sh_headers_contain_city_and_postal_code($headers)
    {
        $city_keys = array('grad', 'city', 'city_name', 'ime_grada', 'naziv_grada', 'name');
        $postal_keys = array('postanski_broj', 'postanski_broj_grada', 'postal_code', 'postcode', 'zip', 'zip_code');

        return count(array_intersect($headers, $city_keys)) > 0 && count(array_intersect($headers, $postal_keys)) > 0;
    }

    private function sh_detect_csv_delimiter($first_row)
    {
        $raw = implode(',', $first_row);
        $delimiters = array(
            ';' => substr_count($raw, ';'),
            "\t" => substr_count($raw, "\t"),
            ',' => substr_count($raw, ','),
        );

        arsort($delimiters);

        return (string) key($delimiters);
    }

    private function sh_reparse_csv_line($data, $delimiter)
    {
        if (count($data) !== 1) {
            return $data;
        }

        return str_getcsv((string) $data[0], $delimiter);
    }

    private function sh_map_row_by_headers($headers, $values)
    {
        $mapped = array();

        foreach ($headers as $index => $header) {
            $mapped[$header] = isset($values[$index]) ? trim((string) $values[$index]) : '';
        }

        return $this->sh_extract_row_from_assoc($mapped);
    }

    private function sh_extract_row_from_assoc($item)
    {
        $city_name = '';
        $postal_code = '';
        $normalized_item = array();

        foreach ($item as $key => $value) {
            $normalized_item[sanitize_key(remove_accents((string) $key))] = $value;
        }

        $city_keys = array('grad', 'city', 'city_name', 'ime_grada', 'naziv_grada', 'name');
        $postal_keys = array('postanski_broj', 'postanski_broj_grada', 'postal_code', 'postcode', 'zip', 'zip_code');

        foreach ($city_keys as $key) {
            if (isset($normalized_item[$key]) && trim((string) $normalized_item[$key]) !== '') {
                $city_name = trim((string) $normalized_item[$key]);
                break;
            }
        }

        foreach ($postal_keys as $key) {
            if (isset($normalized_item[$key]) && trim((string) $normalized_item[$key]) !== '') {
                $postal_code = trim((string) $normalized_item[$key]);
                break;
            }
        }

        if ($city_name === '' || $postal_code === '') {
            return array();
        }

        return array(
            'city_name' => $city_name,
            'postal_code' => $postal_code,
        );
    }
}
