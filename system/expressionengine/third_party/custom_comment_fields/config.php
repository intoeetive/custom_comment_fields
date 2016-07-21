<?php

if ( ! defined('CUSTOM_COMMENT_FIELDS_ADDON_NAME'))
{
	define('CUSTOM_COMMENT_FIELDS_ADDON_NAME',         'Custom Comment Fields');
	define('CUSTOM_COMMENT_FIELDS_ADDON_VERSION',      '0.4');
}

$config['name'] = CUSTOM_COMMENT_FIELDS_ADDON_NAME;
$config['version']= CUSTOM_COMMENT_FIELDS_ADDON_VERSION;

$config['nsm_addon_updater']['versions_xml']='http://www.intoeetive.com/index.php/update.rss/289';