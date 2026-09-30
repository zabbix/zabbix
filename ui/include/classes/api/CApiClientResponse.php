<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * This class is used by the API client to return the results of an API call.
 */
class CApiClientResponse {

	/**
	 * Data returned by the service method.
	 *
	 * @var mixed
	 */
	public $data;

	/**
	 * Error code.
	 *
	 * @var	int
	 */
	public $errorCode;

	/**
	 * Error message.
	 *
	 * @var	string
	 */
	public $errorMessage;

	/**
	 * Debug information.
	 *
	 * @var	array
	 */
	public $debug;

	public function setErrorByException(Exception $e, bool $is_debug = false): void {
		if ($e instanceof APIException) {
			$this->errorCode = $e->getCode();
		}
		elseif ($e instanceof DBException) {
			$this->errorCode = ZBX_API_ERROR_DB;
		}
		else {
			$this->errorCode = ZBX_API_ERROR_INTERNAL;
		}

		$this->errorMessage = $e->getMessage();

		if ($is_debug) {
			$this->debug = $e->getTrace();

			if ($e instanceof APIException) {
				$this->errorMessage = $e->getDebugMessage();
			}
		}
	}
}
