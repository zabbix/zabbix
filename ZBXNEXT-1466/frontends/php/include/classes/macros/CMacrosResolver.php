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


/**
 * A helper class for resolving macros.
 */
class CMacrosResolver {

	const PATTERN_HOST = '{HOSTNAME}|{HOST\.HOST}|{HOST\.NAME}';
	const PATTERN_IP = '{IPADDRESS}|{HOST\.IP}|{HOST\.DNS}|{HOST\.CONN}';

	/**
	 * Interface priorities.
	 *
	 * @var array
	 */
	private $interfacePriorities = array(
		INTERFACE_TYPE_AGENT => 4,
		INTERFACE_TYPE_SNMP => 3,
		INTERFACE_TYPE_JMX => 2,
		INTERFACE_TYPE_IPMI => 1
	);

	/**
	 * Work config name.
	 *
	 * @var string
	 */
	private $config = '';

	/**
	 * Supported macros resolving scenarios.
	 *
	 * @var array
	 */
	private $configs = array(
		'scriptConfirmation' => array(
			'functions' => array('host', 'ip'),
			'source' => null,
			'method' => 'resolveTexts'
		),
		'httpTestName' => array(
			'functions' => array('host', 'ip', 'user'),
			'source' => null,
			'method' => 'resolveTexts'
		),
		'triggerName' => array(
			'functions' => array('host', 'ip', 'user', 'global', 'trigger'),
			'source' => 'description',
			'method' => 'resolveTrigger'
		),
		'triggerDescription' => array(
			'functions' => array('host', 'ip', 'user', 'global', 'trigger'),
			'source' => 'comments',
			'method' => 'resolveTrigger'
		),
		'eventDescription' => array(
			'functions' => array('host', 'ip', 'user', 'global', 'trigger'),
			'source' => 'comments',
			'method' => 'resolveTrigger'
		)
	);

	/**
	 * Resolve macros.
	 *
	 * @param array  $options
	 * @param string $options['config']
	 * @param array  $options['data']
	 */
	public function resolve(array $options) {
		if (empty($options['data'])) {
			return array();
		}

		$this->config = $options['config'];

		// call method
		$method = $this->configs[$options['config']]['method'];
		return $this->$method($options);
	}

	/**
	 * Is function available.
	 *
	 * @param string $function
	 * @param array  $options
	 *
	 * @return bool
	 */
	private function isFunctionAvailable($function, array $options) {
		return isset($this->configs[$options['config']]['functions'][$function]);
	}

