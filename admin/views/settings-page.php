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
        <form method="get" class="sh-validator-search-form">
            <input type="hidden" name="page" value="sh-validator-korpe">
            <p class="search-box">
                <label class="screen-reader-text" for="sh-city-search"><?php esc_html_e('Pretraga gradova', 'sh-validator-korpe'); ?></label>
                <input id="sh-city-search" type="search" name="s" value="<?php echo esc_attr($cities_search); ?>" placeholder="<?php esc_attr_e('Pretraga po gradu ili poštanskom broju', 'sh-validator-korpe'); ?>">
                <button type="submit" class="button"><?php esc_html_e('Pretraži', 'sh-validator-korpe'); ?></button>
                <?php if ($cities_search !== '') : ?>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sh-validator-korpe')); ?>"><?php esc_html_e('Resetuj', 'sh-validator-korpe'); ?></a>
                <?php endif; ?>
            </p>
        </form>

        <form method="post">
            <?php wp_nonce_field('sh_bulk_cities'); ?>
            <input type="hidden" name="s" value="<?php echo esc_attr($cities_search); ?>">
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="sh-bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e('Izaberite grupnu akciju', 'sh-validator-korpe'); ?></label>
                    <select id="sh-bulk-action-selector-top" name="sh_bulk_action">
                        <option value=""><?php esc_html_e('Grupne akcije', 'sh-validator-korpe'); ?></option>
                        <option value="delete"><?php esc_html_e('Obriši', 'sh-validator-korpe'); ?></option>
                    </select>
                    <button type="submit" class="button action" onclick="return confirm('<?php echo esc_js(__('Da li ste sigurni da želite da obrišete izabrane gradove?', 'sh-validator-korpe')); ?>');"><?php esc_html_e('Primeni', 'sh-validator-korpe'); ?></button>
                </div>
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php
                        printf(
                            esc_html__('%d stavki', 'sh-validator-korpe'),
                            (int) $cities_total_items
                        );
                        ?>
                    </span>
                    <?php
                    echo wp_kses_post(
                        paginate_links(
                            array(
                                'base' => add_query_arg(
                                    array(
                                        'page' => 'sh-validator-korpe',
                                        's' => $cities_search,
                                        'paged' => '%#%',
                                    ),
                                    admin_url('admin.php')
                                ),
                                'format' => '',
                                'current' => $cities_current_page,
                                'total' => $cities_total_pages,
                                'prev_text' => '&lsaquo;',
                                'next_text' => '&rsaquo;',
                            )
                        )
                    );
                    ?>
                </div>
                <br class="clear">
            </div>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column"><input type="checkbox" class="sh-select-all-cities"></td>
                        <th><?php esc_html_e('ID', 'sh-validator-korpe'); ?></th>
                        <th><?php esc_html_e('Grad', 'sh-validator-korpe'); ?></th>
                        <th><?php esc_html_e('Poštanski broj', 'sh-validator-korpe'); ?></th>
                        <th><?php esc_html_e('Akcije', 'sh-validator-korpe'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cities)) : ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e('Nema unetih gradova.', 'sh-validator-korpe'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($cities as $city) : ?>
                            <tr>
                                <th scope="row" class="check-column"><input type="checkbox" name="city_ids[]" value="<?php echo esc_attr($city['id']); ?>"></th>
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

            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo wp_kses_post(
                        paginate_links(
                            array(
                                'base' => add_query_arg(
                                    array(
                                        'page' => 'sh-validator-korpe',
                                        's' => $cities_search,
                                        'paged' => '%#%',
                                    ),
                                    admin_url('admin.php')
                                ),
                                'format' => '',
                                'current' => $cities_current_page,
                                'total' => $cities_total_pages,
                                'prev_text' => '&lsaquo;',
                                'next_text' => '&rsaquo;',
                            )
                        )
                    );
                    ?>
                </div>
                <br class="clear">
            </div>
        </form>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var selectAll = document.querySelector('.sh-select-all-cities');

                if (!selectAll) {
                    return;
                }

                selectAll.addEventListener('change', function () {
                    document.querySelectorAll('input[name="city_ids[]"]').forEach(function (checkbox) {
                        checkbox.checked = selectAll.checked;
                    });
                });
            });
        </script>
    </div>
</div>
