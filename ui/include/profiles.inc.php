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
	$update = [];

	foreach ($config as $key => $value) {
		if (!is_null($value)) {
			$update[] = $key.'='.zbx_dbstr($value);
		}
	}

	if (count($update) == 0) {
		error(_('Nothing to do.'));
		return null;
	}

	$result = DBexecute('UPDATE config SET '.implode(',', $update));

	return $result;
}
