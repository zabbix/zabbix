<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


class CTriggerDescription {

	/**
	 * Add one trigger to expand description.
	 * Trigger array must have 'triggerid', 'description' and 'expression' fields.
	 *
	 * @param array $trigger
	 *
	 * @return string
	 */
	public function expand(array $trigger) {
		$triggers = $this->expandDescriptions(array($trigger['triggerid'] => $trigger));
		$trigger = reset($triggers);

		return $trigger['description'];
	}

	/**
	 * Add array of triggers to expand description.
	 * Every trigger must have 'triggerid', 'description' and 'expression' fields.
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	public function batchExpand(array $triggers) {
		return $this->expandDescriptions(zbx_toHash($triggers, 'triggerid'));
	}

	/**
	 * Add trigger by trigger id, required fields for description expanding are queried from DB.
	 *
	 * @param string $triggerId
	 *
	 * @return string
	 */
	public function expandById($triggerId) {
		$trigger = DBfetch(DBselect(
			'SELECT DISTINCT t.description,t.expression,t.triggerid'.
					' FROM triggers t'.
					' WHERE t.triggerid='.zbx_dbstr($triggerId)
		));
		$triggers = $this->expandDescriptions(array($trigger['triggerid'] => $trigger));
		$trigger = reset($triggers);

		return $trigger['description'];
	}

	/**
	 * Add triggers by trigger ids, required fields for description expanding are queried from DB.
	 *
	 * @param array $triggerIds
	 *
	 * @return array
	 */
	public function batchExpandById(array $triggerIds) {
		$dbTriggers = DBselect(
			'SELECT DISTINCT t.description,t.expression,t.triggerid'.
				' FROM triggers t'.
				' WHERE '.dbConditionInt('t.triggerid', $triggerIds)
		);
		$triggers = array();
		while ($trigger = DBfetch($dbTriggers)) {
			$triggers[$trigger['triggerid']] = $trigger;
		}

		return $this->expandDescriptions($triggers);
	}

	/**
	 * Expand macros in trigger descriptions.
	 * Reference macros: $1, $2, $3, ...
	 * User macros: {$MACRO1}, {$MACRO2}, ...
	 * System macros: {HOSTNAME}, {HOST.HOST}, {HOST.NAME}, {IPADDRESS}, {HOST.IP}
	 *     {HOST.DNS}, {HOST.CONN}, {ITEM.LASTVALUE}, {ITEM.VALUE}
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	public function expandDescriptions(array $triggers) {
		$expandHost = array();
		$expandIp = array();
		$expandItem = array();
		$macroValues = array();

		foreach ($triggers as $tid => $trigger) {
			$triggers[$tid]['description'] = $this->expandReferenceMacros($trigger);

			$functions = $this->findFunctions($trigger['expression']);

			foreach($this->findHostMacros($trigger['description']) as $macro => $fNums) {
				foreach ($fNums as $fNum) {
					if (isset($functions[$fNum])) {
						$expandHost[$functions[$fNum]][$macro][] = $fNum;
					}
				}
			}

			foreach($this->findIpMacros($trigger['description']) as $macro => $fNums) {
				foreach ($fNums as $fNum) {
					if (isset($functions[$fNum])) {
						$expandIp[$functions[$fNum]][$macro][] = $fNum;
					}
				}
			}

			foreach($this->findItemMacros($trigger['description']) as $macro => $fNums) {
				foreach ($fNums as $fNum) {
					if (isset($functions[$fNum])) {
						$expandItem[$functions[$fNum]][$macro][] = $fNum;
					}
				}
			}

			$macroValues[$trigger['triggerid']] = $this->expandUserMacros($trigger);
		}

		$macroValues = $this->expandHostMacros($expandHost, $macroValues);
		$macroValues = $this->expandIpMacros($expandIp, $macroValues);
		$macroValues = $this->expandItemMacros($expandItem, $triggers, $macroValues);

		foreach ($triggers as $triggerId => $trigger) {
			$triggers[$triggerId] = $this->replaceMacroValues($trigger, $macroValues[$triggerId]);
		}

		return $triggers;
	}

	/**
	 * Expand reference macros for trigger.
	 * If macro reference non existing value it expands to empty string.
	 *
	 * @param array $trigger
	 *
	 * @return string
	 */
	public function expandReferenceMacros(array $trigger) {
		$expression = $trigger['expression'];
		$description = $trigger['description'];

		// search for reference macros $1, $2, $3, ...
		preg_match_all('/\$([1-9])/', $description, $refNumbers);
		$refNumbers = $refNumbers[1];

		// replace functionids with string 'function' to make values search easier
		$expression = preg_replace('/\{[0-9]+\}/', 'function', $expression);

		// search for numeric values in expression
		preg_match_all('/'.ZBX_PREG_NUMBER.'/', $expression, $values);

		$search = array();
		$replace = array();
		foreach ($refNumbers as $i) {
			$search[] = '$'.$i;
			$replace[] = isset($values[0][$i - 1]) ? $values[0][$i - 1] : '';
		}

		return str_replace($search, $replace, $description);
	}

