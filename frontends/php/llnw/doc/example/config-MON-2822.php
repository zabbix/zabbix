<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\Config\FileLocator;

$configDirectories = array('/etc/zabbix', __DIR__);
$home = null;
if (($home = getmyuid()) && ($home = posix_getpwuid($home)) && !empty($home['dir'])) {
    array_unshift($configDirectories, $home['dir']);
}

$locator = new FileLocator($configDirectories);
$configFiles = $locator->locate('zabbix-llnw.conf.php', null, false);

var_dump($configFiles);
