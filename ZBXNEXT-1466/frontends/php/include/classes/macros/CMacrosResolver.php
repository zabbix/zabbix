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


class CMacrosResolver {

	const PATTERN_HOST = '{HOSTNAME}|{HOST\.HOST}|{HOST\.NAME}';
	const PATTERN_HOST_FUNCTION = '{(HOSTNAME|HOST\.HOST|HOST\.NAME)([1-9]?)}';
	const PATTERN_IP = '{IPADDRESS}|{HOST\.IP}|{HOST\.DNS}|{HOST\.CONN}';
	const PATTERN_IP_FUNCTION = '{(IPADDRESS|HOST\.IP|HOST\.DNS|HOST\.CONN)([1-9]?)}';
	const PATTERN_ITEM_FUNCTION = '{(ITEM\.LASTVALUE|ITEM\.VALUE)([1-9]?)}';
	const PATTERN_ALLOWED = '{HOSTNAME|HOST\.HOST|HOST\.NAME|IPADDRESS|HOST\.IP|HOST\.DNS|HOST\.CONN|ITEM\.LASTVALUE|ITEM\.VALUE[1-9]?}';

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
			'functions' => array('host', 'ip', 'user', 'item'),
			'source' => 'description',
			'method' => 'resolveTrigger'
		),
		'triggerDescription' => array(
			'functions' => array('host', 'ip', 'user', 'item'),
			'source' => 'comments',
			'method' => 'resolveTrigger'
		),
		'eventDescription' => array(
			'functions' => array('host', 'ip', 'user', 'item'),
			'source' => 'description',
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
		$method = $this->configs[$this->config]['method'];
		return $this->$method($options);
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
		$hostIds = array_keys($options['data']);

		$macros = array();

		$isHostMacrosAvailable = false;
		if ($this->isFunctionAvailable('host')) {
			foreach ($options['data'] as $hostId => $texts) {
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
		if ($this->isFunctionAvailable('ip')) {
			foreach ($options['data'] as $hostId => $texts) {
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
				$hostMacros = $this->findMacros(self::PATTERN_HOST, $options['data'][$hostId]);

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
					$ipMacros = $this->findMacros(self::PATTERN_IP, $options['data'][$hostId]);

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

		foreach ($options['data'] as $hostId => $texts) {
			// get user macros
			if ($this->isFunctionAvailable('user')) {
				$macros[$hostId] = !empty($macros[$hostId])
					? array_merge($macros[$hostId], $this->getUserMacros($texts, array('hostid' => $hostId)))
					: $this->getUserMacros($texts, array('hostid' => $hostId));
			}

			// replace macros to value
			foreach ($texts as $tnum => $text) {
				preg_match_all('/'.self::PATTERN_HOST.'|'.self::PATTERN_IP.'|'.ZBX_PREG_EXPRESSION_USER_MACROS.'/', $text, $matches, PREG_OFFSET_CAPTURE);

				for ($i = count($matches[0]) - 1; $i >= 0; $i--) {
					$matche = $matches[0][$i];

					$macrosValue = isset($macros[$hostId][$matche[0]]) ? $macros[$hostId][$matche[0]] : $matche[0];
					$text = substr_replace($text, $macrosValue, $matche[1], strlen($matche[0]));
				}

				$options['data'][$hostId][$tnum] = $text;
			}
		}

		return $options['data'];
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
	private function resolveTrigger(array $options) {
		$macros = array('host' => array(), 'ip' => array(), 'item' => array());
		$macroValues = array();

		// get source field
		$source = $this->getSource();

		// get available functions
		$isHostFunctionAvailable = $this->isFunctionAvailable('host');
		$isIpFunctionAvailable = $this->isFunctionAvailable('ip');
		$isItemFunctionAvailable = $this->isFunctionAvailable('item');
		$isUserFunctionAvailable = $this->isFunctionAvailable('user');

		// find macros
		foreach ($options['data'] as $triggerId => $trigger) {
			$options['data'][$triggerId][$source] = $this->resolveTriggerReference($trigger, $source);

			$functions = $this->findFunctions($trigger['expression']);

			if ($isHostFunctionAvailable) {
				foreach ($this->findFunctionMacros(self::PATTERN_HOST_FUNCTION, $trigger[$source]) as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						if (isset($functions[$fNum])) {
							$macros['host'][$functions[$fNum]][$macro][] = $fNum;
						}
					}
				}
			}

			if ($isIpFunctionAvailable) {
				foreach ($this->findFunctionMacros(self::PATTERN_IP_FUNCTION, $trigger[$source]) as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						if (isset($functions[$fNum])) {
							$macros['ip'][$functions[$fNum]][$macro][] = $fNum;
						}
					}
				}
			}

			if ($isItemFunctionAvailable) {
				foreach ($this->findFunctionMacros(self::PATTERN_ITEM_FUNCTION, $trigger[$source]) as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						if (isset($functions[$fNum])) {
							$macros['item'][$functions[$fNum]][$macro][] = $fNum;
						}
					}
				}
			}

			if ($isUserFunctionAvailable) {
				$macroValues[$trigger['triggerid']] = $this->getUserMacros(array($trigger[$source]), array('triggerid' => $trigger['triggerid']));
			}
		}

		// get macro value
		if ($isHostFunctionAvailable) {
			$macroValues = $this->resolveHostMacros($macros['host'], $macroValues);
		}
		if ($isIpFunctionAvailable) {
			$macroValues = $this->resolveIpMacros($macros['ip'], $macroValues);
		}
		if ($isItemFunctionAvailable) {
			$macroValues = $this->resolveItemMacros($macros['item'], $options['data'], $macroValues);
		}

		// replace values
		foreach ($options['data'] as $triggerId => $trigger) {
			$options['data'][$triggerId] = $this->replaceMacroValues($trigger, $macroValues[$triggerId]);
		}

		return $options['data'];
	}

	/**
	 * Expand reference macros for trigger.
	 * If macro reference non existing value it expands to empty string.
	 *
	 * @param array $trigger
	 *
	 * @return string
	 */
	public function resolveTriggerReference(array $trigger, $source = 'description') {
		$expression = $trigger['expression'];
		$text = $trigger[$source];

		// search for reference macros $1, $2, $3, ...
		preg_match_all('/\$([1-9])/', $text, $refNumbers);
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

		return str_replace($search, $replace, $text);
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
	private function resolveHostMacros(array $macros, array $macroValues = array()) {
		if (!empty($macros)) {
			$dbFuncs = DBselect(
				'SELECT DISTINCT f.triggerid,f.functionid,h.host,h.name'.
				' FROM functions f'.
					' INNER JOIN items i ON f.itemid=i.itemid'.
					' INNER JOIN hosts h ON i.hostid=h.hostid'.
				' WHERE '.DBcondition('f.functionid', array_keys($macros))
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

					$macroValues = $this->prepareMacroValues($macroValues, $fNums, $func['triggerid'], $macro, $replace);
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
	private function resolveIpMacros(array $macros, array $macroValues = array()) {
		if (!empty($macros)) {
			$dbInterfaces = DBselect(
				'SELECT DISTINCT f.triggerid,f.functionid,n.ip,n.dns,n.type,n.useip'.
				' FROM functions f'.
					' INNER JOIN items i ON f.itemid=i.itemid'.
					' INNER JOIN interface n ON i.hostid=n.hostid'.
				' WHERE '.DBcondition('f.functionid', array_keys($macros)).
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

					$macroValues = $this->prepareMacroValues($macroValues, $fNums, $interface['triggerid'], $macro, $replace);
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
	private function resolveItemMacros(array $macros, array $triggers, array $macroValues = array()) {
		if (!empty($macros)) {
			$dbFuncs = DBselect(
				'SELECT DISTINCT f.triggerid,f.functionid,i.itemid,i.lastvalue,i.lastclock,i.value_type,i.units,i.valuemapid,m.newvalue'.
				' FROM functions f'.
					' INNER JOIN items i ON f.itemid=i.itemid'.
					' INNER JOIN hosts h ON i.hostid=h.hostid'.
					' LEFT JOIN mappings m ON i.valuemapid=m.valuemapid AND i.lastvalue=m.value'.
				' WHERE '.DBcondition('f.functionid', array_keys($macros))
			);
			// false passed to DBfetch to get data without null converted to 0, which is done by default
			while ($func = DBfetch($dbFuncs, false)) {
				foreach ($macros[$func['functionid']] as $macro => $fNums) {
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
	 * Resolve {ITEM.LASTVALUE} macro.
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	private function resolveItemLastvalueMacro(array $item) {
		return is_null($item['newvalue'])
			? formatItemValue($item, UNRESOLVED_MACRO_STRING)
			: $item['newvalue'].' ('.$item['lastvalue'].')';
	}

	/**
	 * Resolve {ITEM.VALUE} macro.
	 * For triggers macro is resolved in same way as {ITEM.LASTVALUE} macro. Separate methods are created for event description,
	 * where {ITEM.VALUE} macro resolves in different way.
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
	 * Replace macros in trigger text by values.
	 * All macros are resolved in one go.
	 *
	 * @param array $trigger
	 * @param array $macroValues
	 *
	 * @return array
	 */
	private function replaceMacroValues(array $trigger, array $macroValues) {
		$source = $this->getSource();

		$macroBegin = false;
		for ($i = 0; $i < zbx_strlen($trigger[$source]); $i++) {
			$c = zbx_substr($trigger[$source], $i, 1);

			switch ($c) {
				case '{':
					$macroBegin = $i;
					break;
				case '}':
					if ($macroBegin !== false) {
						$macro = zbx_substr($trigger[$source], $macroBegin, $i - $macroBegin + 1);
						if (isset($macroValues[$macro])) {
							$replace = $macroValues[$macro];
						}
						elseif (preg_match('/'.self::PATTERN_ALLOWED.'/', $macro)) {
							$replace = UNRESOLVED_MACRO_STRING;
						}
						else {
							$replace = false;
						}

						if ($replace !== false) {
							$trigger[$source] = zbx_substr_replace(
								$trigger[$source],
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
	 * Add macro name with corresponding value to replace to $macroValues array.
	 *
	 * @param array  $macroValues
	 * @param array  $fNums
	 * @param int    $triggerId
	 * @param string $macro
	 * @param string $replace
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

	/**
	 * Is function available.
	 *
	 * @param string $function
	 *
	 * @return bool
	 */
	private function isFunctionAvailable($function) {
		$functions = array_flip($this->configs[$this->config]['functions']);

		return isset($functions[$function]);
	}

	/**
	 * Get source field.
	 *
	 * @return string
	 */
	private function getSource() {
		return $this->configs[$this->config]['source'];
	}

}