	/**
	 * Find user macros and store found ones with value to replace to $macroValues property.
	 *
	 * @param array $trigger
	 *
	 * @return mixed
	 */
	protected function expandUserMacros(array $trigger) {
		$macros = array();

		if (preg_match_all('/'.ZBX_PREG_EXPRESSION_USER_MACROS.'/', $trigger['description'], $matches)) {
			$macros = API::UserMacro()->getMacros(array(
				'macros' => $matches[1],
				'triggerid' => $trigger['triggerid']
			));
		}

		return $macros;
	}

	/**
	 * Find function ids in trigger expression.
	 *
	 * @param string $expression
	 *
	 * @return array where key is function id position in expression and value is function id
	 */
	protected function findFunctions($expression) {
		preg_match_all('/\{([0-9]+)\}/', $expression, $matches);
		$functions = array();
		foreach ($matches[1] as $i => $functionid) {
			$functions[$i + 1] = $functionid;
		}

		// macro without number is same as 1. but we need to distinguish them, so it's treated as 0
		if (isset($functions[1])) {
			$functions[0] = $functions[1];
		}

		return $functions;
	}

	/**
	 * Find host data related macros in trigger description.
	 *
	 * @param string $description
	 *
	 * @return array
	 */
	protected function findHostMacros($description) {
		return $this->findMacrosInDescription('HOSTNAME|HOST\.HOST|HOST\.NAME', $description);
	}

	/**
	 * Find interface data related macros in trigger description.
	 *
	 * @param string $description
	 *
	 * @return array
	 */
	protected function findIpMacros($description) {
		return $this->findMacrosInDescription('IPADDRESS|HOST\.IP|HOST\.DNS|HOST\.CONN', $description);
	}

	/**
	 * Find item data related macros in trigger description.
	 *
	 * @param string $description
	 *
	 * @return array
	 */
	protected function findItemMacros($description) {
		return $this->findMacrosInDescription('ITEM\.LASTVALUE|ITEM\.VALUE', $description);
	}

	/**
	 * Find macros in trigger description.
	 *
	 * @param string $macrosPattern string with macros pattern for reg. expression
	 * @param string $description
	 *
	 * @return array where key is found macro and value is array with related function position
	 */
	protected function findMacrosInDescription($macrosPattern, $description) {
		$result = array();

		preg_match_all('/{('.$macrosPattern.')([1-9]?)}/', $description, $matches);
		foreach ($matches[1] as $num => $foundMacro) {
			$fNum = $matches[2][$num] ? $matches[2][$num] : 0;
			$result[$foundMacro][$fNum] = $fNum;
		}

		return $result;
	}

