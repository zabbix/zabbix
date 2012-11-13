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
			'SELECT i.hostid'.
			' FROM graphs_items gi,items i'.
			' WHERE gi.itemid=i.itemid AND gi.graphid='.$graphid.
			' ORDER BY gi.sortorder')
		);

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
		preg_match_all('/{('.ZBX_PREG_HOST_FORMAT.'|({(HOST.HOST|HOSTNAME)[1-9]?})):'.ZBX_PREG_ITEM_KEY_FORMAT.'\.(last|max|min|avg)\(([0-9]+[smhdw]?)\)}/Uu', $str, $matches);

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
					$value = formatItemLastValue($item, UNRESOLVED_MACRO_STRING);
				}
				// macro function is "max", "min" or "avg"
				else {
					$value = getItemFunctionalValue($item, $matches['functions'][$i], $matches['parameters'][$i]);
				}
			}
			// there is no item with given key in given host, or there is no permissions to that item
			else {
				$value = UNRESOLVED_MACRO_STRING;
			}

			$str = str_replace_first($macro, $value, $str);
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
		preg_match_all('/{((HOST.HOST|HOSTNAME)([1-9]?))\}/', $str, $matches);

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
					$macroList[$matches['macroType'][$i]][$position] = ($host) ? $host[0]['host'] : UNRESOLVED_MACRO_STRING;
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
