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

		foreach ($this->triggers as &$trigger) {
			$trigger['description'] = $this->expandReferenceMacros($trigger);

			$functions = $this->findFunctions($trigger['expression']);

			foreach($this->findHostMacros($trigger['description']) as $macro => $fNums) {
				foreach ($fNums as $fNum) {
					$expandHost[$functions[$fNum]][$fNum] = $macro;
				}
			}

			foreach($this->findIpMacros($trigger['description']) as $macro => $fNums) {
				foreach ($fNums as $fNum) {
					$expandIp[$functions[$fNum]][$fNum] = $macro;
				}
			}

			foreach($this->findItemMacros($trigger['description']) as $macro => $fNums) {
				foreach ($fNums as $fNum) {
					$expandItem[$functions[$fNum]][$fNum] = $macro;
				}
			}


			$trigger['description'] = $this->expandUserMacros($trigger);
		}
		unset($trigger);

		$this->expandHostMacros($expandHost);
		$this->expandIpMacros($expandIp);
		$this->expandItemMacros($expandItem);

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
	protected function expandReferenceMacros(array $trigger) {
		$expression = $trigger['expression'];
		$description = $trigger['description'];

		// search for reference macros $1, $2, $3, ...
		preg_match_all('/\$([1-9])/', $description, $refNumbers);
		$refNumbers = $refNumbers[1];

		// replace functionids with string to make values search easier
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
	 * Expand user macros for trigger.
	 *
	 * @param array $trigger
	 *
	 * @return mixed
	 */
	protected function expandUserMacros(array $trigger) {
		$description = $trigger['description'];

		if (preg_match_all('/'.ZBX_PREG_EXPRESSION_USER_MACROS.'/', $description, $matches)) {
			$macros = API::UserMacro()->getMacros(array(
				'macros' => $matches[1],
				'triggerid' => $trigger['triggerid']
			));
			$description = str_replace(array_keys($macros), array_values($macros), $description);
		}

		return $description;
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
			$functionNum = $matches[2][$num] ? $matches[2][$num] : 1;
			$result[$foundMacro][$functionNum] = $functionNum;
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
				foreach ($expandHost[$func['functionid']] as $fNum => $macro) {
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

					$macros = array('{'.$macro.$fNum.'}');
					if ($fNum == 1) {
						$macros[] = '{'.$macro.'}';
					}
					$this->triggers[$func['triggerid']]['description'] = str_replace(
						$macros, $replace, $this->triggers[$func['triggerid']]['description']);
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
			foreach ($expandIp[$interface['functionid']] as $fNum => $macro) {
				switch ($macro) {
					case 'IPADDRESS':
						$replace = $interface['ip'];
						break;
					case 'HOST.DNS':
						$replace = $interface['dns'];
						break;
					case 'HOST.CONN':
						$replace = $interface['useip'] ? $interface['ip'] : $interface['dns'];
						break;
				}

				$macros = array('{'.$macro.$fNum.'}');
				if ($fNum == 1) {
					$macros[] = '{'.$macro.'}';
				}
				$this->triggers[$interface['triggerid']]['description'] = str_replace(
					$macros, $replace, $this->triggers[$interface['triggerid']]['description']);
			}
		}

		return true;
	}

	/**Expand item macros.
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
				foreach ($expandItem[$func['functionid']] as $fNum => $macro) {
					switch ($macro) {
						case 'ITEM.LASTVALUE':
							$replace = $this->resolveItemLastvalueMacro($func);
							break;
						case 'ITEM.VALUE':
							$replace = $this->resolveItemValueMacro($func);
							break;
					}

					$macros = array('{'.$macro.$fNum.'}');
					if ($fNum == 1) {
						$macros[] = '{'.$macro.'}';
					}
					$this->triggers[$func['triggerid']]['description'] = str_replace(
						$macros, $replace, $this->triggers[$func['triggerid']]['description']);
				}
			}
		}

		return true;
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
