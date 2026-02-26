<?php
defined('ABSPATH') or die('No script kiddies please!');
if (isset($_REQUEST['command'])) {
if ('source-connect' === $_REQUEST['command']) {
check_admin_referer('ti-connect-source');
$source = null;
if (isset($_POST['data'])) {
/*
This function ensures that each element of the JSON object is sanitized individually using standard WordPress sanitization functions
*/
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
$source = $pluginManagerInstance->sanitizeJsonData(wp_unslash($_POST['data']));
$pluginManagerInstance->saveConnectedSource($source, isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : null);
}
if (isset($_GET['page'])) {
header('Location: admin.php?page=' . sanitize_text_field(wp_unslash($_GET['page'])) . '&tab=' . sanitize_text_field($selectedTab));
}
exit;
}
else if ('source-connection-failed' === $_REQUEST['command']) {
check_admin_referer('ti-connect-source');
delete_option($pluginManagerInstance->getOptionName('connect-pending'));
exit;
}
else if ('source-connecting' === $_REQUEST['command']) {
check_admin_referer('ti-connect-source');
if (get_option($pluginManagerInstance->getOptionName('source'))) {
delete_option($pluginManagerInstance->getOptionName('connect-pending'));
exit;
}
$source = null;
if (isset($_POST['data'])) {
/*
This function ensures that each element of the JSON object is sanitized individually using standard WordPress sanitization functions
*/
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
$source = $pluginManagerInstance->sanitizeJsonData(wp_unslash($_POST['data']));
}
update_option($pluginManagerInstance->getOptionName('connect-pending'), $source, false);
exit;
}
else if ($_REQUEST['command'] === 'disconnect-source') {
check_admin_referer('ti-disconnect-source');
$pluginManagerInstance->deleteConnectedSource();
delete_option($pluginManagerInstance->getOptionName('source'));
delete_option($pluginManagerInstance->getOptionName('feed-data'));
delete_option($pluginManagerInstance->getOptionName('feed-data-saved'));
delete_option($pluginManagerInstance->getOptionName('public-id'));
delete_option($pluginManagerInstance->getOptionName('token-expires'));
delete_option($pluginManagerInstance->getOptionName('layout'));
delete_option($pluginManagerInstance->getOptionName('template'));
delete_option($pluginManagerInstance->getOptionName('css-content'));
$pluginManagerInstance->setNotificationParam('token-renew', 'active', false);
$pluginManagerInstance->setNotificationParam('token-expired', 'active', false);
if (isset($_GET['page'])) {
header('Location: admin.php?page=' . sanitize_text_field(wp_unslash($_GET['page'])) . '&tab=' . sanitize_text_field($selectedTab));
}
exit;
}
else if ($_REQUEST['command'] === 'select-layout') {
check_admin_referer('ti-select-layout');
$layout = isset($_GET['layout']) ? sanitize_text_field(wp_unslash($_GET['layout'])) : "";
update_option($pluginManagerInstance->getOptionName('layout'), $layout, false);
delete_option($pluginManagerInstance->getOptionName('template'));
delete_option($pluginManagerInstance->getOptionName('css-content'));
if (isset($_GET['page'])) {
header('Location: admin.php?page=' . sanitize_text_field(wp_unslash($_GET['page'])) . '&tab=' . sanitize_text_field($selectedTab));
}
exit;
}
else if ($_REQUEST['command'] === 'select-template') {
check_admin_referer('ti-select-template');
$templateId = isset($_GET['template']) ? sanitize_text_field(wp_unslash($_GET['template'])) : "";
update_option($pluginManagerInstance->getOptionName('template'), $templateId, false);
delete_option($pluginManagerInstance->getOptionName('css-content'));
$feedData = $pluginManagerInstance->getFeedData();
$feedData['style'] = [
'locales' => $feedData['style']['locales'],
'settings' => $feedData['style']['settings'],
'version' => $feedData['style']['version'],
];
$pluginManagerInstance->updateFeedDataWithDefaultTemplateParams($feedData, $templateId);
$pluginManagerInstance->saveFeedData($feedData, false);
if (isset($_GET['page'])) {
header('Location: admin.php?page=' . sanitize_text_field(wp_unslash($_GET['page'])) . '&tab=' . sanitize_text_field($selectedTab));
}
exit;
}
else if ($_REQUEST['command'] === 'save-feed-widget') {
check_admin_referer('ti-save-feed-widget');
$data = null;
if (isset($_POST['data'])) {
/*
This function ensures that each element of the JSON object is sanitized individually using standard WordPress sanitization functions
*/
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
$data = $pluginManagerInstance->sanitizeJsonData(wp_unslash($_POST['data']));
}
if ($data) {
$data['css'] = preg_replace('/\.ti-widget([\s\.\[])/', '.ti-widget[data-wkey="feed-'. $pluginManagerInstance->getShortName() .'"]$1', $data['css']);
update_option($pluginManagerInstance->getOptionName('css-content'), $data['css'], false);
unset($data['css']);
$pluginManagerInstance->saveFeedData($data, false);
$pluginManagerInstance->handleCssFile();
}
if (isset($_GET['page'])) {
header('Location: admin.php?page=' . sanitize_text_field(wp_unslash($_GET['page'])) . '&tab=' . sanitize_text_field($selectedTab));
}
exit;
}
}
$layout = get_option($pluginManagerInstance->getOptionName('layout'));
$template = get_option($pluginManagerInstance->getOptionName('template'));
$css = get_option($pluginManagerInstance->getOptionName('css-content'));
$isReconnectingSource = isset($_GET['reconnect-source']);
$error = null;
$connectPending = get_option($pluginManagerInstance->getOptionName('connect-pending'), []);
if (isset($connectPending['error'])) {
$error = $connectPending['error'];
} elseif (isset($_GET['error'])) {
$error = sanitize_text_field(wp_unslash($_GET['error']));
}
?>
<?php
$stepUrl = '?' . (isset($_GET['page']) ? 'page=' . sanitize_text_field(wp_unslash($_GET['page'])) . '&' : '') . 'tab=feed-configurator&step=%step%';
$stepList = [
/* translators: %s: Platform name */
sprintf(__('Connect %s', 'social-photo-feed-widget'), 'Instagram'),
__('Select Layout', 'social-photo-feed-widget'),
__('Select Template', 'social-photo-feed-widget'),
__('Widget Editor', 'social-photo-feed-widget'),
__('Get Shortcode', 'social-photo-feed-widget')
];
$stepDone = 0;
$stepCurrent = isset($_GET['step']) ? (int)sanitize_text_field(wp_unslash($_GET['step'])) : 0;
if ($css) {
$stepDone = 4;
}
else if ($template) {
$stepDone = 3;
}
else if ($layout) {
$stepDone = 2;
}
else if ($pluginManagerInstance->getConnectedSource()) {
$stepDone = 1;
}
if (!$stepCurrent) {
$stepCurrent = $stepDone + 1;
} else if ($stepCurrent > ($stepDone + 1)) {
$stepCurrent = $stepDone + 1;
}
if ($stepCurrent === 4) {
$stepRightButton = [
'class' => 'btn-feed-editor-save',
'text' => __('Save and get code', 'social-photo-feed-widget')
];
}
if (!isset($_GET['step']) && $pluginManagerInstance->getNotificationParam('token-expired', 'active')) {
$stepCurrent = 1;
}
include(plugin_dir_path(__FILE__) . '../include/step-list.php');
?>
<div class="ti-container<?php if (!in_array($stepCurrent, [ 1, 5 ])): ?> ti-narrow-page<?php endif; ?><?php if ($stepCurrent === 4): ?> ti-no-maxwidth<?php endif; ?>">
<?php if ($stepCurrent !== 4): ?>
<h1 class="ti-header-title"><?php echo esc_html($stepList[ $stepCurrent - 1 ]); ?></h1>
<?php endif; ?>
<?php if ($stepCurrent === 1): ?>
<?php
$source = $pluginManagerInstance->getConnectedSource();
if ($source && !$isReconnectingSource): ?>
<div class="ti-source-box">
<?php
$feedData = $pluginManagerInstance->getFeedData();
$avatarUrl = isset($feedData['sources']['feed-plugin']['user']['avatar_url']) ? $feedData['sources']['feed-plugin']['user']['avatar_url'] : "";
if ($avatarUrl):
echo wp_kses_post($pluginManagerInstance->displayImg($avatarUrl));
endif; ?>
<?php if (isset($source['name']) && $source['name']): ?>
<div class="ti-source-info">
<strong><?php echo esc_html($source['name']); ?></strong>
</div>
<?php endif; ?>

