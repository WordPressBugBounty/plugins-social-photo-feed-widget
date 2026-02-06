<?php
class TRUSTINDEX_Feed_Instagram
{
private $pluginFilePath;
private $pluginName;
private $platformName;
private $version;
private $shortname;
public static $permissionNeeded = 'edit_pages';
public static $downloadCheckSeconds = 5;
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
$json = json_decode(wp_remote_retrieve_body($response), true);
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
wp_safe_redirect(admin_url('admin.php?page=' . $this->getPluginSlug() . '/admin.php'));
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
$isNotificationEnabled = $this->isNotificationEnabled('post-download-available');
if ($isNotificationEnabled &&
$this->getConnectedSource() &&
!$this->isDownloadInProgress() &&
$this->getDownloadAvailableTimestamp() < time() &&
!$this->getNotificationParam('post-download-available', 'hidden') &&
$this->getNotificationParam('post-download-available', 'do-check', true)
) {
$this->setNotificationParam('post-download-available', 'active', true);
$this->setNotificationParam('post-download-available', 'do-check', false);

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
load_textdomain(
$this->getPluginSlug(),
$this->getPluginDir().'/languages/'.$this->getPluginSlug().'-'.get_locale().'.mo'
);
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
public function isDownloadInProgress()
{
return [] !== get_option($this->getOptionName('connect-pending'), []);
}
public function isDownloadManual()
{
return str_contains($this->getConnectedSource()['subtype'] ?? '', 'username');
}
public function getDownloadAvailableTimestamp()
{
return 86400 * 10 + get_option($this->getOptionName('feed-data-downloaded'), 0);
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
public function getFeedData(bool $forceRefresh = false)
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
if (!$data || $dataSaved > (12 * 3600) || !$allImageReplaced || $forceRefresh) {
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
try {
$newData = json_decode(wp_remote_retrieve_body($response), true, 512, JSON_THROW_ON_ERROR);
} catch (Exception $e) {
echo wp_kses_post($this->errorBoxForAdmins(__('Could not download the posts for the widget.<br />Please reload the page.<br />If the problem persists, please write an email to support@trustindex.io.', 'social-photo-feed-widget') .'<br /><br />'. wp_json_encode($e->getMessage())));;
die;
}
$data = $this->updateFeedData($newData, $data);
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

public function checkFeedDownload()
{
if ($this->isDownloadInProgress()) {
return;
}
$source = $this->getConnectedSource();
$params = [
'is_feed_update' => true,
'type' => 'Instagram',
'subtypes' => $source['subtype'],
'username' => $source['name'],
'website' => get_option('siteurl'),
];
if ($webhook = $this->getWebhookUrl()) {
$params['webhook'] = $webhook;
}
$response = wp_remote_get('https://admin.trustindex.io/source/check_feed_download_status', [
'body' => $params,
'timeout' => '30',
'sslverify' => false,
]);
if (is_wp_error($response)) {
return;
}
$data = json_decode($response['body'], true);
if ($data['downloaded']) {
update_option($this->getOptionName('feed-data-downloaded'), time(), false);
$this->setNotificationParam('post-download-available', 'do-check', true);
$this->setNotificationParam('post-download-available', 'active', false);
return;
}
update_option(
$this->getOptionName('connect-pending'),
['username' => $source['name'], 'subtypes' => $source['subtype']],
false
);
}
public function saveFeedData($arr = [], $saveTime = true)
{
$updated = update_option($this->getOptionName('feed-data'), wp_json_encode($arr), false);
if ($updated && $saveTime) {
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
update_option($this->getOptionName('feed-data-downloaded'), time(), false);
$this->setNotificationParam('post-download-available', 'do-check', true);
$this->setNotificationParam('post-download-available', 'active', false);
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
if ('masonry' === $params['layout']['type'] && isset($overrides['card']['ratio'])) {
unset($overrides['card']['ratio']);
}
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
 'name' => 'Grid I.',
 'category' => 'grid',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '1',
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
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_full_name' => 'true',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_title' => 'true',
 'show_post_text' => 'true',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '20',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '12',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'true',
 'target_col_width' => '280',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
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
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 93 => 
 array (
 'name' => 'Grid II.',
 'category' => 'grid',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '3',
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
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'false',
 'show_full_name' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'false',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '20',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
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
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'true',
 'target_col_width' => '350',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
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
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 96 => 
 array (
 'name' => 'Grid III.',
 'category' => 'grid',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '1',
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
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_full_name' => 'true',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_title' => 'false',
 'show_post_text' => 'true',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'rgba(255, 255, 255, 0)',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '20',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '12',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '12',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'true',
 'target_col_width' => '280',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '2',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
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
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 63 => 
 array (
 'name' => 'Grid IV.',
 'category' => 'grid',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '2',
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
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'false',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'false',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '5',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'rgba(255, 255, 255, 0)',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#000000',
 'card-border-radius' => '0',
 'card-background-color' => '#f2f2f2',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#6c6c6c',
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'false',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'true',
 'target_col_width' => '220',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '2',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_profile_picture' => 'true',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 92 => 
 array (
 'name' => 'Grid V.',
 'category' => 'grid',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '1',
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
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'false',
 'show_full_name' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'false',
 'show_comment_num' => 'false',
 'show_repost_num' => 'false',
 'show_date' => 'false',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
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
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'false',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'header' => 
 array (
 'enabled' => 'false',
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'true',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'true',
 'target_col_width' => '350',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'media',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_profile_picture' => 'true',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 134 => 
 array (
 'name' => 'Grid VI.',
 'category' => 'grid',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '1',
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
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'false',
 'show_full_name' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'false',
 'show_comment_num' => 'false',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => '#000000',
 'widget-padding-top' => '20',
 'widget-padding-bottom' => '25',
 'widget-padding-left' => '25',
 'widget-padding-right' => '25',
 'widget-border-weight' => '0',
 'widget-border-color' => 'rgba(255, 255, 255, 0)',
 'widget-border-radius' => '20',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#ffffff',
 'header-padding-top' => '0',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#c9c9c9',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#2a2a2a',
 'header-btn-border-radius' => '8',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '12',
 'card-background-color' => '#2a2a2a',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#f3f3f3',
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '12',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#ffffff',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'true',
 'target_col_width' => '270',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '2',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
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
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 135 => 
 array (
 'name' => 'Grid VII.',
 'category' => 'grid',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '3',
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
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_like_num' => 'false',
 'show_comment_num' => 'false',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '1',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '75',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
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
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'false',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_profile_picture' => 'true',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 136 => 
 array (
 'name' => 'Grid VIII.',
 'category' => 'grid',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '3',
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
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_like_num' => 'false',
 'show_comment_num' => 'false',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'square',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '5',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '75',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
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
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'header' => 
 array (
 'enabled' => 'false',
 'type' => '2',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'true',
 'show_follow_button' => 'true',
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'grid',
 'cols_num_auto' => 'false',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '4',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_profile_picture' => 'true',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 64 => 
 array (
 'name' => 'Slider I.',
 'category' => 'slider',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '1',
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
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_full_name' => 'true',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_title' => 'true',
 'show_post_text' => 'true',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'true',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '20',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '12',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'true',
 'target_col_width' => '280',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '1',
 'width' => 'lg',
 'infinity_loop' => 'true',
 'lazy_load' => 'true',
 'delay_load' => 'false',
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
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 85 => 
 array (
 'name' => 'Slider II.',
 'category' => 'slider',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '3',
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
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'false',
 'show_full_name' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'false',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'true',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '20',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
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
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'true',
 'target_col_width' => '350',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '1',
 'width' => 'lg',
 'infinity_loop' => 'true',
 'lazy_load' => 'true',
 'delay_load' => 'false',
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
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 86 => 
 array (
 'name' => 'Slider III.',
 'category' => 'slider',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '1',
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
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_full_name' => 'true',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_title' => 'false',
 'show_post_text' => 'true',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'true',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'rgba(255, 255, 255, 0)',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '20',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '12',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '12',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'true',
 'target_col_width' => '280',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '1',
 'width' => 'lg',
 'infinity_loop' => 'true',
 'lazy_load' => 'true',
 'delay_load' => 'false',
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
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 87 => 
 array (
 'name' => 'Slider IV.',
 'category' => 'slider',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '2',
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
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'false',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'false',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '5',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'true',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'rgba(255, 255, 255, 0)',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#000000',
 'card-border-radius' => '0',
 'card-background-color' => '#f2f2f2',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#6c6c6c',
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'false',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'true',
 'target_col_width' => '220',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '2',
 'width' => 'lg',
 'infinity_loop' => 'true',
 'lazy_load' => 'true',
 'delay_load' => 'false',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_profile_picture' => 'true',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 88 => 
 array (
 'name' => 'Slider V.',
 'category' => 'slider',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '1',
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
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'false',
 'show_full_name' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'false',
 'show_comment_num' => 'false',
 'show_repost_num' => 'false',
 'show_date' => 'false',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'true',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
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
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'false',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'header' => 
 array (
 'enabled' => 'false',
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'true',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'true',
 'target_col_width' => '350',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '1',
 'width' => 'lg',
 'infinity_loop' => 'true',
 'lazy_load' => 'true',
 'delay_load' => 'false',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'media',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_profile_picture' => 'true',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 131 => 
 array (
 'name' => 'Slider VI.',
 'category' => 'slider',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '1',
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
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'false',
 'show_full_name' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'false',
 'show_comment_num' => 'false',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'true',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => '#000000',
 'widget-padding-top' => '20',
 'widget-padding-bottom' => '25',
 'widget-padding-left' => '25',
 'widget-padding-right' => '25',
 'widget-border-weight' => '0',
 'widget-border-color' => 'rgba(255, 255, 255, 0)',
 'widget-border-radius' => '20',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#ffffff',
 'header-padding-top' => '0',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#c9c9c9',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#2a2a2a',
 'header-btn-border-radius' => '8',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '12',
 'card-background-color' => '#2a2a2a',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#f3f3f3',
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '12',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#ffffff',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'true',
 'target_col_width' => '270',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '1',
 'width' => 'lg',
 'infinity_loop' => 'true',
 'lazy_load' => 'true',
 'delay_load' => 'false',
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
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 132 => 
 array (
 'name' => 'Slider VII.',
 'category' => 'slider',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '3',
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
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_full_name' => 'true',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'false',
 'show_post_title' => 'true',
 'show_post_text' => 'true',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '5',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'true',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '75',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
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
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'false',
 'target_col_width' => '350',
 'cols_num' => '1',
 'loadmore' => 'true',
 'rows_num' => '1',
 'width' => 'lg',
 'infinity_loop' => 'true',
 'lazy_load' => 'true',
 'delay_load' => 'false',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_profile_picture' => 'true',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 133 => 
 array (
 'name' => 'Slider VIII.',
 'category' => 'slider',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '1',
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
 'card' => 
 array (
 'type' => '4',
 'show_profile_picture' => 'true',
 'show_full_name' => 'true',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_title' => 'false',
 'show_post_text' => 'true',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '5',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'true',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '75',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '12',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'slider',
 'cols_num_auto' => 'false',
 'target_col_width' => '350',
 'cols_num' => '1',
 'loadmore' => 'true',
 'rows_num' => '1',
 'width' => 'lg',
 'infinity_loop' => 'true',
 'lazy_load' => 'true',
 'delay_load' => 'false',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_profile_picture' => 'true',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 65 => 
 array (
 'name' => 'List I.',
 'category' => 'list',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '1',
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
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_full_name' => 'true',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_title' => 'true',
 'show_post_text' => 'true',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '20',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '12',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'list',
 'cols_num_auto' => 'false',
 'target_col_width' => '280',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
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
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 89 => 
 array (
 'name' => 'List II.',
 'category' => 'list',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '3',
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
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'false',
 'show_full_name' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'false',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '20',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
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
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'list',
 'cols_num_auto' => 'false',
 'target_col_width' => '350',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
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
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 91 => 
 array (
 'name' => 'List III.',
 'category' => 'list',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '1',
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
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_full_name' => 'true',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_title' => 'false',
 'show_post_text' => 'true',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'rgba(255, 255, 255, 0)',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '20',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '12',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '12',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'list',
 'cols_num_auto' => 'false',
 'target_col_width' => '280',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
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
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 90 => 
 array (
 'name' => 'List IV.',
 'category' => 'list',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '1',
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
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'false',
 'show_full_name' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'false',
 'show_comment_num' => 'false',
 'show_repost_num' => 'false',
 'show_date' => 'false',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
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
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'false',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'header' => 
 array (
 'enabled' => 'false',
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'true',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'list',
 'cols_num_auto' => 'false',
 'target_col_width' => '350',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'media',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_profile_picture' => 'true',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 66 => 
 array (
 'name' => 'Masonry I.',
 'category' => 'masonry',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '1',
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
 'card' => 
 array (
 'type' => '1',
 'show_profile_picture' => 'true',
 'show_full_name' => 'true',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_title' => 'true',
 'show_post_text' => 'true',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'original',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '20',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '12',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'masonry',
 'cols_num_auto' => 'true',
 'target_col_width' => '280',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
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
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 83 => 
 array (
 'name' => 'Masonry II.',
 'category' => 'masonry',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '3',
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
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'false',
 'show_full_name' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'false',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'original',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '20',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
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
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'masonry',
 'cols_num_auto' => 'true',
 'target_col_width' => '350',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
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
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 82 => 
 array (
 'name' => 'Masonry III.',
 'category' => 'masonry',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '1',
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
 'card' => 
 array (
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_full_name' => 'true',
 'show_username' => 'false',
 'show_like_num' => 'true',
 'show_comment_num' => 'true',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'true',
 'show_post_title' => 'false',
 'show_post_text' => 'true',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'original',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'rgba(255, 255, 255, 0)',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '20',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
 'arrow-background-color' => '#ffffff',
 'arrow-color' => '#000000',
 'dots-background-color' => '#efefef',
 'loadmore-color' => '#000000',
 'loadmore-background-color' => '#efefef',
 'card-border-width' => '1',
 'card-border-color' => '#dedede',
 'card-border-radius' => '12',
 'card-background-color' => '#ffffff',
 'card-padding' => '20',
 'card-post-font-size' => '14',
 'card-header-font-size' => '14',
 'card-hover-background-color' => '#000000',
 'card-text-color' => '#000000',
 'card-muted-color' => '#555555',
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '12',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'true',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'masonry',
 'cols_num_auto' => 'true',
 'target_col_width' => '280',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
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
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 84 => 
 array (
 'name' => 'Masonry IV.',
 'category' => 'masonry',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '3',
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
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_like_num' => 'false',
 'show_comment_num' => 'false',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'portrait',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '1',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '75',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
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
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'masonry',
 'cols_num_auto' => 'false',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_profile_picture' => 'true',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 137 => 
 array (
 'name' => 'Masonry V.',
 'category' => 'masonry',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '1',
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
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'false',
 'show_full_name' => 'false',
 'show_username' => 'false',
 'show_like_num' => 'false',
 'show_comment_num' => 'false',
 'show_repost_num' => 'false',
 'show_date' => 'false',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'original',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '10',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '55',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
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
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'false',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'header' => 
 array (
 'enabled' => 'false',
 'type' => '3',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'true',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'false',
 'show_follow_button' => 'true',
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'masonry',
 'cols_num_auto' => 'true',
 'target_col_width' => '350',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'media',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_profile_picture' => 'true',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 138 => 
 array (
 'name' => 'Masonry VI.',
 'category' => 'masonry',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '3',
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
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_like_num' => 'false',
 'show_comment_num' => 'false',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'original',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '1',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '75',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
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
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
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
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'masonry',
 'cols_num_auto' => 'false',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '3',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_profile_picture' => 'true',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
 ),
 ),
 'is-active' => true,
 ),
 139 => 
 array (
 'name' => 'Masonry VII.',
 'category' => 'masonry',
 'params' => 
 array (
 'arrow' => 
 array (
 'type' => '3',
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
 'card' => 
 array (
 'type' => '2',
 'show_profile_picture' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_like_num' => 'false',
 'show_comment_num' => 'false',
 'show_repost_num' => 'false',
 'show_date' => 'true',
 'show_media_icon' => 'false',
 'show_post_title' => 'false',
 'show_post_text' => 'false',
 'show_post_media' => 'true',
 'click_action' => 'lightbox',
 'media_layout' => 'single',
 'align' => 'top',
 'ratio' => 'square',
 ),
 'carousel_album_arrow' => 
 array (
 'type' => '1',
 ),
 'custom_style' => 
 array (
 'gap' => '5',
 'widget-margin-top' => '0',
 'widget-margin-bottom' => '40',
 'arrow_show' => 'false',
 'carousel_album_arrow_show' => 'false',
 'post_lines' => '4',
 'post_overflow_type' => 'readmore',
 'widget-background-color' => 'transparent',
 'widget-padding-top' => '0',
 'widget-padding-bottom' => '0',
 'widget-padding-left' => '0',
 'widget-padding-right' => '0',
 'widget-border-weight' => '0',
 'widget-border-color' => '#000000',
 'widget-border-radius' => '0',
 'widget-font-family' => 'Poppins',
 'widget-body-height' => NULL,
 'header-font-size' => '15',
 'header-font-color' => '#000000',
 'header-padding-top' => '10',
 'header-padding-bottom' => '10',
 'header-padding-left' => '0',
 'header-padding-right' => '0',
 'header-profile-image-size' => '75',
 'header-instagram-avatar-border' => 'true',
 'header-muted-color' => '#828282',
 'header-background-color' => 'rgba(0, 0, 0, 0)',
 'header-btn-color' => '#ffffff',
 'header-btn-background-color' => '#0095f6',
 'header-btn-border-radius' => '8',
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
 'card-post-text-link-color' => '#0064D1',
 'card-media-border-radius' => '0',
 'card-hover-accent-bar' => 'true',
 'card-shadow-x' => '0',
 'card-shadow-y' => '0',
 'card-shadow-blur' => '0',
 'card-shadow-color' => 'rgba(0, 0, 0, 0.1)',
 'card-profile-image-size' => '36',
 'plaform-icon-original-color' => 'false',
 'plaform-icon-color' => '#000000',
 'page-background-color' => '#fafafa',
 'page-text-color' => '#000000',
 ),
 'footer' => 
 array (
 'enabled' => 'true',
 ),
 'header' => 
 array (
 'enabled' => 'false',
 'type' => '2',
 'show_profile_picture' => 'true',
 'show_posts_number' => 'true',
 'show_full_name' => 'false',
 'show_followers_number' => 'true',
 'show_username' => 'true',
 'show_follows_number' => 'true',
 'show_follow_button' => 'true',
 'switch' => 'false',
 ),
 'layout' => 
 array (
 'type' => 'masonry',
 'cols_num_auto' => 'false',
 'target_col_width' => '250',
 'cols_num' => '3',
 'loadmore' => 'true',
 'rows_num' => '4',
 'width' => 'lg',
 'infinity_loop' => 'false',
 'lazy_load' => 'true',
 'delay_load' => 'false',
 ),
 'lightbox' => 
 array (
 'enabled' => 'true',
 'type' => 'scroller',
 'show_like_num' => 'true',
 'show_comments' => 'true',
 'show_date' => 'true',
 'show_post_text' => 'true',
 'show_full_name' => 'false',
 'show_username' => 'true',
 'show_profile_picture' => 'true',
 ),
 'locales' => 
 array (
 'date-format' => 'd mmmm yyyy',
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
 0 => 132,
 1 => 133,
 2 => 135,
 3 => 136,
 4 => 138,
 5 => 139,
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
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
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
wp_localize_script('trustindex-feed-admin-'. $this->getShortName(), 'ajax_object', [
'ajax_url' => admin_url('admin-ajax.php'),
'nonce' => wp_create_nonce('ti-download-check'),
'interval' => self::$downloadCheckSeconds * 1000,
]);
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
'post-download-available' => [
'type' => 'warning',
'extra-class' => "",
'button-text' => __('Download your latest posts! »', 'social-photo-feed-widget'),
'is-closeable' => true,
'hide-on-close' => true,
'hide-on-open' => true,
'remind-later-button' => false,
'redirect' => '?page='.$this->getPluginSlug().'/admin.php&tab=my-posts',
/* translators: %s: Platform name */
'text' => sprintf(__('You can update your %s feed posts.', 'social-photo-feed-widget'), ucfirst($this->getShortName())),
],
'post-download-finished' => [
'type' => 'warning',
'extra-class' => "",
'button-text' => __('Check your latest posts! »', 'social-photo-feed-widget'),
'is-closeable' => true,
'hide-on-close' => true,
'hide-on-open' => true,
'remind-later-button' => false,
'redirect' => '?page='.$this->getPluginSlug().'/admin.php&tab=my-posts',
/* translators: %s: Platform name */
'text' => sprintf(__('Your new %s posts have been downloaded.', 'social-photo-feed-widget'), ucfirst($this->getShortName())),
],
'connect-finished' => [
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
'ti-notification'
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
$username = '';
$source = get_option($this->getOptionName('connect-pending'), []);
if (isset($source['username'])) {
$username = $source['username'];
} elseif ($source = $this->getConnectedSource()) {
$username = $source['name'];
}
switch ($type) {
case 'connect-finished':
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
2018-2026 &copy; <b>Trustindex.io</b><br/>
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
case 'post-download-finished':
$link = admin_url('admin.php?page='.$this->getPluginSlug().'/admin.php&tab=my-posts');
$subject = 'New posts have been added to your feed';
$message = strtr(
'<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>New posts have been added to your feed</title>
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
Your Instagram account (@%username%) has been refreshed and new posts are now available.<br /> Check out the latest posts on your list and manage your feed.<p style="text-align: center; padding: 30px;"><a href="%link%" style="background-color: #2AA8D7; margin: 10px; padding: 20px; border-radius: 4px; color: #ffffff; text-decoration: none; font-family: Arial, sans-serif; font-size: 16px; font-weight: bold; display: block;">View Posts</a></p></td>
</tr>
<tr>
<td align="center" bgcolor="#242F62" style="padding: 15px 10px 15px 10px; color: #ffffff; font-family: Arial, sans-serif; font-size: 12px; line-height: 18px;">
2018-2026 &copy; <b>Trustindex.io</b><br/>
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
'feed-data-downloaded',
'feed-data-download-checked',
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
public function getAuthError(WP_REST_Request $request)
{
$signature = $request->get_header('X-Signature');
$timestamp = (int) $request->get_header('X-Timestamp');
if (1800 < (time() - $timestamp)) {
return new WP_Error('expired', 'Request expired', ['status' => 401]);
}
$body = $request->get_body();
$expected = hash_hmac('sha256', $body.$timestamp, get_option($this->getOptionName('public-id'), $request->get_param('data')['public_id']));
if (!hash_equals($expected, $signature)) {
return new WP_Error('invalid_signature', 'Signature mismatch', ['status' => 403]);
}
return null;
}
}
?>
