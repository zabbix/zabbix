<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


class CGraphMacroResolver {

	private static $instance;

	public static function i() {
		if (!self::$instance) {
			self::$instance = new CGraphMacroResolver();
		}
		return self::$instance;
	}


	/**
	 * Resolve positional macros and functional item macros, for example, {{HOST.HOST1}:key.func(param)}.
	 *
	 * @param type $str string in which macros should be resolved
	 * @param array $items list of graph items
	 * @param array $items[n]['hostid'] graph n-th item corresponding host Id
	 *
	 * @return string string with macros replaces with corresponding values
	 */
	public function resolve($str, $items) {

		$str = $this->resolveFunctionalItemMacros($str, $items);

		// if at some point we want to resolve positional macros in graph names
		// $str = $this->resolvePositionalMacros($str, $items);

		return $str;
	}

	/**
	 * Resolve positional macros and functional item macros, for example, {{HOST.HOST1}:key.func(param)}.
	 *
	 * @param type $str string in which macros should be resolved
	 * @param int $graphid graph id for which macro should be resolved
	 *
	 * @return string string with macros replaces with corresponding values
	 */
	public function resolveById($str, $graphid) {
		$items = DBfetchArray(DBselect(
			'SELECT hostid FROM graphs_items gi, items i
			WHERE gi.itemid = i.itemid AND gi.graphid = '.zbx_dbstr($graphid)
			.' ORDER BY gi.sortorder ASC'
		));

		return $this->resolve($str, $items);
	}

	/**
	 * Resolve functional macros, like {hostname:key.function(param)}.
	 * If macro can not be resolved it is replaced with UNRESOLVED_MACRO_STRING string i.e. "*UNKNOWN*"
	 * Supports function "last", "min", "max" and "avg".
	 * Supports seconds as parameters, except "last" function.
	 * Supports postfixes s,m,h,d and w for paramter.
	 *
	 * @param string $str string in which macros should be resolved
	 * @param array $items list of graph items
	 * @param array $items[n]['hostid'] graph n-th item corresponding host Id
	 *
	 * @return string string with macros replaces with corresponding values
	 */
	private function resolveFunctionalItemMacros($str, $items) {
		// extract all macros into $matches
		// searches for macros, for example, "{somehost:somekey["param[123]"].min(10m)}"
		preg_match_all('/{('.ZBX_PREG_HOST_FORMAT.'|({(HOST.HOST|HOSTNAME)[0-9]?})):'.ZBX_PREG_ITEM_KEY_FORMAT.'\.(last|max|min|avg)\(([0-9]+[smhdw]?)\)}/Uu', $str, $matches);

		// match found groups if ever regexp should change
		$matches['macros'] = $matches[0];
		$matches['hosts'] = $matches[1];
		$matches['keys'] = $matches[5];
		$matches['functions'] = $matches[7];
		$matches['parameters'] = $matches[8];

		// resolve positional macros in host part
		foreach ($matches['hosts'] as $i => $host) {
			$matches['hosts'][$i] = $this->resolvePositionalMacros($host, $items);
		}

		// build list of macros: $macroList['{hostname:key.function(param)}'] = 'value';
		$macroList = array();
		foreach ($matches['macros'] as $i => $macro) {
			// get item with key within host
			$item = API::Item()->get(array(
				'host' => $matches['hosts'][$i],
				'filter' => array(
					'key_' => $matches['keys'][$i]
				),
				'output' => array('lastclock', 'lastvalue', 'value_type', 'units', 'valuemapid')
			));

			// item exists and has permissions
			if ($item = reset($item)) {
				// macro function is "last"
				if ($matches['functions'][$i] == 'last') {
					// if no data gathered for item
					if ($item['lastclock'] == 0) {
						$macroList[$macro] = UNRESOLVED_MACRO_STRING;
						continue;
					}
					// process item last value, add units and value map
					else {
						$macroList[$macro] = ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64)
							? convert_units($item['lastvalue'], $item['units']) : $item['lastvalue'];
						if ($item['valuemapid']) {
							$macroList[$macro] = applyValueMap($macroList[$macro], $item['valuemapid']);
						}
					}
				}
				// macro function is "max", "min" or "avg"
				else {
					// allowed item types for min, max and avg function
					$historyTables = array(ITEM_VALUE_TYPE_FLOAT => 'history', ITEM_VALUE_TYPE_UINT64 => 'history_uint');
					if (!isset($historyTables[$item['value_type']])) {
						$macroList[$macro] = UNRESOLVED_MACRO_STRING;
						continue;
					}

					// search for item function data in DB corresponding history table
					$result = DBselect(
						'SELECT '.$matches['functions'][$i].'(value) AS value'.
						' FROM '.$historyTables[$item['value_type']].
						' WHERE clock>'.(time() - convertFunctionValue($matches['parameters'][$i])).
						' AND itemid='.$item['itemid']
						.' HAVING COUNT(*) > 0' // necessary because DBselect() return 0 if empty data set, for graph templates
					);
					if ($row = DBfetch($result)) {
						$macroList[$macro] = convert_units($row['value'], $item['units']);
					}
					// no data in history
					else {
						$macroList[$macro] = UNRESOLVED_MACRO_STRING;
						continue;
					}

				}
			}
			// there is no item with given key in given host, or there is no permissions to that item
			else {
				$macroList[$macro] = UNRESOLVED_MACRO_STRING;
			}
		}

