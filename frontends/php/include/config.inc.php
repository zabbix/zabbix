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

require_once dirname(__FILE__).'/debug.inc.php';
require_once dirname(__FILE__).'/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/defines.inc.php';
require_once dirname(__FILE__).'/func.inc.php';
require_once dirname(__FILE__).'/html.inc.php';
require_once dirname(__FILE__).'/perm.inc.php';
require_once dirname(__FILE__).'/audit.inc.php';
require_once dirname(__FILE__).'/js.inc.php';
require_once dirname(__FILE__).'/users.inc.php';
require_once dirname(__FILE__).'/validate.inc.php';
require_once dirname(__FILE__).'/profiles.inc.php';

// abc sorting
require_once dirname(__FILE__).'/acknow.inc.php';
require_once dirname(__FILE__).'/actions.inc.php';
require_once dirname(__FILE__).'/discovery.inc.php';
require_once dirname(__FILE__).'/events.inc.php';
require_once dirname(__FILE__).'/graphs.inc.php';
require_once dirname(__FILE__).'/hosts.inc.php';
require_once dirname(__FILE__).'/httptest.inc.php';
require_once dirname(__FILE__).'/ident.inc.php';
require_once dirname(__FILE__).'/images.inc.php';
require_once dirname(__FILE__).'/items.inc.php';
require_once dirname(__FILE__).'/maintenances.inc.php';
require_once dirname(__FILE__).'/maps.inc.php';
require_once dirname(__FILE__).'/media.inc.php';
require_once dirname(__FILE__).'/services.inc.php';
require_once dirname(__FILE__).'/sounds.inc.php';
require_once dirname(__FILE__).'/triggers.inc.php';
require_once dirname(__FILE__).'/valuemap.inc.php';

require_once dirname(__FILE__).'/nodes.inc.php';

try {
	Z::getInstance()->run();
}
catch (DBException $e) {
	$warningMessage = 'Database error: '.$e->getMessage();
	require_once dirname(__FILE__).'/../warning.php';
	exit;
}
catch (ConfigFileException $e) {
	switch ($e->getCode()) {
		case CConfigFile::CONFIG_NOT_FOUND:
			redirect('setup.php');
			exit;

		case CConfigFile::CONFIG_ERROR:
			$warningMessage = 'Configuration file error: '.$e->getMessage();
			require_once dirname(__FILE__).'/../warning.php';
			exit;
	}
}
catch (Exception $e) {
	$warningMessage = $e->getMessage();
	require_once dirname(__FILE__).'/../warning.php';
	exit;
}

CProfiler::getInstance()->start();

global $ZBX_PAGE_POST_JS, $page;
global $ZBX_SERVER, $ZBX_SERVER_PORT;
$page = array();


