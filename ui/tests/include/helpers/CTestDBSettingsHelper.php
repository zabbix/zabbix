<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once __DIR__.'/../../../include/classes/api/helpers/CApiSettingsHelper.php';
require_once __DIR__.'/../../../include/classes/data/CSettingsSchema.php';

/**
 * Class for getting parameters from the DB settings table
 */
class CTestDBSettingsHelper extends CApiSettingsHelper {

	/**
	 * Return value for a single setting parameter.
	 *
	 * @param string $parameter_name    name of setting table parameter
	 *
	 * @return mixed
	 */
	public static function getParameterValue($parameter_name) {
		return current(self::getParameters([$parameter_name]));
	}
}
