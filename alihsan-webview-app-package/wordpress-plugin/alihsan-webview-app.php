<?php
/**
 * Plugin Name: Al-Ihsan WebView App
 * Plugin URI: https://alihsan.free.nf/
 * Description: إعدادات وتكوين تطبيق Android يعرض موقع مدرسة الإحسان داخل WebView.
 * Version: 1.0.0
 * Author: Al-Ihsan
 * License: GPL-2.0-or-later
 * Text Domain: alihsan-webview-app
 */

if (!defined('ABSPATH')) { exit; }

final class AlIhsan_WebView_App {
    const OPTION = 'alihsan_webview_app_options';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function defaults() {
        return [
            'site_url' => home_url('/'),
            'app_name' => 'مدرسة الإحسان',
            'package_name' => 'com.alihsan.app',
            'theme_color' => '#176b4f',
            'splash_color' => '#176b4f',
            'allow_external_links' => '0',
        ];
    }

    private function options() {
        return wp_parse_args((array) get_option(self::OPTION, []), $this->defaults());
    }

    public function admin_menu() {
        add_options_page('تطبيق مدرسة الإحسان', 'تطبيق WebView', 'manage_options', 'alihsan-webview-app', [$this, 'settings_page']);
    }

    public function register_settings() {
        register_setting('alihsan_webview_app_group', self::OPTION, [$this, 'sanitize']);
        add_settings_section('alihsan_main', 'إعدادات التطبيق', function () {
            echo '<p>هذه الإعدادات تُستخدم مع قالب تطبيق Android المرفق. التطبيق يعرض موقعك عبر HTTPS فقط.</p>';
        }, 'alihsan-webview-app');
        $fields = [
            'site_url' => ['رابط الموقع', 'url'],
            'app_name' => ['اسم التطبيق', 'text'],
            'package_name' => ['اسم الحزمة Android', 'text'],
            'theme_color' => ['لون التطبيق', 'color'],
            'splash_color' => ['لون شاشة البداية', 'color'],
        ];
        foreach ($fields as $key => $field) {
            add_settings_field($key, esc_html($field[0]), [$this, 'field'], 'alihsan-webview-app', 'alihsan_main', ['key' => $key, 'type' => $field[1]]);
        }
        add_settings_field('allow_external_links', 'الروابط الخارجية', [$this, 'external_field'], 'alihsan-webview-app', 'alihsan_main');
    }

    public function sanitize($input) {
        $old = $this->options();
        $out = $old;
        $out['site_url'] = esc_url_raw($input['site_url'] ?? $old['site_url']);
        if (!preg_match('#^https://#i', $out['site_url'])) { $out['site_url'] = $old['site_url']; }
        $out['app_name'] = sanitize_text_field($input['app_name'] ?? $old['app_name']);
        $out['package_name'] = preg_replace('/[^a-zA-Z0-9_.]/', '', $input['package_name'] ?? $old['package_name']);
        $out['theme_color'] = sanitize_hex_color($input['theme_color'] ?? $old['theme_color']) ?: $old['theme_color'];
        $out['splash_color'] = sanitize_hex_color($input['splash_color'] ?? $old['splash_color']) ?: $old['splash_color'];
        $out['allow_external_links'] = !empty($input['allow_external_links']) ? '1' : '0';
        return $out;
    }

    public function field($args) {
        $o = $this->options(); $key = $args['key'];
        printf('<input class="regular-text" type="%s" name="%s[%s]" value="%s" />', esc_attr($args['type']), esc_attr(self::OPTION), esc_attr($key), esc_attr($o[$key]));
    }

    public function external_field() {
        $o = $this->options();
        printf('<label><input type="checkbox" name="%s[allow_external_links]" value="1" %s /> فتح الروابط الخارجية في المتصفح</label>', esc_attr(self::OPTION), checked($o['allow_external_links'], '1', false));
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) { return; }
        $o = $this->options();
        ?>
        <div class="wrap" dir="rtl">
            <h1>تطبيق مدرسة الإحسان — WebView</h1>
            <p>بعد تثبيت الإضافة، استخدم القيم التالية عند بناء مشروع Android المرفق.</p>
            <form method="post" action="options.php">
                <?php settings_fields('alihsan_webview_app_group'); do_settings_sections('alihsan-webview-app'); submit_button('حفظ الإعدادات'); ?>
            </form>
            <hr>
            <h2>رابط إعدادات التطبيق</h2>
            <p><code><?php echo esc_html(rest_url('alihsan/v1/config')); ?></code></p>
            <p>لا تضع مفاتيح سرية في هذه الإعدادات؛ هذه الواجهة عامة وتُرجع إعدادات العرض فقط.</p>
        </div>
        <?php
    }

    public function register_rest_routes() {
        register_rest_route('alihsan/v1', '/config', [
            'methods' => 'GET',
            'callback' => [$this, 'config_response'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function config_response() {
        $o = $this->options();
        return rest_ensure_response([
            'app_name' => $o['app_name'],
            'site_url' => $o['site_url'],
            'theme_color' => $o['theme_color'],
            'splash_color' => $o['splash_color'],
            'allow_external_links' => $o['allow_external_links'] === '1',
            'plugin_version' => '1.0.0',
        ]);
    }
}

new AlIhsan_WebView_App();
