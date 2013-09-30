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


class CMacrosResolver {

	const PATTERN_HOST = '{(HOSTNAME|HOST\.HOST|HOST\.NAME)}';
	const PATTERN_HOST_INTERNAL = 'HOST\.HOST|HOSTNAME';
	const PATTERN_MACRO_PARAM = '[1-9]?';
	const PATTERN_HOST_FUNCTION = '{(HOSTNAME|HOST\.HOST|HOST\.NAME)([1-9]?)}';
	const PATTERN_IP = '{(IPADDRESS|HOST\.IP|HOST\.DNS|HOST\.CONN)}';
	const PATTERN_IP_FUNCTION = '{(IPADDRESS|HOST\.IP|HOST\.DNS|HOST\.CONN)([1-9]?)}';
	const PATTERN_ITEM_FUNCTION = '{(ITEM\.LASTVALUE|ITEM\.VALUE)([1-9]?)}';

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
			'types' => array('host', 'ip', 'user'),
			'method' => 'resolveTexts'
		),
		'httpTestName' => array(
			'types' => array('host', 'ip', 'user'),
			'method' => 'resolveTexts'
		),
		'triggerName' => array(
			'types' => array('host', 'ip', 'user', 'item', 'reference'),
			'source' => 'description',
			'method' => 'resolveTrigger'
		),
		'triggerDescription' => array(
			'types' => array('host', 'ip', 'user', 'item'),
			'source' => 'comments',
			'method' => 'resolveTrigger'
		),
		'eventDescription' => array(
			'types' => array('host', 'ip', 'user', 'item', 'reference'),
			'source' => 'description',
			'method' => 'resolveTrigger'
		),
		'graphName' => array(
			'types' => array('graphFunctionalItem'),
			'source' => 'name',
			'method' => 'resolveGraph'
		)
	);

	/**
	 * Resolve macros.
	 *
	 * Macros examples:
	 * reference: $1, $2, $3, ...
	 * user: {$MACRO1}, {$MACRO2}, ...
	 * host: {HOSTNAME}, {HOST.HOST}, {HOST.NAME}
	 * ip: {IPADDRESS}, {HOST.IP}, {HOST.DNS}, {HOST.CONN}
	 * item: {ITEM.LASTVALUE}, {ITEM.VALUE}
	 *
	 * @param array  $options
	 * @param string $options['config']
	 * @param array  $options['data']
	 *
	 * @return array
	 */
	public function resolve(array $options) {
		if (empty($options['data'])) {
			return array();
		}

		$this->config = $options['config'];

		// call method
		$method = $this->configs[$this->config]['method'];

		return $this->$method($options['data']);
	}

	/**
	 * Batch resolving macros in text using host id.
	 *
	 * @param array $data (as $hostId => array(texts))
	 *
	 * @return array (as $hostId => array(texts))
	 */
	private function resolveTexts(array $data) {
		$hostIds = array_keys($data);

		$macros = array();

		$isHostMacrosAvailable = false;
		if ($this->isTypeAvailable('host')) {
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
		if ($this->isTypeAvailable('ip')) {
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
			$dbHosts = DBselect('SELECT h.hostid,h.name,h.host FROM hosts h WHERE '.dbConditionInt('h.hostid', $hostIds));
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
					' AND '.dbConditionInt('i.hostid', $hostIds).
					' AND '.dbConditionInt('i.type', $this->interfacePriorities)
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

		if (!empty($macros)) {
			foreach ($data as $hostId => $texts) {
				// get user macros
				if ($this->isTypeAvailable('user')) {
					$macros[$hostId] = !empty($macros[$hostId])
						? array_merge($macros[$hostId], $this->getUserMacros($texts, array('hostid' => $hostId)))
						: $this->getUserMacros($texts, array('hostid' => $hostId));
				}

				// replace macros to value
				if (!empty($macros[$hostId])) {
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
			}
		}

		return $data;
	}

	/**
	 * Resolve macros in trigger.
	 *
	 * @param array  $data (as int $triggerId => array $trigger)
	 * @param string $data[$triggerId]['expression']
	 * @param string $data[$triggerId]['description'] depend from config
	 * @param string $data[$triggerId]['comments'] depend from config
	 *
	 * @return array
	 */
	private function resolveTrigger(array $data) {
		$macros = array('host' => array(), 'ip' => array(), 'item' => array());
		$macroValues = array();

		// get source field
		$source = $this->getSource();

		// get available functions
		$isHostMacrosAvailable = $this->isTypeAvailable('host');
		$isIpMacrosAvailable = $this->isTypeAvailable('ip');
		$isItemMacrosAvailable = $this->isTypeAvailable('item');
		$isUserMacrosAvailable = $this->isTypeAvailable('user');
		$isReferenceMacrosAvailable = $this->isTypeAvailable('reference');

		// find macros
		foreach ($data as $triggerId => $trigger) {
			$functions = $this->findFunctions($trigger['expression']);

			if ($isUserMacrosAvailable) {
				$macroValues[$triggerId] = $this->getUserMacros(array($trigger[$source]), array('triggerid' => $triggerId));
			}

			if ($isHostMacrosAvailable) {
				foreach ($this->findFunctionMacros(self::PATTERN_HOST_FUNCTION, $trigger[$source]) as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						$macroValues[$triggerId][$this->getFunctionMacroName($macro, $fNum)] = UNRESOLVED_MACRO_STRING;

						if (isset($functions[$fNum])) {
							$macros['host'][$functions[$fNum]][$macro][] = $fNum;
						}
					}
				}
			}

			if ($isIpMacrosAvailable) {
				foreach ($this->findFunctionMacros(self::PATTERN_IP_FUNCTION, $trigger[$source]) as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						$macroValues[$triggerId][$this->getFunctionMacroName($macro, $fNum)] = UNRESOLVED_MACRO_STRING;

						if (isset($functions[$fNum])) {
							$macros['ip'][$functions[$fNum]][$macro][] = $fNum;
						}
					}
				}
			}

			if ($isItemMacrosAvailable) {
				foreach ($this->findFunctionMacros(self::PATTERN_ITEM_FUNCTION, $trigger[$source]) as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						$macroValues[$triggerId][$this->getFunctionMacroName($macro, $fNum)] = UNRESOLVED_MACRO_STRING;

						if (isset($functions[$fNum])) {
							$macros['item'][$functions[$fNum]][$macro][] = $fNum;
						}
					}
				}
			}

			if ($isReferenceMacrosAvailable) {
				foreach ($this->getTriggerReference($trigger['expression'], $trigger[$source]) as $macro => $value) {
					$macroValues[$triggerId][$macro] = $value;
				}
			}
		}

		// get macro value
		if ($isHostMacrosAvailable) {
			$macroValues = $this->resolveHostMacros($macros['host'], $macroValues);
		}
		if ($isIpMacrosAvailable) {
			$macroValues = $this->resolveIpMacros($macros['ip'], $macroValues);
		}
		if ($isItemMacrosAvailable) {
			$macroValues = $this->resolveItemMacros($macros['item'], $data, $macroValues);
		}

		// replace macros to value
		foreach ($data as $triggerId => $trigger) {
			preg_match_all('/'.self::PATTERN_HOST_FUNCTION.
								'|'.self::PATTERN_IP_FUNCTION.
								'|'.self::PATTERN_ITEM_FUNCTION.
								'|'.ZBX_PREG_EXPRESSION_USER_MACROS.
								'|\$([1-9])/', $trigger[$source], $matches, PREG_OFFSET_CAPTURE);

			for ($i = count($matches[0]) - 1; $i >= 0; $i--) {
				$matche = $matches[0][$i];

				$macrosValue = isset($macroValues[$triggerId][$matche[0]]) ? $macroValues[$triggerId][$matche[0]] : $matche[0];
				$trigger[$source] = substr_replace($trigger[$source], $macrosValue, $matche[1], strlen($matche[0]));
			}

			$data[$triggerId][$source] = $trigger[$source];
		}

		return $data;
	}

	/**
	 * Expand reference macros for trigger.
	 * If macro reference non existing value it expands to empty string.
	 *
	 * @param string $expression
	 * @param string $text
	 *
	 * @return string
	 */
	public function resolveTriggerReference($expression, $text) {
		$macros = $this->getTriggerReference($expression, $text);

		if (!empty($macros)) {
			foreach ($macros as $i => $value) {
				$text = str_replace($i, $value, $text);
			}
		}

		return $text;
	}

	/**
	 * Get reference macros for trigger.
	 * If macro reference non existing value it expands to empty string.
	 *
	 * @param string $expression
	 * @param string $text
	 *
	 * @return array
	 */
	private function getTriggerReference($expression, $text) {
		$result = array();

		// search for reference macros $1, $2, $3, ...
		preg_match_all('/\$([1-9])/', $text, $refNumbers);

		if (empty($refNumbers)) {
			return $result;
		}

		// replace functionids with string 'function' to make values search easier
		$expression = preg_replace('/\{[0-9]+\}/', 'function', $expression);

		// search for numeric values in expression
		preg_match_all('/'.ZBX_PREG_NUMBER.'/', $expression, $values);

		foreach ($refNumbers[1] as $i) {
			$result['$'.$i] = isset($values[0][$i - 1]) ? $values[0][$i - 1] : '';
		}

		return $result;
	}

	/**
	 * Get user macros.
	 *
	 * @param array $texts
	 * @param array $options
	 * @param int   $options['hostid']
	 * @param int   $options['triggerid']
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
	 * @param array  $texts
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
	 * Find macros with function position.
	 *
	 * @param string $pattern
	 * @param string $text
	 *
	 * @return array where key is found macro and value is array with related function position
	 */
	private function findFunctionMacros($pattern, $text) {
		$result = array();

		preg_match_all('/'.$pattern.'/', $text, $matches);

		foreach ($matches[1] as $num => $macro) {
			$fNum = !empty($matches[2][$num]) ? $matches[2][$num] : 0;
			$result[$macro][$fNum] = $fNum;
		}

		return $result;
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
	 * Resolve host macros.
	 *
	 * @param array $macros
	 * @param array $macroValues
	 *
	 * @return bool
	 */
	private function resolveHostMacros(array $macros, array $macroValues) {
		if (!empty($macros)) {
			$dbFuncs = DBselect(
				'SELECT f.triggerid,f.functionid,h.host,h.name'.
				' FROM functions f'.
					' JOIN items i ON f.itemid=i.itemid'.
					' JOIN hosts h ON i.hostid=h.hostid'.
				' WHERE '.dbConditionInt('f.functionid', array_keys($macros))
			);
			while ($func = DBfetch($dbFuncs)) {
				foreach ($macros[$func['functionid']] as $macro => $fNums) {
					switch ($macro) {
						case 'HOSTNAME':
						case 'HOST.HOST':
							$replace = $func['host'];
							break;
						case 'HOST.NAME':
							$replace = $func['name'];
							break;
					}

					$macroValues = $this->prepareFunctionMacroValues($macroValues, $fNums, $func['triggerid'], $macro, $replace);
				}
			}
		}

		return $macroValues;
	}

	/**
	 * Resolve interface macros.
	 *
	 * @param array $macros
	 * @param array $macroValues
	 *
	 * @return bool
	 */
	private function resolveIpMacros(array $macros, array $macroValues) {
		if (!empty($macros)) {
			$dbInterfaces = DBselect(
				'SELECT f.triggerid,f.functionid,n.ip,n.dns,n.type,n.useip'.
				' FROM functions f'.
					' JOIN items i ON f.itemid=i.itemid'.
					' JOIN interface n ON i.hostid=n.hostid'.
				' WHERE '.dbConditionInt('f.functionid', array_keys($macros)).
					' AND n.main=1'
			);

			// macro should be resolved to interface with highest priority ($priorities)
			$interfaces = array();
			while ($dbInterface = DBfetch($dbInterfaces)) {
				if (isset($interfaces[$dbInterface['functionid']])
						&& $this->interfacePriorities[$interfaces[$dbInterface['functionid']]['type']] > $this->interfacePriorities[$dbInterface['type']]) {
					continue;
				}
				$interfaces[$dbInterface['functionid']] = $dbInterface;
			}

			foreach ($interfaces as $interface) {
				foreach ($macros[$interface['functionid']] as $macro => $fNums) {
					switch ($macro) {
						case 'IPADDRESS':
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

					$macroValues = $this->prepareFunctionMacroValues($macroValues, $fNums, $interface['triggerid'], $macro, $replace);
				}
			}
		}

		return $macroValues;
	}

	/**
	 * Resolve item macros.
	 *
	 * @param array $macros
	 * @param array $triggers
	 * @param array $macroValues
	 *
	 * @return bool
	 */
	private function resolveItemMacros(array $macros, array $triggers, array $macroValues) {
		if ($macros) {
			$functions = DbFetchArray(DBselect(
				'SELECT f.triggerid,f.functionid,i.itemid,i.value_type,i.units,i.valuemapid'.
				' FROM functions f'.
					' JOIN items i ON f.itemid=i.itemid'.
					' JOIN hosts h ON i.hostid=h.hostid'.
				' WHERE '.dbConditionInt('f.functionid', array_keys($macros))
			));

			$history = Manager::History()->getLast($functions);

			// false passed to DBfetch to get data without null converted to 0, which is done by default
			foreach ($functions as $func) {
				foreach ($macros[$func['functionid']] as $macro => $fNums) {
					$lastValue = isset($history[$func['itemid']]) ? $history[$func['itemid']][0]['value'] : null;

					switch ($macro) {
						case 'ITEM.LASTVALUE':
							$replace = $this->resolveItemLastvalueMacro($lastValue, $func);
							break;
						case 'ITEM.VALUE':
							$replace = $this->resolveItemValueMacro($lastValue, $func, $triggers[$func['triggerid']]);
							break;
					}

					$macroValues = $this->prepareFunctionMacroValues($macroValues, $fNums, $func['triggerid'], $macro, $replace);
				}
			}
		}

		return $macroValues;
	}

	/**
	 * Resolve {ITEM.LASTVALUE} macro.
	 *
	 * @param mixed $lastValue
	 * @param array $item
	 *
	 * @return string
	 */
	private function resolveItemLastvalueMacro($lastValue, array $item) {
		return ($lastValue !== null) ? formatHistoryValue($lastValue, $item) : UNRESOLVED_MACRO_STRING;
	}

	/**
	 * Resolve {ITEM.VALUE} macro.
	 * For triggers macro is resolved in same way as {ITEM.LASTVALUE} macro. Separate methods are created for event description,
	 * where {ITEM.VALUE} macro resolves in different way.
	 *
	 * @param mixed $lastValue
	 * @param array $item
	 * @param array $trigger
	 *
	 * @return string
	 */
	private function resolveItemValueMacro($lastValue, array $item, array $trigger) {
		if ($this->config == 'eventDescription') {
			$value = item_get_history($item, $trigger['clock'], $trigger['ns']);

			return ($value !== null) ? formatHistoryValue($value, $item) : UNRESOLVED_MACRO_STRING;
		}
		else {
			return $this->resolveItemLastvalueMacro($lastValue, $item);
		}
	}

	/**
	 * Add function macro name with corresponding value to replace to $macroValues array.
	 *
	 * @param array  $macroValues
	 * @param array  $fNums
	 * @param int    $triggerId
	 * @param string $macro
	 * @param string $replace
	 *
	 * @return array
	 */
	private function prepareFunctionMacroValues(array $macroValues, array $fNums, $triggerId, $macro, $replace) {
		foreach ($fNums as $fNum) {
			$macroValues[$triggerId][$this->getFunctionMacroName($macro, $fNum)] = $replace;
		}

		return $macroValues;
	}

	/**
	 * Calculate function macro name.
	 *
	 * @param string $macro
	 * @param int    $fNum
	 *
	 * @return string
	 */
	private function getFunctionMacroName($macro, $fNum) {
		return '{'.(($fNum == 0) ? $macro : $macro.$fNum).'}';
	}

	/**
	 * Is type available.
	 *
	 * @param string $type
	 *
	 * @return bool
	 */
	private function isTypeAvailable($type) {
		return in_array($type, $this->configs[$this->config]['types']);
	}

	/**
	 * Get source field.
	 *
	 * @return string
	 */
	private function getSource() {
		return $this->configs[$this->config]['source'];
	}

	/**
	 * Resolve functional item macros, for example, {{HOST.HOST1}:key.func(param)}.
	 *
	 * @param array		$data							list or hashmap of graphs
	 * @param type		$data[]['name']					string in which macros should be resolved
	 * @param array		$data[]['items']				list of graph items
	 * @param int		$data[]['items'][n]['hostid']	graph n-th item corresponding host Id
	 * @param string	$data[]['items'][n]['host']	    graph n-th item corresponding host name
	 *
	 * @return string	inputted data with resolved source field
	 */
	private function resolveGraph($data) {
		$source = $this->getSource();
		if ($this->isTypeAvailable('graphFunctionalItem')){
			$strList = array();
			$itemsList = array();
			foreach ($data as $graph) {
				$strList[] = $graph[$source];
				$itemsList[] = $graph['items'];
			}

			$resolvedStrList = $this->resolveGraphsFunctionalItemMacros($strList, $itemsList);

			$resolvedStr = reset($resolvedStrList);
			foreach ($data as &$graph) {
				$graph[$source] = $resolvedStr;
				$resolvedStr = next($resolvedStrList);
			}
			unset($graph);
		}

		return $data;
	}

	/**
	 * Resolve functional macros, like {hostname:key.function(param)}.
	 * If macro can not be resolved it is replaced with UNRESOLVED_MACRO_STRING string i.e. "*UNKNOWN*"
	 * Supports function "last", "min", "max" and "avg".
	 * Supports seconds as parameters, except "last" function.
	 * Supports postfixes s,m,h,d and w for parameter.
	 *
	 * @param array 	$strList			    list of string in which macros should be resolved
	 * @param array		$itemsList			    list of	lists of graph items
	 * @param int		$items[n][m]['hostid']  n-th graph m-th item corresponding host Id
	 * @param string	$items[n][m]['host']    n-th graph m-th item corresponding host name
	 *
	 * @return array	list of strings with macros replaced with corresponding values
	 */
	private function resolveGraphsFunctionalItemMacros($strList, $itemsList) {
		// retrieve all string macros and all host-key pairs
		$hostKeyPairs = array();
		$matchesList = array();
		$items = reset($itemsList);
		foreach ($strList as $str) {
			// extract all macros into $matches - keys: macros, hosts, keys, functions and parameters are used
			// searches for macros, for example, "{somehost:somekey["param[123]"].min(10m)}"
			preg_match_all('/(?<macros>{'.
				'(?<hosts>('.ZBX_PREG_HOST_FORMAT.'|({('.self::PATTERN_HOST_INTERNAL.')'.self::PATTERN_MACRO_PARAM.'}))):'.
				'(?<keys>'.ZBX_PREG_ITEM_KEY_FORMAT.')\.'.
				'(?<functions>(last|max|min|avg))\('.
				'(?<parameters>([0-9]+[smhdw]?))'.
				'\)}{1})/Uux', $str, $matches, PREG_OFFSET_CAPTURE);

			if (!empty($matches['hosts'])) {
				foreach ($matches['hosts'] as $i => $host) {
					$matches['hosts'][$i][0] = $this->resolveGraphPositionalMacros($host[0], $items);
					if ($matches['hosts'][$i][0] !== UNRESOLVED_MACRO_STRING) {
						if (!isset($hostKeyPairs[$matches['hosts'][$i][0]])) {
							$hostKeyPairs[$matches['hosts'][$i][0]] = array();
						}
						$hostKeyPairs[$matches['hosts'][$i][0]][$matches['keys'][$i][0]] = 1;
					}
				}

				$matchesList[] = $matches;
				$items = next($itemsList);
			}
		}

		// stop, if no macros found
		if (empty($matchesList)) {
			return $strList;
		}

		// build item retrieval query from host-key pairs
		$query = 'SELECT h.host,i.key_,i.itemid,i.value_type,i.units,i.valuemapid'.
					' FROM items i, hosts h'.
					' WHERE i.hostid=h.hostid AND (';
		foreach ($hostKeyPairs as $host => $keys) {
			$query .= '(h.host='.zbx_dbstr($host).' AND i.key_ IN(';
			foreach ($keys as $key => $val) {
				$query .= zbx_dbstr($key).',';
			}
			$query = substr($query, 0, -1);
			$query .= ')) OR ';
		}
		$query = substr($query, 0, -4);
		$query .= ')';

		// get necessary items for all graph strings
		$items = DBfetchArrayAssoc(DBselect($query), 'itemid');

		$allowedItems = API::Item()->get(array(
			'itemids' => array_keys($items),
			'webitems' => true,
			'output' => array('itemid', 'value_type'),
			'preservekeys' => true
		));

		// map item data only for allowed items
		foreach ($items as $item) {
			if (isset($allowedItems[$item['itemid']])) {
				$hostKeyPairs[$item['host']][$item['key_']] = $item;
			}
		}

		// fetch history
		$history = Manager::History()->getLast($items);

		// replace macros with their corresponding values in graph strings
		$matches = reset($matchesList);
		foreach ($strList as &$str) {
			// iterate array backwards!
			$i = count($matches['macros']);
			while ($i--) {
				// host is real and item exists and has permissions
				if ($matches['hosts'][$i][0] !== UNRESOLVED_MACRO_STRING &&
					is_array($hostKeyPairs[$matches['hosts'][$i][0]][$matches['keys'][$i][0]])) {

					$item = $hostKeyPairs[$matches['hosts'][$i][0]][$matches['keys'][$i][0]];

					// macro function is "last"
					if ($matches['functions'][$i][0] == 'last') {
						if (isset($history[$item['itemid']])) {
							$value = formatHistoryValue($history[$item['itemid']][0]['value'], $item);
						}
						else {
							$value = UNRESOLVED_MACRO_STRING;
						}
					}
					// macro function is "max", "min" or "avg"
					else {
						$value = getItemFunctionalValue($item, $matches['functions'][$i][0], $matches['parameters'][$i][0]);
					}
				}
				// there is no item with given key in given host, or there is no permissions to that item
				else {
					$value = UNRESOLVED_MACRO_STRING;
				}

				$str = substr_replace($str, $value, $matches['macros'][$i][1], strlen($matches['macros'][$i][0]));
			}

			$matches = next($matchesList);
		}
		unset($str);

		return $strList;
	}

	/**
	 * Resolve positional macros, like {HOST.HOST2}.
	 * If macro can not be resolved it is replaced with UNRESOLVED_MACRO_STRING string i.e. "*UNKNOWN*"
	 * Supports HOST.HOST<1..9> macros.
	 *
	 * @param string	$str				string in which macros should be resolved
	 * @param array		$items				list of graph items
	 * @param int 		$items[n]['hostid'] graph n-th item corresponding host Id
	 * @param string	$items[n]['host']   graph n-th item corresponding host name
	 *
	 * @return string	string with macros replaces with corresponding values
	 */
	private function resolveGraphPositionalMacros($str, $items) {
		// extract all macros into $matches
		preg_match_all('/{(('.self::PATTERN_HOST_INTERNAL.')('.self::PATTERN_MACRO_PARAM.'))\}/', $str, $matches);

		// match found groups if ever regexp should change
		$matches['macroType'] = $matches[2];
		$matches['position'] = $matches[3];

		// build structure of macros: $macroList['HOST.HOST'][2] = 'host name';
		$macroList = array();
		// $matches[3] contains positions, e.g., '',1,2,2,3,...
		foreach ($matches['position'] as $i => $position) {

			// take care of macro without positional index
			$posInItemList = ($position === '') ? 0 : $position - 1;

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
					$macroList[$matches['macroType'][$i]][$position] = $items[$posInItemList]['host'];
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
