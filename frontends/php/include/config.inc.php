<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
setlocale(LC_CTYPE, array(
	'C', 'POSIX', 'en', 'en_US', 'en_US.UTF-8', 'English_United States.1252', 'en_GB', 'en_GB.UTF-8'
));

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


/**
 * Use the global user details to check if the user is in a RW or RO group.
 * @return boolean
 */
function _llnw_is_user_rw() {
	global $USER_DETAILS;
	// Consider CWebUser::getType() in place of USER_DETAILS...
	global $LLNW_RW_ROLE, $LLNW_RO_ROLE; // Global configuration role information
	// Inititalize the config...
	if (empty($LLNW_RO_ROLE)) {
		$LLNW_RO_ROLE = -1; // Assume no default role assignment.
	}
	if (empty($LLNW_RW_ROLE)) {
		$LLNW_RW_ROLE = -1;
	}

	// Find/populate our user's ID
	$userid='';
	if (!empty($USER_DETAILS['userid'])) {
		$userid = $USER_DETAILS['userid'];
	}
	else if (!empty(CZBXAPI::$userData['userid'])) {
		$userid = CZBXAPI::$userData['userid'];
	}

	if (empty($userid)) {
		return true; // consider the lack of a userid as anonymous
	}
	else {
		static $groups; // cache the groups per request - we only need to call the API once.
		if (empty($groups)) {
			$groups = array();
			$apigroups = API::UserGroup()->get(array('userids' => $userid, 'output' => API_OUTPUT_EXTEND));
			foreach ($apigroups as $group) {
				$groups[] = $group['usrgrpid'];
			}
		}

		// Return status based on RO,RW Role config.
		if (in_array($LLNW_RO_ROLE, $groups)) {
			return false;
		}
		if (in_array($LLNW_RW_ROLE, $groups)) {
			return true;
		}
	}
	return false; // catchall
}
