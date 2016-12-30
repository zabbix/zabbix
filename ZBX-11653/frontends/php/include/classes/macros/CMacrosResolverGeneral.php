<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CMacrosResolverGeneral {

	const PATTERN_HOST_INTERNAL = 'HOST\.HOST|HOSTNAME';
	const PATTERN_MACRO_PARAM = '[1-9]?';

	/**
	 * Interface priorities.
	 *
	 * @var array
	 */
	protected $interfacePriorities = [
		INTERFACE_TYPE_AGENT => 4,
		INTERFACE_TYPE_SNMP => 3,
		INTERFACE_TYPE_JMX => 2,
		INTERFACE_TYPE_IPMI => 1
	];

	/**
	 * Work config name.
	 *
	 * @var string
	 */
	protected $config = '';

	/**
	 * Get reference macros for trigger.
	 * If macro reference non existing value it expands to empty string.
	 *
	 * @param string $expression
	 * @param array  $references
	 *
	 * @return array
	 */
	protected function resolveTriggerReferences($expression, $references) {
		$matched_macros = $this->getMacroPositions($expression, ['usermacros' => true]);

		// Replace user macros with string 'macro' to make values search easier.
		foreach (array_reverse($matched_macros, true) as $pos => $macro) {
			$expression = substr_replace($expression, 'macro', $pos, strlen($macro));
		}

		// Replace functionids with string 'function' to make values search easier.
		$expression = preg_replace('/\{[0-9]+\}/', 'function', $expression);

		// Search for numeric values in expression.
		preg_match_all('/'.ZBX_PREG_NUMBER.'/', $expression, $values);

		foreach ($references as $reference => &$value) {
			$i = (int) $reference[1] - 1;
			$value = array_key_exists($i, $values[0]) ? $values[0][$i] : '';
		}
		unset($value);

		return $references;
	}

	/**
	 * Checking existence of the macros.
	 *
	 * @param array  $texts
	 * @param array  $type
	 *
	 * @return bool
	 */
	protected function hasMacros(array $texts, array $types) {
		foreach ($texts as $text) {
			if ($this->getMacroPositions($text, $types)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Transform types, used in extractMacros() function to types which can be used in getMacroPositions().
	 *
	 * @param array  $types
	 *
	 * @return array
	 */
	protected function transformToPositionTypes(array $types) {
		foreach (['macros', 'macros_n'] as $type) {
			if (array_key_exists($type, $types)) {
				$patterns = [];
				foreach ($types[$type] as $key => $_patterns) {
					$patterns = array_merge($patterns, $_patterns);
				}
				$types[$type] = $patterns;
			}
		}

		return $types;
	}

	/**
	 * Extract positions of the macros from a string.
	 *
	 * @param string $text
	 * @param array  $types
	 * @param bool   $types['usermacros']
	 * @param array  $types['macros'][<macro_patterns>]
	 * @param array  $types['macros_n'][<macro_patterns>]
	 * @param bool   $types['references']
	 * @param bool   $types['lldmacros']
	 * @param bool   $types['functionids']
	 *
	 * @return array
	 */
	protected function getMacroPositions($text, array $types) {
		$macros = [];
		$extract_usermacros = array_key_exists('usermacros', $types);
		$extract_macros = array_key_exists('macros', $types);
		$extract_macros_n = array_key_exists('macros_n', $types);
		$extract_references = array_key_exists('references', $types);
		$extract_lldmacros = array_key_exists('lldmacros', $types);
		$extract_functionids = array_key_exists('functionids', $types);

		if ($extract_usermacros) {
			$user_macro_parser = new CUserMacroParser();
		}

		if ($extract_macros) {
			$macro_parser = new CMacroParser($types['macros']);
		}

		if ($extract_macros_n) {
			$macro_n_parser = new CMacroParser($types['macros_n'], ['allow_reference' => true]);
		}

		if ($extract_references) {
			$reference_parser = new CReferenceParser();
		}

		if ($extract_lldmacros) {
			$lld_macro_parser = new CLLDMacroParser();
		}

		if ($extract_functionids) {
			$functionid_parser = new CFunctionIdParser();
		}

		for ($pos = 0; isset($text[$pos]); $pos++) {
			if ($extract_usermacros && $user_macro_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
				$macros[$pos] = $user_macro_parser->getMatch();
				$pos += $user_macro_parser->getLength() - 1;
			}
			elseif ($extract_macros && $macro_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
				$macros[$pos] = $macro_parser->getMatch();
				$pos += $macro_parser->getLength() - 1;
			}
			elseif ($extract_macros_n && $macro_n_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
				$macros[$pos] = $macro_n_parser->getMatch();
				$pos += $macro_n_parser->getLength() - 1;
			}
			elseif ($extract_references && $reference_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
				$macros[$pos] = $reference_parser->getMatch();
				$pos += $reference_parser->getLength() - 1;
			}
			elseif ($extract_lldmacros && $lld_macro_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
				$macros[$pos] = $lld_macro_parser->getMatch();
				$pos += $lld_macro_parser->getLength() - 1;
			}
			elseif ($extract_functionids && $functionid_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
				$macros[$pos] = $functionid_parser->getMatch();
				$pos += $functionid_parser->getLength() - 1;
			}
		}

		return $macros;
	}

	/**
	 * Extract macros from a string.
	 *
	 * @param array  $texts
	 * @param array  $types
	 * @param bool   $types['usermacros']
	 * @param array  $types['macros'][][<macro_patterns>]
	 * @param array  $types['macros_n'][][<macro_patterns>]
	 * @param bool   $types['references']
	 * @param bool   $types['lldmacros']
	 * @param bool   $types['functionids']
	 *
	 * @return array
	 */
	protected function extractMacros(array $texts, array $types) {
		$macros = [];
		$extract_usermacros = array_key_exists('usermacros', $types);
		$extract_macros = array_key_exists('macros', $types);
		$extract_macros_n = array_key_exists('macros_n', $types);
		$extract_references = array_key_exists('references', $types);
		$extract_lldmacros = array_key_exists('lldmacros', $types);
		$extract_functionids = array_key_exists('functionids', $types);

		if ($extract_usermacros) {
			$macros['usermacros'] = [];

			$user_macro_parser = new CUserMacroParser();
		}

		if ($extract_macros) {
			$macros['macros'] = [];

			foreach ($types['macros'] as $key => $macro_patterns) {
				$types['macros'][$key] = new CMacroParser($macro_patterns);
				$macros['macros'][$key] = [];
			}
		}

		if ($extract_macros_n) {
			$macros['macros_n'] = [];

			foreach ($types['macros_n'] as $key => $macro_patterns) {
				$types['macros_n'][$key] = new CMacroParser($macro_patterns, ['allow_reference' => true]);
				$macros['macros_n'][$key] = [];
			}
		}

		if ($extract_references) {
			$macros['references'] = [];

			$reference_parser = new CReferenceParser();
		}

		if ($extract_lldmacros) {
			$macros['lldmacros'] = [];

			$lld_macro_parser = new CLLDMacroParser();
		}

		if ($extract_functionids) {
			$macros['functionids'] = [];

			$functionid_parser = new CFunctionIdParser();
		}

		foreach ($texts as $text) {
			for ($pos = 0; isset($text[$pos]); $pos++) {
				if ($extract_usermacros && $user_macro_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
					$macros['usermacros'][$user_macro_parser->getMatch()] = null;
					$pos += $user_macro_parser->getLength() - 1;
					continue;
				}

				if ($extract_macros) {
					foreach ($types['macros'] as $key => $macro_parser) {
						if ($macro_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
							$macros['macros'][$key][$macro_parser->getMatch()] = true;
							$pos += $macro_parser->getLength() - 1;
							continue 2;
						}
					}
				}

				if ($extract_macros_n) {
					foreach ($types['macros_n'] as $key => $macro_n_parser) {
						if ($macro_n_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
							$macros['macros_n'][$key][$macro_n_parser->getMacro()][] = $macro_n_parser->getN();
							$pos += $macro_n_parser->getLength() - 1;
							continue 2;
						}
					}
				}

				if ($extract_references && $reference_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
					$macros['references'][$reference_parser->getMatch()] = null;
					$pos += $reference_parser->getLength() - 1;
					continue;
				}

				if ($extract_lldmacros && $lld_macro_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
					$macros['lldmacros'][$lld_macro_parser->getMatch()] = null;
					$pos += $lld_macro_parser->getLength() - 1;
					continue;
				}

				if ($extract_functionids && $functionid_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
					$macros['functionids'][$functionid_parser->getMatch()] = null;
					$pos += $functionid_parser->getLength() - 1;
					continue;
				}
			}
		}

		if ($extract_macros) {
			foreach ($types['macros'] as $key => $macro_parser) {
				$macros['macros'][$key] = array_keys($macros['macros'][$key]);
			}
		}

		return $macros;
	}

	/**
	 * Returns the list of the item key parameters.
	 *
	 * @param string $params_raw
	 *
	 * @return array
	 */
	private function getItemKeyParameters($params_raw) {
		$item_key_parameters = [];

		foreach ($params_raw as $param_raw) {
			switch ($param_raw['type']) {
				case CItemKey::PARAM_ARRAY:
					$item_key_parameters = array_merge($item_key_parameters,
						$this->getItemKeyParameters($param_raw['parameters'])
					);
					break;

				case CItemKey::PARAM_UNQUOTED:
					$item_key_parameters[] = $param_raw['raw'];
					break;

				case CItemKey::PARAM_QUOTED:
					$item_key_parameters[] = CItemKey::unquoteParam($param_raw['raw']);
					break;
			}
		}

		return $item_key_parameters;
	}

	/**
	 * Extract macros from an item key.
	 *
	 * @param string $key		an item key
	 * @param array  $types		the types of macros (see extractMacros() for more details)
	 *
	 * @return array			see extractMacros() for more details
	 */
	protected function extractItemKeyMacros($key, array $types) {
		$item_key_parser = new CItemKey();

		$item_key_parameters = [];
		if ($item_key_parser->parse($key) == CParser::PARSE_SUCCESS) {
			$item_key_parameters = $this->getItemKeyParameters($item_key_parser->getParamsRaw());
		}

		return $this->extractMacros($item_key_parameters, $types);
	}

	/**
	 * Extract macros from a trigger function.
	 *
	 * @param string $function	a trigger function, for example 'last({$LAST})'
	 * @param array  $types		the types of macros (see extractMacros() for more details)
	 *
	 * @return array			see extractMacros() for more details
	 */
	protected function extractFunctionMacros($function, array $types) {
		$function_parser = new CFunctionParser();

		$function_parameters = [];
		if ($function_parser->parse($function) == CParser::PARSE_SUCCESS) {
			foreach ($function_parser->getParamsRaw()['parameters'] as $param_raw) {
				switch ($param_raw['type']) {
					case CFunctionParser::PARAM_UNQUOTED:
						$function_parameters[] = $param_raw['raw'];
						break;

					case CFunctionParser::PARAM_QUOTED:
						$function_parameters[] = CFunctionParser::unquoteParam($param_raw['raw']);
						break;
				}
			}
		}

		return $this->extractMacros($function_parameters, $types);
	}

	/**
	 * Resolves macros in the item key parameters.
	 *
	 * @param string $key_chain		an item key chain
	 * @param string $params_raw
	 * @param array  $macros		the list of macros (['{<MACRO>}' => '<value>', ...])
	 * @param array  $types			the types of macros (see getMacroPositions() for more details)
	 *
	 * @return string
	 */
	private function resolveItemKeyParamsMacros($key_chain, array $params_raw, array $macros, array $types) {
		foreach (array_reverse($params_raw) as $param_raw) {
			$param = $param_raw['raw'];
			$forced = false;

			switch ($param_raw['type']) {
				case CItemKey::PARAM_ARRAY:
					$param = $this->resolveItemKeyParamsMacros($param, $param_raw['parameters'], $macros, $types);
					break;

				case CItemKey::PARAM_QUOTED:
					$param = CItemKey::unquoteParam($param);
					$forced = true;
					// break; is not missing here

				case CItemKey::PARAM_UNQUOTED:
					$matched_macros = $this->getMacroPositions($param, $types);

					foreach (array_reverse($matched_macros, true) as $pos => $macro) {
						$param = substr_replace($param, $macros[$macro], $pos, strlen($macro));
					}

					$param = quoteItemKeyParam($param, $forced);
					break;
			}

			$key_chain = substr_replace($key_chain, $param, $param_raw['pos'], strlen($param_raw['raw']));
		}

		return $key_chain;
	}

	/**
	 * Resolves macros in the item key.
	 *
	 * @param string $key		an item key
	 * @param array  $macros	the list of macros (['{<MACRO>}' => '<value>', ...])
	 * @param array  $types		the types of macros (see getMacroPositions() for more details)
	 *
	 * @return string
	 */
	protected function resolveItemKeyMacros($key, array $macros, array $types) {
		$item_key_parser = new CItemKey();

		if ($item_key_parser->parse($key) == CParser::PARSE_SUCCESS) {
			$key = $this->resolveItemKeyParamsMacros($key, $item_key_parser->getParamsRaw(), $macros, $types);
		}

		return $key;
	}

	/**
	 * Resolves macros in the trigger function parameters.
	 *
	 * @param string $function	a trigger function
	 * @param array  $macros	the list of macros (['{<MACRO>}' => '<value>', ...])
	 * @param array  $types		the types of macros (see getMacroPositions() for more details)
	 *
	 * @return string
	 */
	protected function resolveFunctionMacros($function, array $macros, array $types) {
		$function_parser = new CFunctionParser();

		if ($function_parser->parse($function) == CParser::PARSE_SUCCESS) {
			$params_raw = $function_parser->getParamsRaw();
			$function_chain = $params_raw['raw'];

			foreach (array_reverse($params_raw['parameters']) as $param_raw) {
				$param = $param_raw['raw'];
				$forced = false;

				switch ($param_raw['type']) {
					case CFunctionParser::PARAM_QUOTED:
						$param = CFunctionParser::unquoteParam($param);
						$forced = true;
						// break; is not missing here

					case CFunctionParser::PARAM_UNQUOTED:
						$matched_macros = $this->getMacroPositions($param, $types);

						foreach (array_reverse($matched_macros, true) as $pos => $macro) {
							$param = substr_replace($param, $macros[$macro], $pos, strlen($macro));
						}

						$param = quoteFunctionParam($param, $forced);
						break;
				}

				$function_chain = substr_replace($function_chain, $param, $param_raw['pos'], strlen($param_raw['raw']));
			}

			$function = substr_replace($function, $function_chain, $params_raw['pos'], strlen($params_raw['raw']));
		}

		return $function;
	}

	/**
	 * Find function ids in trigger expression.
	 *
	 * @param string $expression
	 *
	 * @return array	where key is function id position in expression and value is function id
	 */
	protected function findFunctions($expression) {
		$functionids = [];

		$functionid_parser = new CFunctionIdParser();
		$macro_parser = new CMacroParser(['{TRIGGER.VALUE}']);
		$user_macro_parser = new CUserMacroParser();

		for ($pos = 0, $i = 1; isset($expression[$pos]); $pos++) {
			if ($functionid_parser->parse($expression, $pos) != CParser::PARSE_FAIL) {
				$pos += $functionid_parser->getLength() - 1;
				$functionids[$i++] = substr($functionid_parser->getMatch(), 1, -1);
			}
			elseif ($user_macro_parser->parse($expression, $pos) != CParser::PARSE_FAIL) {
				$pos += $user_macro_parser->getLength() - 1;
			}
			elseif ($macro_parser->parse($expression, $pos) != CParser::PARSE_FAIL) {
				$pos += $macro_parser->getLength() - 1;
			}
		}

		if (array_key_exists(1, $functionids)) {
			$functionids[0] = $functionids[1];
		}

		return $functionids;
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
	protected function getFunctionMacroValues(array $macroValues, array $fNums, $triggerId, $macro, $replace) {
		foreach ($fNums as $fNum) {
			$macroValues[$triggerId][$this->getFunctionMacroName($macro, $fNum)] = $replace;
		}

		return $macroValues;
	}

	/**
	 * Get {ITEM.LASTVALUE} macro.
	 *
	 * @param mixed $lastValue
	 * @param array $item
	 *
	 * @return string
	 */
	protected function getItemLastValueMacro($lastValue, array $item) {
		return ($lastValue === null) ? UNRESOLVED_MACRO_STRING : formatHistoryValue($lastValue, $item);
	}

	/**
	 * Get function macro name.
	 *
	 * @param string $macro
	 * @param int    $fNum
	 *
	 * @return string
	 */
	protected function getFunctionMacroName($macro, $fNum) {
		return '{'.(($fNum == 0) ? $macro : $macro.$fNum).'}';
	}

	/**
	 * Get interface macros.
	 *
	 * @param array $macros
	 * @param array $macroValues
	 * @param bool  $port
	 *
	 * @return array
	 */
	protected function getIpMacros(array $macros, array $macroValues, $port) {
		if ($macros) {
			$selectPort = $port ? ',n.port' : '';

			$dbInterfaces = DBselect(
				'SELECT f.triggerid,f.functionid,n.ip,n.dns,n.type,n.useip'.$selectPort.
				' FROM functions f'.
					' JOIN items i ON f.itemid=i.itemid'.
					' JOIN interface n ON i.hostid=n.hostid'.
				' WHERE '.dbConditionInt('f.functionid', array_keys($macros)).
					' AND n.main=1'
			);

			// Macro should be resolved to interface with highest priority ($priorities).
			$interfaces = [];

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
						case 'HOST.PORT':
							$replace = $interface['port'];
							break;
					}

					$macroValues = $this->getFunctionMacroValues($macroValues, $fNums, $interface['triggerid'], $macro, $replace);
				}
			}
		}

		return $macroValues;
	}

	/**
	 * Get item macros.
	 *
	 * @param array $macros
	 * @param array $triggers
	 * @param array $macroValues
	 * @param bool  $events			resolve {ITEM.VALUE} macro using 'clock' and 'ns' fields
	 *
	 * @return array
	 */
	protected function getItemMacros(array $macros, array $triggers, array $macroValues, $events) {
		if ($macros) {
			$functions = DbFetchArray(DBselect(
				'SELECT f.triggerid,f.functionid,i.itemid,i.value_type,i.units,i.valuemapid'.
				' FROM functions f'.
					' JOIN items i ON f.itemid=i.itemid'.
					' JOIN hosts h ON i.hostid=h.hostid'.
				' WHERE '.dbConditionInt('f.functionid', array_keys($macros))
			));

			$history = Manager::History()->getLast($functions, 1, ZBX_HISTORY_PERIOD);

			// False passed to DBfetch to get data without null converted to 0, which is done by default.
			foreach ($functions as $func) {
				foreach ($macros[$func['functionid']] as $macro => $fNums) {
					$lastValue = isset($history[$func['itemid']]) ? $history[$func['itemid']][0]['value'] : null;

					switch ($macro) {
						case 'ITEM.LASTVALUE':
							$replace = $this->getItemLastValueMacro($lastValue, $func);
							break;
						case 'ITEM.VALUE':
							if ($events) {
								$trigger = $triggers[$func['triggerid']];
								$value = item_get_history($func, $trigger['clock'], $trigger['ns']);

								$replace = ($value === null)
									? UNRESOLVED_MACRO_STRING
									: formatHistoryValue($value, $func);
							}
							else {
								$replace = $this->getItemLastValueMacro($lastValue, $func);
							}
							break;
					}

					$macroValues = $this->getFunctionMacroValues($macroValues, $fNums, $func['triggerid'], $macro, $replace);
				}
			}
		}

		return $macroValues;
	}

	/**
	 * Get host macros.
	 *
	 * @param array $macros
	 * @param array $macroValues
	 *
	 * @return array
	 */
	protected function getHostMacros(array $macros, array $macroValues) {
		if ($macros) {
			$dbFuncs = DBselect(
				'SELECT f.triggerid,f.functionid,h.hostid,h.host,h.name'.
				' FROM functions f'.
					' JOIN items i ON f.itemid=i.itemid'.
					' JOIN hosts h ON i.hostid=h.hostid'.
				' WHERE '.dbConditionInt('f.functionid', array_keys($macros))
			);
			while ($func = DBfetch($dbFuncs)) {
				foreach ($macros[$func['functionid']] as $macro => $fNums) {
					switch ($macro) {
						case 'HOST.ID':
							$replace = $func['hostid'];
							break;

						case 'HOSTNAME':
						case 'HOST.HOST':
							$replace = $func['host'];
							break;

						case 'HOST.NAME':
							$replace = $func['name'];
							break;
					}

					$macroValues = $this->getFunctionMacroValues($macroValues, $fNums, $func['triggerid'], $macro, $replace);
				}
			}
		}

		return $macroValues;
	}

	/**
	 * Is type available.
	 *
	 * @param string $type
	 *
	 * @return bool
	 */
	protected function isTypeAvailable($type) {
		return in_array($type, $this->configs[$this->config]['types']);
	}

	/**
	 * Get source field.
	 *
	 * @return string
	 */
	protected function getSource() {
		return $this->configs[$this->config]['source'];
	}

	/**
	 * Get macros with values.
	 *
	 * @param array $data
	 * @param array $data[<id>]			any identificator
	 * @param array $data['hostids']	the list of host ids; [<hostid1>, ...]
	 * @param array $data['macros']		the list of user macros to resolve, ['<usermacro1>' => null, ...]
	 *
	 * @return array
	 */
	protected function getUserMacros(array $data) {
		// User macros.
		$hostids = [];
		foreach ($data as $element) {
			foreach ($element['hostids'] as $hostid) {
				$hostids[$hostid] = true;
			}
		}

		if (!$hostids) {
			return $data;
		}

		/*
		 * @var array $host_templates
		 * @var array $host_templates[<hostid>]		array of templates
		 */
		$host_templates = [];

		/*
		 * @var array  $host_macros
		 * @var array  $host_macros[<hostid>]
		 * @var array  $host_macros[<hostid>][<macro>]				macro base without curly braces
		 * @var string $host_macros[<hostid>][<macro>]['value']		base macro value (without context); can be null
		 * @var array  $host_macros[<hostid>][<macro>]['contexts']	context values; ['<context1>' => '<value1>', ...]
		 */
		$host_macros = [];

		$user_macro_parser = new CUserMacroParser();

		do {
			$db_host_macros = DBselect(
				'SELECT hm.hostid,hm.macro,hm.value'.
				' FROM hostmacro hm'.
				' WHERE '.dbConditionInt('hm.hostid', array_keys($hostids))
			);
			while ($db_host_macro = DBfetch($db_host_macros)) {
				if ($user_macro_parser->parse($db_host_macro['macro']) != CParser::PARSE_SUCCESS) {
					continue;
				}

				$macro = $user_macro_parser->getMacro();
				$context = $user_macro_parser->getContext();
				$host_macros[$db_host_macro['hostid']][$macro] = ['value' => null, 'contexts' => []];

				if ($context === null) {
					$host_macros[$db_host_macro['hostid']][$macro]['value'] = $db_host_macro['value'];
				}
				else {
					$host_macros[$db_host_macro['hostid']][$macro]['contexts'][$context] = $db_host_macro['value'];
				}
			}

			$templateids = [];
			$db_host_templates = DBselect(
				'SELECT ht.hostid,ht.templateid'.
				' FROM hosts_templates ht'.
				' WHERE '.dbConditionInt('ht.hostid', array_keys($hostids))
			);
			while ($db_host_template = DBfetch($db_host_templates)) {
				$host_templates[$db_host_template['hostid']][] = $db_host_template['templateid'];
				$templateids[$db_host_template['templateid']] = true;

				if (!array_key_exists($db_host_template['hostid'], $host_macros)) {
					$host_macros[$db_host_template['hostid']] = [];
				}
			}

			foreach ($hostids as $key => $value) {
				if (!array_key_exists($key, $host_templates)) {
					$host_templates[$key] = [];
				}
			}

			// only unprocessed templates will be populated
			$hostids = [];
			foreach ($templateids as $key => $value) {
				if (!array_key_exists($key, $host_templates)) {
					$hostids[$key] = true;
				}
			}
		} while ($hostids);

		$all_macros_resolved = true;

		$user_macro_parser = new CUserMacroParser();

		foreach ($data as &$element) {
			$hostids = [];
			foreach ($element['hostids'] as $hostid) {
				$hostids[$hostid] = true;
			}

			$hostids = array_keys($hostids);
			natsort($hostids);

			foreach ($element['macros'] as $usermacro => &$value) {
				if ($user_macro_parser->parse($usermacro) == CParser::PARSE_SUCCESS) {
					$value = $this->getHostUserMacros($hostids, $user_macro_parser->getMacro(),
						$user_macro_parser->getContext(), $host_templates, $host_macros
					);

					if ($value['value'] === null) {
						$all_macros_resolved = false;
					}
				}
				else {
					// This macro cannot be resolved.
					$value = ['value' => $usermacro, 'value_default' => null];
				}
			}
			unset($value);
		}
		unset($element);

		if (!$all_macros_resolved) {
			// Global macros.
			$db_global_macros = API::UserMacro()->get([
				'output' => ['macro', 'value'],
				'globalmacro' => true
			]);

			/*
			 * @var array  $global_macros
			 * @var array  $global_macros[<macro>]				macro base without curly braces
			 * @var string $global_macros[<macro>]['value']		base macro value (without context); can be null
			 * @var array  $global_macros[<macro>]['contexts']	context values; ['<context1>' => '<value1>', ...]
			 */
			$global_macros = [];

			foreach ($db_global_macros as $db_global_macro) {
				if ($user_macro_parser->parse($db_global_macro['macro']) == CParser::PARSE_SUCCESS) {
					$macro = $user_macro_parser->getMacro();
					$context = $user_macro_parser->getContext();

					if (!array_key_exists($macro, $global_macros)) {
						$global_macros[$macro] = ['value' => null, 'contexts' => []];
					}

					if ($context === null) {
						$global_macros[$macro]['value'] = $db_global_macro['value'];
					}
					else {
						$global_macros[$macro]['contexts'][$context] = $db_global_macro['value'];
					}
				}
			}

			foreach ($data as &$element) {
				foreach ($element['macros'] as $usermacro => &$value) {
					if ($value['value'] === null && $user_macro_parser->parse($usermacro) == CParser::PARSE_SUCCESS) {
						$macro = $user_macro_parser->getMacro();
						$context = $user_macro_parser->getContext();

						if (array_key_exists($macro, $global_macros)) {
							if ($context !== null && array_key_exists($context, $global_macros[$macro]['contexts'])) {
								$value['value'] = $global_macros[$macro]['contexts'][$context];
							}
							elseif ($global_macros[$macro]['value'] !== null) {
								if ($context === null) {
									$value['value'] = $global_macros[$macro]['value'];
								}
								elseif ($value['value_default'] === null) {
									$value['value_default'] = $global_macros[$macro]['value'];
								}
							}
						}
					}
				}
				unset($value);
			}
			unset($element);
		}

		foreach ($data as &$element) {
			foreach ($element['macros'] as $usermacro => &$value) {
				if ($value['value'] !== null) {
					$value = $value['value'];
				}
				elseif ($value['value_default'] !== null) {
					$value = $value['value_default'];
				}
				else {
					// Unresolved macro.
					$value = $usermacro;
				}
			}
			unset($value);
		}
		unset($element);

		return $data;
	}

	/**
	 * Get user macro from the requested hosts.
	 *
	 * Use the base value returned by host macro as default value when expanding expand global macro. This will ensure
	 * the following user macro resolving priority:
	 *  1) host/template context macro
	 *  2) global context macro
	 *  3) host/template base (default) macro
	 *  4) global base (default) macro
	 *
	 * @param array  $hostids			The sorted list of hosts where macros will be looked for (hostid => hostid)
	 * @param string $macro				Macro base without curly braces, for example: SNMP_COMMUNITY
	 * @param string $context			Macro context to resolve
	 * @param array  $host_templates	The list of linked templates (see getUserMacros() for more details)
	 * @param array  $host_macros		The list of macros on hosts (see getUserMacros() for more details)
	 * @param string $value_default		Value
	 *
	 * @return array
	 */
	private function getHostUserMacros(array $hostids, $macro, $context, array $host_templates, array $host_macros,
			$value_default = null) {
		foreach ($hostids as $hostid) {
			if (array_key_exists($hostid, $host_macros) && array_key_exists($macro, $host_macros[$hostid])) {
				if ($context !== null && array_key_exists($context, $host_macros[$hostid][$macro]['contexts'])) {
					return [
						'value' => $host_macros[$hostid][$macro]['contexts'][$context],
						'value_default' => $value_default
					];
				}

				if ($host_macros[$hostid][$macro]['value'] !== null) {
					if ($context === null) {
						return ['value' => $host_macros[$hostid][$macro]['value'], 'value_default' => $value_default];
					}
					elseif ($value_default === null) {
						$value_default = $host_macros[$hostid][$macro]['value'];
					}
				}
			}
		}

		if (!$host_templates) {
			return ['value' => null, 'value_default' => $value_default];
		}

		$templateids = [];

		foreach ($hostids as $hostid) {
			if (array_key_exists($hostid, $host_templates)) {
				foreach ($host_templates[$hostid] as $templateid) {
					$templateids[$templateid] = true;
				}
			}
		}

		if ($templateids) {
			$templateids = array_keys($templateids);
			natsort($templateids);

			return $this->getHostUserMacros($templateids, $macro, $context, $host_templates, $host_macros,
				$value_default
			);
		}

		return ['value' => null, 'value_default' => $value_default];
	}
}
