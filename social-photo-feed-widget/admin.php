<?php
defined('ABSPATH') or die('No script kiddies please!');
$pluginManager = 'TRUSTINDEX_Feed_Instagram';
$pluginManagerInstance = $trustindex_feed_instagram;
$pluginNameForEmails = 'Instagram feed';
$noContainerElementTabs = [ 'feed-configurator' ];
$logoCampaignId = 'wp-feed-instagram-l';
$logoFile = 'assets/img/trustindex.svg';
$assetCheckJs = [
'common' => 'assets/js/admin.js',
];
$assetCheckCssId = 'trustindex-feed-admin-instagram';
$assetCheckCssFile = 'assets/css/admin.css';
include(plugin_dir_path(__FILE__) . 'include' . DIRECTORY_SEPARATOR . 'admin.php');
?>