	/**
	 * Expand host macros.
	 *
	 * @param array $expandHost
	 * @param array $macroValues
	 *
	 * @return bool
	 */
	protected function expandHostMacros(array $expandHost, array $macroValues = array()) {
		if (!empty($expandHost)) {
			$dbFuncs = DBselect(
				'SELECT DISTINCT f.triggerid,f.functionid,h.host,h.name'.
					' FROM functions f'.
					' INNER JOIN items i ON f.itemid=i.itemid'.
					' INNER JOIN hosts h ON i.hostid=h.hostid'.
					' WHERE '.dbConditionInt('f.functionid', array_keys($expandHost))
			);
			while ($func = DBfetch($dbFuncs)) {
				foreach ($expandHost[$func['functionid']] as $macro => $fNums) {
					switch ($macro) {
						case 'HOSTNAME':
							/* fall through */
						case 'HOST.HOST':
							$replace = $func['host'];
							break;
						case 'HOST.NAME':
							$replace = $func['name'];
							break;
					}

					$macroValues = $this->prepareMacroValues($macroValues, $fNums, $func['triggerid'], $macro, $replace);
				}
			}
		}

		return $macroValues;
	}

	/**
	 * Expand interface macros.
	 *
	 * @param array $expandIp
	 * @param array $macroValues
	 *
	 * @return bool
	 */
	protected function expandIpMacros(array $expandIp, array $macroValues = array()) {
		if (!empty($expandIp)) {
			$priorities = array(
				INTERFACE_TYPE_AGENT => 4,
				INTERFACE_TYPE_SNMP => 3,
				INTERFACE_TYPE_JMX => 2,
				INTERFACE_TYPE_IPMI => 1
			);
			$dbInterfaces = DBselect(
				'SELECT DISTINCT f.triggerid,f.functionid,n.ip,n.dns,n.type,n.useip'.
						' FROM functions f'.
						' INNER JOIN items i ON f.itemid=i.itemid'.
						' INNER JOIN interface n ON i.hostid=n.hostid'.
						' WHERE '.dbConditionInt('f.functionid', array_keys($expandIp)).
							' AND n.main=1'
			);
			// macro should be resolved to interface with highest priority ($priorities)
			$interfaces = array();
			while ($dbInterface = DBfetch($dbInterfaces)) {
				if (isset($interfaces[$dbInterface['functionid']])
						&& $priorities[$interfaces[$dbInterface['functionid']]['type']] > $priorities[$dbInterface['type']]) {
					continue;
				}
				$interfaces[$dbInterface['functionid']] = $dbInterface;
			}

			foreach ($interfaces as $interface) {
				foreach ($expandIp[$interface['functionid']] as $macro => $fNums) {
					switch ($macro) {
						case 'IPADDRESS':
							/* fall through */
						case 'HOST.IP':
							$replace = $interface['ip'];
							break;
						case 'HOST.DNS':
							$replace = $interface['dns'];
							break;
						case 'HOST.CONN':
							$replace = $interface['useip'] ? $interface['ip'] : $interface['dns'];
							break;
					}

					$macroValues = $this->prepareMacroValues($macroValues, $fNums, $interface['triggerid'], $macro, $replace);
				}
			}
		}

		return $macroValues;
	}

	/**
	 * Expand item macros.
	 *
	 * @param array $expandItem
	 * @param array $triggers
	 * @param array $macroValues
	 *
	 * @return bool
	 */
	protected function expandItemMacros(array $expandItem, array $triggers, array $macroValues = array()) {
		if (!empty($expandItem)) {
			$dbFuncs = DBselect(
				'SELECT DISTINCT f.triggerid,f.functionid,i.itemid,i.lastvalue,i.lastclock,i.value_type,i.units,i.valuemapid,m.mappingid,m.newvalue'.
						' FROM functions f'.
						' INNER JOIN items i ON f.itemid=i.itemid'.
						' INNER JOIN hosts h ON i.hostid=h.hostid'.
						' LEFT JOIN mappings m ON i.valuemapid=m.valuemapid AND i.lastvalue=m.value'.
						' WHERE '.dbConditionInt('f.functionid', array_keys($expandItem))
			);
			// false passed to DBfetch to get data without null converted to 0, which is done by default
			while ($func = DBfetch($dbFuncs, false)) {
				foreach ($expandItem[$func['functionid']] as $macro => $fNums) {
					switch ($macro) {
						case 'ITEM.LASTVALUE':
							$replace = $this->resolveItemLastvalueMacro($func);
							break;
						case 'ITEM.VALUE':
							$replace = $this->resolveItemValueMacro($func, $triggers[$func['triggerid']]);
							break;
					}

					$macroValues = $this->prepareMacroValues($macroValues, $fNums, $func['triggerid'], $macro, $replace);
				}
			}
		}

		return $macroValues;
	}