	/**
	 * Batch resolving macros in text using host id.
	 *
	 * @param array $options
	 * @param array $options['data'] (as $hostid => array(texts))
	 *
	 * @return array (as $hostid => array(texts))
	 */
	private function resolveTexts(array $options) {
		$data = $options['data'];
		$hostIds = array_keys($data);

		$macros = array();

		$isHostMacrosAvailable = false;
		if ($this->isFunctionAvailable('host', $options)) {
			foreach ($data as $hostId => $texts) {
				$hostMacros = $this->findMacros(self::PATTERN_HOST, $texts);
				if (!empty($hostMacros)) {
					foreach ($hostMacros as $hostMacro) {
						$macros[$hostId][$hostMacro] = UNRESOLVED_MACRO_STRING;
					}

					$isHostMacrosAvailable = true;
				}
			}
		}

		$isIpMacrosAvailable = false;
		if ($this->isFunctionAvailable('ip', $options)) {
			foreach ($data as $hostId => $texts) {
				$ipMacros = $this->findMacros(self::PATTERN_IP, $texts);
				if (!empty($ipMacros)) {
					foreach ($ipMacros as $ipMacro) {
						$macros[$hostId][$ipMacro] = UNRESOLVED_MACRO_STRING;
					}

					$isIpMacrosAvailable = true;
				}
			}
		}

		// host macros
		if ($isHostMacrosAvailable) {
			$dbHosts = DBselect('SELECT h.hostid,h.name,h.host FROM hosts h WHERE '.DBcondition('h.hostid', $hostIds));
			while ($dbHost = DBfetch($dbHosts)) {
				$hostId = $dbHost['hostid'];
				$hostMacros = $this->findMacros(self::PATTERN_HOST, $data[$hostId]);

				if (!empty($hostMacros)) {
					foreach ($hostMacros as $hostMacro) {
						switch ($hostMacro) {
							case '{HOSTNAME}':
							case '{HOST.HOST}':
								$macros[$hostId][$hostMacro] = $dbHost['host'];
								break;
							case '{HOST.NAME}':
								$macros[$hostId][$hostMacro] = $dbHost['name'];
								break;
						}
					}
				}
			}
		}

		// ip macros, macro should be resolved to interface with highest priority
		if ($isIpMacrosAvailable) {
			$interfaces = array();

			$dbInterfaces = DBselect(
				'SELECT i.hostid,i.ip,i.dns,i.useip,i.type'.
				' FROM interface i'.
				' WHERE i.main=1'.
					' AND '.DBcondition('i.hostid', $hostIds).
					' AND '.DBcondition('i.type', $this->interfacePriorities)
			);
			while ($dbInterface = DBfetch($dbInterfaces)) {
				$hostId = $dbInterface['hostid'];

				if (!isset($interfaces[$hostId]) || $this->interfacePriorities[$dbInterface['type']] > $interfaces[$hostId]['type']) {
					$interfaces[$hostId] = $dbInterface;
				}
			}

			if (!empty($interfaces)) {
				foreach ($interfaces as $hostId => $interface) {
					$ipMacros = $this->findMacros(self::PATTERN_IP, $data[$hostId]);

					if (!empty($ipMacros)) {
						foreach ($ipMacros as $ipMacro) {
							switch ($ipMacro) {
								case '{IPADDRESS}':
								case '{HOST.IP}':
									$macros[$hostId][$ipMacro] = $interface['ip'];
									break;
								case '{HOST.DNS}':
									$macros[$hostId][$ipMacro] = $interface['dns'];
									break;
								case '{HOST.CONN}':
									$macros[$hostId][$ipMacro] = $interface['useip'] ? $interface['ip'] : $interface['dns'];
									break;
							}

							// Resolving macros in macros. If interface is AGENT macros stay unresolved.
							if ($interface['type'] != INTERFACE_TYPE_AGENT) {
								if ($this->findMacros(self::PATTERN_HOST, array($macros[$hostId][$ipMacro]))
										|| $this->findMacros(ZBX_PREG_EXPRESSION_USER_MACROS, array($macros[$hostId][$ipMacro]))) {
									// attention recursion!
									$macrosInMacros = $this->resolveTexts(array($hostId => array($macros[$hostId][$ipMacro])));
									$macros[$hostId][$ipMacro] = $macrosInMacros[$hostId][0];
								}
								elseif ($this->findMacros(self::PATTERN_IP, array($macros[$hostId][$ipMacro]))) {
									$macros[$hostId][$ipMacro] = UNRESOLVED_MACRO_STRING;
								}
							}
						}
					}
				}
			}
		}

		foreach ($data as $hostId => $texts) {
			// get user macros
			if ($this->isFunctionAvailable('user', $options)) {
				$macros[$hostId] = !empty($macros[$hostId])
					? array_merge($macros[$hostId], $this->getUserMacros($texts, $hostId))
					: $this->getUserMacros($texts, $hostId);
			}

			// replace macros to value
			foreach ($texts as $tnum => $text) {
				preg_match_all('/'.self::PATTERN_HOST.'|'.self::PATTERN_IP.'|'.ZBX_PREG_EXPRESSION_USER_MACROS.'/', $text, $matches, PREG_OFFSET_CAPTURE);

				for ($i = count($matches[0]) - 1; $i >= 0; $i--) {
					$matche = $matches[0][$i];

					$macrosValue = isset($macros[$hostId][$matche[0]]) ? $macros[$hostId][$matche[0]] : $matche[0];
					$text = substr_replace($text, $macrosValue, $matche[1], strlen($matche[0]));
				}

				$data[$hostId][$tnum] = $text;
			}
		}

		return $data;
	}

	/**
	 * Get user macros.
	 *
	 * @param array $texts
	 * @param array $options
	 * @param int $options['hostid']
	 * @param int $options['triggerid']
	 *
	 * @return array
	 */
	private function getUserMacros(array $texts, array $options = array()) {
		$matches = $this->findMacros(ZBX_PREG_EXPRESSION_USER_MACROS, $texts);

		if (empty($matches)) {
			return array();
		}

		$options['macros'] = $matches;

		return API::UserMacro()->getMacros($options);
	}

