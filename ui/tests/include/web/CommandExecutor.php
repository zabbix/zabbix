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


require_once 'vendor/autoload.php';

use Facebook\WebDriver\Remote\HttpCommandExecutor;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\WebDriverCommand;
use Facebook\WebDriver\Exception\Internal\WebDriverCurlException;
use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\Exception\StaleElementReferenceException;
use Facebook\WebDriver\Exception\UnknownErrorException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\UnexpectedAlertOpenException;

/**
 * Helper class that allows custom command execution.
 */
class CommandExecutor extends HttpCommandExecutor {

	const STRATEGY_DEFAULT = 'default';
	const STRATEGY_ACCEPT_ALERT = 'accept';
	const STRATEGY_DISMISS_ALERT = 'dismiss';

	protected static $alert_strategy = self::STRATEGY_DEFAULT;

	/**
	 * Original executor object.
	 *
	 * @var WebDriverCommandExecutor
	 */
	protected $executor;

	/**
	 * Constructor.
	 *
	 * @param WebDriverCommandExecutor $executor
	 */
	public function __construct($executor) {
		$this->executor = $executor;
	}

	/**
	 * Defines how alerts should be handled during test execution.
	 *
	 * @param string $strategy    available alert strategies: do nothing and remain open, accept or dismiss
	 */
	public static function setAlertStrategy($strategy) {
		self::$alert_strategy = $strategy;
	}

	/**
	 * @inheritdoc
	 */
	public function execute(WebDriverCommand $command) {
		try {
			return $this->executor->execute($command);
		}
		catch (UnexpectedAlertOpenException $exception) {
			switch (self::$alert_strategy) {
				case self::STRATEGY_ACCEPT_ALERT:
					CElementQuery::getPage()->acceptAlert();
					break;

				case self::STRATEGY_DISMISS_ALERT:
					CElementQuery::getPage()->dismissAlert();
					break;

				default:
					CElementQuery::getPage()->dismissAlert();
					throw $exception;
			}
		}
		// Allow single communication timeout during test execution
		catch (WebDriverCurlException $exception) {
			// Code is not missing here
		}
		catch (UnknownErrorException|NoSuchElementException $exception) {
			if (strpos($exception->getMessage(), 'ode with given id') !== false) {
				throw new StaleElementReferenceException($exception->getMessage());
			}
		}
		// Workaround for communication errors present on Jenkins
		catch (WebDriverException $exception) {
			if (strpos($exception->getMessage(), 'START_MAP') === false) {
				throw $exception;
			}
		}

		return $this->executor->execute($command);
	}

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