<?php if (isset($_GET['page'])): ?>
<a href="<?php echo esc_url(wp_nonce_url('?page='. esc_attr(sanitize_text_field(wp_unslash($_GET['page']))) .'&tab='. esc_attr($selectedTab) .'&command=disconnect-source', 'ti-disconnect-source')); ?>" class="ti-btn ti-btn-default ti-btn-loading-on-click"><?php echo esc_html(__('Disconnect', 'social-photo-feed-widget')); ?></a>
<?php endif; ?>
</div>
<?php if ($pluginManagerInstance->isNotificationEnabled('token-renew') && $tokenExpireTimestamp = (int)get_option($pluginManagerInstance->getOptionName('token-expires'))): ?>
<div class="ti-box ti-notice-<?php if ($tokenExpireTimestamp < time()): ?>error<?php elseif ($tokenExpireTimestamp < time() + (86400 * 7)): ?>warning<?php else: ?>info<?php endif; ?>">
<p>
<strong><?php
$tokenExpireDate = gmdate('Y-m-d H:i', $tokenExpireTimestamp);
if ($tokenExpireTimestamp > time()) {
/* translators: 1: Platform name, 2: Date string */
echo esc_html(sprintf(__('Your %1$s Access Token expires on %2$s.', 'social-photo-feed-widget'), 'Instagram', $tokenExpireDate));
} else {
/* translators: 1: Platform name, 2: Date string */
echo esc_html(sprintf(__('Your %1$s Access Token expired on %2$s.', 'social-photo-feed-widget'), 'Instagram', $tokenExpireDate));
}
?></strong><br />
<?php echo esc_html(__('Please renew your token by clicking the "Reconnect" button on the Connect Page.', 'social-photo-feed-widget')); ?><br />
<?php
/* translators: %s: Platform name */
echo esc_html(sprintf(__('This will ensure that your %s Feed Widget continues to update automatically.', 'social-photo-feed-widget'), 'Instagram'));
?><br /><br />
<?php if (isset($_GET['page'])): ?>
<a href="<?php echo esc_url('?page='. esc_attr(sanitize_text_field(wp_unslash($_GET['page']))) .'&tab='. esc_attr($selectedTab) .'&step=1&reconnect-source'); ?>" class="ti-btn ti-btn-loading-on-click"><?php echo esc_html(__('Go to Connect Page', 'social-photo-feed-widget')); ?></a>
<?php endif; ?>
</p>
</div>
<?php endif; ?>
<?php else: ?>

