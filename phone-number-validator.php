<?php
/**
 * Plugin Name: Phone Number Prefix Validator
 * Description: A plugin to restrict phone numbers to specific prefixes and lengths.
 * Version: 1.0
 * Author: Your Name
 */

add_action('wp_enqueue_scripts', 'pnv_enqueue_checkout_js');
function pnv_enqueue_checkout_js() {
    if (is_checkout()) {
        $js_file = plugin_dir_path(__FILE__) . 'assets/phone-validator.js';
        $js_url  = plugins_url('assets/phone-validator.js', __FILE__);
        $version = file_exists($js_file) ? filemtime($js_file) : '1.0';

        wp_enqueue_script('pnv-phone-validator', $js_url, array('jquery'), $version, true);

        // Enqueue CSS
        $css_file = plugin_dir_path(__FILE__) . 'assets/phone-validator.css';
        $css_url  = plugins_url('assets/phone-validator.css', __FILE__);
        $css_version = file_exists($css_file) ? filemtime($css_file) : '1.0';
        wp_enqueue_style('pnv-phone-validator', $css_url, array(), $css_version);

        // Get first allowed prefix (digits only in settings)
        $allowed_prefixes = array_values(array_filter(array_map('trim', explode(',', get_option('pnv_allowed_prefixes', '')))));
        $raw_prefix = isset($allowed_prefixes[0]) ? $allowed_prefixes[0] : '';

        $expected_len_digits = intval(get_option('pnv_phone_length', '10')); // total digits incl. prefix
        $ui_prefix = $raw_prefix ? $raw_prefix : ''; // no '+' sign for Romanian format

        wp_localize_script('pnv-phone-validator', 'pnv_data', array(
            'maxLength' => $expected_len_digits,
            'prefix'    => $ui_prefix
        ));
    }
}




//  Create admin settings page
add_action('admin_menu', function() {
    add_options_page(
        'Phone Number Validator',
        'Phone Validator',
        'manage_options',
        'phone-validator',
        'pnv_settings_page'
    );
});

function pnv_settings_page() {
    ?>
    <div class="wrap">
        <h1>Phone Number Validator Settings</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields('pnv_settings_group');
                do_settings_sections('phone-validator');
                submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', function() {
    register_setting('pnv_settings_group', 'pnv_allowed_prefixes');
    register_setting('pnv_settings_group', 'pnv_phone_length');

    add_settings_section('pnv_section', 'Validation Settings', null, 'phone-validator');

    add_settings_field(
        'pnv_allowed_prefixes',
        'Allowed Prefixes (comma-separated)',
        function() {
            $val = esc_attr(get_option('pnv_allowed_prefixes', ''));
            echo "<input type='text' name='pnv_allowed_prefixes' value='$val' size='50'>";
            echo "<p class='description'>Example: 0901,0902,0903</p>";
        },
        'phone-validator',
        'pnv_section'
    );

    add_settings_field(
        'pnv_phone_length',
        'Expected Phone Number Length',
        function() {
            $val = esc_attr(get_option('pnv_phone_length', '9'));
            echo "<input type='number' name='pnv_phone_length' value='$val' min='1' max='15'>";
        },
        'phone-validator',
        'pnv_section'
    );
});

// Validate phone numbers (for WooCommerce checkout field, for example)
add_action('woocommerce_checkout_process', 'pnv_validate_phone_number');
function pnv_validate_phone_number() {
    $allowed_prefixes = array_filter(array_map('trim', explode(',', get_option('pnv_allowed_prefixes', ''))));
    $expected_length  = intval(get_option('pnv_phone_length', '9')); // total digits incl. prefix, no '+'

    if (empty($allowed_prefixes) || !$expected_length) return;

    if (isset($_POST['billing_phone'])) {
        // Remove all non-digits (so +, spaces, dashes, etc. are ignored)
        $digits = preg_replace('/\D+/', '', (string) $_POST['billing_phone']);

        // Also normalize prefixes to digits-only
        $allowed_prefixes_digits = array_map(function($p){ return preg_replace('/\D+/', '', $p); }, $allowed_prefixes);

        $is_valid = false;
        foreach ($allowed_prefixes_digits as $prefix) {
            if ($prefix !== '' && strpos($digits, $prefix) === 0 && strlen($digits) === $expected_length) {
                // Romanian validation: check if starts with 07 and has correct length
                if ($prefix === '07' && strlen($digits) === 10) {
                    $is_valid = true;
                    break;
                } elseif ($prefix !== '07' && strpos($digits, $prefix) === 0 && strlen($digits) === $expected_length) {
                    // Other prefixes (future-proofing for other countries)
                    $is_valid = true;
                    break;
                }
            }
        }

        if (!$is_valid) {
            if (strpos($digits, '07') === 0 && strlen($digits) !== 10) {
                wc_add_notice(
                    __('Numărul de telefon mobil românesc trebuie să înceapă cu 07. Verificați numărul dvs.', 'phone-validator'),
                    'error'
                );
            } else {
                wc_add_notice(
                    __('Introduceți un număr de telefon mobil românesc valid în formatul 07XXXXXXXX', 'phone-validator'),
                    'error'
                );
            }
        }
    }
}

add_filter('woocommerce_checkout_fields', 'pnv_add_maxlength_to_phone_field');
function pnv_add_maxlength_to_phone_field($fields) {
    $expected_len_digits = intval(get_option('pnv_phone_length', '10')); // digits only
    $allowed_prefixes = array_values(array_filter(array_map('trim', explode(',', get_option('pnv_allowed_prefixes', '')))));
    $has_prefix = !empty($allowed_prefixes);
    // No extra char needed for Romanian format (no '+' sign)
    $fields['billing']['billing_phone']['maxlength'] = $expected_len_digits;
    $fields['billing']['billing_phone']['inputmode'] = 'tel';
    return $fields;
}
