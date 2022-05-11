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

require_once 'vendor/autoload.php';

use Facebook\WebDriver\Remote\HttpCommandExecutor;
use Facebook\WebDriver\Remote\RemoteWebDriver;

/**
 * Helper class that allows custom command execution.
 */
class CommandExecutor extends HttpCommandExecutor {

	/**
	 * Execute custom command for WebDriver.
	 *
	 * @param RemoteWebDriver $driver    WebDriver instance
	 * @param array           $params    command parameters
	 *
	 * @return mixed
	 */
	public static function executeCustom(RemoteWebDriver $driver, array $params = []) {
		foreach (['commands', 'w3cCompliantCommands'] as $field) {
			if (!isset(HttpCommandExecutor::$$field['custom'])) {
				HttpCommandExecutor::$$field['custom'] = [
					'method' => 'POST',
					'url' => '/session/:sessionId/chromium/send_command_and_get_result'
				];
			}
		}

		return $driver->execute('custom', $params);
	}
}
