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


class CProfile {

	private static $userDetails = array();
	private static $profiles = null;
	private static $update = array();
	private static $insert = array();
	private static $stringProfileMaxLength;

	public static function init() {
		self::$userDetails = CWebUser::$data;
		self::$profiles = array();

		$profilesTableSchema = DB::getSchema('profiles');
		self::$stringProfileMaxLength = $profilesTableSchema['fields']['value_str']['length'];

		$db_profiles = DBselect(
			'SELECT p.*'.
			' FROM profiles p'.
			' WHERE p.userid='.self::$userDetails['userid'].
				andDbNode('p.profileid', false).
			' ORDER BY p.userid,p.profileid'
		);
		while ($profile = DBfetch($db_profiles)) {
			$value_type = self::getFieldByType($profile['type']);

			if (!isset(self::$profiles[$profile['idx']])) {
				self::$profiles[$profile['idx']] = array();
			}
			self::$profiles[$profile['idx']][$profile['idx2']] = $profile[$value_type];
		}
	}

	public static function flush() {
		// if not initialised, no changes were made
		if (is_null(self::$profiles)) {
			return true;
		}

		if (self::$userDetails['userid'] <= 0) {
			return null;
		}

		if (!empty(self::$insert) || !empty(self::$update)) {
			DBstart();
			foreach (self::$insert as $idx => $profile) {
				foreach ($profile as $idx2 => $data) {
					self::insertDB($idx, $data['value'], $data['type'], $idx2);
				}
			}

			ksort(self::$update);
			foreach (self::$update as $idx => $profile) {
				ksort($profile);
				foreach ($profile as $idx2 => $data) {
					self::updateDB($idx, $data['value'], $data['type'], $idx2);
				}
			}
			DBend();
		}
	}

	public static function clear() {
		self::$insert = array();
		self::$update = array();
	}

	public static function get($idx, $default_value = null, $idx2 = 0) {
		// no user data available, just return the default value
		if (!CWebUser::$data) {
			return $default_value;
		}

		if (is_null(self::$profiles)) {
			self::init();
		}

		if (isset(self::$profiles[$idx][$idx2])) {
			return self::$profiles[$idx][$idx2];
		}
		else {
			return $default_value;
		}
	}

	/**
	 * Removes profile values from DB and profiles cache
	 *
	 * @param string $idx	first identifier
	 * @param mixed  $idx2	second identifier, which can be list of identifiers as well
	 */
	public static function delete($idx, $idx2) {
		if (!is_array($idx2)) {
			$idx2 = array($idx2);
		}

		// remove from DB
		DBexecute('DELETE FROM profiles WHERE idx='.zbx_dbstr($idx).' AND '.dbConditionString('idx2', $idx2));

		// remove from cache
		if (!is_null(self::$profiles)) {
			foreach ($idx2 as $v) {
				unset(self::$profiles[$idx][$v]);
			}
		}
	}

	/**
	 * Update favorite values in DB profiles table.
	 *
	 * @param string	$idx		max length is 96
	 * @param mixed		$value		max length 255 for string
	 * @param int		$type
	 * @param int		$idx2
	 */
	public static function update($idx, $value, $type, $idx2 = 0) {
		if (is_null(self::$profiles)) {
			self::init();
		}

		if (!self::checkValueType($value, $type)) {
			return false;
		}

		$profile = array(
			'idx' => $idx,
			'value' => $value,
			'type' => $type,
			'idx2' => $idx2
		);

		$current = CProfile::get($idx, null, $idx2);
		if (is_null($current)) {
			if (!isset(self::$insert[$idx])) {
				self::$insert[$idx] = array();
			}
			self::$insert[$idx][$idx2] = $profile;
		}
		else {
			if ($current != $value) {
				if (!isset(self::$update[$idx])) {
					self::$update[$idx] = array();
				}
				self::$update[$idx][$idx2] = $profile;
			}
		}

		if (!isset(self::$profiles[$idx])) {
			self::$profiles[$idx] = array();
		}
		self::$profiles[$idx][$idx2] = $value;
	}

