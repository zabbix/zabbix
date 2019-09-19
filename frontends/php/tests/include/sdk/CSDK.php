<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * Interface to construct API request objects. Provides basic validation for developer safety.
 * Capable to rollback all performed requests.
 */
class CSDK {

	/**
	 * @var []CRequest
	 */
	public $call_stack = [];

	/**
	 * @param array $params
	 *
	 * @return CRequest
	 */
	public function hostgroupCreate(array $params) {
		return $this->createRequest('hostgroup.create', $params);
	}

	/**
	 * @param array $params
	 *
	 * @return CRequest
	 */
	public function templateCreate(array $params) {
		return $this->createRequest('template.create', $params);
	}

	/**
	 * @param array $params
	 *
	 * @return CRequest
	 */
	public function configurationImport(array $params) {
		return $this->createRequest('configuration.import', $params);
	}

	/**
	 * @param array $params
	 *
	 * @return CRequest
	 */
	public function configurationExport(array $params) {
		return $this->createRequest('configuration.export', $params);
	}

	/**
	 * @param CClient
	 */
	public function undo(CClient &$client) {
		foreach($this->call_stack as $request) {
			$request->undo($client);
		}
	}

	/**
	 * @param array $params
	 * @param string $method
	 *
	 * @throws Exception
	 *
	 * @return CRequest
	 */
	protected function createRequest(string $method, array $params) {
		switch ($method) {
			case 'template.create':
				$required = ['groups'];
				break;
			case 'hostgroup.create':
				$required = ['name'];
				break;
			case 'configuration.import':
				$required = ['format', 'source', 'rules'];
				break;
			case 'configuration.export':
				$required = ['format', 'options'];
				break;
			default:
				throw new Exception($method.' is not implemented.');
		}

		if (count(array_intersect(array_keys($params), $required)) < count($required)) {
			throw new Exception($method.' required fields are: '.implode(',', $required));
		}

		$request = new CRequest($method, $params);
		$this->call_stack[] =& $request;

		return $request;
	}
}
