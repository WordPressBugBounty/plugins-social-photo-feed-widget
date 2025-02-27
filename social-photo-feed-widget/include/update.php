<?php
defined('ABSPATH') or die('No script kiddies please!');
if (version_compare($this->getVersion(), $this->getVersion('update-version-check'))) {
foreach ($this->getNotUsedOptionNames() as $optName) {
delete_option($this->getOptionName($optName));
}
$this->updateVersion('update-version-check', $this->getVersion());
if (get_option($this->getOptionName('css-content'))) {
$cssCdnVersion = $this->getCdnVersion('feed-css');
if (version_compare($cssCdnVersion, $this->getVersion('feed-css'))) {
$this->updateVersion('feed-css', $cssCdnVersion);
}
}
}
?>
