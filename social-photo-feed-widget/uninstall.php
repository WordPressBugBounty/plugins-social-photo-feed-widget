<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
die;
}
require_once plugin_dir_path( __FILE__ ) . 'trustindex-feed-plugin.class.php';
$trustindex_feed_instagram = new TRUSTINDEX_Feed_Instagram("instagram", __FILE__, "1.7.2", "Widgets for Social Photo Feed", "Instagram");
$trustindex_feed_instagram->uninstall();
?>