	private static function insertDB($idx, $value, $type, $idx2) {
		$value_type = self::getFieldByType($type);

		$values = array(
			'profileid' => get_dbid('profiles', 'profileid'),
			'userid' => self::$userDetails['userid'],
			'idx' => zbx_dbstr($idx),
			$value_type => zbx_dbstr($value),
			'type' => $type,
			'idx2' => $idx2
		);
		return DBexecute('INSERT INTO profiles ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')');
	}

	private static function updateDB($idx, $value, $type, $idx2) {
		$sql_cond = '';

		if ($idx != 'web.nodes.switch_node') {
			$sql_cond .= andDbNode('profileid', false);
		}

		if ($idx2 > 0) {
			$sql_cond .= ' AND idx2='.$idx2.andDbNode('idx2', false);
		}

		$value_type = self::getFieldByType($type);

		return DBexecute(
			'UPDATE profiles SET '.
				$value_type.'='.zbx_dbstr($value).','.
				' type='.$type.
			' WHERE userid='.self::$userDetails['userid'].
				' AND idx='.zbx_dbstr($idx).
				$sql_cond
		);
	}

	public static function getFieldByType($type) {
		switch ($type) {
			case PROFILE_TYPE_INT:
				$field = 'value_int';
				break;
			case PROFILE_TYPE_STR:
				$field = 'value_str';
				break;
			case PROFILE_TYPE_ID:
			default:
				$field = 'value_id';
		}
		return $field;
	}

	private static function checkValueType($value, $type) {
		switch ($type) {
			case PROFILE_TYPE_ID:
				return zbx_ctype_digit($value);
			case PROFILE_TYPE_INT:
				return zbx_is_int($value);
			case PROFILE_TYPE_STR:
				return zbx_strlen($value) <= self::$stringProfileMaxLength;
			default:
				return true;
		}
	}
}

/************ CONFIG **************/
function select_config($cache = true, $nodeid = null) {
	global $page, $ZBX_LOCALNODEID;
	static $config;

	if ($cache && isset($config)) {
		return $config;
	}
	if (is_null($nodeid)) {
		$nodeid = $ZBX_LOCALNODEID;
	}

	$db_config = DBfetch(DBselect(
			'SELECT c.*'.
			' FROM config c'.
			whereDbNode('c.configid', $nodeid)
	));
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

	return DBexecute(
			'UPDATE config'.
			' SET '.implode(',', $update).
			whereDbNode('configid', false)
	);
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

function add_user_history($page) {
	$userid = CWebUser::$data['userid'];
	$title = $page['title'];

	if (isset($page['hist_arg']) && is_array($page['hist_arg'])) {
		$url = '';
		foreach ($page['hist_arg'] as $arg) {
			if (isset($_REQUEST[$arg])) {
				$url .= url_param($arg, true);
			}
		}
		if (!empty($url)) {
			$url[0] = '?';
		}
		$url = $page['file'].$url;
	}
	else {
		$url = $page['file'];
	}

	// if url length is greater than db field size, skip history update
	$historyTableSchema = DB::getSchema('user_history');
	if (zbx_strlen($url) > $historyTableSchema['fields']['url5']['length']) {
		return false;
	}

	$history5 = DBfetch(DBSelect(
		'SELECT uh.title5,uh.url5'.
		' FROM user_history uh'.
		' WHERE uh.userid='.$userid
	));

	if ($history5 && ($history5['title5'] == $title)) {
		if ($history5['url5'] != $url) {
			// title same, url isnt, change only url
			$sql = 'UPDATE user_history'.
					' SET url5='.zbx_dbstr($url).
					' WHERE userid='.$userid;
		}
		else {
			// no need to change anything;
			return null;
		}
	}
	else {
		// new page with new title is added
		if ($history5 === false) {
			$userhistoryid = get_dbid('user_history', 'userhistoryid');
			$sql = 'INSERT INTO user_history (userhistoryid, userid, title5, url5)'.
					' VALUES('.$userhistoryid.', '.$userid.', '.zbx_dbstr($title).', '.zbx_dbstr($url).')';
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
					' WHERE userid='.$userid;
		}
	}
	return DBexecute($sql);
}
