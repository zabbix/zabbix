<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