<p><?php echo esc_html(__("Select the type of posts you'd like to display in your feed", 'social-photo-feed-widget')); ?></p>
<?php if (isset($error) && 'no-posts' === $error): ?>
<?php echo wp_kses_post($pluginManagerInstance::getAlertBox('error', __('The source you attempted to connect does not contain any posts. Please connect a different source.', 'social-photo-feed-widget'))); ?>
<?php endif; ?>
<form method="post" id="ti-connect-source-form">
<?php wp_nonce_field('ti-connect-source'); ?>
<input type="hidden" name="command" value="connect-source" />
<input type="hidden" name="data" required="required" value="" />
</form>
<?php $connectUrl = 'https://admin.trustindex.io/source/edit_feed/type/Instagram/iframe/1'; ?>
<?php
if ($isReconnectingSource) {
$connectUrl .= '/public_id/'.get_option($pluginManagerInstance->getOptionName('public-id'));
}
?>

<?php
$connectUrlParams = array_merge(
isset($connectPending['error']) ? [] : $connectPending,
array(
'website' => esc_attr(urlencode(get_option('siteurl'))),
'version' => esc_attr($pluginManagerInstance->getVersion()),
),
(null !== $pluginManagerInstance->getWebhookUrl() ? array('webhook' => esc_attr(urlencode($pluginManagerInstance->getWebhookUrl()))) : array())
);
?>
<iframe src="<?php echo esc_url(add_query_arg($connectUrlParams, esc_attr($connectUrl))); ?>"
id="ti-admin-iframe" scrolling="no" allowfullscreen="true"
data-error-message="<?php echo esc_attr(implode("\n", [
__('We couldn’t reach our server at the moment.', 'social-photo-feed-widget'),
__('Please refresh the page or try again in 5 minutes.', 'social-photo-feed-widget'),
__('This is only a temporary issue – no need to switch plugins, everything will be back to normal shortly.', 'social-photo-feed-widget')
])); ?>"
></iframe>

