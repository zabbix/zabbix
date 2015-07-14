<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


global $DB, $ZBX_SERVER, $ZBX_SERVER_PORT;

$pageHeader = new CPageHeader($data['page']['title']);

$scripts = [];

$theme = ZBX_DEFAULT_THEME;
if (!empty($DB['DB'])) {
	$config = select_config();
	$theme = getUserTheme($data['user']);

	$severityCss = <<<CSS
.disaster { background: #{$config['severity_color_5']} !important; }
.high { background: #{$config['severity_color_4']} !important; }
.average { background: #{$config['severity_color_3']} !important; }
.warning { background: #{$config['severity_color_2']} !important; }
.information { background: #{$config['severity_color_1']} !important; }
.not_classified { background: #{$config['severity_color_0']} !important; }
CSS;
	$pageHeader->addStyle($severityCss);

	// perform Zabbix server check only for standard pages
	if ($config['server_check_interval'] && !empty($ZBX_SERVER) && !empty($ZBX_SERVER_PORT)) {
		$scripts[] = 'servercheck.js';
	}
}
$pageHeader->addCssFile('styles/'.CHtml::encode($theme).'.css');

$pageHeader->addJsFile('js/browsers.js');
$pageHeader->addJsBeforeScripts('var PHP_TZ_OFFSET = '.date('Z').';');

// show GUI messages in pages with menus and in fullscreen mode
$showGuiMessaging = (!defined('ZBX_PAGE_NO_MENU') || $_REQUEST['fullscreen'] == 1) ? 1 : 0;
$path = 'jsLoader.php?ver='.ZABBIX_VERSION.'&amp;lang='.$data['user']['lang'].'&showGuiMessaging='.$showGuiMessaging;
$pageHeader->addJsFile($path);

if ($scripts) {
	$pageHeader->addJsFile('jsLoader.php?'.'files[]='.implode('&amp;files[]=', $scripts));
}

foreach ($data['javascript']['files'] as $path) {
	$pageHeader->addJsFile($path);
}

$pageHeader->display();

echo "<body class=\"$css\">";
echo "<div class=\"msg-bad-global\" id=\"msg-bad-global\"></div>";
