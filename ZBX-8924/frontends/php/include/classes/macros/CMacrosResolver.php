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


class CMacrosResolver extends CMacrosResolverGeneral {

	/**
	 * Supported macros resolving scenarios.
	 *
	 * @var array
	 */
	protected $configs = array(
		'scriptConfirmation' => array(
			'types' => array('host', 'interfaceWithoutPort', 'user'),
			'method' => 'resolveTexts'
		),
		'httpTestName' => array(
			'types' => array('host', 'interfaceWithoutPort', 'user'),
			'method' => 'resolveTexts'
		),
		'hostInterfaceIpDns' => array(
			'types' => array('host', 'agentInterface', 'user'),
			'method' => 'resolveTexts'
		),
		'hostInterfaceIpDnsAgentPrimary' => array(
			'types' => array('host', 'user'),
			'method' => 'resolveTexts'
		),
		'hostInterfacePort' => array(
			'types' => array('user'),
			'method' => 'resolveTexts'
		),
		'triggerName' => array(
			'types' => array('host', 'interface', 'user', 'item', 'reference'),
			'source' => 'description',
			'method' => 'resolveTrigger'
		),
		'triggerDescription' => array(
			'types' => array('host', 'interface', 'user', 'item'),
			'source' => 'comments',
			'method' => 'resolveTrigger'
		),
		'triggerExpressionUser' => array(
			'types' => array('user'),
			'source' => 'expression',
			'method' => 'resolveTrigger'
		),
		'eventDescription' => array(
			'types' => array('host', 'interface', 'user', 'item', 'reference'),
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
	 * @param array $data	(as $hostId => array(texts))
	 *
	 * @return array		(as $hostId => array(texts))
	 */
	private function resolveTexts(array $data) {
		$hostIds = array_keys($data);

		$macros = array();

		$hostMacrosAvailable = $agentInterfaceAvailable = $interfaceWithoutPortMacrosAvailable = false;

		if ($this->isTypeAvailable('host')) {
			foreach ($data as $hostId => $texts) {
				if ($hostMacros = $this->findMacros(self::PATTERN_HOST, $texts)) {
					foreach ($hostMacros as $hostMacro) {
						$macros[$hostId][$hostMacro] = UNRESOLVED_MACRO_STRING;
					}

					$hostMacrosAvailable = true;
				}
			}
		}

		if ($this->isTypeAvailable('agentInterface')) {
			foreach ($data as $hostId => $texts) {
				if ($interfaceMacros = $this->findMacros(self::PATTERN_INTERFACE, $texts)) {
					foreach ($interfaceMacros as $interfaceMacro) {
						$macros[$hostId][$interfaceMacro] = UNRESOLVED_MACRO_STRING;
					}

					$agentInterfaceAvailable = true;
				}
			}
		}

		if ($this->isTypeAvailable('interfaceWithoutPort')) {
			foreach ($data as $hostId => $texts) {
				if ($interfaceMacros = $this->findMacros(self::PATTERN_INTERFACE, $texts)) {
					foreach ($interfaceMacros as $interfaceMacro) {
						$macros[$hostId][$interfaceMacro] = UNRESOLVED_MACRO_STRING;
					}

					$interfaceWithoutPortMacrosAvailable = true;
				}
			}
		}

		// host macros
		if ($hostMacrosAvailable) {
			$dbHosts = DBselect('SELECT h.hostid,h.name,h.host FROM hosts h WHERE '.dbConditionInt('h.hostid', $hostIds));

			while ($dbHost = DBfetch($dbHosts)) {
				$hostId = $dbHost['hostid'];

				if ($hostMacros = $this->findMacros(self::PATTERN_HOST, $data[$hostId])) {
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

		// interface macros, macro should be resolved to main agent interface
		if ($agentInterfaceAvailable) {
			foreach ($data as $hostId => $texts) {
				if ($interfaceMacros = $this->findMacros(self::PATTERN_INTERFACE, $texts)) {
					$dbInterface = DBfetch(DBselect(
						'SELECT i.hostid,i.ip,i.dns,i.useip'.
						' FROM interface i'.
						' WHERE i.main='.INTERFACE_PRIMARY.
							' AND i.type='.INTERFACE_TYPE_AGENT.
							' AND i.hostid='.zbx_dbstr($hostId)
					));

					$dbInterfaceTexts = array($dbInterface['ip'], $dbInterface['dns']);

					if ($this->findMacros(self::PATTERN_HOST, $dbInterfaceTexts)
							|| $this->findMacros(ZBX_PREG_EXPRESSION_USER_MACROS, $dbInterfaceTexts)) {
						$saveCurrentConfig = $this->config;

						$dbInterfaceMacros = $this->resolve(array(
							'config' => 'hostInterfaceIpDnsAgentPrimary',
							'data' => array($hostId => $dbInterfaceTexts)
						));

						$dbInterfaceMacros = reset($dbInterfaceMacros);
						$dbInterface['ip'] = $dbInterfaceMacros[0];
						$dbInterface['dns'] = $dbInterfaceMacros[1];

						$this->config = $saveCurrentConfig;
					}

					foreach ($interfaceMacros as $interfaceMacro) {
						switch ($interfaceMacro) {
							case '{IPADDRESS}':
							case '{HOST.IP}':
								$macros[$hostId][$interfaceMacro] = $dbInterface['ip'];
								break;
							case '{HOST.DNS}':
								$macros[$hostId][$interfaceMacro] = $dbInterface['dns'];
								break;
							case '{HOST.CONN}':
								$macros[$hostId][$interfaceMacro] = $dbInterface['useip'] ? $dbInterface['ip'] : $dbInterface['dns'];
								break;
						}
					}
				}
			}
		}

		// interface macros, macro should be resolved to interface with highest priority
		if ($interfaceWithoutPortMacrosAvailable) {
			$interfaces = array();

			$dbInterfaces = DBselect(
				'SELECT i.hostid,i.ip,i.dns,i.useip,i.type'.
				' FROM interface i'.
				' WHERE i.main='.INTERFACE_PRIMARY.
					' AND '.dbConditionInt('i.hostid', $hostIds).
					' AND '.dbConditionInt('i.type', $this->interfacePriorities)
			);

			while ($dbInterface = DBfetch($dbInterfaces)) {
				$hostId = $dbInterface['hostid'];

				if (isset($interfaces[$hostId])) {
					$dbPriority = $this->interfacePriorities[$dbInterface['type']];
					$existPriority = $this->interfacePriorities[$interfaces[$hostId]['type']];

					if ($dbPriority > $existPriority) {
						$interfaces[$hostId] = $dbInterface;
					}
				}
				else {
					$interfaces[$hostId] = $dbInterface;
				}
			}

			if ($interfaces) {
				foreach ($interfaces as $hostId => $interface) {
					if ($interfaceMacros = $this->findMacros(self::PATTERN_INTERFACE, $data[$hostId])) {
						foreach ($interfaceMacros as $interfaceMacro) {
							switch ($interfaceMacro) {
								case '{IPADDRESS}':
								case '{HOST.IP}':
									$macros[$hostId][$interfaceMacro] = $interface['ip'];
									break;
								case '{HOST.DNS}':
									$macros[$hostId][$interfaceMacro] = $interface['dns'];
									break;
								case '{HOST.CONN}':
									$macros[$hostId][$interfaceMacro] = $interface['useip'] ? $interface['ip'] : $interface['dns'];
									break;
							}

							// Resolving macros to AGENT main interface. If interface is AGENT macros stay unresolved.
							if ($interface['type'] != INTERFACE_TYPE_AGENT) {
								if ($this->findMacros(self::PATTERN_HOST, array($macros[$hostId][$interfaceMacro]))
										|| $this->findMacros(ZBX_PREG_EXPRESSION_USER_MACROS, array($macros[$hostId][$interfaceMacro]))) {
									// attention recursion!
									$macrosInMacros = $this->resolveTexts(array($hostId => array($macros[$hostId][$interfaceMacro])));
									$macros[$hostId][$interfaceMacro] = $macrosInMacros[$hostId][0];
								}
								elseif ($this->findMacros(self::PATTERN_INTERFACE, array($macros[$hostId][$interfaceMacro]))) {
									$macros[$hostId][$interfaceMacro] = UNRESOLVED_MACRO_STRING;
								}
							}
						}
					}
				}
			}
		}

		// get user macros
		if ($this->isTypeAvailable('user')) {
			$userMacrosData = array();

			foreach ($data as $hostId => $texts) {
				$userMacros = $this->findMacros(ZBX_PREG_EXPRESSION_USER_MACROS, $texts);

				foreach ($userMacros as $userMacro) {
					if (!isset($userMacrosData[$hostId])) {
						$userMacrosData[$hostId] = array(
							'hostids' => array($hostId),
							'macros' => array()
						);
					}

					$userMacrosData[$hostId]['macros'][$userMacro] = null;
				}
			}

			$userMacros = $this->getUserMacros($userMacrosData);

			foreach ($userMacros as $hostId => $userMacro) {
				$macros[$hostId] = isset($macros[$hostId])
					? array_merge($macros[$hostId], $userMacro['macros'])
					: $userMacro['macros'];
			}
		}

		// replace macros to value
		if ($macros) {
			foreach ($data as $hostId => $texts) {
				if (isset($macros[$hostId])) {
					foreach ($texts as $tnum => $text) {
						preg_match_all('/'.self::PATTERN_HOST.'|'.self::PATTERN_INTERFACE.'|'.ZBX_PREG_EXPRESSION_USER_MACROS.'/', $text, $matches, PREG_OFFSET_CAPTURE);

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
	 * @param string $triggers[$triggerId]['expression']
	 * @param string $triggers[$triggerId]['description']			depend from config
	 * @param string $triggers[$triggerId]['comments']				depend from config
	 *
	 * @return array
	 */
	private function resolveTrigger(array $triggers) {
		$macros = array(
			'host' => array(),
			'interfaceWithoutPort' => array(),
			'interface' => array(),
			'item' => array()
		);
		$macroValues = $userMacrosData = array();

		// get source field
		$source = $this->getSource();

		// get available functions
		$hostMacrosAvailable = $this->isTypeAvailable('host');
		$interfaceWithoutPortMacrosAvailable = $this->isTypeAvailable('interfaceWithoutPort');
		$interfaceMacrosAvailable = $this->isTypeAvailable('interface');
		$itemMacrosAvailable = $this->isTypeAvailable('item');
		$userMacrosAvailable = $this->isTypeAvailable('user');
		$referenceMacrosAvailable = $this->isTypeAvailable('reference');

		// find macros
		foreach ($triggers as $triggerId => $trigger) {
			if ($userMacrosAvailable) {
				$userMacros = $this->findMacros(ZBX_PREG_EXPRESSION_USER_MACROS, array($trigger[$source]));

				if ($userMacros) {
					if (!isset($userMacrosData[$triggerId])) {
						$userMacrosData[$triggerId] = array('macros' => array(), 'hostids' => array());
					}

					foreach ($userMacros as $userMacro) {
						$userMacrosData[$triggerId]['macros'][$userMacro] = null;
					}
				}
			}

			$functions = $this->findFunctions($trigger['expression']);

			if ($hostMacrosAvailable) {
				foreach ($this->findFunctionMacros(self::PATTERN_HOST_FUNCTION, $trigger[$source]) as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						$macroValues[$triggerId][$this->getFunctionMacroName($macro, $fNum)] = UNRESOLVED_MACRO_STRING;

						if (isset($functions[$fNum])) {
							$macros['host'][$functions[$fNum]][$macro][] = $fNum;
						}
					}
				}
			}

			if ($interfaceWithoutPortMacrosAvailable) {
				foreach ($this->findFunctionMacros(self::PATTERN_INTERFACE_FUNCTION_WITHOUT_PORT, $trigger[$source]) as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						$macroValues[$triggerId][$this->getFunctionMacroName($macro, $fNum)] = UNRESOLVED_MACRO_STRING;

						if (isset($functions[$fNum])) {
							$macros['interfaceWithoutPort'][$functions[$fNum]][$macro][] = $fNum;
						}
					}
				}
			}

			if ($interfaceMacrosAvailable) {
				foreach ($this->findFunctionMacros(self::PATTERN_INTERFACE_FUNCTION, $trigger[$source]) as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						$macroValues[$triggerId][$this->getFunctionMacroName($macro, $fNum)] = UNRESOLVED_MACRO_STRING;

						if (isset($functions[$fNum])) {
							$macros['interface'][$functions[$fNum]][$macro][] = $fNum;
						}
					}
				}
			}

			if ($itemMacrosAvailable) {
				foreach ($this->findFunctionMacros(self::PATTERN_ITEM_FUNCTION, $trigger[$source]) as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						$macroValues[$triggerId][$this->getFunctionMacroName($macro, $fNum)] = UNRESOLVED_MACRO_STRING;

						if (isset($functions[$fNum])) {
							$macros['item'][$functions[$fNum]][$macro][] = $fNum;
						}
					}
				}
			}

			if ($referenceMacrosAvailable) {
				foreach ($this->getTriggerReference($trigger['expression'], $trigger[$source]) as $macro => $value) {
					$macroValues[$triggerId][$macro] = $value;
				}
			}
		}

		// get macro value
		if ($hostMacrosAvailable) {
			$macroValues = $this->getHostMacros($macros['host'], $macroValues);
		}
		if ($interfaceWithoutPortMacrosAvailable) {
			$macroValues = $this->getIpMacros($macros['interfaceWithoutPort'], $macroValues, false);
		}
		if ($interfaceMacrosAvailable) {
			$macroValues = $this->getIpMacros($macros['interface'], $macroValues, true);
			$patternInterfaceFunction = self::PATTERN_INTERFACE_FUNCTION;
		}
		else {
			$patternInterfaceFunction = self::PATTERN_INTERFACE_FUNCTION_WITHOUT_PORT;
		}
		if ($itemMacrosAvailable) {
			$macroValues = $this->getItemMacros($macros['item'], $triggers, $macroValues);
		}
		if ($userMacrosData) {
			// get hosts for triggers
			$dbTriggers = API::Trigger()->get(array(
				'output' => array('triggerid'),
				'selectHosts' => array('hostid'),
				'triggerids' => array_keys($userMacrosData),
				'preservekeys' => true
			));

			foreach ($userMacrosData as $triggerId => $userMacro) {
				if (isset($dbTriggers[$triggerId])) {
					$userMacrosData[$triggerId]['hostids'] =
						zbx_objectValues($dbTriggers[$triggerId]['hosts'], 'hostid');
				}
			}

			// get user macros values
			$userMacros = $this->getUserMacros($userMacrosData);

			foreach ($userMacros as $triggerId => $userMacro) {
				$macroValues[$triggerId] = isset($macroValues[$triggerId])
					? array_merge($macroValues[$triggerId], $userMacro['macros'])
					: $userMacro['macros'];
			}
		}

		// replace macros to value
		foreach ($triggers as $triggerId => $trigger) {
			preg_match_all('/'.self::PATTERN_HOST_FUNCTION.
								'|'.$patternInterfaceFunction.
								'|'.self::PATTERN_ITEM_FUNCTION.
								'|'.ZBX_PREG_EXPRESSION_USER_MACROS.
								'|\$([1-9])/', $trigger[$source], $matches, PREG_OFFSET_CAPTURE);

			for ($i = count($matches[0]) - 1; $i >= 0; $i--) {
				$matche = $matches[0][$i];

				$macrosValue = isset($macroValues[$triggerId][$matche[0]]) ? $macroValues[$triggerId][$matche[0]] : $matche[0];
				$trigger[$source] = substr_replace($trigger[$source], $macrosValue, $matche[1], strlen($matche[0]));
			}

			$triggers[$triggerId][$source] = $trigger[$source];
		}

		return $triggers;
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
		foreach ($this->getTriggerReference($expression, $text) as $key => $value) {
			$text = str_replace($key, $value, $text);
		}

		return $text;
	}

	/**
	 * Resolve functional item macros, for example, {{HOST.HOST1}:key.func(param)}.
	 *
	 * @param array  $data							list or hashmap of graphs
	 * @param type   $data[]['name']				string in which macros should be resolved
	 * @param array  $data[]['items']				list of graph items
	 * @param int    $data[]['items'][n]['hostid']	graph n-th item corresponding host ID
	 * @param string $data[]['items'][n]['host']	graph n-th item corresponding host name
	 *
	 * @return string	inputted data with resolved source field
	 */
	private function resolveGraph($data) {
		if ($this->isTypeAvailable('graphFunctionalItem')) {
			$source = $this->getSource();

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
	 * If macro can not be resolved it is replaced with UNRESOLVED_MACRO_STRING string i.e. "*UNKNOWN*".
	 *
	 * Supports function "last", "min", "max" and "avg".
	 * Supports seconds as parameters, except "last" function.
	 * Second parameter like {hostname:key.last(0,86400) and offsets like {hostname:key.last(#1)} are not supported.
	 * Supports postfixes s,m,h,d and w for parameter.
	 *
	 * @param array  $strList				list of string in which macros should be resolved
	 * @param array  $itemsList				list of	lists of graph items
	 * @param int    $items[n][m]['hostid']	n-th graph m-th item corresponding host Id
	 * @param string $items[n][m]['host']	n-th graph m-th item corresponding host name
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
			preg_match_all('/(?P<macros>{'.
				'(?P<hosts>('.ZBX_PREG_HOST_FORMAT.'|({('.self::PATTERN_HOST_INTERNAL.')'.self::PATTERN_MACRO_PARAM.'}))):'.
				'(?P<keys>'.ZBX_PREG_ITEM_KEY_FORMAT.')\.'.
				'(?P<functions>(last|max|min|avg))\('.
				'(?P<parameters>([0-9]+['.ZBX_TIME_SUFFIXES.']?)?)'.
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
			$query = substr($query, 0, -1).')) OR ';
		}
		$query = substr($query, 0, -4).')';

		// get necessary items for all graph strings
		$items = DBfetchArrayAssoc(DBselect($query), 'itemid');

		$allowedItems = API::Item()->get(array(
			'itemids' => array_keys($items),
			'webitems' => true,
			'output' => array('itemid', 'value_type', 'lastvalue', 'lastclock'),
			'preservekeys' => true
		));

		// map item data only for allowed items
		foreach ($items as $item) {
			if (isset($allowedItems[$item['itemid']])) {
				$item['lastvalue'] = $allowedItems[$item['itemid']]['lastvalue'];
				$item['lastclock'] = $allowedItems[$item['itemid']]['lastclock'];
				$hostKeyPairs[$item['host']][$item['key_']] = $item;
			}
		}


		// replace macros with their corresponding values in graph strings
		$matches = reset($matchesList);

		foreach ($strList as &$str) {
			// iterate array backwards!
			$i = count($matches['macros']);

			while ($i--) {
				$host = $matches['hosts'][$i][0];
				$key = $matches['keys'][$i][0];
				$function = $matches['functions'][$i][0];
				$parameter = $matches['parameters'][$i][0];

				// host is real and item exists and has permissions
				if ($host !== UNRESOLVED_MACRO_STRING && is_array($hostKeyPairs[$host][$key])) {
					$item = $hostKeyPairs[$host][$key];

					// macro function is "last"
					if ($function == 'last') {
						$value = ($item['lastclock'] > 0)
							? formatHistoryValue($item['lastvalue'], $item)
							: UNRESOLVED_MACRO_STRING;
					}
					// macro function is "max", "min" or "avg"
					else {
						$value = getItemFunctionalValue($item, $function, $parameter);
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

	/**
	 * Resolve item name macros to "name_expanded" field.
	 *
	 * @param array  $items
	 * @param string $items[n]['itemid']
	 * @param string $items[n]['hostid']
	 * @param string $items[n]['name']
	 * @param string $items[n]['key_']				item key (optional)
	 *												but is (mandatory) if macros exist and "key_expanded" is not present
	 * @param string $items[n]['key_expanded']		expanded item key (optional)
	 *
	 * @return array
	 */
	public function resolveItemNames(array $items) {
		// define resolving fields
		foreach ($items as &$item) {
			$item['name_expanded'] = $item['name'];
		}
		unset($item);

		$macros = $itemsWithReferenceMacros = $itemsWithUnResolvedKeys = array();

		// reference macros - $1..$9
		foreach ($items as $key => $item) {
			$matchedMacros = $this->findMacros(self::PATTERN_ITEM_NUMBER, array($item['name_expanded']));

			if ($matchedMacros) {
				$macros[$key] = array('macros' => array());

				foreach ($matchedMacros as $macro) {
					$macros[$key]['macros'][$macro] = null;
				}

				$itemsWithReferenceMacros[$key] = $item;
			}
		}

		if ($itemsWithReferenceMacros) {
			// resolve macros in item key
			foreach ($itemsWithReferenceMacros as $key => $item) {
				if (!isset($item['key_expanded'])) {
					$itemsWithUnResolvedKeys[$key] = $item;
				}
			}

			if ($itemsWithUnResolvedKeys) {
				$itemsWithUnResolvedKeys = $this->resolveItemKeys($itemsWithUnResolvedKeys);

				foreach ($itemsWithUnResolvedKeys as $key => $item) {
					$itemsWithReferenceMacros[$key] = $item;
				}
			}

			// reference macros - $1..$9
			foreach ($itemsWithReferenceMacros as $key => $item) {
				$itemKey = new CItemKey($item['key_expanded']);

				if ($itemKey->isValid()) {
					foreach ($itemKey->getParameters() as $n => $keyParameter) {
						$paramNum = '$'.++$n;

						if (array_key_exists($paramNum, $macros[$key]['macros'])) {
							$macros[$key]['macros'][$paramNum] = $keyParameter;
						}
					}
				}
			}
		}

		// user macros
		$userMacros = array();

		foreach ($items as $item) {
			$matchedMacros = $this->findMacros(ZBX_PREG_EXPRESSION_USER_MACROS, array($item['name_expanded']));

			if ($matchedMacros) {
				foreach ($matchedMacros as $macro) {
					if (!isset($userMacros[$item['hostid']])) {
						$userMacros[$item['hostid']] = array(
							'hostids' => array($item['hostid']),
							'macros' => array()
						);
					}

					$userMacros[$item['hostid']]['macros'][$macro] = null;
				}
			}
		}

		if ($userMacros) {
			$userMacros = $this->getUserMacros($userMacros);

			foreach ($items as $key => $item) {
				if (isset($userMacros[$item['hostid']])) {
					$macros[$key]['macros'] = isset($macros[$key])
						? zbx_array_merge($macros[$key]['macros'], $userMacros[$item['hostid']]['macros'])
						: $userMacros[$item['hostid']]['macros'];
				}
			}
		}

		// replace macros to value
		if ($macros) {
			foreach ($macros as $key => $macroData) {
				$items[$key]['name_expanded'] = str_replace(
					array_keys($macroData['macros']),
					array_values($macroData['macros']),
					$items[$key]['name_expanded']
				);
			}
		}

		return $items;
	}

	/**
	 * Resolve item key macros to "key_expanded" field.
	 *
	 * @param array  $items
	 * @param string $items[n]['itemid']
	 * @param string $items[n]['hostid']
	 * @param string $items[n]['key_']
	 *
	 * @return array
	 */
	public function resolveItemKeys(array $items) {
		// define resolving field
		foreach ($items as &$item) {
			$item['key_expanded'] = $item['key_'];
		}
		unset($item);

		$macros = $itemIds = array();

		// host, ip macros
		foreach ($items as $key => $item) {
			$matchedMacros = $this->findMacros(self::PATTERN_ITEM_MACROS, array($item['key_expanded']));

			if ($matchedMacros) {
				$itemIds[$item['itemid']] = $item['itemid'];

				$macros[$key] = array(
					'itemid' => $item['itemid'],
					'macros' => array()
				);

				foreach ($matchedMacros as $macro) {
					$macros[$key]['macros'][$macro] = null;
				}
			}
		}

		if ($macros) {
			$dbItems = API::Item()->get(array(
				'itemids' => $itemIds,
				'selectInterfaces' => array('ip', 'dns', 'useip'),
				'selectHosts' => array('host', 'name'),
				'webitems' => true,
				'output' => array('itemid'),
				'filter' => array('flags' => null),
				'preservekeys' => true
			));

			foreach ($macros as $key => $macroData) {
				if (isset($dbItems[$macroData['itemid']])) {
					$host = reset($dbItems[$macroData['itemid']]['hosts']);
					$interface = reset($dbItems[$macroData['itemid']]['interfaces']);

					// if item without interface or template item, resolve interface related macros to *UNKNOWN*
					if (!$interface) {
						$interface = array(
							'ip' => UNRESOLVED_MACRO_STRING,
							'dns' => UNRESOLVED_MACRO_STRING,
							'useip' => false
						);
					}

					foreach ($macroData['macros'] as $macro => $value) {
						switch ($macro) {
							case '{HOST.NAME}':
								$macros[$key]['macros'][$macro] = $host['name'];
								break;

							case '{HOST.HOST}':
							case '{HOSTNAME}': // deprecated
								$macros[$key]['macros'][$macro] = $host['host'];
								break;

							case '{HOST.IP}':
							case '{IPADDRESS}': // deprecated
								$macros[$key]['macros'][$macro] = $interface['ip'];
								break;

							case '{HOST.DNS}':
								$macros[$key]['macros'][$macro] = $interface['dns'];
								break;

							case '{HOST.CONN}':
								$macros[$key]['macros'][$macro] = $interface['useip'] ? $interface['ip'] : $interface['dns'];
								break;
						}
					}
				}

				unset($macros[$key]['itemid']);
			}
		}

		// user macros
		$userMacros = array();

		foreach ($items as $item) {
			$matchedMacros = $this->findMacros(ZBX_PREG_EXPRESSION_USER_MACROS, array($item['key_expanded']));

			if ($matchedMacros) {
				foreach ($matchedMacros as $macro) {
					if (!isset($userMacros[$item['hostid']])) {
						$userMacros[$item['hostid']] = array(
							'hostids' => array($item['hostid']),
							'macros' => array()
						);
					}

					$userMacros[$item['hostid']]['macros'][$macro] = null;
				}
			}
		}

		if ($userMacros) {
			$userMacros = $this->getUserMacros($userMacros);

			foreach ($items as $key => $item) {
				if (isset($userMacros[$item['hostid']])) {
					$macros[$key]['macros'] = isset($macros[$key])
						? zbx_array_merge($macros[$key]['macros'], $userMacros[$item['hostid']]['macros'])
						: $userMacros[$item['hostid']]['macros'];
				}
			}
		}

		// replace macros to value
		if ($macros) {
			foreach ($macros as $key => $macroData) {
				$items[$key]['key_expanded'] = str_replace(
					array_keys($macroData['macros']),
					array_values($macroData['macros']),
					$items[$key]['key_expanded']
				);
			}
		}

		return $items;
	}

	/**
	 * Resolve function parameter macros to "parameter_expanded" field.
	 *
	 * @param array  $data
	 * @param string $data[n]['hostid']
	 * @param string $data[n]['parameter']
	 *
	 * @return array
	 */
	public function resolveFunctionParameters(array $data) {
		// define resolving field
		foreach ($data as &$function) {
			$function['parameter_expanded'] = $function['parameter'];
		}
		unset($function);

		$macros = array();

		// user macros
		$userMacros = array();

		foreach ($data as $function) {
			$matchedMacros = $this->findMacros(ZBX_PREG_EXPRESSION_USER_MACROS, array($function['parameter_expanded']));

			if ($matchedMacros) {
				foreach ($matchedMacros as $macro) {
					if (!isset($userMacros[$function['hostid']])) {
						$userMacros[$function['hostid']] = array(
							'hostids' => array($function['hostid']),
							'macros' => array()
						);
					}

					$userMacros[$function['hostid']]['macros'][$macro] = null;
				}
			}
		}

		if ($userMacros) {
			$userMacros = $this->getUserMacros($userMacros);

			foreach ($data as $key => $function) {
				if (isset($userMacros[$function['hostid']])) {
					$macros[$key]['macros'] = isset($macros[$key])
						? zbx_array_merge($macros[$key]['macros'], $userMacros[$function['hostid']]['macros'])
						: $userMacros[$function['hostid']]['macros'];
				}
			}
		}

		// replace macros to value
		if ($macros) {
			foreach ($macros as $key => $macroData) {
				$data[$key]['parameter_expanded'] = str_replace(
					array_keys($macroData['macros']),
					array_values($macroData['macros']),
					$data[$key]['parameter_expanded']
				);
			}
		}

		return $data;
	}
}
