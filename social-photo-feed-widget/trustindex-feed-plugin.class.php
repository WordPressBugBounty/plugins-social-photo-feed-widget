<?php
class TRUSTINDEX_Feed_Instagram
{
private $pluginFilePath;
private $pluginName;
private $platformName;
private $version;
private $shortname;
public static $permissionNeeded = 'edit_pages';
public function __construct($shortname, $pluginFilePath, $version, $pluginName, $platformName)
{
$this->shortname = $shortname;
$this->pluginFilePath = $pluginFilePath;
$this->version = $version;
$this->pluginName = $pluginName;
$this->platformName = $platformName;
}
public function getShortName()
{
return $this->shortname;
}
public function getWebhookAction()
{
return 'trustindex_feed_hook_' . $this->getShortName();
}
public function getWebhookUrl()
{
$webhookUrl = rest_url($this->getWebhookAction());
$response = wp_remote_get('https://admin.trustindex.io/api/testWordpressWebbookUrl?webhook=' . $webhookUrl, [
'timeout' => 30,
'sslverify' => false,
]);
if (is_wp_error($response)) {
return null;
}
$json = json_decode($response['body'], true);
if (!$json || !isset($json['valid']) || true !== $json['valid']) {
return null;
}
return $webhookUrl;
}


public function getPluginTabs()
{
$tabs = [];
$tabs[] = [
'place' => 'left',
'slug' => 'feed-configurator',
'name' => __('Feed Configurator', 'social-photo-feed-widget')
];
if ($this->getConnectedSource()) {
$tabs[] = [
'place' => 'left',
'slug' => 'my-posts',
'name' => __('My Posts', 'social-photo-feed-widget')
];
}
$tabs[] = [
'place' => 'left',
'slug' => 'get-more-features',
'name' => __('Get more features', 'social-photo-feed-widget')
];
$tabs[] = [
'place' => 'right',
'slug' => 'advanced',
'name' => __('Advanced', 'social-photo-feed-widget')
];
return $tabs;
}
public function getPluginDir()
{
return plugin_dir_path($this->pluginFilePath);
}
public function getPluginFileUrl($file, $addVersioning = true)
{
$info = pathinfo($file);
if (!isset($info['dirname'], $info['basename'], $info['extension'])) {
return $file;
}
$url = plugins_url($file, $this->pluginFilePath);
if ($addVersioning) {
$appendMark = strpos($url, '?') === FALSE ? '?' : '&';
$url .= $appendMark . 'ver=' . $this->getVersion();
}
return $url;
}
public function displayImg($image_url, $attributes = array())
{
$isUrl = preg_match('#^https?://#i', $image_url);
if (!$isUrl) {
$image_url = $this->getPluginFileUrl($image_url);
}
$defaults = array(
'src' => $isUrl ? esc_url($image_url) : sanitize_text_field($image_url),
'alt' => '',
'class' => '',
'style' => '',
);
$attributes = wp_parse_args($attributes, $defaults);
$attr_string = '';
foreach ($attributes as $key => $value) {
$attr_string .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
}
return sprintf('<img%s />', $attr_string);
}
public function getPluginSlug()
{
return basename($this->getPluginDir());
}
public function getPluginCurrentVersion()
{
$response = wp_remote_get('https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]='. $this->getPluginSlug());
$json = json_decode($response['body'], true);
if (!$json || !isset($json['version'])) {
return false;
}
return $json['version'];
}


public function activate()
{
include $this->getPluginDir() . 'include' . DIRECTORY_SEPARATOR . 'activate.php';
if (!$this->getNotificationParam('rate-us', 'hidden', false) && $this->getNotificationParam('rate-us', 'active', true)) {
$this->setNotificationParam('rate-us', 'active', true);
$this->setNotificationParam('rate-us', 'timestamp', time() + 86400);
}
update_option($this->getOptionName('activation-redirect'), 1, false);
}
public function load()
{
$this->loadI18N();
include $this->getPluginDir() . 'include' . DIRECTORY_SEPARATOR . 'update.php';
if (get_option($this->getOptionName('activation-redirect'))) {
delete_option($this->getOptionName('activation-redirect'));
wp_redirect(admin_url('admin.php?page=' . $this->getPluginSlug() . '/admin.php'));
exit;
}
$tokenExpireTimestamp = (int)get_option($this->getOptionName('token-expires'));
$isNotificationEnabled = $this->isNotificationEnabled('token-renew');
if ($tokenExpireTimestamp &&
$tokenExpireTimestamp < time() + (86400 * 7) &&
($this->getNotificationParam('token-renew', 'do-check', true) || !$isNotificationEnabled)
) {
$this->setNotificationParam('token-renew', 'active', $isNotificationEnabled);
$this->setNotificationParam('token-renew', 'do-check', false);
$this->setNotificationParam('token-expired', 'do-check', true);
}
$isNotificationEnabled = $this->isNotificationEnabled('token-expired');
if ($tokenExpireTimestamp &&
$tokenExpireTimestamp < time() &&
($this->getNotificationParam('token-expired', 'do-check', true) || !$isNotificationEnabled)
) {
$this->setNotificationParam('token-renew', 'active', false);
$this->setNotificationParam('token-expired', 'active', $isNotificationEnabled);
$this->setNotificationParam('token-expired', 'do-check', false);
}
}
public function deactivate()
{
update_option($this->getOptionName('active'), '0');
}
public function uninstall()
{
$this->deleteConnectedSource();
include $this->getPluginDir() . 'include' . DIRECTORY_SEPARATOR . 'uninstall.php';
if (is_file($this->getCssFile())) {
wp_delete_file($this->getCssFile());
}
}
public function outputBuffer()
{
ob_start();
}
public function loadI18N()
{
load_plugin_textdomain($this->getPluginSlug(), false, $this->getPluginSlug() . DIRECTORY_SEPARATOR . 'languages');
}


public function getShortcodeName($isAdmin = false)
{
return 'trustindex-feed'.($isAdmin ? '' : '-'.$this->getShortName());
}
public function shortcode()
{
$pluginManager = $this;
add_shortcode($this->getShortcodeName(), function($atts) use($pluginManager) {
if (!$pluginManager->getConnectedSource()) {
return $pluginManager->errorBoxForAdmins(__('You have to connect your source!', 'social-photo-feed-widget'));
}
return '<div id="'.esc_attr($pluginManager->getContainerKey($pluginManager->getWidget())).'"></div>';
});
add_shortcode($this->getShortcodeName(true), function($atts) use($pluginManager) {
$atts = shortcode_atts(['widget-id' => null], $atts);
if (!isset($atts['widget-id']) || !$atts['widget-id']) {
return false;
}
return $this->getAdminWidget($atts['widget-id']);
});
}


public function getConnectedSource()
{
$source = get_option($this->getOptionName('source'));
if (isset($source['name'])) {
$source['name'] = json_decode($source['name']);
}
return $source;
}
public function deleteConnectedSource()
{
$publicId = get_option($this->getOptionName('public-id'));
if (!$publicId) {
return false;
}
wp_remote_post('https://admin.trustindex.io/source/saveFeedWordpress', [
'body' => [
'is-delete' => 1,
'public-id' => $publicId
],
'timeout' => '30',
'redirection' => '5',
'blocking' => true
]);
return true;
}
public function getFeedData()
{
$data = [];
if ($jsonStr = get_option($this->getOptionName('feed-data'), "")) {
$data = json_decode($jsonStr, true);
$data['style'] = array_merge($data['style'], [
'settings' => [
'platform_style' => ucfirst($this->getShortName()),
'hidden_posts' => $data['style']['settings']['hidden_posts'] ?? [],
],
]);
if (!isset($data['style']['type'])) {
$data['style']['type'] = 'custom-style';
}
}
$dataSaved = time() - (int)get_option($this->getOptionName('feed-data-saved'), 0);
$allImageReplaced = true;
if ($data && $dataSaved > 600) {
foreach ($data['posts'] as $post) {
foreach ($post['media_content'] as $media) {
if (isset($media['image_url']) && !isset($media['image_urls'])) {
$allImageReplaced = false;
break 2;
}
}
}
}
if (!$data || $dataSaved > (12 * 3600) || !$allImageReplaced) {
$publicId = get_option($this->getOptionName('public-id'));
if (!$publicId) {
return $data;
}
$response = wp_remote_get('https://cdn.trustindex.io/wp-feeds/'. substr($publicId, 0, 2) .'/'. $publicId .'/data.json', [
'timeout' => 30,
'sslverify' => false
]);
if (is_wp_error($response)) {
echo wp_kses_post($this->errorBoxForAdmins(__('Could not download the posts for the widget.<br />Please reload the page.<br />If the problem persists, please write an email to support@trustindex.io.', 'social-photo-feed-widget') .'<br /><br />'. wp_json_encode($response)));
die;
}
$data = $this->updateFeedData(json_decode($response['body'], true), $data);
if ($tokenExpires = strtotime($data['token_expires'] ?? '')) {
update_option($this->getOptionName('token-expires'), $tokenExpires, false);
if (time() > $tokenExpires) {
$this->setNotificationParam('token-renew', 'active', false);
$this->setNotificationParam('token-expired', 'active', true);
} else {
$this->setNotificationParam('token-expired', 'active', false);
}
}
}
return $data;
}
public function saveFeedData($arr = [], $saveTime = true)
{
update_option($this->getOptionName('feed-data'), wp_json_encode($arr), false);
if ($saveTime) {
update_option($this->getOptionName('feed-data-saved'), time(), false);
}
}
public function updateFeedData($newData = [], $data = null)
{
if (!$data) {
$data = $this->getFeedData();
}
if (!$newData) {
return $data;
}
if ($data) {
foreach ($data as $key => $value) {
if (!in_array($key, [ 'posts', 'sources', 'source_types', 'sprite', 'token_expires' ])) {
$newData[ $key ] = $value;
}
}
}
switch (isset($newData['order']) ? $newData['order'] : "") {
default:
case 'newer_sooner':
usort($newData['posts'], function ($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
break;
case 'older_sooner':
usort($newData['posts'], function ($a, $b) { return strtotime($a['created_at']) - strtotime($b['created_at']); });
break;
case 'random':
shuffle($newData['posts']);
break;
}
$this->saveFeedData($newData);
return $newData;
}
public function saveConnectedSource($source, $pageToRedirectOnError = null)
{
if (!$source) {
return ['error' => 'no-source'];
}
if ((int)$source['token_expires'] <= 0) {
update_option($this->getOptionName('token-expires'), 0, false);
} else {
update_option($this->getOptionName('token-expires'), time() + (int) $source['token_expires'], false);
$this->setNotificationParam('token-renew', 'active', false);
$this->setNotificationParam('token-renew', 'do-check', true);
$this->setNotificationParam('token-expired', 'active', false);
$this->setNotificationParam('token-expired', 'do-check', true);
}
if (empty($source['feed_data']['posts'])) {
if (isset($pageToRedirectOnError)) {
header('Location: admin.php?page='.sanitize_text_field(wp_unslash($pageToRedirectOnError)).'&error=no-posts');
exit;
}
update_option(
$this->getOptionName('connect-pending'),
array_merge(
get_option($this->getOptionName('connect-pending'), []),
['error' => 'no-posts']
),
false
);
return ['error' => 'no-posts'];
}
if (isset($source['is_reconnecting']) && $source['is_reconnecting']) {
$this->updateFeedData($source['feed_data']);
$oldSource = $this->getConnectedSource();
$oldSource['access_token'] = $source['access_token'];
$source = $oldSource;
} else {
$this->saveFeedData($source['feed_data']);
update_option($this->getOptionName('public-id'), $source['public_id'], false);
unset($source['avatar_url']);
unset($source['feed_data']);
unset($source['token_expires']);
unset($source['access_token_expires']);
unset($source['public_id']);
unset($source['is_reconnecting']);
}
if ($source['name']) {
$source['name'] = wp_json_encode($source['name']);
}
update_option($this->getOptionName('source'), $source, false);
delete_option($this->getOptionName('connect-pending'));
return $source;
}
public function updateFeedDataWithDefaultTemplateParams(&$data, $templateId)
{
$params = self::$widgetParams;
$overrides = self::$widgetParamOverrides;
foreach ($params as $component => $param) {
$layoutParam = isset(self::$widgetTemplates[ $templateId ]['params'][ $component ]) ? self::$widgetTemplates[ $templateId ]['params'][ $component ] : null;
if (is_array($param) && is_array($layoutParam)) {
$params[ $component ] = array_merge($param, $layoutParam);
}
}
$params['type'] = 'custom-style';
foreach ($params as $key => $value) {
if (is_array($value)) {
$data['style'][$key] = array_merge($data['style'][$key] ?? [], $value, $overrides[$key] ?? []);
} else {
$data['style'][$key] = $overrides[$key] ?? $value;
}
}
return $data;
}
public function sanitizeJsonData($data, $decode = true)
{
if ($decode) {
$data = json_decode($data, true);
}
foreach ($data as $key => $value) {
if (is_array($value)) {
if ('list' === $key) {
$data[ $key ] = array_map(function ($item) {
$item = explode('-', $item, 2);
return implode('-', [sanitize_text_field($item[0]), $this->isUrl($item[1]) ? esc_url_raw($item[1]) : sanitize_text_field($item[1])]);
}, $value);
} else {
$data[ $key ] = $this->sanitizeJsonData(wp_unslash($value), false);
}
continue;
}
switch ($key) {
case 'profile_url':
case 'image_url':
case 'avatar_url':
case 'author_img':
case 'url':
if ($this->isUrl($value)) {
$data[ $key ] = esc_url_raw($value);
} else {
$data[ $key ] = sanitize_text_field($value);
}
break;
default:
$data[ $key ] = sanitize_text_field($value);
break;
}
}
return $data;
}
private function isUrl($url)
{
return preg_match('#^https?://#i', $url);
}


public static $widgetCategories = array (
 0 => 'slider',
 1 => 'grid',
 2 => 'list',
 3 => 'masonry',
);
public static $widgetTemplates = array (
 94 => 
 array (
 'name' => 'Grid - Card 1',
 'category' => 'grid',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'true',
 'target_col_width' => '350',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '14',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '3',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'false',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'true',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 93 => 
 array (
 'name' => 'Grid - Card 2',
 'category' => 'grid',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '1',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '0',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '2',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'false',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 96 => 
 array (
 'name' => 'Grid - Card 3',
 'category' => 'grid',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '2',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'hover-only',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '6',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '3',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'false',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'show_post_media' => 'true',
 'media_layout' => 'carousel',
 'align' => 'top',
 'ratio' => 'square',
 'click_action' => 'lightbox',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 63 => 
 array (
 'name' => 'Grid - Card 4',
 'category' => 'grid',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '2',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '6',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '3',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '4',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'grid',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 92 => 
 array (
 'name' => 'Grid - Mini',
 'category' => 'grid',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'false',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '1',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '0',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '3',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'true',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'false',
 'show_post_text' => 'false',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 64 => 
 array (
 'name' => 'Slider - Card 1',
 'category' => 'slider',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '1',
 'infinity_loop' => 'true',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'true',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'true',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 85 => 
 array (
 'name' => 'Slider - Card 2',
 'category' => 'slider',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'true',
 'target_col_width' => '200',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '2',
 'infinity_loop' => 'true',
 ),
 'custom_style' => 
 array (
 'gap' => '1',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'true',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '2',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'false',
 'show_media_icon' => 'false',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 86 => 
 array (
 'name' => 'Slider - Card 3',
 'category' => 'slider',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'true',
 'target_col_width' => '300',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '1',
 'infinity_loop' => 'true',
 ),
 'custom_style' => 
 array (
 'gap' => '15',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'true',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '75',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '10',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '2',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 87 => 
 array (
 'name' => 'Slider - Single Card 1',
 'category' => 'slider',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'false',
 'target_col_width' => '300',
 'cols_num' => '1',
 'loadmore' => 'true',
 'rows_num' => '1',
 'infinity_loop' => 'true',
 ),
 'custom_style' => 
 array (
 'gap' => '5',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'true',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '75',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '3',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'false',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 88 => 
 array (
 'name' => 'Slider - Single Card 4',
 'category' => 'slider',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'false',
 'target_col_width' => '300',
 'cols_num' => '1',
 'loadmore' => 'true',
 'rows_num' => '1',
 'infinity_loop' => 'true',
 ),
 'custom_style' => 
 array (
 'gap' => '5',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'hover-only',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '75',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '4',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'true',
 'interval' => '5',
 ),
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'false',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'left',
 'click_action' => 'redirect',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'false',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'false',
 'type' => 'scroller',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => false,
 ),
 65 => 
 array (
 'name' => 'List - Card 1',
 'category' => 'list',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'list',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '50',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'true',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 89 => 
 array (
 'name' => 'List - Card 3',
 'category' => 'list',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'list',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '50',
 'header-muted-color' => '#555555',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '8',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 91 => 
 array (
 'name' => 'List - Card 4',
 'category' => 'list',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'list',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '50',
 'header-muted-color' => '#555555',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '8',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '4',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'false',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 90 => 
 array (
 'name' => 'List Horizontal - Card 3',
 'category' => 'list',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'list',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '50',
 'header-muted-color' => '#555555',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '8',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#385898',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'grid',
 'align' => 'left',
 'click_action' => 'lightbox',
 'ratio' => 'square',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 66 => 
 array (
 'name' => 'Masonry - Card 1',
 'category' => 'masonry',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'masonry',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '14',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '8',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '3',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'original',
 'click_action' => 'lightbox',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'true',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 83 => 
 array (
 'name' => 'Masonry - Card 2',
 'category' => 'masonry',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'masonry',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '1',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '0',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '2',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'false',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'original',
 'click_action' => 'lightbox',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 82 => 
 array (
 'name' => 'Masonry - Card 3',
 'category' => 'masonry',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'masonry',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '14',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '8',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_text' => 'true',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'original',
 'click_action' => 'lightbox',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '2',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'true',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
 84 => 
 array (
 'name' => 'Masonry - Mini',
 'category' => 'masonry',
 'params' => 
 array (
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'layout' => 
 array (
 'type' => 'masonry',
 'cols_num_auto' => 'false',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'infinity_loop' => 'false',
 ),
 'custom_style' => 
 array (
 'gap' => '1',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'carousel_album_arrow_show' => 'false',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-body-height' => '',
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#3578e5',
 'header-btn-border-radius' => '4',
 'header-instagram-avatar-border' => 'true',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '0',
 'card-border-color' => '#dedede',
 'card-border-radius' => '0',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#000000',
 'card-media-border-radius' => '0',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 ),
 'arrow' => 
 array (
 'type' => '3',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'true',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'false',
 'show_post_text' => 'false',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'original',
 'click_action' => 'lightbox',
 ),
 'post_overflow' => 
 array (
 'open' => 'Read more',
 'close' => 'Hide',
 ),
 'actions' => 
 array (
 'view' => 'View',
 'share' => 'Share',
 'follow' => 'Follow',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_profile_picture' => 'true',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 ),
 ),
 'is-active' => true,
 ),
);
public static $widgetParams = array (
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'true',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => 3,
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
 ),
 'header' => 
 array (
 'enabled' => 'true',
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'false',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 'switch' => 'false',
 ),
 'arrow' => 
 array (
 'type' => '2',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'true',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_title' => 'false',
 'show_post_text' => 'true',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'square',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'carousel',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_profile_picture' => 'true',
 ),
 'autoplay_widget' => 
 array (
 'enabled' => 'false',
 'interval' => '10',
 ),
 'autoplay_widget_card' => 
 array (
 'enabled' => 'false',
 'interval' => '4',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'summary' => 
 array (
 'author_name' => '',
 'author_bio' => '',
 'avatar_url' => NULL,
 ),
 'settings' => 
 array (
 'media_only' => 'true',
 'selected_source_id' => '',
 'platform_style' => 'custom',
 'hide_copyright' => 'false',
 'post_filter_hide' => 'false',
 'post_ids' => 
 array (
 ),
 'included_tags' => 
 array (
 ),
 'excluded_tags' => 
 array (
 ),
 ),
);
public static $widgetParamOverrides = array (
 'card' => 
 array (
 'ratio' => 'portrait',
 ),
 'custom_style' => 
 array (
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
 ),
);
public static $widgetHalfWidthLayouts = array (
 0 => 84,
 1 => 87,
 2 => 88,
 3 => 92,
);
public function getWidget($templateId = null)
{
$isPreview = true;
if (!$templateId) {
$templateId = (int)get_option($this->getOptionName('template'));
$isPreview = false;
}
$id = uniqid($templateId);
$feedData = $this->getFeedData();
if (!$feedData || !$templateId) {
return;
}
if ($isPreview) {
$this->updateFeedDataWithDefaultTemplateParams($feedData, $templateId);
$feedData['widget-key'] = $templateId;
wp_enqueue_style($this->getCssKey($id), 'https://cdn.trustindex.io/assets/widget-presetted-css/'. $templateId .'-'. ucfirst($this->platformName) .'.css', [], $this->getVersion());
}
else {
$cssCdnVersion = $this->getCdnVersion('feed-css');
if ($cssCdnVersion && version_compare($cssCdnVersion, $this->getVersion('feed-css'))) {
$response = wp_remote_post('https://admin.trustindex.io/api/getFeedCss', [
'headers' => ['Content-Type' => 'application/json'],
'body' => wp_json_encode([
'data' => $feedData,
'template-id' => $templateId,
'public-id' => get_option($this->getOptionName('public-id'))
]),
'timeout' => '60'
]);
if (!is_wp_error($response)) {
$json = json_decode($response['body'], true);
if (isset($json['content'])) {
update_option($this->getOptionName('css-content'), $json['content'], false);
$this->handleCssFile();
}
}
$this->updateVersion('feed-css', $cssCdnVersion);
}
$feedData['widget-key'] = 'feed-'. $this->getShortName();
$cssKey = $this->getCssKey();
if (!wp_style_is($cssKey, 'registered') || get_option($this->getOptionName('load-css-inline'), 0)) {
$cssContent = get_option($this->getOptionName('css-content'));
if (!get_option($this->getOptionName('load-css-inline'), 0) || !$cssContent) {
if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
}
else {
return $this->errorBoxForAdmins(__('CSS file could not saved.', 'social-photo-feed-widget'));
}
}
wp_register_style($cssKey, false, [], true);
wp_enqueue_style($cssKey);
/*
This function ensures that CSS data is properly escaped. Since there is no native WordPress CSS escape function, we developed our own based on WordPress's built-in functions.
*/
wp_add_inline_style($cssKey, trustindex_esc_css($cssContent));
}
else {
wp_enqueue_style($cssKey);
}
}
return $this->registerWidget($id, $feedData, $isPreview);
}
private function registerWidget($id, $feedData = null, $isPreview = true)
{
$dataId = $this->getWidgetDataKey($id);
$isWpWidget = isset($feedData);
$enqueueData = function () use ($id, $feedData, $dataId, $isWpWidget) {
$data = [
'container' => esc_attr($this->getContainerKey($id)),
];
if ($isWpWidget) {
$data['data'] = $feedData;
if (!get_option($this->getOptionName('load-css-inline'), 0)) {
$data['cssUrl'] = $this->getCssUrl().(is_file($this->getCssFile()) ? '?'.filemtime($this->getCssFile()) : '');
}
$data['pluginVersion'] = $this->getVersion();
}
$data = 'script_content_start'.base64_encode(wp_json_encode($data, JSON_UNESCAPED_SLASHES)).'script_content_end';
wp_enqueue_script($dataId, 'https://cdn.trustindex.io/loader-feed.js', [], $id.($isWpWidget ? '|wordpress' : '').$data, ['in_footer' => false]);
};
if (!$isPreview || !$isWpWidget) {
$this->registerLoaderScript([$dataId]);
}
add_action('wp_footer', $enqueueData);
add_action('admin_footer', $enqueueData);
return $id;
}
public function registerLoaderScript($deps = []) {
$enqueueLoader = function () use ($deps) {
wp_enqueue_script($this->getLoaderScriptKey(), 'https://cdn.trustindex.io/loader-feed.js', $deps, $this->getVersion(), ['in_footer' => true]);
};
add_action('wp_footer', $enqueueLoader);
add_action('admin_footer', $enqueueLoader);
}
public function getContainerKey($widgetId) {
return 'trustindex-feed-container-'.$this->getShortName().'-'.$widgetId;
}
public function getCssKey($widgetId = null) {
return 'trustindex-feed-widget-css-'.$this->getShortName().($widgetId ? '-'.$widgetId : '');
}
public function getWidgetDataKey($widgetId) {
return 'trustindex-feed-data-'.$this->getShortName().'-'.$widgetId;
}
public function getLoaderScriptKey() {
return 'trustindex-feed-loader-js';
}
public function getAdminWidget($id)
{
return '<div id="'.esc_attr($this->getContainerKey($this->registerWidget($id))).'"></div>';
}


public function getCssFile($returnOnlyFile = false)
{
$file = 'trustindex-feed-'. $this->getShortName() .'-widget.css';
if ($returnOnlyFile) {
return $file;
}
$uploadDir = wp_upload_dir();
return trailingslashit($uploadDir['basedir']) . $file;
}
private function getCssUrl()
{
$path = wp_upload_dir()['baseurl'] .'/'. $this->getCssFile(true);
if (is_ssl()) {
$path = str_replace('http://', 'https://', $path);
}
return $path;
}
private function getFilesystemApi($url)
{
if (!function_exists('request_filesystem_credentials') || !function_exists('WP_Filesystem')) {
return null;
}
$creds = request_filesystem_credentials($url, '', false, false, null);
if (false === $creds) {
return null;
}
if (!WP_Filesystem($creds)) {
request_filesystem_credentials($url, '', true, false, null);
return null;
}
global $wp_filesystem;
return $wp_filesystem;
}
public function getCssFileContent()
{
$wp_filesystem = $this->getFilesystemApi($this->getCssFile());
if (!$wp_filesystem) {
return null;
}
return $wp_filesystem->get_contents($this->getCssFile());
}
public function isCssWriteable()
{
$wp_filesystem = $this->getFilesystemApi($this->getCssFile());
if (!$wp_filesystem) {
return null;
}
return $wp_filesystem->is_writable(dirname($this->getCssFile()));
}
public function handleCssFile()
{
$css = get_option($this->getOptionName('css-content'));
if (!$css) {
return;
}
if (get_option($this->getOptionName('load-css-inline'), 0)) {
return;
}
$fileExists = is_file($this->getCssFile());
$success = false;
$errorType = null;
$errorMessage = "";
if ($fileExists && !is_readable($this->getCssFile())) {
$errorType = 'permission';
}
else {
add_filter('filesystem_method', array($this, 'filterFilesystemMethod'));
if ($fileExists && $css === $this->getCssFileContent()) {
return;
}
set_error_handler(function ($errSeverity, $errMsg, $errFile, $errLine, $errContext = []) {
throw new ErrorException(wp_kses_post($errMsg), 0, esc_html($errSeverity), esc_html($errFile), esc_html($errLine));
}, E_WARNING);
try {
$wp_filesystem = $this->getFilesystemApi($this->getCssFile());
$success = $wp_filesystem && $wp_filesystem->put_contents($this->getCssFile(), $css, 0777);
}
catch (Exception $e) {
if (strpos($e->getMessage(), 'Permission denied') !== FALSE) {
$errorType = 'permission';
}
else {
$errorType = 'filesystem';
$errorMessage = $e->__toString();
}
}
restore_error_handler();
remove_filter('filesystem_method', array($this, 'filterFilesystemMethod'));
}
if (!$success) {
add_action('admin_notices', function() use ($fileExists, $errorType, $errorMessage) {
$html = '
<div class="notice notice-error" style="margin: 5px 0 15px">
<p>' .
'<strong>'. __('ERROR with the following plugin:', 'social-photo-feed-widget') .'</strong> '. $this->pluginName .'<br /><br />' .
__('CSS file could not saved.', 'social-photo-feed-widget') .' <strong>('. $this->getCssFile() .')</strong> '. __('Your widgets do not display properly!', 'social-photo-feed-widget') . '<br />';
if ($errorType === 'filesystem') {
$html .= '<br />
<strong>There is an error with your filesystem. We got the following error message:</strong>
<pre style="display: block; margin: 10px 0; padding: 20px; background: #eee">'. $errorMessage .'</pre>
<strong>Maybe you configured your filesystem incorrectly.<br />
<a href="https://wordpress.org/support/article/editing-wp-config-php/#wordpress-upgrade-constants" target="_blank">Here you can read about how to configure filesystem in your WordPress.</a></strong>';
}
else {
if ($fileExists) {
$html .= __('CSS file exists and it is not writeable. Delete the file', 'social-photo-feed-widget');
}
else {
$html .= __('Grant write permissions to upload folder', 'social-photo-feed-widget');
}
$html .= '<br />' .
__('or', 'social-photo-feed-widget') . '<br />' .
/* translators: %s: URL of Advanced page */
sprintf(__("enable 'CSS internal loading' in the %s page!", 'social-photo-feed-widget'), '<a href="'. admin_url('admin.php?page=' . $this->getPluginSlug() . '/admin.php&tab=advanced') .'>'. __('Advanced', 'social-photo-feed-widget') .'</a>');
}
echo wp_kses_post($html) . '</p></div>';
});
}
return $success;
}
public function filterFilesystemMethod($method)
{
if ($method !== 'direct' && !defined('FS_METHOD')) {
return 'direct';
}
return $method;
}


public function getTableName($name = "")
{
global $wpdb;
return $wpdb->prefix .'trustindex_feed_' . $name;
}
public function isTableExists($name = "")
{

return false;
}


public function addSettingMenu()
{
global $menu, $submenu;
$permission = 'edit_pages';
$adminPageUrl = $this->getPluginSlug() . "/admin.php";
$adminPageTitle = $this->platformName . ' Feed';
$menuBadge = '';
if ($this->getNotificationParam('token-expired', 'active', false)) {
$menuBadge = '<span class="update-plugins count-1" style="position:absolute;"><span class="plugin-count">1</span></span>';
}
add_menu_page(
$adminPageTitle,
$adminPageTitle.$menuBadge,
$permission,
$adminPageUrl,
'',
$this->getPluginFileUrl('assets/img/trustindex-sign-logo.png')
);
}
public function addPluginActionLinks($links, $file)
{
if (basename($file) === $this->getPluginSlug() . '.php') {
$platformLink = '<a style="background-color: #1a976a; color: white; font-weight: bold; padding: 1px 8px; border-radius: 4px; position: relative" href="'.admin_url('admin.php?page='.$this->getPluginSlug().'/admin.php').'">';
if (!get_option($this->getOptionName('source'), 0)) {
/* translators: %s: Platform name */
$platformLink .= sprintf(__('Connect %s', 'social-photo-feed-widget'), $this->platformName);
} elseif (!get_option($this->getOptionName('css-content'), 0)) {
 $platformLink .= __('Create Widget', 'social-photo-feed-widget');
} else {
$platformLink .= __('Widget Settings', 'social-photo-feed-widget');
}
$platformLink .= '</a>';
array_unshift($links, $platformLink);
}
return $links;
}
public function addPluginMetaLinks($meta, $file)
{
if (basename($file) === $this->getPluginSlug() . '.php') {
$meta[] = '<a href="'. admin_url('admin.php?page=' . $this->getPluginSlug() . '/admin.php&tab=get-more-features') .'">'.__('Get more Features', 'social-photo-feed-widget').' →</a>';
$meta[] = '<a href="http://wordpress.org/support/view/plugin-reviews/'. $this->getPluginSlug() .'" target="_blank" rel="noopener noreferrer">'.__('Rate our plugin', 'social-photo-feed-widget').' <span style="color: #F6BB07; font-size: 1.2em; line-height: 1; position: relative; top: 0.05em;">★★★★★</span></a>';
}
return $meta;
}
public function addScripts($hook)
{
$tmp = explode('/', $hook);
$currentSlug = array_shift($tmp);
if ($this->getPluginSlug() === $currentSlug) {
if (file_exists($this->getPluginDir() . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'admin.css')) {
wp_enqueue_style('trustindex-feed-admin-'. $this->getShortName(), $this->getPluginFileUrl('assets/css/admin.css'), [], $this->getVersion());
}
if (file_exists($this->getPluginDir() . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'admin.js')) {
wp_enqueue_script('trustindex-feed-admin-'. $this->getShortName(), $this->getPluginFileUrl('assets/js/admin.js'), [], $this->getVersion(), [ 'in_footer' => false ]);
}
}
wp_register_script('trustindex_admin_notification', $this->getPluginFileUrl('assets/js/admin-notification.js'), [], $this->getVersion(), [ 'in_footer' => false ]);
wp_enqueue_script('trustindex_admin_notification');
wp_enqueue_style('trustindex_admin_notification', $this->getPluginFileUrl('assets/css/admin-notification.css'), [], $this->getVersion());
}


public static function getAlertBox($type, $content, $newline_content = true)
{
$types = [
'warning' => [
'css' => 'color: #856404; background-color: #fff3cd; border-color: #ffeeba;',
'icon' => 'dashicons-warning'
],
'info' => [
'css' => 'color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb;',
'icon' => 'dashicons-info'
],
'error' => [
'css' => 'color: #721c24; background-color: #f8d7da; border-color: #f5c6cb;',
'icon' => 'dashicons-info'
]
];
return '<div style="margin:20px 0px; padding:10px; '. $types[ $type ]['css'] .' border-radius: 5px">'
. '<span class="dashicons '. $types[ $type ]['icon'] .'"></span> <strong>'. strtoupper($type) .'</strong>'
. ($newline_content ? '<br />' : "")
. $content
. '</div>';
}
public function errorBoxForAdmins($text)
{
if (!current_user_can(self::$permissionNeeded)) {
return "";
}
return self::getAlertBox('error', ' @ <strong>Trustindex plugin</strong> <i style="opacity: 0.65">('. __('This message is not be visible to visitors in public mode.', 'social-photo-feed-widget') .')</i><br /><br />'. $text, false);
}


public function getNotificationOptions($type = "")
{
$tokenExpireDate = gmdate('Y-m-d H:i', (int)get_option($this->getOptionName('token-expires')));
$list = [
'rate-us' => [
'type' => 'warning',
'extra-class' => 'trustindex-popup',
'button-text' => "",
'is-closeable' => true,
'hide-on-close' => false,
'hide-on-open' => true,
'remind-later-button' => false,
'redirect' => 'https://wordpress.org/support/plugin/'. $this->getPluginSlug() .'/reviews/?rate=5#new-post',
'text' =>
/* translators: %s: Name of the plugin */
sprintf(__('We have worked a lot on the free "%s" plugin.', 'social-photo-feed-widget'), $this->pluginName) . '<br />' .
__('If you love our features, please write a review to help us make the plugin even better.', 'social-photo-feed-widget') . '<br />' .
/* translators: %s: Trustindex CEO */
sprintf(__('Thank you. Gabor, %s', 'social-photo-feed-widget'), 'Trustindex CEO'),
],
'token-renew' => [
'type' => 'warning',
'extra-class' => "",
'button-text' => __('Go to Connect Page', 'social-photo-feed-widget'),
'is-closeable' => false,
'hide-on-close' => false,
'hide-on-open' => false,
'remind-later-button' => false,
'redirect' => '?page='. $this->getPluginSlug() .'/admin.php&tab=feed-configurator&step=1&reconnect-source',
'text' =>
'<strong>'.
__('Important: ', 'social-photo-feed-widget').
/* translators: 1: Platform name, 2: Date string */
sprintf(__('Your %1$s Access Token expires on %2$s.', 'social-photo-feed-widget'), ucfirst($this->getShortName()), $tokenExpireDate).
'</strong><br />'.
__('Please renew your token by clicking the "Reconnect" button on the Connect Page.', 'social-photo-feed-widget').'<br />'.
/* translators: %s: Platform name */
sprintf(__('This will ensure that your %s Feed Widget continues to update automatically.', 'social-photo-feed-widget'), ucfirst($this->getShortName())),
],
'token-expired' => [
'type' => 'error',
'extra-class' => "",
'button-text' => __('Go to Connect Page', 'social-photo-feed-widget'),
'is-closeable' => false,
'hide-on-close' => false,
'hide-on-open' => false,
'remind-later-button' => false,
'redirect' => '?page='. $this->getPluginSlug() .'/admin.php&tab=feed-configurator&step=1&reconnect-source',
'text' =>
'<strong>'.
__('Important: ', 'social-photo-feed-widget').
/* translators: 1: Platform name, 2: Date string */
sprintf(__('Your %1$s Access Token expired on %2$s.', 'social-photo-feed-widget'), ucfirst($this->getShortName()), $tokenExpireDate).
'</strong><br />'.
__('Please renew your token by clicking the "Reconnect" button on the Connect Page.', 'social-photo-feed-widget').'<br />'.
/* translators: %s: Platform name */
sprintf(__('This will ensure that your %s Feed Widget continues to update automatically.', 'social-photo-feed-widget'), ucfirst($this->getShortName())),
 'short-message' =>
$this->displayImg(str_replace('%platform%', ucfirst($this->getShortName()), 'https://cdn.trustindex.io/assets/platform/%platform%/icon-feed.svg'), array('alt' => ucfirst($this->getShortName()))).
 '<p>'.
 '<strong>' . __('Important: ', 'social-photo-feed-widget') . '</strong>'.
 /* translators: %s: Platform name */
 sprintf(__('We can no longer update the posts in your %s feed widget.', 'social-photo-feed-widget'), ucfirst($this->getShortName())).
 '<br/><a href="#">'. __('Click here to reconnect', 'social-photo-feed-widget') .'</a>'.
 '</p>',
],
'posts-download-finished' => [
'type' => 'info',
'extra-class' => "",
/* translators: %s: Platform name */
'button-text' => sprintf(__('Create %s Feed Widget', 'social-photo-feed-widget'), ucfirst($this->getShortName())),
'is-closeable' => true,
'hide-on-close' => true,
'hide-on-open' => true,
'remind-later-button' => false,
'redirect' => '?page='.$this->getPluginSlug().'/admin.php&tab=feed-configurator&step=2',
'text' =>
'<strong>'.
/* translators: %s: Platform name */
sprintf(__('%s posts ready', 'social-photo-feed-widget'), ucfirst($this->getShortName())).
'</strong><br />'.
/* translators: %s: Platform name */
sprintf(__('Your %s posts are imported and ready.', 'social-photo-feed-widget'), ucfirst($this->getShortName())).'<br />'.
__('Create and embed your feed widget', 'social-photo-feed-widget'),
'short-message' =>
$this->displayImg(str_replace('%platform%', ucfirst($this->getShortName()), 'https://cdn.trustindex.io/assets/platform/%platform%/icon-feed.svg'), array('alt' => ucfirst($this->getShortName()))).
'<p>'.
'<strong>'.
/* translators: %s: Platform name */
sprintf(__('%s posts ready', 'social-photo-feed-widget'), ucfirst($this->getShortName())).
'</strong><br />'.
/* translators: %s: Platform name */
sprintf(__('Your %s posts are imported and ready.', 'social-photo-feed-widget'), ucfirst($this->getShortName())).'<br />'.
__('Create and embed your feed widget', 'social-photo-feed-widget').'<br />'.
/* translators: %s: Platform name */
'<a href="#">'.sprintf(__('Create %s Feed Widget', 'social-photo-feed-widget'), ucfirst($this->getShortName())).'</a>'.
'</p>',
],
];
return $type ? $list[$type] : $list;
}
public function getNotificationActionUrl($type, $action, $remindDays = null)
{
return wp_nonce_url(
add_query_arg(
array_filter(array(
'page' => $this->getPluginSlug() . '/admin.php',
'notification' => $type,
'action' => $action,
'remind-days' => $remindDays,
)),
admin_url('admin.php')
),
'ti-' . $type . '_' . $action,
'notification_action_nonce'
);
}
public function setNotificationParam($type, $param, $value)
{
$notifications = get_option($this->getOptionName('notifications'), []);
if (!isset($notifications[ $type ])) {
$notifications[ $type ] = [];
}
$notifications[ $type ][ $param ] = $value;
update_option($this->getOptionName('notifications'), $notifications, false);
}
public function getNotificationParam($type, $param, $default = null)
{
$notifications = get_option($this->getOptionName('notifications'), []);
if (!isset($notifications[ $type ]) || !isset($notifications[ $type ][ $param ])) {
return $default;
}
return $notifications[ $type ][ $param ];
}
public function isNotificationActive($type)
{
$notifications = get_option($this->getOptionName('notifications'), []);
if (
!isset($notifications[ $type ]) ||
!isset($notifications[ $type ]['active']) || !$notifications[ $type ]['active'] ||
(isset($notifications[ $type ]['hidden']) && $notifications[ $type ]['hidden']) ||
(isset($notifications[ $type ]['timestamp']) && $notifications[ $type ]['timestamp'] > time())
) {
return false;
}
return true;
}
public function isNotificationEnabled($type)
{
$notifications = get_option($this->getOptionName('notifications'), []);
return isset($notifications[$type]);
}
public function getNotificationEmailContent($type)
{
$subject = '';
$message = '';
switch ($type) {
case 'posts-download-finished':
$username = '';
$source = get_option($this->getOptionName('connect-pending'), []);
if (isset($source['username'])) {
$username = $source['username'];
} elseif ($source = $this->getConnectedSource()) {
$username = $source['name'];
}
$link = admin_url('admin.php?page='.$this->getPluginSlug().'/admin.php&tab=feed-configurator&step=2');
$subject = 'Create your Instagram feed widget';
$message = strtr(
'<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Create your Instagram feed widget</title>
<meta name="viewport" content="width=device-width" />
 <style type="text/css">
@media only screen and (max-width: 550px), screen and (max-device-width: 550px) {
body[yahoo] .buttonwrapper { background-color: transparent !important; }
body[yahoo] .button { padding: 0 !important; }
body[yahoo] .button a { background-color: #69b899; padding: 15px 25px !important; }
}
@media only screen and (min-device-width: 601px) {
.content { width: 600px !important; }
.col387 { width: 387px !important; }
}
</style>
</head>
<body bgcolor="#f9f9f9" style="margin: 0; padding: 10px; background-color: #f9f9f9;" yahoo="fix">
<!--[if (gte mso 9)|(IE)]>
<table width="600" align="center" cellpadding="0" cellspacing="0" border="0">
 <tr>
<td>
<![endif]-->
<table align="center" border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse; width: 100%; max-width: 600px; background-color: white; border: 1px solid #ccc;" class="content">
<tr>
<td style="padding: 15px 10px;"> </td>
</tr>
<tr>
<td bgcolor="#ffffff" style="padding: 0 20px 20px 20px; color: #222222; font-family: Arial, sans-serif; font-size: 15px; line-height: 24px;">
Your Instagram account (@%username%) has been successfully connected and the posts have been downloaded – you can now create your Instagram feed widget.<p style="text-align: center; padding: 30px;"><a href="%link%" style="background-color: #2AA8D7; margin: 10px; padding: 20px; border-radius: 4px; color: #ffffff; text-decoration: none; font-family: Arial, sans-serif; font-size: 16px; font-weight: bold; display: block;">Create Instagram Feed Widget</a></p></td>
</tr>
<tr>
<td align="center" bgcolor="#242F62" style="padding: 15px 10px 15px 10px; color: #ffffff; font-family: Arial, sans-serif; font-size: 12px; line-height: 18px;">
2018-2025 &copy; <b>Trustindex.io</b><br/>
<a target="_blank" href="https://www.trustindex.io" style="color:#ffffff;">https://www.trustindex.io</a>
</td>
</tr>
</table>
<!--[if (gte mso 9)|(IE)]>
</td>
</tr>
</table>
<![endif]-->
</body>
</html>
',
[
'%username%' => $username,
'%link%' => $link,
]
);
break;
}
return [
'subject' => $subject,
'message' => $message,
];
}
public function sendNotificationEmail($type)
{
if ($email = $this->getNotificationParam($type, 'email', get_option('admin_email'))) {
if (!$this->getNotificationParam($type, 'hidden')) {
$this->setNotificationParam($type, 'do-check', true);
$this->setNotificationParam($type, 'active', false);
}
$msg = $this->getNotificationEmailContent($type);
if ($msg['subject'] && $msg['message']) {
try {
return wp_mail($email, $msg['subject'], $msg['message'], ['Content-Type: text/html; charset=UTF-8'], ['']);
} catch (Exception $e) {
return false;
}
}
}
}


public function getOptionName($optName)
{
if (!in_array($optName, $this->getOptionNames()) && !in_array($optName, $this->getNotUsedOptionNames())) {
echo "Option not registered in plugin (TrustindexFeed class)";
}
if (in_array($optName, [ 'proxy-check', 'cdn-version-control' ])) {
return 'trustindex-'. $optName;
}
else {
return 'trustindex-feed-'. $this->getShortName() .'-'. $optName;
}
}
public function getOptionNames()
{
return [
'active',
'activation-redirect',
'proxy-check',
'notifications',
'rate-us-feedback',
'cdn-version-control',
'version-control',
'preview',
'source',
'connect-pending',
'feed-data',
'feed-data-saved',
'public-id',
'token-expires',
'css-content',
'load-css-inline',
'layout',
'template',
];
}
public function getNotUsedOptionNames()
{
return [
'version',
'update-version-check',
];
}


public function getCdnVersionControl()
{
$data = get_option($this->getOptionName('cdn-version-control'), []);
if (!$data || $data['last-saved-at'] < time() + 60) {
$response = wp_remote_get('https://cdn.trustindex.io/version-control.json', [
'timeout' => 60,
'sslverify' => false,
]);
if (!is_wp_error($response)) {
$data = array_merge($data, json_decode($response['body'], true));
}
$data['last-saved-at'] = time();
update_option($this->getOptionName('cdn-version-control'), $data, false);
}
return $data;
}
public function getCdnVersion($name = "")
{
$data = $this->getCdnVersionControl();
return isset($data[ $name ]) ? $data[ $name ] : "";
}
public function getVersion($name = "")
{
if (!$name) {
return $this->version;
}
$data = get_option($this->getOptionName('version-control'), []);
return isset($data[ $name ]) ? $data[ $name ] : "1.0";
}
public function updateVersion($name, $value)
{
$data = get_option($this->getOptionName('version-control'), []);
$data[ $name ] = $value;
return update_option($this->getOptionName('version-control'), $data, false);
}
}
?>
