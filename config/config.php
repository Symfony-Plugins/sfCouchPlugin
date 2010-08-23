<?php

/**
 * @package sfCouchPlugin
 */

$configCache = $this->getConfigCache();

$couch_config_file = 'couchdb.yml';
$configCache->registerConfigHandler($couch_config_file, 'sfDefineEnvironmentConfigHandler', array (
	'prefix' => 'couchdb_',
));
if ($file = $configCache->checkConfig($couch_config_file, true)) {
	include($file);
}