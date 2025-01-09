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


class CHostNormalValidator extends CValidator {

	/**
	 * Error message
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Checks is any of the given hosts are discovered.
	 *
	 * @param $hostIds
	 *
	 * @return bool
	 */
	public function validate($hostIds) {
		$hosts = API::Host()->get([
			'output' => ['host'],
			'hostids' => $hostIds,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_CREATED],
			'limit' => 1
		]);

		if ($hosts) {
			$host = reset($hosts);
			$this->error($this->message, $host['host']);
			return false;
		}

		return true;
	}
}
