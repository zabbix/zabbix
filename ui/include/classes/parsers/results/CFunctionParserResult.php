<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Class to store function parser results.
 */
class CFunctionParserResult extends CParserResult {

	public $length;
	public $match;
	public $function;
	public $parameters;
	public $params_raw;

	public function __construct() {
		$this->match = '';
		$this->length = 0;
		$this->function = '';
		$this->parameters = '';
		$this->params_raw = [];
	}

	/**
	 * Return list hosts found in parsed trigger function.
	 *
	 * @return array
	 */
	public function getHosts(): array {
		$hosts = [];
		foreach ($this->params_raw['parameters'] as $param) {
			if ($param instanceof CFunctionParserResult) {
				$hosts = array_merge($hosts, $param->getHosts());
			}
			elseif ($param instanceof CQueryParserResult) {
				$hosts[] = $param->host;
			}
		}

		return array_keys(array_flip($hosts));
	}

	/**
	 * Return array containing items found in parsed trigger expression function grouped by host.
	 *
	 * Example:
	 * [
	 *   'host1' => [
	 *     'item1' => 'item1',
	 *     'item2' => 'item2'
	 *   ]
	 * ],
	 * [
	 *   'host2' => [
	 *     'item3' => 'item3',
	 *   ]
	 * ]
	 *
	 * @return array
	 */
	public function getItemsGroupedByHosts(): array {
		$params_stack = $this->params_raw['parameters'];
		$hosts = [];
		while ($params_stack) {
			$param = array_shift($params_stack);

			if ($param instanceof CQueryParserResult) {
				if (!array_key_exists($param->host, $hosts)) {
					$hosts[$param->host] = [];
				}
				$hosts[$param->host][$param->item] = $param->item;
			}
			elseif ($param instanceof CFunctionParserResult) {
				$params_stack = array_merge($params_stack, $param->params_raw['parameters']);
			}
		}

		return $hosts;
	}

	/**
	 * Return function /host/key query result object.
	 *
	 * @return CQueryParserResult|null
	 */
	public function getFunctionTriggerQuery(): ?CQueryParserResult {
		return ($this->params_raw['parameters'][0] instanceof CQueryParserResult)
			? $this->params_raw['parameters'][0]
			: null;
	}
}
