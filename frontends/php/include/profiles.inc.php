<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


/**
 * Select configuration parameters.
 *
 * @static array $config	Array containing configuration parameters.
 *
 * @return array
 */
function select_config() {
	static $config;

	if (!isset($config)) {
		$config = DBfetch(DBselect('SELECT c.* FROM config c'));
	}

	return $config;
}

function setHostGroupInternal($groupid, $internal) {
	return DBexecute(
		'UPDATE groups'.
		' SET internal='.zbx_dbstr($internal).
		' WHERE '.dbConditionInt('groupid', [$groupid])
	);
}

function update_config($config) {
	$configOrig = select_config();

	if (array_key_exists('discovery_groupid', $config)) {
		$hostGroups = API::HostGroup()->get([
			'output' => ['name'],
			'groupids' => $config['discovery_groupid']
		]);
		if (!$hostGroups) {
			error(_('Incorrect host group.'));
			return false;
		}
	}

	if (array_key_exists('alert_usrgrpid', $config) && $config['alert_usrgrpid'] != 0) {
		$userGroup = DBfetch(DBselect(
			'SELECT u.name'.
			' FROM usrgrp u'.
			' WHERE u.usrgrpid='.zbx_dbstr($config['alert_usrgrpid'])
		));
		if (!$userGroup) {
			error(_('Incorrect user group.'));
			return false;
		}
	}

	$updateSeverity = false;
	for ($i = 0; $i < TRIGGER_SEVERITY_COUNT; $i++) {
		if (isset($config['severity_name_'.$i])) {
			$updateSeverity = true;
			break;
		}
	}

	if ($updateSeverity) {
		// check duplicate severity names and if name is empty.
		$names = [];
		for ($i = 0; $i < TRIGGER_SEVERITY_COUNT; $i++) {
			$varName = 'severity_name_'.$i;
			if (!array_key_exists($varName, $config)) {
				$config[$varName] = $configOrig[$varName];
			}

			if (isset($names[$config[$varName]])) {
				error(_s('Duplicate severity name "%s".', $config[$varName]));
				return false;
			}
			else {
				$names[$config[$varName]] = true;
			}
		}
	}

	$update = [];

	foreach ($config as $key => $value) {
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

	$result = DBexecute('UPDATE config SET '.implode(',', $update));

	if ($result) {
		$msg = [];
		if (array_key_exists('hk_events_trigger', $config)) {
			$msg[] = _s('Trigger event and alert data storage period (in days) "%1$s".', $config['hk_events_trigger']);
		}
		if (array_key_exists('hk_events_internal', $config)) {
			$msg[] = _s('Internal event and alert data storage period (in days) "%1$s".',
				$config['hk_events_internal']
			);
		}
		if (array_key_exists('hk_events_discovery', $config)) {
			$msg[] = _s('Network discovery event and alert data storage period (in days) "%1$s".',
				$config['hk_events_discovery']
			);
		}
		if (array_key_exists('hk_events_autoreg', $config)) {
			$msg[] = _s('Auto-registration event and alert data storage period (in days) "%1$s".',
				$config['hk_events_autoreg']
			);
		}
		if (array_key_exists('hk_services', $config)) {
			$msg[] = _s('IT service data storage period (in days) "%1$s".', $config['hk_services']);
		}
		if (array_key_exists('hk_audit', $config)) {
			$msg[] = _s('Audit data storage period (in days) "%1$s".', $config['hk_audit']);
		}
		if (array_key_exists('hk_sessions', $config)) {
			$msg[] = _s('User session data storage period (in days) "%1$s".', $config['hk_sessions']);
		}
		if (array_key_exists('hk_history', $config)) {
			$msg[] = _s('History data storage period (in days) "%1$s".', $config['hk_history']);
		}
		if (array_key_exists('hk_trends', $config)) {
			$msg[] = _s('Trend data storage period (in days) "%1$s".', $config['hk_trends']);
		}
		if (array_key_exists('work_period', $config)) {
			$msg[] = _s('Working time "%1$s".', $config['work_period']);
		}
		if (array_key_exists('default_theme', $config)) {
			$msg[] = _s('Default theme "%1$s".', $config['default_theme']);
		}
		if (array_key_exists('event_ack_enable', $config)) {
			$msg[] = _s('Event acknowledges "%1$s".', $config['event_ack_enable']);
		}
		if (array_key_exists('event_expire', $config)) {
			$msg[] = _s('Show events not older than (in days) "%1$s".', $config['event_expire']);
		}
		if (array_key_exists('event_show_max', $config)) {
			$msg[] = _s('Show events max "%1$s".', $config['event_show_max']);
		}
		if (array_key_exists('dropdown_first_entry', $config)) {
			$msg[] = _s('Dropdown first entry "%1$s".', $config['dropdown_first_entry']);
		}
		if (array_key_exists('dropdown_first_remember', $config)) {
			$msg[] = _s('Dropdown remember selected "%1$s".', $config['dropdown_first_remember']);
		}
		if (array_key_exists('max_in_table', $config)) {
			$msg[] = _s('Max count of elements to show inside table cell "%1$s".', $config['max_in_table']);
		}
		if (array_key_exists('server_check_interval', $config)) {
			$msg[] = _s('Zabbix server is running check interval "%1$s".', $config['server_check_interval']);
		}
		if (array_key_exists('refresh_unsupported', $config)) {
			$msg[] = _s('Refresh unsupported items (in sec) "%1$s".', $config['refresh_unsupported']);
		}
		if (array_key_exists('discovery_groupid', $config)) {
			$msg[] = _s('Group for discovered hosts "%1$s".', $hostGroups[0]['name']);

			if (bccomp($config['discovery_groupid'], $configOrig['discovery_groupid']) != 0) {
				setHostGroupInternal($configOrig['discovery_groupid'], ZBX_NOT_INTERNAL_GROUP);
				setHostGroupInternal($config['discovery_groupid'], ZBX_INTERNAL_GROUP);
			}
		}
		if (array_key_exists('alert_usrgrpid', $config)) {
			$msg[] = _s('User group for database down message "%1$s".',
				$config['alert_usrgrpid'] != 0 ? $userGroup['name'] : _('None')
			);
		}

		if ($msg) {
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ZABBIX_CONFIG, implode('; ', $msg));
		}
	}

	return $result;
}
