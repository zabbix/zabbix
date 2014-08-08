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


/************ CONFIG **************/
function select_config($cache = true) {
	global $page;
	static $config;

	if ($cache && isset($config)) {
		return $config;
	}

	$db_config = DBfetch(DBselect('SELECT c.* FROM config c'));

	if (!empty($db_config)) {
		$config = $db_config;
		return $db_config;
	}
	elseif (isset($page['title']) && $page['title'] != _('Installation')) {
		error(_('Unable to select configuration.'));
	}

	return $db_config;
}

function update_config($configs) {
	$update = array();

	if (isset($configs['work_period'])) {
		$timePeriodValidator = new CTimePeriodValidator();
		if (!$timePeriodValidator->validate($configs['work_period'])) {
			error(_('Incorrect working time.'));
			return false;
		}
	}
	if (isset($configs['alert_usrgrpid'])) {
		if ($configs['alert_usrgrpid'] != 0 && !DBfetch(DBselect('SELECT u.usrgrpid FROM usrgrp u WHERE u.usrgrpid='.zbx_dbstr($configs['alert_usrgrpid'])))) {
			error(_('Incorrect user group.'));
			return false;
		}
	}

	if (isset($configs['discovery_groupid'])) {
		$groupid = API::HostGroup()->get(array(
			'groupids' => $configs['discovery_groupid'],
			'output' => array('groupid'),
			'preservekeys' => true
		));
		if (empty($groupid)) {
			error(_('Incorrect host group.'));
			return false;
		}
	}

	// checking color values to be correct hexadecimal numbers
	$colors = array(
		'severity_color_0',
		'severity_color_1',
		'severity_color_2',
		'severity_color_3',
		'severity_color_4',
		'severity_color_5',
		'problem_unack_color',
		'problem_ack_color',
		'ok_unack_color',
		'ok_ack_color'
	);
	$colorvalidator = new CColorValidator();
	foreach ($colors as $color) {
		if (isset($configs[$color]) && !is_null($configs[$color])) {
			if (!$colorvalidator->validate($configs[$color])) {
				error($colorvalidator->getError());
				return false;
			}
		}
	}

	if (isset($configs['ok_period']) && !is_null($configs['ok_period']) && !ctype_digit($configs['ok_period'])) {
		error(_('"Display OK triggers" needs to be "0" or a positive integer.'));
		return false;
	}

	if (isset($configs['blink_period']) && !is_null($configs['blink_period']) && !ctype_digit($configs['blink_period'])) {
		error(_('"Triggers blink on status change" needs to be "0" or a positive integer.'));
		return false;
	}

	$currentConfig = select_config();

	// check duplicate severity names and if name is empty.
	$names = array();
	for ($i = 0; $i < TRIGGER_SEVERITY_COUNT; $i++) {
		$varName = 'severity_name_'.$i;
		if (!isset($configs[$varName]) || is_null($configs[$varName])) {
			$configs[$varName] = $currentConfig[$varName];
		}

		if ($configs[$varName] == '') {
			error(_('Severity name cannot be empty.'));
			return false;
		}

		if (isset($names[$configs[$varName]])) {
			error(_s('Duplicate severity name "%s".', $configs[$varName]));
			return false;
		}
		else {
			$names[$configs[$varName]] = 1;
		}
	}

	foreach ($configs as $key => $value) {
		if (!is_null($value)) {
			if ($key == 'alert_usrgrpid') {
				$update[] = $key.'='.(($value == '0') ? 'NULL' : $value);
			}
			else{
				$update[] = $key.'='.zbx_dbstr($value);
			}
		}
	}

	if (count($update) == 0) {
		error(_('Nothing to do.'));
		return null;
	}

	return DBexecute('UPDATE config SET '.implode(',', $update));
}

/************ HISTORY **************/
function get_user_history() {
	$result = array();
	$delimiter = new CSpan('&raquo;', 'delimiter');

	$history = DBfetch(DBSelect(
		'SELECT uh.title1,uh.url1,uh.title2,uh.url2,uh.title3,uh.url3,uh.title4,uh.url4,uh.title5,uh.url5'.
		' FROM user_history uh'.
		' WHERE uh.userid='.CWebUser::$data['userid'])
	);

	if (!empty($history) && !zbx_empty($history['url4'])) {
		CWebUser::$data['last_page'] = array('title' => $history['title4'], 'url' => $history['url4']);
	}
	else {
		CWebUser::$data['last_page'] = array('title' => _('Dashboard'), 'url' => 'dashboard.php');
	}

	for ($i = 1; $i < 6; $i++) {
		if (!zbx_empty($history['title'.$i])) {
			$url = new CLink($history['title'.$i], $history['url'.$i], 'history');
			array_push($result, array(SPACE, $url, SPACE));
			array_push($result, $delimiter);
		}
	}
	array_pop($result);
	return $result;
}

/**
 * Check if url length is greater than DB field size. If size is OK, return URL string.
 *
 * @param string $page['hist_arg']
 * @param string $page['file']
 *
 * @return string
 */
function getHistoryUrl($page) {
	if (isset($page['hist_arg']) && is_array($page['hist_arg'])) {
		$url = '';

		foreach ($page['hist_arg'] as $arg) {
			if (isset($_REQUEST[$arg])) {
				$url .= url_param($arg, true);
			}
		}

		if ($url) {
			$url[0] = '?';
		}

		$url = $page['file'].$url;
	}
	else {
		$url = $page['file'];
	}

	// if url length is greater than db field size, skip history update
	$historyTableSchema = DB::getSchema('user_history');

	// $url is encoded
	return (strlen($url) > $historyTableSchema['fields']['url5']['length']) ? '' : $url;
}

function addUserHistory($title, $url) {
	$userId = CWebUser::$data['userid'];

	$history5 = DBfetch(DBSelect(
		'SELECT uh.title5,uh.url5'.
		' FROM user_history uh'.
		' WHERE uh.userid='.$userId
	));

	if ($history5) {
		if ($history5['title5'] === $title) {
			if ($history5['url5'] === $url) {
				return true;
			}
			else {
				$sql = 'UPDATE user_history SET url5='.zbx_dbstr($url).' WHERE userid='.$userId;
			}
		}
		else {
			$sql = 'UPDATE user_history'.
					' SET title1=title2,'.
						' url1=url2,'.
						' title2=title3,'.
						' url2=url3,'.
						' title3=title4,'.
						' url3=url4,'.
						' title4=title5,'.
						' url4=url5,'.
						' title5='.zbx_dbstr($title).','.
						' url5='.zbx_dbstr($url).
					' WHERE userid='.$userId;
		}
	}
	else {
		$userHistoryId = get_dbid('user_history', 'userhistoryid');

		$sql = 'INSERT INTO user_history (userhistoryid, userid, title5, url5)'.
				' VALUES('.$userHistoryId.', '.$userId.', '.zbx_dbstr($title).', '.zbx_dbstr($url).')';
	}

	return DBexecute($sql);
}
