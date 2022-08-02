<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

/**
 * Configuration file helper.
 */
class CConfigHelper {

	/**
	 * Backup stack.
	 *
	 * @var array
	 */
	static $backups = [];

	/**
	 * Saves zabbix.conf.php configuration file in temporary storage.
	 */
	public static function backupConfig() {
		$file_name = dirname(__FILE__).'/../../../conf/zabbix.conf.php';
		$backup_name = PHPUNIT_COMPONENT_DIR.'zabbix.conf.php.'.count(self::$backups);
		self::$backups[] = $backup_name;

		if (copy($file_name, $backup_name) === false) {
			throw new Exception('Cannot perform configuration file backup.');
		}
	}

	/**
	 * Restores zabbix.conf.php configuration file from temporary storage. backupConfig() must be called first.
	 */
	public static function restoreConfig() {
		$file_name = dirname(__FILE__).'/../../../conf/zabbix.conf.php';
		$backup_name = array_pop(self::$backups);

		if (copy($backup_name, $file_name) === false || unlink($backup_name) === false) {
			throw new Exception('Cannot perform configuration file restore.');
		}
	}
}
