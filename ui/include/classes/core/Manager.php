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

			foreach (CSettingsHelper::getDbVersionStatus() as $dbversion) {
				if (array_key_exists('history_pk', $dbversion)) {
					if ($dbversion['history_pk'] == 1) {
						$instance->setPrimaryKeysEnabled();
					}

					break;
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