		// replace macros with values in $str
		foreach ($macroList as $macro => $value) {
			$str = str_replace($macro, $value, $str);
		}

		return $str;
	}

	/**
	 * Resolve positional macros, like {HOST.HOST2}.
	 * If macro can not be resolved it is replaced with UNRESOLVED_MACRO_STRING string i.e. "*UNKNOWN*"
	 * Supports HOST.HOST<1..9> macros.
	 *
	 * @param string $str string in which macros should be resolved
	 * @param array $items list of graph items
	 * @param array $items[n]['hostid'] graph n-th item corresponding host Id
	 *
	 * @return string string with macros replaces with corresponding values
	 */
	private function resolvePositionalMacros($str, $items) {
		// extract all macros into $matches
		// possible to add other macros "'/\{((HOST.HOST|SOME.OTHER.MACRO)([0-9]?))\}/'"
		preg_match_all('/{((HOST.HOST|HOSTNAME)([0-9]?))\}/', $str, $matches);

		// match found groups if ever regexp should change
		$matches['macroType'] = $matches[2];
		$matches['position'] = $matches[3];


		// build structure of macros: $macroList['HOST.HOST'][2] = 'host name';
		$macroList = array();
		// $matches[3] contains positions, e.g., '',1,2,2,3,...
		foreach ($matches['position'] as $i => $position) {

			// take care of macro without positional index
			$posInItemList = ($position === '') ? 0 : $posInItemList = $position - 1;

			// init array
			if (!isset($macroList[$matches['macroType'][$i]])) {
				$macroList[$matches['macroType'][$i]] = array();
			}

			// skip computing for duplicate macros
			if (isset($macroList[$matches['macroType'][$i]][$position])) {
				continue;
			}

			// positional index larger than item count, resolve to UNKNOWN
			if (!isset($items[$posInItemList])) {
				$macroList[$matches['macroType'][$i]][$position] = UNRESOLVED_MACRO_STRING;
				continue;
			}

			// retrieve macro replacement data
			switch ($matches['macroType'][$i]) {
				case 'HOSTNAME':
				case 'HOST.HOST':
					$host = API::Host()->get(array(
						'hostids' => $items[$posInItemList]['hostid'],
						'output' => array('host')
					));
					$template = API::Template()->get(array(
						'templateids' => $items[$posInItemList]['hostid'],
						'output' => array('host')
					));
					$ht = $host + $template;
					$macroList[$matches['macroType'][$i]][$position] = $ht[0]['host'];
					break;
			}
		}

		// replace macros with values in $str
		foreach ($macroList as $macroType => $positions) {
			foreach ($positions as $position => $replacement) {
				$str = str_replace('{'.$macroType.$position.'}', $replacement, $str);
			}
		}

		return $str;
	}

}
