<?php
namespace Elementor;
use TRUSTINDEX_Feed_Instagram;
use Elementor\Widget_Base;
defined('ABSPATH') or die('No script kiddies please!');
class TrustrindexFeedWidget_Instagram extends Widget_Base {
public function get_name() {
return 'social-photo-feed-widget';
}
public function get_title() {
return __('Instagram Feed', 'social-photo-feed-widget');
}
public function get_icon() {

return 'eicon-instagram-gallery';
}
public function get_categories() {
return ['trustindex'];
}
protected function render() {
$pluginManagerInstance = new TRUSTINDEX_Feed_Instagram("instagram", __FILE__, "1.7.4", "Widgets for Social Photo Feed", "Instagram");
echo do_shortcode('['.$pluginManagerInstance->getShortcodeName().']');
}
}
