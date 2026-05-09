<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap sh-validator-admin">
    <h1><?php esc_html_e('Validator korpe za internet prodavnicu', 'sh-validator-korpe'); ?></h1>
    <p><?php esc_html_e('Dodatak za internet prodavnicu koji proverava i sređuje podatke kupca tokom procesa poručivanja.', 'sh-validator-korpe'); ?></p>

    <?php if (!empty($notice)) : ?>
        <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible">
            <p><?php echo esc_html($notice); ?></p>
        </div>
    <?php endif; ?>

    <div class="sh-validator-grid">
        <div class="sh-validator-card">
            <h2><?php echo $editing_city ? esc_html__('Izmena grada', 'sh-validator-korpe') : esc_html__('Dodavanje grada', 'sh-validator-korpe'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('sh_save_city'); ?>
                <input type="hidden" name="city_id" value="<?php echo !empty($editing_city['id']) ? esc_attr($editing_city['id']) : 0; ?>">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="sh-city-name"><?php esc_html_e('Grad', 'sh-validator-korpe'); ?></label></th>
                        <td><input id="sh-city-name" type="text" class="regular-text" name="city_name" value="<?php echo !empty($editing_city['city_name']) ? esc_attr($editing_city['city_name']) : ''; ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sh-postal-code"><?php esc_html_e('Poštanski broj', 'sh-validator-korpe'); ?></label></th>
                        <td><input id="sh-postal-code" type="text" class="regular-text" name="postal_code" value="<?php echo !empty($editing_city['postal_code']) ? esc_attr($editing_city['postal_code']) : ''; ?>" required></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="sh_save_city" class="button button-primary"><?php esc_html_e('Sačuvaj grad', 'sh-validator-korpe'); ?></button>
                    <?php if ($editing_city) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=sh-validator-korpe')); ?>" class="button"><?php esc_html_e('Otkaži izmenu', 'sh-validator-korpe'); ?></a>
                    <?php endif; ?>
                </p>
            </form>
        </div>

        <div class="sh-validator-card">
            <h2><?php esc_html_e('Česte email greške', 'sh-validator-korpe'); ?></h2>
            <p><?php esc_html_e('Svaki red unesite kao pogresan-domen=ispravan-domen. Primer: gmaik.com=gmail.com', 'sh-validator-korpe'); ?></p>
            <form method="post">
                <?php wp_nonce_field('sh_save_email_typos'); ?>
                <textarea name="email_typos" rows="14" class="large-text code"><?php echo esc_textarea($email_typos); ?></textarea>
                <p class="submit">
                    <button type="submit" name="sh_save_email_typos" class="button button-primary"><?php esc_html_e('Sačuvaj email pravila', 'sh-validator-korpe'); ?></button>
                </p>
            </form>
        </div>
    </div>

    <div class="sh-validator-card sh-validator-table-card">
        <h2><?php esc_html_e('Lista gradova', 'sh-validator-korpe'); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'sh-validator-korpe'); ?></th>
                    <th><?php esc_html_e('Grad', 'sh-validator-korpe'); ?></th>
                    <th><?php esc_html_e('Poštanski broj', 'sh-validator-korpe'); ?></th>
                    <th><?php esc_html_e('Akcije', 'sh-validator-korpe'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cities)) : ?>
                    <tr>
                        <td colspan="4"><?php esc_html_e('Nema unetih gradova.', 'sh-validator-korpe'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($cities as $city) : ?>
                        <tr>
                            <td><?php echo esc_html($city['id']); ?></td>
                            <td><?php echo esc_html($city['city_name']); ?></td>
                            <td><?php echo esc_html($city['postal_code']); ?></td>
                            <td>
                                <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=sh-validator-korpe&action=edit&city_id=' . absint($city['id']))); ?>"><?php esc_html_e('Izmeni', 'sh-validator-korpe'); ?></a>
                                <a class="button button-small button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=sh-validator-korpe&action=delete&city_id=' . absint($city['id'])), 'sh_delete_city_' . absint($city['id']))); ?>" onclick="return confirm('<?php echo esc_js(__('Da li ste sigurni da želite da obrišete grad?', 'sh-validator-korpe')); ?>');"><?php esc_html_e('Obriši', 'sh-validator-korpe'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