<?php endif; ?>
<?php elseif ($stepCurrent === 2): ?>
<div class="ti-category-container">
<?php foreach ($pluginManager::$widgetCategories as $category): ?>
<div class="ti-box">
<div class="ti-box-header"><?php echo esc_html(ucfirst($category)); ?></div>
<?php echo wp_kses_post($pluginManagerInstance->displayImg('assets/img/select-'. $category .'.png')); ?>
<?php if (isset($_GET['page'])): ?>
<div class="ti-box-footer">
<a href="<?php echo esc_url(wp_nonce_url('?page='. esc_attr(sanitize_text_field(wp_unslash($_GET['page']))) .'&tab='. esc_attr($selectedTab) .'&command=select-layout&layout='. esc_attr($category), 'ti-select-layout')); ?>" class="ti-btn ti-btn-loading-on-click"><?php echo esc_html(__('Select', 'social-photo-feed-widget')); ?></a>
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php elseif ($stepCurrent === 3): ?>
<div class="ti-preview-boxes-container">
<?php foreach ($pluginManager::$widgetTemplates as $id => $template): ?>
<?php
$className = 'ti-full-width';
if (in_array($template['category'], [ 'list' ]) || in_array($id, $pluginManager::$widgetHalfWidthLayouts)) {
$className = 'ti-half-width';
}
if ($template['is-active'] && $template['category'] === $layout):
?>
<div class="<?php echo esc_attr($className); ?>">
<div class="ti-box ti-preview-boxes">
<div class="ti-box-inner">
<div class="ti-box-header ti-box-header-normal">
<?php echo esc_html(__('Template', 'social-photo-feed-widget')); ?>:
<strong><?php echo esc_html($template['name']); ?></strong>
<?php if (isset($_GET['page'])): ?>
<a href="<?php echo esc_url(wp_nonce_url('?page='. esc_attr(sanitize_text_field(wp_unslash($_GET['page']))) .'&tab='. esc_attr($selectedTab) .'&command=select-template&template='. esc_attr($id), 'ti-select-template')); ?>" class="ti-btn ti-btn-sm ti-btn-loading-on-click ti-pull-right"><?php echo esc_html(__('Select', 'social-photo-feed-widget')); ?></a>
<?php endif; ?>
<div class="clear"></div>
</div>
<div class="preview">
<div id="<?php
$widgetId = $pluginManagerInstance->getWidget($id);
echo esc_attr($pluginManagerInstance->getContainerKey($widgetId));
?>"></div>
</div>
</div>
</div>
</div>
<?php endif; ?>
<?php endforeach; ?>
</div>
<?php elseif ($stepCurrent === 4): ?>
<form method="post" id="ti-widget-editor-form">
<?php wp_nonce_field('ti-save-feed-widget'); ?>
<input type="hidden" name="command" value="save-feed-widget" />
<input type="hidden" name="data" required="required" value="" />
<?php
$feedData = $pluginManagerInstance->getFeedData();
$iframeUrl = 'https://admin.trustindex.io/widget/edit/layout_id/'. esc_attr($template) .'/source/'. esc_attr(ucfirst($pluginManagerInstance->getShortName())) .'/iframe/1/layout-set/'. esc_attr($feedData['style']['type']);
?>
<script type="application/json">{"data":<?php echo wp_json_encode($feedData); ?>}</script>
<iframe id="ti-admin-iframe" class="ti-narrow-iframe" name="ti-widget-editor-iframe"
data-src="<?php echo esc_url($iframeUrl . '?version='. esc_attr($pluginManagerInstance->getVersion())); ?>"
scrolling="no" allowfullscreen="true"
data-error-message="<?php echo esc_attr(implode("\n", [
__('We couldn’t reach our server at the moment.', 'social-photo-feed-widget'),
__('Please refresh the page or try again in 5 minutes.', 'social-photo-feed-widget'),
__('This is only a temporary issue – no need to switch plugins, everything will be back to normal shortly.', 'social-photo-feed-widget')
])); ?>"
></iframe>
</form>
<?php else:
$pluginManagerInstance->setNotificationParam('connect-finished', 'active', false);
?>
<div class="ti-box ti-mb-2">
<div class="ti-box-header"><?php echo esc_html(__('Insert this shortcode into your website', 'social-photo-feed-widget')); ?></div>
<div class="ti-form-group" style="margin-bottom: 2px">
<label>Shortcode</label>
<code class="ti-shortcode">[<?php echo esc_html($pluginManagerInstance->getShortcodeName()); ?>]</code>
<a href=".ti-shortcode" class="ti-btn ti-tooltip ti-toggle-tooltip btn-copy2clipboard">
<?php echo esc_html(__('Copy to clipboard', 'social-photo-feed-widget')); ?>
<span class="ti-tooltip-message">
<span style="color: #00ff00; margin-right: 2px">✓</span>
<?php echo esc_html(__('Copied', 'social-photo-feed-widget')); ?>
</span>
</a>
</div>
<div class="ti-info-text"><?php echo esc_html(__('Copy and paste this shortcode into post, page or widget.', 'social-photo-feed-widget')); ?></div>
</div>
<?php if (!get_option($pluginManagerInstance->getOptionName('rate-us-feedback'), 0)): ?>
<?php include(plugin_dir_path(__FILE__) . '../include/rate-us-feedback-box.php'); ?>
<?php endif; ?>
<?php
$tiCampaign1 = 'wp-feed-instagram-1';
$tiCampaign2 = 'wp-feed-instagram-2';
include(plugin_dir_path(__FILE__) . '../include/get-more-customers-box.php');
?>
<?php endif; ?>
</div>