	/**
	 * Replace macros in trigger description by values.
	 * All macros are resolved in one go.
	 *
	 * @param array $trigger
	 * @param array $macroValues
	 *
	 * @return array
	 */
	protected function replaceMacroValues(array $trigger, array $macroValues) {
		$macroBegin = false;
		for ($i = 0; $i < zbx_strlen($trigger['description']); $i++) {
			$c = zbx_substr($trigger['description'], $i, 1);

			switch ($c) {
				case '{':
					$macroBegin = $i;
					break;
				case '}':
					if ($macroBegin !== false) {
						$macro = zbx_substr($trigger['description'], $macroBegin, $i - $macroBegin + 1);
						if (isset($macroValues[$macro])) {
							$replace = $macroValues[$macro];
						}
						elseif ($this->isAllowedMacro($macro)) {
							$replace = UNRESOLVED_MACRO_STRING;
						}
						else {
							$replace = false;
						}

						if ($replace !== false) {
							$trigger['description'] = zbx_substr_replace(
								$trigger['description'],
								$replace,
								$macroBegin,
								zbx_strlen($macro)
							);
							// - 1 because for loop adds 1 on next iteration
							$i = $macroBegin + zbx_strlen($replace) - 1;
							$macroBegin = false;
						}
					}
					break;
			}
		}

		return $trigger;
	}

	/**
	 * Resolve {ITEM.LASTVALUE} macro.
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	protected function resolveItemLastvalueMacro(array $item) {
		if (is_null($item['mappingid'])) {
			$value = formatItemValue($item, UNRESOLVED_MACRO_STRING);
		}
		else {
			$value = $item['newvalue'].' ('.$item['lastvalue'].')';
		}

		return $value;
	}

	/**
	 * Resolve {ITEM.VALUE} macro.
	 * For triggers macro is resolved in same way as {ITEM.LASTVALUE} macro. Separate methods are created for event description,
	 * where {ITEM.VALUE} macro resolves in different way.
	 *
	 * @see CEventDescription
	 *
	 * @param array $item
	 * @param array $trigger
	 *
	 * @return string
	 */
	protected function resolveItemValueMacro(array $item, array $trigger) {
		return $this->resolveItemLastvalueMacro($item);
	}

	/**
	 * Check if the string is a macro supported in trigger description.
	 *
	 * @param string $macro
	 *
	 * @return bool
	 */
	protected function isAllowedMacro($macro) {
		return preg_match('/{HOSTNAME|HOST\.HOST|HOST\.NAME|IPADDRESS|HOST\.IP|HOST\.DNS|HOST\.CONN|ITEM\.LASTVALUE|ITEM\.VALUE[1-9]?}/', $macro);
	}

	/**
	 * Add macro name with corresponding value to replace to $macroValues array.
	 *
	 * @param array $macroValues
	 * @param array $fNums
	 * @param       $triggerId
	 * @param       $macro
	 * @param       $replace
	 *
	 * @return array
	 */
	protected function prepareMacroValues(array $macroValues, array $fNums, $triggerId, $macro, $replace) {
		foreach ($fNums as $fNum) {
			if ($fNum == 0 || $fNum == 1) {
				$macroValues[$triggerId]['{'.$macro.'}'] = $replace;
				$macroValues[$triggerId]['{'.$macro.'1}'] = $replace;
			}
			else {
				$macroValues[$triggerId]['{'.$macro.$fNum.'}'] = $replace;
			}
		}

		return $macroValues;
	}
}