	/**
	 * Find macros in text by pattern.
	 *
	 * @param string $pattern
	 * @param array $texts
	 *
	 * @return array
	 */
	private function findMacros($pattern, array $texts) {
		$result = array();

		foreach ($texts as $text) {
			preg_match_all('/'.$pattern.'/', $text, $matches);

			$result = array_merge($result, $matches[0]);
		}

		return array_unique($result);
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
	public function resolveTrigger(array $options) {
		$triggers = $options['data'];

		$expandHost = array();
		$expandIp = array();
		$expandItem = array();
		$macroValues = array();

		foreach ($triggers as $triggerId => $trigger) {
			$triggers[$triggerId]['description'] = $this->resolveTriggerReference($trigger);

			$functions = $this->findFunctions($trigger['expression']);

			foreach ($this->findHostMacros($trigger['description']) as $macro => $fNums) {
				foreach ($fNums as $fNum) {
					if (isset($functions[$fNum])) {
						$expandHost[$functions[$fNum]][$macro][] = $fNum;
					}
				}
			}

			foreach ($this->findIpMacros($trigger['description']) as $macro => $fNums) {
				foreach ($fNums as $fNum) {
					if (isset($functions[$fNum])) {
						$expandIp[$functions[$fNum]][$macro][] = $fNum;
					}
				}
			}

			foreach ($this->findItemMacros($trigger['description']) as $macro => $fNums) {
				foreach ($fNums as $fNum) {
					if (isset($functions[$fNum])) {
						$expandItem[$functions[$fNum]][$macro][] = $fNum;
					}
				}
			}

			$macroValues[$trigger['triggerid']] = $this->getUserMacros(array($trigger['description']), array('triggerid' => $trigger['triggerid']));
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
	public function resolveTriggerReference(array $trigger) {
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
	 * Find function ids in trigger expression.
	 *
	 * @param string $expression
	 *
	 * @return array where key is function id position in expression and value is function id
	 */
	private function findFunctions($expression) {
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
	private function findHostMacros($description) {
		return $this->findMacrosInDescription('HOSTNAME|HOST\.HOST|HOST\.NAME', $description);
	}

	/**
	 * Find interface data related macros in trigger description.
	 *
	 * @param string $description
	 *
	 * @return array
	 */
	private function findIpMacros($description) {
		return $this->findMacrosInDescription('IPADDRESS|HOST\.IP|HOST\.DNS|HOST\.CONN', $description);
	}

	/**
	 * Find item data related macros in trigger description.
	 *
	 * @param string $description
	 *
	 * @return array
	 */
	private function findItemMacros($description) {
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
	private function findMacrosInDescription($macrosPattern, $description) {
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
	private function expandHostMacros(array $expandHost, array $macroValues = array()) {
		if (!empty($expandHost)) {
			$dbFuncs = DBselect(
				'SELECT DISTINCT f.triggerid,f.functionid,h.host,h.name'.
				' FROM functions f'.
					' INNER JOIN items i ON f.itemid=i.itemid'.
					' INNER JOIN hosts h ON i.hostid=h.hostid'.
				' WHERE '.DBcondition('f.functionid', array_keys($expandHost))
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
	private function expandIpMacros(array $expandIp, array $macroValues = array()) {
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
	private function expandItemMacros(array $expandItem, array $triggers, array $macroValues = array()) {
		if (!empty($expandItem)) {
			$dbFuncs = DBselect(
				'SELECT DISTINCT f.triggerid,f.functionid,i.itemid,i.lastvalue,i.lastclock,i.value_type,i.units,i.valuemapid,m.newvalue'.
				' FROM functions f'.
					' INNER JOIN items i ON f.itemid=i.itemid'.
					' INNER JOIN hosts h ON i.hostid=h.hostid'.
					' LEFT JOIN mappings m ON i.valuemapid=m.valuemapid AND i.lastvalue=m.value'.
				' WHERE '.DBcondition('f.functionid', array_keys($expandItem))
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
	private function replaceMacroValues(array $trigger, array $macroValues) {
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
	private function resolveItemLastvalueMacro(array $item) {
		if (is_null($item['newvalue'])) {
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
	private function resolveItemValueMacro(array $item, array $trigger) {
		if ($this->config == 'eventDescription') {
			$item['lastvalue'] = item_get_history($item, 0, $trigger['clock'], $trigger['ns']);

			return formatItemValue($item, UNRESOLVED_MACRO_STRING);
		}
		else {
			return $this->resolveItemLastvalueMacro($item);
		}
	}

	/**
	 * Check if the string is a macro supported in trigger description.
	 *
	 * @param string $macro
	 *
	 * @return bool
	 */
	private function isAllowedMacro($macro) {
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
	private function prepareMacroValues(array $macroValues, array $fNums, $triggerId, $macro, $replace) {
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
