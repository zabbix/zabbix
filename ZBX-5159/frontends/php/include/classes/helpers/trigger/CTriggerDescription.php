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
	 * @var array
	 */
	protected $triggers;

	/**
	 * Determines what is returned by expand() method.
	 * If true: expanded description string from first trigger is returned.
	 * If false: all triggers with expanded description are returned.
	 *
	 * @var bool
	 */
	protected $soloTrigger;

	/**
	 * Values to replace for macros.
	 *
	 * @var array
	 */
	protected $macroValues = array();

	/**
	 * Add one trigger to expand description.
	 * Trigger array must have 'triggerid', 'description' and 'expression' fields.
	 *
	 * @param array $trigger
	 */
	public function addTrigger(array $trigger) {
		$this->soloTrigger = true;

		$this->add(array($trigger));
	}

	/**
	 * Add array of triggers to expand description.
	 * Every trigger must have 'triggerid', 'description' and 'expression' fields.
	 *
	 * @param array $triggers
	 */
	public function addTriggers(array $triggers) {
		$this->soloTrigger = false;
		$this->add($triggers);
	}

	/**
	 * Add trigger by trigger id, required fields for description expanding are queried from DB.
	 *
	 * @param string $triggerId
	 */
	public function addTriggerById($triggerId) {
		$this->soloTrigger = true;

		$trigger = DBfetch(DBselect(
			'SELECT DISTINCT t.description,t.expression,t.triggerid'.
					' FROM triggers t'.
					' WHERE t.triggerid='.$triggerId
		));
		$this->add(array($trigger));
	}

	/**
	 * Add triggers by trigger ids, required fields for description expanding are queried from DB.
	 *
	 * @param string $triggerIds
	 */
	public function addTriggersById(array $triggerIds) {
		$this->soloTrigger = false;

		$dbTriggers = DBselect(
			'SELECT DISTINCT t.description,t.expression,t.triggerid'.
				' FROM triggers t'.
				' WHERE '.DBcondition('t.triggerid', $triggerIds)
		);
		$triggers = array();
		while ($trigger = DBfetch($dbTriggers)) {
			$triggers[] = $trigger;
		}
		$this->add($triggers);
	}

	/**
	 * @param array $triggers
	 */
	protected function add(array $triggers) {
		$this->triggers = zbx_toHash($triggers, 'triggerid');
	}

	/**
	 * Expand macros in trigger descriptions.
	 * Reference macros: $1, $2, $3, ...
	 * User macros: {$MACRO1}, {$MACRO2}, ...
	 * System macros: {HOSTNAME}, {HOST.HOST}, {HOST.NAME}, {IPADDRESS}, {HOST.IP}
	 *     {HOST.DNS}, {HOST.CONN}, {ITEM.LASTVALUE}, {ITEM.VALUE}
	 *
	 * @return array|string depends on $soloTrigger property
	 */
	public function expand() {
		$expandHost = array();
		$expandIp = array();
		$expandItem = array();

		foreach ($this->triggers as $tid => $trigger) {
			$this->triggers[$tid]['description'] = $this->expandReferenceMacros($trigger);

			$functions = $this->findFunctions($trigger['expression']);

			foreach($this->findHostMacros($trigger['description']) as $macro => $fNums) {
				foreach ($fNums as $fNum) {
					if (isset($functions[$fNum])) {
						$expandHost[$functions[$fNum]][$macro] = $fNum;
					}
				}
			}

			foreach($this->findIpMacros($trigger['description']) as $macro => $fNums) {
				foreach ($fNums as $fNum) {
					if (isset($functions[$fNum])) {
						$expandIp[$functions[$fNum]][$macro] = $fNum;
					}
				}
			}

			foreach($this->findItemMacros($trigger['description']) as $macro => $fNums) {
				foreach ($fNums as $fNum) {
					if (isset($functions[$fNum])) {
						$expandItem[$functions[$fNum]][$macro] = $fNum;
					}
				}
			}

			$this->expandUserMacros($trigger);
		}

		$this->expandHostMacros($expandHost);
		$this->expandIpMacros($expandIp);
		$this->expandItemMacros($expandItem);

		$this->replaceMacroValues();

		if ($this->soloTrigger) {
			$trigger = reset($this->triggers);
			return $trigger['description'];
		}
		else {
			return $this->triggers;
		}
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
		if (preg_match_all('/'.ZBX_PREG_EXPRESSION_USER_MACROS.'/', $trigger['description'], $matches)) {
			$macros = API::UserMacro()->getMacros(array(
				'macros' => $matches[1],
				'triggerid' => $trigger['triggerid']
			));

			foreach ($macros as $macro => $value) {
				$this->macroValues[$trigger['triggerid']][$macro] = $value;
			}
		}

		return true;
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
	 *
	 * @return bool
	 */
	protected function expandHostMacros(array $expandHost) {
		if (!empty($expandHost)) {
			$dbFuncs = DBselect(
				'SELECT DISTINCT f.triggerid,f.functionid,h.host,h.name'.
					' FROM functions f'.
					' INNER JOIN items i ON f.itemid=i.itemid'.
					' INNER JOIN hosts h ON i.hostid=h.hostid'.
					' WHERE '.DBcondition('f.functionid', array_keys($expandHost))
			);
			while ($func = DBfetch($dbFuncs)) {
				foreach ($expandHost[$func['functionid']] as $macro => $fNum) {
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

					if ($fNum == 0 || $fNum == 1) {
						$m = '{'.$macro.'}';
						$this->macroValues[$func['triggerid']][$m] = $replace;
						$m = '{'.$macro.'1}';
						$this->macroValues[$func['triggerid']][$m] = $replace;
					}
					else {
						$m = '{'.$macro.$fNum.'}';
						$this->macroValues[$func['triggerid']][$m] = $replace;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Expand interface macros.
	 *
	 * @param array $expandIp
	 *
	 * @return bool
	 */
	protected function expandIpMacros(array $expandIp) {
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
						' WHERE '.DBcondition('f.functionid', array_keys($expandIp)).
						' AND n.main=1'.
						' AND '.DBcondition('n.type', array_keys($priorities))
			);
			$priority = 0;
			while ($dbInterface = DBfetch($dbInterfaces)) {
				if ($priority >= $priorities[$dbInterface['type']]) {
					continue;
				}
				$priority = $priorities[$dbInterface['type']];
				$interface = $dbInterface;
			}
			foreach ($expandIp[$interface['functionid']] as $macro => $fNum) {
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

				if ($fNum == 0 || $fNum == 1) {
					$m = '{'.$macro.'}';
					$this->macroValues[$interface['triggerid']][$m] = $replace;
					$m = '{'.$macro.'1}';
					$this->macroValues[$interface['triggerid']][$m] = $replace;
				}
				else {
					$m = '{'.$macro.$fNum.'}';
					$this->macroValues[$interface['triggerid']][$m] = $replace;
				}
			}
		}

		return true;
	}

	/**
	 * Expand item macros.
	 *
	 * @param array $expandItem
	 *
	 * @return bool
	 */
	protected function expandItemMacros(array $expandItem) {
		if (!empty($expandItem)) {
			$dbFuncs = DBselect(
				'SELECT DISTINCT f.triggerid,f.functionid,i.itemid,i.lastvalue,i.lastclock,i.value_type,i.units,i.valuemapid,m.newvalue'.
						' FROM functions f'.
						' INNER JOIN items i ON f.itemid=i.itemid'.
						' INNER JOIN hosts h ON i.hostid=h.hostid'.
						' LEFT JOIN mappings m ON i.valuemapid=m.valuemapid AND i.lastvalue=m.value'.
						' WHERE '.DBcondition('f.functionid', array_keys($expandItem))
			);
			while ($func = DBfetch($dbFuncs)) {
				foreach ($expandItem[$func['functionid']] as $macro => $fNum) {
					switch ($macro) {
						case 'ITEM.LASTVALUE':
							$replace = $this->resolveItemLastvalueMacro($func);
							break;
						case 'ITEM.VALUE':
							$replace = $this->resolveItemValueMacro($func);
							break;
					}

					if ($fNum == 0 || $fNum == 1) {
						$m = '{'.$macro.'}';
						$this->macroValues[$func['triggerid']][$m] = $replace;
						$m = '{'.$macro.'1}';
						$this->macroValues[$func['triggerid']][$m] = $replace;
					}
					else {
						$m = '{'.$macro.$fNum.'}';
						$this->macroValues[$func['triggerid']][$m] = $replace;
					}
				}
			}
		}

		return true;
	}

	protected function replaceMacroValues() {
		foreach ($this->triggers as &$trigger) {
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
							if (isset($this->macroValues[$trigger['triggerid']][$macro])) {
								$replace = $this->macroValues[$trigger['triggerid']][$macro];
								$trigger['description'] = zbx_substr_replace(
									$trigger['description'],
									$replace,
									$macroBegin,
									zbx_strlen($macro)
								);
								$i = $macroBegin + zbx_strlen($replace);
							}
						}
						break;
				}
			}
		}
		unset($trigger);
	}

	/**
	 * Resolve {ITEM.LASTVALUE} macro.
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	protected function resolveItemLastvalueMacro(array $item) {
		if ($item['newvalue']) {
			$value = $item['newvalue'].' ('.$item['lastvalue'].')';
		}
		else {
			$value = formatItemValueType($item);
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
	 *
	 * @return string
	 */
	protected function resolveItemValueMacro(array $item) {
		return $this->resolveItemLastvalueMacro($item);
	}
}
