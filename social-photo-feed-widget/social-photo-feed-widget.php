<?php
/*
Plugin Name: Widgets for Social Photo Feed
Plugin Title: Widgets for Social Photo Feed Plugin
Plugin URI: https://wordpress.org/plugins/social-photo-feed-widget/
Description: Instagram Feed Widgets. Display your Instagram feed on your website to increase engagement, sales and SEO.
Tags: instagram, instagram feed, instagram gallery, instagram photos, instagram widget
Version: 1.7.5
Requires at least: 6.2
Requires PHP: 7.0
Author: Trustindex.io <support@trustindex.io>
Author URI: https://www.trustindex.io/
Contributors: trustindex
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: social-photo-feed-widget
Domain Path: /languages/
Donate link: https://www.trustindex.io/prices/
*/
/*
You should have received a copy of the GNU General Public License
along with Review widget addon for Divi. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/
/*
Copyright 2019 Trustindex Kft (email: support@trustindex.io)
*/
defined('ABSPATH') or die('No script kiddies please!');
require_once plugin_dir_path(__FILE__) . 'include' . DIRECTORY_SEPARATOR . 'cache-plugin-filters.php';
require_once plugin_dir_path( __FILE__ ) . 'trustindex-feed-plugin.class.php';
$trustindex_feed_instagram = new TRUSTINDEX_Feed_Instagram("instagram", __FILE__, "1.7.5", "Widgets for Social Photo Feed", "Instagram");
$pluginManagerInstance = $trustindex_feed_instagram;
register_activation_hook(__FILE__, [ $pluginManagerInstance, 'activate' ]);
register_deactivation_hook(__FILE__, [ $pluginManagerInstance, 'deactivate' ]);
add_action('plugins_loaded', [ $pluginManagerInstance, 'load' ]);
add_action('admin_menu', [ $pluginManagerInstance, 'addSettingMenu' ], 10);
add_filter('plugin_action_links', [ $pluginManagerInstance, 'addPluginActionLinks' ], 10, 2);
add_filter('plugin_row_meta', [ $pluginManagerInstance, 'addPluginMetaLinks' ], 10, 2);
add_action('init', [ $pluginManagerInstance, 'outputBuffer' ]);
add_action('admin_enqueue_scripts', [ $pluginManagerInstance, 'addScripts' ]);
if (is_file($pluginManagerInstance->getCssFile())) {
add_action('init', function() use ($pluginManagerInstance) {
$path = wp_upload_dir()['baseurl'] .'/'. $pluginManagerInstance->getCssFile(true);
if (is_ssl()) {
$path = str_replace('http://', 'https://', $path);
}
wp_register_style($pluginManagerInstance->getCssKey(), $path, [], filemtime($pluginManagerInstance->getCssFile()));
});
}
if (!function_exists('trustindex_esc_css')) {
 function trustindex_esc_css($css) {
 $css = wp_strip_all_tags($css);
 $css = esc_html($css);
 $css = str_replace(['&lt;','&gt;','&quot;', '&#039;', '&amp;'], ['<', '>', '"', "'", '&'], $css);
 return $css;
 }
}
add_action('init', [ $pluginManagerInstance, 'shortcode' ]);
add_filter('script_loader_tag', function($tag, $handle, $src) {
if ($handle === 'trustindex-feed-loader-js' && strpos($tag, 'defer async') === false) {
$tag = str_replace(' src', ' defer async src', $tag);
}
if (strpos($handle, 'trustindex-feed-data') !== false) {
$content = [];
$content_pattern = '/script_content_start(.*)script_content_end/';
if (preg_match($content_pattern, $tag, $content)) {
$replace = [
 '/\s+type\s*=\s*["\'][^"\']+["\']/' => '',
'/\s+src\s*=\s*/' => ' type="application/json" data-src=',
$content_pattern => '',
'/><\//' => '>'. base64_decode($content[1]) .'</',
];
$tag = preg_replace(array_keys($replace), array_values($replace), $tag);
}
}
return $tag;
}, 10, 3);
add_action('rest_api_init', function () use ($pluginManagerInstance) {
register_rest_route($pluginManagerInstance->getWebhookAction(), '/get-token', [
'methods' => 'GET',
'callback' => function () {
return rest_ensure_response([
'nonce' => wp_create_nonce('admin_action_nonce'),
]);
},
'permission_callback' => '__return_true',
]);
register_rest_route($pluginManagerInstance->getWebhookAction(), '/troubleshooting', [
'methods' => 'GET',
'callback' => function () use ($pluginManagerInstance) {
header('Content-Type: text/plain; charset=UTF-8');
global $wpdb;
include $pluginManagerInstance->getPluginDir() . 'include' . DIRECTORY_SEPARATOR . 'troubleshooting.php';
exit;
},
'permission_callback' => '__return_true',
]);
register_rest_route($pluginManagerInstance->getWebhookAction(), '/submit-data', [
'methods' => 'POST',
'callback' => function (WP_REST_Request $request) use ($pluginManagerInstance) {
$nonce = $request->get_param('nonce');
if (!wp_verify_nonce($nonce, 'admin_action_nonce')) {
return new WP_REST_Response(['error' => 'Invalid nonce'], 403);
}
$oldSource = $pluginManagerInstance->getConnectedSource();
/*
This function ensures that each element of the JSON object is sanitized individually using standard WordPress sanitization functions
*/
$source = $pluginManagerInstance->sanitizeJsonData(wp_unslash($request->get_param('data')), false);
$source = $pluginManagerInstance->saveConnectedSource($source);
if (empty($source['error']) && !$pluginManagerInstance->isDownloadInProgress()) {
$lastDownloadChecked = get_option($pluginManagerInstance->getOptionName('feed-data-download-checked'), 0);
if ($lastDownloadChecked + $pluginManagerInstance::$downloadCheckSeconds + 1 < time()) {
if ($oldSource) {
$notificationType = 'post-download-finished';
} else {
$notificationType = 'connect-finished';
}
$pluginManagerInstance->sendNotificationEmail($notificationType);
$pluginManagerInstance->setNotificationParam($notificationType, 'active', true);
$pluginManagerInstance->setNotificationParam($notificationType, 'do-check', false);
}
}
return new WP_REST_Response([
'token' => get_option($pluginManagerInstance->getOptionName('public-id')),
]);
},
'permission_callback' => '__return_true',
]);
});
add_action('wp_ajax_download_check', function() use ($pluginManagerInstance) {
check_ajax_referer('ti-download-check', 'nonce');
update_option($pluginManagerInstance->getOptionName('feed-data-download-checked'), time(), false);
wp_send_json(['downloaded' => !$pluginManagerInstance->isDownloadInProgress()]);
});
add_action('admin_notices', function() use ($pluginManagerInstance) {
if (!current_user_can($pluginManagerInstance::$permissionNeeded)) {
return;
}
foreach ($pluginManagerInstance->getNotificationOptions() as $type => $options) {
if (!$pluginManagerInstance->isNotificationActive($type)) {
continue;
}
echo '<div class="notice notice-'. esc_attr($options['type']) .' '. ($options['is-closeable'] ? 'is-dismissible' : '') .' trustindex-notification-row '. esc_attr($options['extra-class']).'" data-close-url="'. esc_url($pluginManagerInstance->getNotificationActionUrl($type, 'close')) .'">';
if ($type === 'rate-us') {
echo '<div class="trustindex-star-row">&starf;&starf;&starf;&starf;&starf;</div>';
}
echo '<p>'. wp_kses_post($options['text']) .'<p>';
if ($type === 'rate-us') {
echo '
<a href="'. esc_url($pluginManagerInstance->getNotificationActionUrl($type, 'open')) .'" class="ti-close-notification" target="_blank">
<button class="button ti-button-primary button-primary">'. esc_html(__('Write a review', 'social-photo-feed-widget')) .'</button>
</a>
<a href="'. esc_url($pluginManagerInstance->getNotificationActionUrl($type, 'later')) .'" class="ti-remind-later">
'. esc_html(__('Maybe later', 'social-photo-feed-widget')) .'
</a>
<a href="'. esc_url($pluginManagerInstance->getNotificationActionUrl($type, 'hide')) .'" class="ti-hide-notification" style="float: right; margin-top: 14px">
'. esc_html(__('Do not remind me again', 'social-photo-feed-widget')) .'
</a>
';
} else {
echo '
<a href="'. esc_url($pluginManagerInstance->getNotificationActionUrl($type, 'open')) .'">
<button class="button button-primary">'. esc_html($options['button-text']) .'</button>
</a>';
if ($options['remind-later-button']) {
echo '
<a href="'. esc_url($pluginManagerInstance->getNotificationActionUrl($type, 'later')) .'" class="ti-remind-later" style="margin-left: 5px">
'. esc_html(__('Remind me later', 'social-photo-feed-widget')) .'
</a>';
}
}
echo '
</p>
</div>';
}
});
add_action('elementor/widgets/widgets_registered', function ($widgetsManager) {
require_once(__DIR__ . '/include/trustindex-elementor-widgets.php');
$widgetsManager->register(new \Elementor\TrustrindexFeedWidget_Instagram());
});
add_action('elementor/elements/categories_registered', function ($elementsManager) {
$elementsManager->add_category(
'trustindex',
[
'title' => __('Trustindex', 'social-photo-feed-widget'),
'icon' => 'fa fa-plug',
]
);
});
add_action('wp_enqueue_scripts', function() use ($pluginManagerInstance) {
if (!is_user_logged_in() || !current_user_can($pluginManagerInstance::$permissionNeeded)) {
return;
}
foreach ($pluginManagerInstance->getNotificationOptions() as $type => $options) {
if (!$pluginManagerInstance->isNotificationActive($type) || !isset($options['short-message'])) {
continue;
}
echo '<div class="trustindex-notice notice-'. esc_attr($options['type']) . '" style="left:-50%;opacity:0;" data-redirect-url="'. esc_url($pluginManagerInstance->getNotificationActionUrl($type, 'open')) .'">';
echo '<span class="trustindex-notice-dismiss" data-close-url="' . esc_url($pluginManagerInstance->getNotificationActionUrl($type, 'later', '1')) . '"></span>';
echo wp_kses_post($options['short-message']);
echo '</div>';
}
wp_register_script('trustindex_frontend_notification', $pluginManagerInstance->getPluginFileUrl('assets/js/frontend-notifictions.js'), [], $pluginManagerInstance->getVersion(), ['in_footer' => false]);
wp_enqueue_script('trustindex_frontend_notification');
wp_enqueue_style('trustindex_frontend_notification', $pluginManagerInstance->getPluginFileUrl('assets/css/frontend-notifictions.css'), [], $pluginManagerInstance->getVersion());
});
unset($pluginManagerInstance);
?>
