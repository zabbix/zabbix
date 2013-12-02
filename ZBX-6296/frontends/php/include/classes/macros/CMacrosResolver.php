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


class CMacrosResolver extends CMacrosResolverGeneral {

	/**
	 * Supported macros resolving scenarios.
	 *
	 * @var array
	 */
	protected $configs = array(
		'scriptConfirmation' => array(
			'types' => array('host', 'interfaceWithPriorities', 'user'),
			'method' => 'resolveTexts'
		),
		'httpTestName' => array(
			'types' => array('host', 'interfaceWithPriorities', 'user'),
			'method' => 'resolveTexts'
		),
		'hostInterfaceIpDns' => array(
			'types' => array('host', 'interface', 'user'),
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
			'types' => array('host', 'interfaceWithPriorities', 'user', 'item', 'reference'),
			'source' => 'description',
			'method' => 'resolveTrigger'
		),
		'triggerDescription' => array(
			'types' => array('host', 'interfaceWithPriorities', 'user', 'item'),
			'source' => 'comments',
			'method' => 'resolveTrigger'
		),
		'eventDescription' => array(
			'types' => array('host', 'interfaceWithPriorities', 'user', 'item', 'reference'),
			'source' => 'description',
			'method' => 'resolveTrigger'
		),
		'graphName' => array(
			'types' => array('graphFunctionalItem'),
			'source' => 'name',
			'method' => 'resolveGraph'
		),
		'itemName' => array(
			'types' => array('name'),
			'method' => 'resolveItems'
		),
		'itemKey' => array(
			'types' => array('key'),
			'method' => 'resolveItemKeys'
		),
		'itemNameAndKey' => array(
			'types' => array('item', 'key'),
			'method' => 'resolveItems'
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

		$isHostMacrosAvailable = false;
		if ($this->isTypeAvailable('host')) {
			foreach ($data as $hostId => $texts) {
				if ($hostMacros = $this->findMacros(self::PATTERN_HOST, $texts)) {
					foreach ($hostMacros as $hostMacro) {
						$macros[$hostId][$hostMacro] = UNRESOLVED_MACRO_STRING;
					}

					$isHostMacrosAvailable = true;
				}
			}
		}

		$isInterfaceMacrosAvailable = false;
		if ($this->isTypeAvailable('interface')) {
			foreach ($data as $hostId => $texts) {
				if ($interfaceMacros = $this->findMacros(self::PATTERN_INTERFACE, $texts)) {
					foreach ($interfaceMacros as $interfaceMacro) {
						$macros[$hostId][$interfaceMacro] = UNRESOLVED_MACRO_STRING;
					}

					$isInterfaceMacrosAvailable = true;
				}
			}
		}

		$isInterfaceWithPrioritiesMacrosAvailable = false;
		if ($this->isTypeAvailable('interfaceWithPriorities')) {
			foreach ($data as $hostId => $texts) {
				if ($interfaceMacros = $this->findMacros(self::PATTERN_INTERFACE, $texts)) {
					foreach ($interfaceMacros as $interfaceMacro) {
						$macros[$hostId][$interfaceMacro] = UNRESOLVED_MACRO_STRING;
					}

					$isInterfaceWithPrioritiesMacrosAvailable = true;
				}
			}
		}

		// host macros
		if ($isHostMacrosAvailable) {
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
		if ($isInterfaceMacrosAvailable) {
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
		if ($isInterfaceWithPrioritiesMacrosAvailable) {
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
	 * @param array  $data								(as int $triggerId => array $trigger)
	 * @param string $data[$triggerId]['expression']
	 * @param string $data[$triggerId]['description']	depend from config
	 * @param string $data[$triggerId]['comments']		depend from config
	 *
	 * @return array
	 */
	private function resolveTrigger(array $data) {
		$macros = array('host' => array(), 'interfaceWithPriorities' => array(), 'item' => array());
		$macroValues = array();

		// get source field
		$source = $this->getSource();

		// get available functions
		$isHostMacrosAvailable = $this->isTypeAvailable('host');
		$isInterfaceWithPrioritiesMacrosAvailable = $this->isTypeAvailable('interfaceWithPriorities');
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

			if ($isInterfaceWithPrioritiesMacrosAvailable) {
				foreach ($this->findFunctionMacros(self::PATTERN_INTERFACE_FUNCTION, $trigger[$source]) as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						$macroValues[$triggerId][$this->getFunctionMacroName($macro, $fNum)] = UNRESOLVED_MACRO_STRING;

						if (isset($functions[$fNum])) {
							$macros['interfaceWithPriorities'][$functions[$fNum]][$macro][] = $fNum;
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
			$macroValues = $this->getHostMacros($macros['host'], $macroValues);
		}
		if ($isInterfaceWithPrioritiesMacrosAvailable) {
			$macroValues = $this->getIpMacros($macros['interfaceWithPriorities'], $macroValues);
		}
		if ($isItemMacrosAvailable) {
			$macroValues = $this->getItemMacros($macros['item'], $data, $macroValues);
		}

		// replace macros to value
		foreach ($data as $triggerId => $trigger) {
			preg_match_all('/'.self::PATTERN_HOST_FUNCTION.
								'|'.self::PATTERN_INTERFACE_FUNCTION.
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
		if ($macros = $this->getTriggerReference($expression, $text)) {
			foreach ($macros as $i => $value) {
				$text = str_replace($i, $value, $text);
			}
		}

		return $text;
	}

	/**
	 * Resolve functional item macros, for example, {{HOST.HOST1}:key.func(param)}.
	 *
	 * @param array  $data							list or hashmap of graphs
	 * @param type   $data[]['name']				string in which macros should be resolved
	 * @param array  $data[]['items']				list of graph items
	 * @param int    $data[]['items'][n]['hostid']	graph n-th item corresponding host Id
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
			$query = substr($query, 0, -1).')) OR ';
		}
		$query = substr($query, 0, -4).')';

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
				if ($matches['hosts'][$i][0] !== UNRESOLVED_MACRO_STRING
						&& is_array($hostKeyPairs[$matches['hosts'][$i][0]][$matches['keys'][$i][0]])) {
					$item = $hostKeyPairs[$matches['hosts'][$i][0]][$matches['keys'][$i][0]];

					// macro function is "last"
					if ($matches['functions'][$i][0] == 'last') {
						$value = isset($history[$item['itemid']])
							? formatHistoryValue($history[$item['itemid']][0]['value'], $item)
							: UNRESOLVED_MACRO_STRING;
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

	/**
	 * Resolve item macros.
	 *
	 * @param array  $items
	 * @param string $items[n]['itemid']
	 * @param string $items[n]['hostid']
	 * @param string $items[n]['name']
	 * @param string $items[n]['key_']
	 *
	 * @return array
	 */
	private function resolveItems(array $items) {
		$items = zbx_toHash($items, 'itemid');

		// user macros
		$items = $this->resolveItemUserMacros($items, 'name');

		// macros in item key
		if ($this->isTypeAvailable('key')) {
			$items = $this->resolveItemKeys($items);
		}

		// reference macros - $1..$9
		$itemsWithMacros = array();

		foreach ($items as $item) {
			if (preg_match(self::PATTERN_ITEM_NUMBER, $item['name'])) {
				$itemsWithMacros[$item['itemid']] = $item;
			}
		}

		if ($itemsWithMacros) {
			// macros in item key
			if (!$this->isTypeAvailable('key')) {
				$itemsWithMacros = $this->resolveItemKeys($itemsWithMacros);
			}

			foreach ($itemsWithMacros as $item) {
				// parsing key to get the parameters out of it
				$itemKey = new CItemKey($item['key_']);

				if ($itemKey->isValid()) {
					$keyParameters = $itemKey->getParameters();
					$searchOffset = 0;

					while (preg_match('/\$[1-9]/', $item['name'], $matches, PREG_OFFSET_CAPTURE, $searchOffset)) {
						// matches[0][0] - matched param, [1] - second character of it
						$paramNumber = $matches[0][0][1] - 1;
						$replaceString = isset($keyParameters[$paramNumber]) ? $keyParameters[$paramNumber] : '';
						$searchOffset = $matches[0][1] + strlen($replaceString);

						$item['name'] = substr_replace($item['name'], $replaceString, $matches[0][1], 2);
					}
				}

				// set resolved item
				$items[$item['itemid']] = $item;
			}
		}

		return $items;
	}

	/**
	 * Resolve macros in item key.
	 *
	 * @param array  $items
	 * @param string $items[n]['itemid']
	 * @param string $items[n]['hostid']
	 * @param string $items[n]['key_']
	 *
	 * @return array
	 */
	private function resolveItemKeys(array $items) {
		$items = zbx_toHash($items, 'itemid');

		// host and ip macros
		$itemMacros = array();

		foreach ($items as $item) {
			$macros = $this->findMacros(self::PATTERN_ITEM_MACROS, array($item['key_']));

			if ($macros) {
				$itemMacros[$item['itemid']] = $macros;
			}
		}

		if ($itemMacros) {
			$dbItems = API::Item()->get(array(
				'itemids' => array_keys($itemMacros),
				'selectInterfaces' => array('ip', 'dns', 'useip'),
				'selectHosts' => array('host', 'name'),
				'webitems' => true,
				'output' => array('itemid'),
				'filter' => array('flags' => null),
				'preservekeys' => true
			));

			foreach ($dbItems as $dbItem) {
				$itemId = $dbItem['itemid'];
				$host = reset($dbItem['hosts']);
				$interface = reset($dbItem['interfaces']);

				// if item without interface or template item, resolve interface related macros to *UNKNOWN*
				if (!$interface) {
					$interface = array(
						'ip' => UNRESOLVED_MACRO_STRING,
						'dns' => UNRESOLVED_MACRO_STRING,
						'useip' => false
					);
				}

				$key = $items[$itemId]['key_'];

				foreach ($itemMacros[$itemId] as $macro) {
					switch ($macro) {
						case '{HOST.NAME}':
							$key = str_replace('{HOST.NAME}', $host['name'], $key);
							break;

						case '{HOSTNAME}': // deprecated
							$key = str_replace('{HOSTNAME}', $host['host'], $key);
							break;

						case '{HOST.HOST}':
							$key = str_replace('{HOST.HOST}', $host['host'], $key);
							break;

						case '{HOST.IP}':
							$key = str_replace('{HOST.IP}', $interface['ip'], $key);
							break;

						case '{IPADDRESS}': // deprecated
							$key = str_replace('{IPADDRESS}', $interface['ip'], $key);
							break;

						case '{HOST.DNS}':
							$key = str_replace('{HOST.DNS}', $interface['dns'], $key);
							break;

						case '{HOST.CONN}':
							$key = str_replace('{HOST.CONN}', $interface['useip'] ? $interface['ip'] : $interface['dns'], $key);
							break;
					}
				}

				$items[$itemId]['key_'] = $key;
			}
		}

		// user macros
		$items = $this->resolveItemUserMacros($items, 'key_');

		return $items;
	}

	/**
	 * Resolve user macros in items.
	 *
	 * @param array  $items
	 * @param string $items[n]['itemid']
	 * @param string $items[n]['hostid']
	 * @param string $items[n][$field]
	 * @param string $field
	 *
	 * @return array
	 */
	private function resolveItemUserMacros(array $items, $field) {
		$userMacros = array();

		foreach ($items as $item) {
			$macros = $this->findMacros(ZBX_PREG_EXPRESSION_USER_MACROS, array($item[$field]));

			if ($macros) {
				foreach ($macros as $macro) {
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

			foreach ($items as &$item) {
				if (isset($userMacros[$item['hostid']])) {
					$macros = $userMacros[$item['hostid']]['macros'];

					$item[$field] = str_replace(array_keys($macros), array_values($macros), $item[$field]);
				}
			}
			unset($item);
		}

		return $items;
	}
}
