<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


require_once dirname(__FILE__).'/classes/core/Z.php';

try {
	Z::getInstance()->run();
}
catch (DBException $e) {
	$warningView = new CView('general.warning', array('message' => 'Database error: '.$e->getMessage()));
	$warningView->render();
	exit;
}
catch (ConfigFileException $e) {
	switch ($e->getCode()) {
		case CConfigFile::CONFIG_NOT_FOUND:
			redirect('setup.php');
			exit;

		case CConfigFile::CONFIG_ERROR:
			$warningView = new CView('general.warning', array('message' => 'Configuration file error: '.$e->getMessage()));
			$warningView->render();
			exit;
	}
}
catch (Exception $e) {
	$warningView = new CView('general.warning', array('message' => $e->getMessage()));
	$warningView->render();
	exit;
}

CProfiler::getInstance()->start();

global $ZBX_PAGE_POST_JS, $page;
global $ZBX_SERVER, $ZBX_SERVER_PORT;
$page = array();
