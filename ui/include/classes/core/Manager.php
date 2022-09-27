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
 * A class for creating a storing instances of DB objects managers.
 */
class Manager extends CRegistryFactory {

	/**
	 * An instance of the manager factory.
	 *
	 * @var Manager
	 */
	protected static $instance;

	/**
	 * Returns an instance of the manager factory object.
	 *
	 * @return Manager
	 */
	public static function getInstance() {
		if (!self::$instance) {
			$class = __CLASS__;
			self::$instance = new $class([
				'history' => 'CHistoryManager',
				'httptest' => 'CHttpTestManager'
			]);
		}

		return self::$instance;
	}

	/**
	 * @return CHistoryManager
	 */
	public static function History() {
		static $instance;

		if ($instance === null) {
			$instance = self::getInstance()->getObject('history');

			$dbversion_status = CSettingsHelper::getGlobal(CSettingsHelper::DBVERSION_STATUS);

			if ($dbversion_status !== '') {
				$dbversion_status = json_decode($dbversion_status, true);
				if ($dbversion_status !== null && array_key_exists('history_pk', $dbversion_status)
						&& $dbversion_status['history_pk'] == 1) {
					$instance->setPrimaryKeysEnabled();
				}
			}
		}

		return $instance;
	}

	/**
	 * @return CHttpTestManager
	 */
	public static function HttpTest() {
		return self::getInstance()->getObject('httptest');
	}
}
