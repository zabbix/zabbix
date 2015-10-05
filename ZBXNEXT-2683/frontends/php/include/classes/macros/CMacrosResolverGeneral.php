<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

		// replace user macros with string 'macro' to make values search easier
		foreach (array_reverse($matched_macros, true) as $pos => $macro) {
			$text = substr_replace($expression, 'macro', $pos, strlen($macro));
		}

		// replace functionids with string 'function' to make values search easier
		$expression = preg_replace('/\{[0-9]+\}/', 'function', $expression);

		// search for numeric values in expression
		preg_match_all('/'.ZBX_PREG_NUMBER.'/', $expression, $values);

		foreach ($references as $reference => &$value) {
			$i = (int) $reference[1] - 1;
			$value = array_key_exists($i, $values[0]) ? $values[0][$i] : '';
		}
		unset($value);

		return $references;
	}

	/**
	 * Parse reference macros
	 *
	 * @param array  $text
	 * @param int    $pos
	 *
	 * @return bool|array
	 */
	private function parseReferenceMacro($text, $pos) {
		if ($text[$pos] != '$' || !isset($text[$pos + 1])) {
			return false;
		}

		if ($text[$pos + 1] < '1' || $text[$pos + 1] > '9') {
			return false;
		}

		return [
			'match' => substr($text, $pos, 2),
			'length' => 2
		];
	}

	/**
	 * Parse functionid macros like {23425}
	 *
	 * @param array  $expression
	 * @param int    $pos
	 *
	 * @return bool|array
	 */
	private function parseFunctionId($expression, $pos) {
		if ($expression[$pos] != '{') {
			return false;
		}

		for ($p = $pos + 1; isset($expression[$p]) && ctype_digit($expression[$p]); $p++)
			;

		if ($p == $pos + 1 || $expression[$p] != '}') {
			return false;
		}

		$p++;

		return [
			'match' => substr($expression, $pos, $p - $pos),
			'length' => $p - $pos
		];
	}

	/**
	 * Parse macros like {HOST.HOST<1-9>}
	 *
	 * @param array  $text
	 * @param int    $pos
	 * @param array  $macros	the list of macros like ['{HOST.HOST}', '{HOST.NAME}']
	 *
	 * @return bool|array
	 */
	private function parseMacrosN($text, $pos, $macros) {
		if ($text[$pos] != '{') {
			return false;
		}

		$p = $pos + 1;

		$set_parser = new CSetParser(array_map(function($macro) { return substr($macro, 1, -1); }, $macros));

		if ($set_parser->parse($text, $p) == CParser::PARSE_FAIL) {
			return false;
		}
		$n = 0;
		$p += $set_parser->getLength();

		if ($text[$p] >= '1' && $text[$p] <= '9') {
			$n = (int) $text[$p];
			$p++;
		}

		if ($text[$p] != '}') {
			return false;
		}
		$p++;

		return [
			'match' => substr($text, $pos, $p - $pos),
			'macro' => $set_parser->getMatch(),
			'n' => $n,
			'length' => $p - $pos
		];
	}

	/**
	 * Checking existance of the macros.
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
	 * Transform types, used in extractMacros() function to types which can be used in getMacroPositions()
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
	 * @param array  $types		supported types:
	 * 								[
	 * 									'usermacros' => true,
	 * 									'macros' => [<macro_pattern>, ...]
	 * 									'macros_n' => [<macro_pattern>, ...]
	 * 									'references' => true
	 * 								]
	 *
	 * @return array
	 */
	protected function getMacroPositions($text, array $types) {
		$macros = [];
		$extract_usermacros = array_key_exists('usermacros', $types);
		$extract_macros = array_key_exists('macros', $types);
		$extract_macros_n = array_key_exists('macros_n', $types);
		$extract_references = array_key_exists('references', $types);

		if ($extract_usermacros) {
			$user_macro_parser = new CUserMacroParser();
		}

		if ($extract_macros) {
			$set_parser = new CSetParser($types['macros']);
		}

		for ($pos = 0; isset($text[$pos]); $pos++) {
			if ($extract_usermacros && $user_macro_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
				$macros[$pos] = $user_macro_parser->getMatch();
				$pos += $user_macro_parser->getLength() - 1;
			}
			elseif ($extract_macros && $set_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
				$macros[$pos] = $set_parser->getMatch();
				$pos += $set_parser->getLength() - 1;
			}
			elseif ($extract_macros_n && ($result = $this->parseMacrosN($text, $pos, $types['macros_n'])) !== false) {
				$macros[$pos] = $result['match'];
				$pos += $result['length'] - 1;
			}
			elseif ($extract_references && ($result = $this->parseReferenceMacro($text, $pos)) != false) {
				$macros[$pos] = $result['match'];
				$pos += $result['length'] - 1;
			}
		}

		return $macros;
	}

	/**
	 * Extract macros from a string.
	 *
	 * @param array  $texts
	 * @param array  $types		supported types:
	 * 								[
	 * 									'usermacros' => true,
	 * 									'macros' => [<key> => [<macro_pattern>, ...], ...],
	 * 									'macros_n' => [<key> => [<macro_pattern>, ...], ...],
	 * 									'references' => true
	 * 								]
	 *
	 * @return array
	 * 								[
	 * 									'usermacros' => [
	 * 										'{$MACRO}' => null,
	 * 										'{$MACRO: context}' => null
	 * 									],
	 * 									'references' => [
	 * 										'$1' => null,
	 * 										'$2' => null
	 * 									]
	 * 								]
	 */
	protected function extractMacros(array $texts, array $types) {
		$macros = [];
		$extract_usermacros = array_key_exists('usermacros', $types);
		$extract_macros = array_key_exists('macros', $types);
		$extract_macros_n = array_key_exists('macros_n', $types);
		$extract_references = array_key_exists('references', $types);

		if ($extract_usermacros) {
			$macros['usermacros'] = [];

			$user_macro_parser = new CUserMacroParser();
		}

		if ($extract_macros) {
			$macros['macros'] = [];

			foreach ($types['macros'] as $key => $macro_patterns) {
				$types['macros'][$key] = new CSetParser($macro_patterns);
				$macros['macros'][$key] = [];
			}
		}

		if ($extract_macros_n) {
			$macros['macros_n'] = [];

			foreach ($types['macros_n'] as $key => $macro_patterns) {
				$macros['macros_n'][$key] = [];
			}
		}

		if ($extract_references) {
			$macros['references'] = [];
		}

		foreach ($texts as $text) {
			for ($pos = 0; isset($text[$pos]); $pos++) {
				if ($extract_usermacros && $user_macro_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
					$macros['usermacros'][$user_macro_parser->getMatch()] = null;
					$pos += $user_macro_parser->getLength() - 1;
					continue;
				}

				if ($extract_macros) {
					foreach ($types['macros'] as $key => $set_parser) {
						if ($set_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
							$macros['macros'][$key][$set_parser->getMatch()] = true;
							$pos += $set_parser->getLength() - 1;
							continue 2;
						}
					}
				}

				if ($extract_macros_n) {
					foreach ($types['macros_n'] as $key => $patterns) {
						if (($result = $this->parseMacrosN($text, $pos, $patterns)) !== false) {
							$macros['macros_n'][$key][$result['macro']][] = $result['n'];
							$pos += $result['length'] - 1;
							continue 2;
						}
					}
				}

				if ($extract_references && ($result = $this->parseReferenceMacro($text, $pos)) !== false) {
					$macros['references'][$result['match']] = null;
					$pos += $result['length'] - 1;
					continue;
				}
			}
		}

		if ($extract_macros) {
			foreach ($types['macros'] as $key => $set_parser) {
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
	 * Find function ids in trigger expression.
	 *
	 * @param string $expression
	 *
	 * @return array	where key is function id position in expression and value is function id
	 */
	protected function findFunctions($expression) {
		$functionids = [];

		$set_parser = new CSetParser(['{TRIGGER.VALUE}']);
		$user_macro_parser = new CUserMacroParser();

		for ($pos = 0, $i = 1; isset($expression[$pos]); $pos++) {
			if (($result = $this->parseFunctionId($expression, $pos)) !== false) {
				$pos += $result['length'] - 1;
				$functionids[$i++] = substr($result['match'], 1, -1);
			}
			elseif ($user_macro_parser->parse($expression, $pos) != CParser::PARSE_FAIL) {
				$pos += $user_macro_parser->getLength() - 1;
			}
			elseif ($set_parser->parse($expression, $pos) != CParser::PARSE_FAIL) {
				$pos += $set_parser->getLength() - 1;
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

			// macro should be resolved to interface with highest priority ($priorities)
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

			// false passed to DBfetch to get data without null converted to 0, which is done by default
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
								$value = item_get_history($item, $trigger['clock'], $trigger['ns']);

								$replace = ($value === null)
									? UNRESOLVED_MACRO_STRING
									: formatHistoryValue($value, $item);
							}
							else {
								$replace = $this->getItemLastValueMacro($lastValue, $item);
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
	 * @param array $data	Macros to resolve
	 * 							[
	 * 								<id> => [
	 * 									'hostids' => [<hostid>, ...],
	 * 									'macros' => [<macro> => null, ...]
	 * 								],
	 * 								...
	 * 							]
	 *
	 * @return array
	 */
	protected function getUserMacros(array $data) {
		/*
		 * User macros
		 */
		$hostIds = [];

		foreach ($data as $element) {
			foreach ($element['hostids'] as $hostId) {
				$hostIds[$hostId] = $hostId;
			}
		}

		if (!$hostIds) {
			return $data;
		}

		/*
		 *	[
		 *		<hostid> => [<templateid>, ...],
		 *		...
		 *	]
		 */
		$hostTemplates = [];

		/*
		 *	[
		 *		<hostid> => [
		 *			<macro_name> => [
		 *				'value' => null|<value>
		 *				'contexts' => [
		 *					<context> => <value>,
		 *					...
		 *				]
		 *			],
		 *			...
		 *		],
		 *		...
		 *	]
		 */
		$hostMacros = [];

		$user_macro_parser = new CUserMacroParser();

		do {
			$dbHosts = API::Host()->get([
				'hostids' => $hostIds,
				'templated_hosts' => true,
				'output' => ['hostid'],
				'selectParentTemplates' => ['templateid'],
				'selectMacros' => ['macro', 'value']
			]);

			$hostIds = [];

			if ($dbHosts) {
				foreach ($dbHosts as $dbHost) {
					$hostTemplates[$dbHost['hostid']] = zbx_objectValues($dbHost['parentTemplates'], 'templateid');

					foreach ($dbHost['macros'] as $dbMacro) {
						if ($user_macro_parser->parse($dbMacro['macro']) != CParser::PARSE_SUCCESS) {
							continue;
						}

						$macro_name = $user_macro_parser->getMacro();
						$macro_context = $user_macro_parser->getContext();

						if (!array_key_exists($dbHost['hostid'], $hostMacros)) {
							$hostMacros[$dbHost['hostid']] = [];
						}

						if (!array_key_exists($macro_name, $hostMacros[$dbHost['hostid']])) {
							$hostMacros[$dbHost['hostid']][$macro_name] = ['value' => null, 'contexts' => []];
						}

						if ($macro_context === null) {
							$hostMacros[$dbHost['hostid']][$macro_name]['value'] = $dbMacro['value'];
						}
						else {
							$hostMacros[$dbHost['hostid']][$macro_name]['contexts'][$macro_context] = $dbMacro['value'];
						}
					}
				}

				foreach ($dbHosts as $dbHost) {
					// only unprocessed templates will be populated
					foreach ($hostTemplates[$dbHost['hostid']] as $templateId) {
						if (!array_key_exists($templateId, $hostTemplates)) {
							$hostIds[$templateId] = $templateId;
						}
					}
				}
			}
		} while ($hostIds);

		$allMacrosResolved = true;

		$user_macro_parser = new CUserMacroParser();

		foreach ($data as &$element) {
			$hostIds = [];

			foreach ($element['hostids'] as $hostId) {
				$hostIds[$hostId] = $hostId;
			}

			natsort($hostIds);

			foreach ($element['macros'] as $macro => &$value) {
				if ($user_macro_parser->parse($macro) == CParser::PARSE_SUCCESS) {
					$macro_name = $user_macro_parser->getMacro();
					$macro_context = $user_macro_parser->getContext();

					$value = $this->getHostUserMacros($hostIds, $macro_name, $macro_context, $hostTemplates,
						$hostMacros
					);

					if ($value === null) {
						$allMacrosResolved = false;
					}
				}
				else {
					$allMacrosResolved = false;
				}
			}
			unset($value);
		}
		unset($element);

		if ($allMacrosResolved) {
			// there are no more hosts with unresolved macros
			return $data;
		}

		/*
		 * Global macros
		 */
		$dbGlobalMacros = API::UserMacro()->get([
			'output' => ['macro', 'value'],
			'globalmacro' => true
		]);

		/*
		 *	[
		 *		<macro_name> => [
		 *			'value' => null|<value>
		 *			'contexts' => [
		 *				<context> => <value>,
		 *				...
		 *			]
		 *		],
		 *		...
		 *	]
		 */
		$global_macros = [];

		foreach ($dbGlobalMacros as $dbGlobalMacro) {
			if ($user_macro_parser->parse($dbGlobalMacro['macro']) != CParser::PARSE_SUCCESS) {
				continue;
			}

			$macro_name = $user_macro_parser->getMacro();
			$macro_context = $user_macro_parser->getContext();

			if (!array_key_exists($macro_name, $global_macros)) {
				$global_macros[$macro_name] = ['value' => null, 'contexts' => []];
			}

			if ($macro_context === null) {
				$global_macros[$macro_name]['value'] = $dbGlobalMacro['value'];
			}
			else {
				$global_macros[$macro_name]['contexts'][$macro_context] = $dbGlobalMacro['value'];
			}
		}

		foreach ($data as &$element) {
			foreach ($element['macros'] as $macro => &$value) {
				if ($value === null && $user_macro_parser->parse($macro) == CParser::PARSE_SUCCESS) {
					$macro_name = $user_macro_parser->getMacro();
					$macro_context = $user_macro_parser->getContext();

					if (array_key_exists($macro_name, $global_macros)) {
						if ($macro_context !== null
								&& array_key_exists($macro_context, $global_macros[$macro_name]['contexts'])) {
							$value = $global_macros[$macro_name]['contexts'][$macro_context];
						}
						elseif ($global_macros[$macro_name]['value'] !== null) {
							$value = $global_macros[$macro_name]['value'];
						}
					}
				}

				/*
				 * Unresolved macros stay as is
				 */
				if ($value === null) {
					$value = $macro;
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
	 * @param array  $hostIds			The sorted list of hosts where macros will be looked for (hostid => hostid)
	 * @param string $macro_name		Macro to resolve
	 * @param string $macro_context		Macro context to resolve
	 * @param array  $hostTemplates		The list of linked templates (see getUserMacros() for more details)
	 * @param array  $hostMacros		The list of macros on hosts (see getUserMacros() for more details)
	 *
	 * @return array
	 */
	private function getHostUserMacros(array $hostIds, $macro_name, $macro_context, array $hostTemplates,
			array $hostMacros) {
		foreach ($hostIds as $hostId) {
			if (array_key_exists($hostId, $hostMacros) && array_key_exists($macro_name, $hostMacros[$hostId])) {
				if ($macro_context !== null
						&& array_key_exists($macro_context, $hostMacros[$hostId][$macro_name]['contexts'])) {
					return $hostMacros[$hostId][$macro_name]['contexts'][$macro_context];
				}

				if ($hostMacros[$hostId][$macro_name]['value'] !== null) {
					return $hostMacros[$hostId][$macro_name]['value'];
				}
			}
		}

		if (!$hostTemplates) {
			return null;
		}

		$templateIds = [];

		foreach ($hostIds as $hostId) {
			if (array_key_exists($hostId, $hostTemplates)) {
				foreach ($hostTemplates[$hostId] as $templateId) {
					$templateIds[$templateId] = true;
				}
			}
		}

		if ($templateIds) {
			$templateIds = array_keys($templateIds);
			natsort($templateIds);

			return $this->getHostUserMacros($templateIds, $macro_name, $macro_context, $hostTemplates, $hostMacros);
		}

		return null;
	}
}
