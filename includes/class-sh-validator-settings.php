<?php

if (!defined('ABSPATH')) {
    exit;
}

class SH_Validator_Settings
{
    const OPTION_EMAIL_TYPOS = 'sh_validator_email_typos';

    public function sh_get_email_typos()
    {
        $typos = get_option(self::OPTION_EMAIL_TYPOS, array());

        if (!is_array($typos) || empty($typos)) {
            $typos = SH_Validator_Installer::sh_get_default_email_typos();
        }

        ksort($typos);

        return $typos;
    }

    public function sh_get_email_typos_for_textarea()
    {
        $lines = array();

        foreach ($this->sh_get_email_typos() as $wrong => $correct) {
            $lines[] = $wrong . '=' . $correct;
        }

        return implode("\n", $lines);
    }

    public function sh_save_email_typos_from_textarea($raw_value)
    {
        $typos = array();
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw_value);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }

            list($wrong, $correct) = array_map('trim', explode('=', $line, 2));

            if ($wrong === '' || $correct === '') {
                continue;
            }

            $wrong = strtolower(sanitize_text_field($wrong));
            $correct = strtolower(sanitize_text_field($correct));
            $typos[$wrong] = $correct;
        }

        if (empty($typos)) {
            $typos = SH_Validator_Installer::sh_get_default_email_typos();
        }

        ksort($typos);
        update_option(self::OPTION_EMAIL_TYPOS, $typos);
    }

    public function sh_get_email_suggestion($email)
    {
        $email = strtolower(trim((string) $email));

        if ($email === '' || strpos($email, '@') === false) {
            return '';
        }

        list($local_part, $domain_part) = explode('@', $email, 2);

        if ($local_part === '' || $domain_part === '') {
            return '';
        }

        $domain_fixes = $this->sh_get_email_typos();

        if (isset($domain_fixes[$domain_part])) {
            return $local_part . '@' . $domain_fixes[$domain_part];
        }

        $last_dot_position = strrpos($domain_part, '.');

        if ($last_dot_position === false) {
            return '';
        }

        $domain_name = substr($domain_part, 0, $last_dot_position);
        $tld = substr($domain_part, $last_dot_position + 1);
        $tld_fixes = array(
            'co' => 'com',
            'cim' => 'com',
            'cm' => 'com',
            'cn' => 'com',
            'comm' => 'com',
            'con' => 'com',
            'ne' => 'net',
            'nett' => 'net',
            'og' => 'org',
            'ogr' => 'org',
            'rss' => 'rs',
            'rsss' => 'rs',
        );

        if (isset($tld_fixes[$tld])) {
            return $local_part . '@' . $domain_name . '.' . $tld_fixes[$tld];
        }

        return '';
    }
}
