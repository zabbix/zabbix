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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


// reset the LC_CTYPE locale so that case transformation functions would work correctly
// it is also required for PHP to work with the Turkish locale (https://bugs.php.net/bug.php?id=18556)
// WARNING: this must be done before executing any other code, otherwise code execution could fail!
// this will be unnecessary in PHP 5.5
setlocale(LC_CTYPE, [
	'C', 'POSIX', 'en', 'en_US', 'en_US.UTF-8', 'English_United States.1252', 'en_GB', 'en_GB.UTF-8'
]);

require_once dirname(__FILE__).'/classes/core/Z.php';

try {
	Z::getInstance()->run();
}
catch (DBException $e) {
	(new CView('general.warning', ['header' => 'Database error', 'messages' => [$e->getMessage()]]))->render();
	exit;
}
catch (ConfigFileException $e) {
	switch ($e->getCode()) {
		case CConfigFile::CONFIG_NOT_FOUND:
			redirect('setup.php');
			exit;

		case CConfigFile::CONFIG_ERROR:
			(new CView('general.warning', ['header' => 'Configuration file error', 'messages' => [$e->getMessage()]]))->
				render();
			exit;
	}
}
catch (Exception $e) {
	(new CView('general.warning', ['header' => $e->getMessage(), 'messages' => []]))->render();
	exit;
}

CProfiler::getInstance()->start();

global $ZBX_SERVER, $ZBX_SERVER_PORT, $page;

$page = [
	'title' => null,
	'file' => null,
	'scripts' => null,
	'type' => null,
	'menu' => null
];
