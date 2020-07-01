<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
		'UPDATE hstgrp'.
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

	$fields = [
		'refresh_unsupported' => [
			'min' => 0,
			'max' => SEC_PER_DAY,
			'allow_zero' => false,
			'message' => _('Invalid refresh of unsupported items: %1$s')
		],
	];

	foreach ($fields as $field => $args) {
		if (array_key_exists($field, $config)
				&& !validateTimeUnit($config[$field], $args['min'], $args['max'], $args['allow_zero'], $error)) {
			error(sprintf($args['message'], $error));

			return false;
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
		if (array_key_exists('refresh_unsupported', $config)) {
			$msg[] = _s('Refresh unsupported items "%1$s".', $config['refresh_unsupported']);
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
