<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
				'application' => 'CApplicationManager',
				'history' => 'CHistoryManager',
				'httptest' => 'CHttpTestManager'
			]);
		}

		return self::$instance;
	}

	/**
	 * @return CApplicationManager
	 */
	public static function Application() {
		return self::getInstance()->getObject('application');
	}

	/**
	 * @return CHistoryManager
	 */
	public static function History() {
		return self::getInstance()->getObject('history');
	}

	/**
	 * @return CHttpTestManager
	 */
	public static function HttpTest() {
		return self::getInstance()->getObject('httptest');
	}
}
