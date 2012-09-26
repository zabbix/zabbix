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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
					' WHERE t.triggerid='.$triggerId
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
				' WHERE '.DBcondition('t.triggerid', $triggerIds)
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
	 * @param array $triggers array of triggers where trigger ids as keys
	 * @param string $triggers[]['expression']
	 * @param string $triggers[]['description']
	 * @param string $triggers[]['triggerid']
	 * @param string $triggers[]['clock'] only if called for CEventDescription
	 * @param string $triggers[]['ns'] only if called for CEventDescription
	 *
	 * @return array triggers with expanded description
	 */
	public function expandDescriptions(array $triggers) {
		$macroValues = array();

		$functions = array();

		foreach ($triggers as $triggerId => $trigger) {
			$triggers[$triggerId]['description'] = $this->expandReferenceMacros($trigger);

			$triggerFunctionsByPos = $this->findFunctions($trigger['expression']);
			foreach ($triggerFunctionsByPos as $funcId) {
				$functions[$funcId] = 1;
			}

			$macroRegex = array(
				'host' => 'HOSTNAME|HOST\.HOST|HOST\.NAME',
				'interface' => 'IPADDRESS|HOST\.IP|HOST\.DNS|HOST\.CONN',
				'item' => 'ITEM\.LASTVALUE|ITEM\.VALUE'
			);

			$functionsByMacros = array();
			foreach ($macroRegex as $type => $regex) {
				preg_match_all('/{('.$regex.')([1-9]?)}/', $trigger['description'], $matches);
				$functionsByMacros[$type] = array();
				foreach ($matches[1] as $key => $macro) {
					$pos = ($matches[2][$key])?$matches[2][$key]:0;
					$functionsByMacros[$type][$macro][$pos] = $triggerFunctionsByPos[$pos];
				}
			}

			$macroValues[$triggerId] = $this->expandUserMacros($trigger);
		}

		$macroValues = $this->expandHostMacros($functionsByMacros['host'], $functions, $macroValues);
		$macroValues = $this->expandHostInterfaceMacros($functionsByMacros['interface'], $functions, $macroValues);
		$macroValues = $this->expandItemMacros($functionsByMacros['item'], $functions, $triggers, $macroValues);

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
	 * @param array $functionsByMacros structure ['HOST.HOST'][2][2345] - function 2345 corresponds to {HOST.HOST2} macro
	 * @param array $functions list of all function ids used in $functionsByMacros
	 * @param array $macroValues
	 *
	 * @return array $macroValues with extra data
	 */
	protected function expandHostMacros(array $functionsByMacros, array $functions, array $macroValues = array()) {
		if (!empty($functionsByMacros)) {
			$dbFuncs = DBselect(
				'SELECT DISTINCT f.triggerid,f.functionid,h.host,h.name'.
					' FROM functions f'.
					' INNER JOIN items i ON f.itemid=i.itemid'.
					' INNER JOIN hosts h ON i.hostid=h.hostid'.
					' WHERE '.DBcondition('f.functionid', array_keys($functions))
			);
			while ($func = DBfetch($dbFuncs)) {
				$functionsById[$func['functionid']] = $func;
			}

			// macro to db field mapping
			$macroToKey = array('HOSTNAME' => 'host', 'HOST.HOST' => 'host', 'HOST.NAME' => 'name');
			foreach ($functionsByMacros as $macro => $funcIdList) {
				foreach ($funcIdList as $pos => $funcId) {
					$macroValues[$functionsById[$funcId]['triggerid']]['{'.$macro.($pos?$pos:'').'}']
								= $functionsById[$funcId][$macroToKey[$macro]];
				}
			}

		}

		return $macroValues;
	}

	/**
	 * Expand interface macros.
	 *
	 * @param array $functionsByMacros structure ['HOST.IP'][2][2345] - function 2345 corresponds to {HOST.IP2} macro
	 * @param array $functions list of all function ids used in $functionsByMacros
	 * @param array $macroValues
	 *
	 * @return array $macroValues with extra data
	 */
	protected function expandHostInterfaceMacros(array $functionsByMacros, array $functions, array $macroValues = array()) {
		if (!empty($functionsByMacros)) {
			// priorities for interfaces doesn't match interface type ids
			$priorities = array(
				INTERFACE_TYPE_AGENT => 4,
				INTERFACE_TYPE_SNMP => 3,
				INTERFACE_TYPE_JMX => 2,
				INTERFACE_TYPE_IPMI => 1
			);
			$dbFuncs = DBselect(
				'SELECT DISTINCT f.triggerid,f.functionid,n.ip,n.dns,n.type,n.useip'.
						' FROM functions f'.
						' INNER JOIN items i ON f.itemid=i.itemid'.
						' INNER JOIN interface n ON i.hostid=n.hostid'.
						' WHERE '.DBcondition('f.functionid', array_keys($functions)).' AND n.main=1'
			);

			// build function map takking priorities into account
			while ($func = DBfetch($dbFuncs)) {
				$priority = isset($functionsById[$func['functionid']]) ? $priorities[$functionsById[$func['functionid']]['type']] : 0;
				if ($priority < $priorities[$func['type']]) {
					$functionsById[$func['functionid']] = $func;
				}
			}
			// macro to db field mapping, if array then check if 0th = true use 2nd else 1st db field
			$macroToKey = array(
				'IPADDRESS' => 'ip', 'HOST.IP' => 'ip', 'HOST.DNS' => 'dns',
				'HOST.CONN' => array('useip', 'dns', 'ip')
			);
			foreach ($functionsByMacros as $macro => $funcIdList) {
				foreach ($funcIdList as $pos => $funcId) {
					if (is_array($macroToKey[$macro])) {
						$key = $functionsById[$funcId][$macroToKey[$macro][0]] ? $macroToKey[$macro][2] : $macroToKey[$macro][1];
					}
					else {
						$key = $macroToKey[$macro];
					}
					$macroValues[$functionsById[$funcId]['triggerid']]['{'.$macro.($pos?$pos:'').'}']
							= $functionsById[$funcId][$key];
				}
			}
		}

		return $macroValues;
	}

	/**
	 * Expand item macros.
	 *
	 * @param array $functionsByMacros structure ['HOST.IP'][2][2345] - function 2345 corresponds to {HOST.IP2} macro
	 * @param array $functions list of all function ids used in $functionsByMacros
	 * @param array $triggers list of triggers, used only in cotext with CEventDescription
	 * @param array $triggers[]['triggerid']
	 * @param array $triggers[]['clock']
	 * @param array $triggers[]['ns']
	 * @param array $macroValues
	 *
	 * @return array $macroValues with extra data
	 */
	protected function expandItemMacros(array $functionsByItemMacros, array $functions, array $triggers, array $macroValues = array()) {
		if (!empty($functionsByItemMacros)) {
			$dbFuncs = DBselect(
				'SELECT DISTINCT f.triggerid,f.functionid,i.itemid,i.lastvalue,i.lastclock,i.value_type,i.units,i.valuemapid,m.newvalue'.
						' FROM functions f'.
						' INNER JOIN items i ON f.itemid=i.itemid'.
						' INNER JOIN hosts h ON i.hostid=h.hostid'.
						' LEFT JOIN mappings m ON i.valuemapid=m.valuemapid AND i.lastvalue=m.value'.
						' WHERE '.DBcondition('f.functionid', array_keys($functions))
			);

			while ($func = DBfetch($dbFuncs)) {
				$functionsById[$func['functionid']] = $func;
			}

			foreach ($functionsByItemMacros as $macro => $funcIdList) {
				foreach ($funcIdList as $pos => $funcId) {
					switch ($macro) {
						case 'ITEM.LASTVALUE':
							$replace = $this->resolveItemLastvalueMacro($functionsById[$funcId]);
							break;
						case 'ITEM.VALUE':
							// trigger data is used for event macros only
							$replace = $this->resolveItemValueMacro($functionsById[$funcId], $triggers[$functionsById[$funcId]['triggerid']]);
							break;
					}
					$macroValues[$functionsById[$funcId]['triggerid']]['{'.$macro.($pos?$pos:'').'}']
								= $replace;
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

						if ($replace) {
							$trigger['description'] = zbx_substr_replace(
								$trigger['description'],
								$replace,
								$macroBegin,
								zbx_strlen($macro)
							);
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
		if (!is_null($item['newvalue'])) {
			$value = $item['newvalue'].' ('.$item['lastvalue'].')';
		}
		else {
			$value = formatItemValue($item, UNRESOLVED_MACRO_STRING);
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
	protected  function isAllowedMacro($macro) {
		return preg_match('/{HOSTNAME|HOST\.HOST|HOST\.NAME|IPADDRESS|HOST\.IP|HOST\.DNS|HOST\.CONN|ITEM\.LASTVALUE|ITEM\.VALUE[1-9]?}/', $macro);
	}
}
