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
		'triggerUrl' => array(
			'types' => array('trigger', 'host2', 'interface2', 'user'),
			'source' => 'url',
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
		),
		'screenElementURL' => array(
			'types' => array('host', 'hostId', 'interfaceWithoutPort', 'user'),
			'source' => 'url',
			'method' => 'resolveTexts'
		),
		'screenElementURLUser' => array(
			'types' => array('user'),
			'source' => 'url',
			'method' => 'resolveTexts'
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

		$hostMacrosAvailable = false;
		$agentInterfaceAvailable = false;
		$interfaceWithoutPortMacrosAvailable = false;

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

		if ($this->isTypeAvailable('hostId')) {
			foreach ($data as $hostId => $texts) {
				if ($hostId != 0) {
					$hostIdMacros = $this->findMacros(self::PATTERN_HOST_ID, $texts);
					if ($hostIdMacros) {
						foreach ($hostIdMacros as $hostMacro) {
							$macros[$hostId][$hostMacro] = $hostId;
						}
					}
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
			$pattern = '/'.self::PATTERN_HOST.'|'.self::PATTERN_HOST_ID.'|'.self::PATTERN_INTERFACE.'|'.
				ZBX_PREG_EXPRESSION_USER_MACROS.'/';

			foreach ($data as $hostId => $texts) {
				if (isset($macros[$hostId])) {
					foreach ($texts as $tnum => $text) {
						preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);

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
	 * @param string $triggers[$triggerId]['url']					depend from config
	 *
	 * @return array
	 */
	private function resolveTrigger(array $triggers) {
		$macros = array(
			'host' => array(),
			'host2' => array(),
			'interfaceWithoutPort' => array(),
			'interface' => array(),
			'interface2' => array(),
			'item' => array()
		);
		$macroValues = array();
		$userMacrosData = array();

		// get source field
		$source = $this->getSource();

		// get available functions
		$hostMacrosAvailable = $this->isTypeAvailable('host');
		$hostMacrosAvailable2 = $this->isTypeAvailable('host2');
		$interfaceWithoutPortMacrosAvailable = $this->isTypeAvailable('interfaceWithoutPort');
		$interfaceMacrosAvailable = $this->isTypeAvailable('interface');
		$interfaceMacrosAvailable2 = $this->isTypeAvailable('interface2');
		$itemMacrosAvailable = $this->isTypeAvailable('item');
		$userMacrosAvailable = $this->isTypeAvailable('user');
		$referenceMacrosAvailable = $this->isTypeAvailable('reference');
		$triggerMacrosAvailable = $this->isTypeAvailable('trigger');

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

			if ($hostMacrosAvailable2) {
				$foundMacros = $this->findFunctionMacros(self::PATTERN_HOST_FUNCTION2, $trigger[$source]);
				foreach ($foundMacros as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						$macroValues[$triggerId][$this->getFunctionMacroName($macro, $fNum)] = UNRESOLVED_MACRO_STRING;

						if (isset($functions[$fNum])) {
							$macros['host2'][$functions[$fNum]][$macro][] = $fNum;
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

			if ($interfaceMacrosAvailable2) {
				$foundMacros = $this->findFunctionMacros(self::PATTERN_INTERFACE_FUNCTION2, $trigger[$source]);
				foreach ($foundMacros as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						$macroValues[$triggerId][$this->getFunctionMacroName($macro, $fNum)] = UNRESOLVED_MACRO_STRING;

						if (isset($functions[$fNum])) {
							$macros['interface2'][$functions[$fNum]][$macro][] = $fNum;
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

			if ($triggerMacrosAvailable) {
				foreach ($this->findMacros(self::PATTERN_TRIGGER, array($trigger[$source])) as $macro) {
					$macroValues[$triggerId][$macro] = $triggerId;
				}
			}
		}

		$patterns = array();

		// get macro value
		if ($hostMacrosAvailable) {
			$macroValues = $this->getHostMacros($macros['host'], $macroValues);
			$patterns[] = self::PATTERN_HOST_FUNCTION;
		}

		if ($hostMacrosAvailable2) {
			$macroValues = $this->getHostMacros($macros['host2'], $macroValues);
			$patterns[] = self::PATTERN_HOST_FUNCTION2;
		}

		if ($interfaceWithoutPortMacrosAvailable) {
			$macroValues = $this->getIpMacros($macros['interfaceWithoutPort'], $macroValues, false);
			$patterns[] = self::PATTERN_INTERFACE_FUNCTION_WITHOUT_PORT;
		}

		if ($interfaceMacrosAvailable) {
			$macroValues = $this->getIpMacros($macros['interface'], $macroValues, true);
			$patterns[] = self::PATTERN_INTERFACE_FUNCTION;
		}

		if ($interfaceMacrosAvailable2) {
			$macroValues = $this->getIpMacros($macros['interface2'], $macroValues, true);
			$patterns[] = self::PATTERN_INTERFACE_FUNCTION2;
		}

		if ($itemMacrosAvailable) {
			$macroValues = $this->getItemMacros($macros['item'], $triggers, $macroValues);
			$patterns[] = self::PATTERN_ITEM_FUNCTION;
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
			$patterns[] = ZBX_PREG_EXPRESSION_USER_MACROS;
		}

		if ($referenceMacrosAvailable) {
			$patterns[] = '\$([1-9])';
		}

		if ($triggerMacrosAvailable) {
			$patterns[] = self::PATTERN_TRIGGER;
		}

		$pattern = '/'.implode('|', $patterns).'/';

		// replace macros to value
		foreach ($triggers as $triggerId => $trigger) {
			preg_match_all($pattern, $trigger[$source], $matches, PREG_OFFSET_CAPTURE);

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
	 * @param array  $graphs							list or hashmap of graphs
	 * @param string $graphs[]['name']				string in which macros should be resolved
	 * @param array  $graphs[]['items']				list of graph items
	 * @param int    $graphs[]['items'][n]['hostid']	graph n-th item corresponding host Id
	 * @param string $graphs[]['items'][n]['host']	graph n-th item corresponding host name
	 *
	 * @return string	inputted data with resolved source field
	 */
	private function resolveGraph($graphs) {
		if ($this->isTypeAvailable('graphFunctionalItem')) {
			$sourceKeyName = $this->getSource();

			$sourceStringList = array();
			$itemsList = array();

			foreach ($graphs as $graph) {
				$sourceStringList[] = $graph[$sourceKeyName];
				$itemsList[] = $graph['items'];
			}

			$resolvedStringList = $this->resolveGraphsFunctionalItemMacros($sourceStringList, $itemsList);
			$resolvedString = reset($resolvedStringList);

			foreach ($graphs as &$graph) {
				$graph[$sourceKeyName] = $resolvedString;
				$resolvedString = next($resolvedStringList);
			}
			unset($graph);
		}

		return $graphs;
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
	 * @param array  $sourceStringList			list of strings from graphs in which macros should be resolved
	 * @param array  $itemsList					list of lists of graph items used in graphs
	 * @param int    $itemsList[n][m]['hostid']	n-th graph m-th item corresponding host ID
	 * @param string $itemsList[n][m]['host']	n-th graph m-th item corresponding host name
	 *
	 * @return array	list of strings, possibly with macros in them replaced with resolved values
	 */
	private function resolveGraphsFunctionalItemMacros(array $sourceStringList, array $itemsList) {
		$hostKeyPairs = array();
		$matchesList = array();

		$items = reset($itemsList);
		foreach ($sourceStringList as $sourceString) {
			// Extract all macros into $matches - keys: macros, hosts, keys, functions and parameters are used
			// searches for macros, for example, "{somehost:somekey["param[123]"].min(10m)}"
			preg_match_all('/(?P<macros>{'.
				'(?P<hosts>('.ZBX_PREG_HOST_FORMAT.'|({('.self::PATTERN_HOST_INTERNAL.')'.self::PATTERN_MACRO_PARAM.'}))):'.
				'(?P<keys>'.ZBX_PREG_ITEM_KEY_FORMAT.')\.'.
				'(?P<functions>(last|max|min|avg))\('.
				'(?P<parameters>([0-9]+['.ZBX_TIME_SUFFIXES.']?)?)'.
				'\)}{1})/Uux', $sourceString, $matches, PREG_OFFSET_CAPTURE);

			foreach ($matches['hosts'] as $i => &$host) {
				$host[0] = $this->resolveGraphPositionalMacros($host[0], $items);

				if ($host[0] !== UNRESOLVED_MACRO_STRING) {
					// Take note that resolved host has a such key (and it is used in a macro).
					if (!isset($hostKeyPairs[$host[0]])) {
						$hostKeyPairs[$host[0]] = array();
					}
					$hostKeyPairs[$host[0]][$matches['keys'][$i][0]] = true;
				}
			}
			unset($host);

			// Remember match for later use.
			$matchesList[] = $matches;

			$items = next($itemsList);
		}

		// If no host/key pairs found in macro-like parts of source string then there is nothing to do but return
		// source strings as they are.
		if (!$hostKeyPairs) {
			return $sourceStringList;
		}

		// Build item retrieval query from host-key pairs and get all necessary items for all source strings
		$queryParts = array();
		foreach ($hostKeyPairs as $host => $keys) {
			$queryParts[] = '(h.host='.zbx_dbstr($host).' AND '.dbConditionString('i.key_', array_keys($keys)).')';
		}
		$items = DBfetchArrayAssoc(DBselect(
			'SELECT h.host,i.key_,i.itemid,i.value_type,i.units,i.valuemapid'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND ('.join(' OR ', $queryParts).')'
		), 'itemid');

		// Get items for which user has permission ...
		$allowedItems = API::Item()->get(array(
			'itemids' => array_keys($items),
			'webitems' => true,
			'output' => array('itemid', 'value_type', 'lastvalue', 'lastclock'),
			'preservekeys' => true
		));

		// ... and map item data only for those allowed items and set "value_type" for allowed items.
		foreach ($items as $item) {
			if (isset($allowedItems[$item['itemid']])) {
				$item['lastvalue'] = $allowedItems[$item['itemid']]['lastvalue'];
				$item['lastclock'] = $allowedItems[$item['itemid']]['lastclock'];
				$hostKeyPairs[$item['host']][$item['key_']] = $item;
			}
		}


		// replace macros with their corresponding values in graph strings
		// Replace macros with their resolved values in source strings.
		$matches = reset($matchesList);
		foreach ($sourceStringList as &$sourceString) {
			// We iterate array backwards so that replacing unresolved macro string (see lower) with actual value
			// does not mess up originally captured offsets!
			$i = count($matches['macros']);

			while ($i--) {
				$host = $matches['hosts'][$i][0];
				$key = $matches['keys'][$i][0];
				$function = $matches['functions'][$i][0];
				$parameter = $matches['parameters'][$i][0];

				// If host is real and item exists and has permissions
				if ($host !== UNRESOLVED_MACRO_STRING && is_array($hostKeyPairs[$host][$key])) {
					$item = $hostKeyPairs[$host][$key];

					// macro function is "last"
					if ($function == 'last') {
						$value = ($item['lastclock'] > 0)
							? formatHistoryValue($item['lastvalue'], $item)
							: UNRESOLVED_MACRO_STRING;
					}
					// For other macro functions ("max", "min" or "avg") get item value.
					else {
						$value = getItemFunctionalValue($item, $function, $parameter);
					}
				}
				// Or if there is no item with given key in given host, or there is no permissions to that item
				else {
					$value = UNRESOLVED_MACRO_STRING;
				}

				// Replace macro string with actual, resolved string value. This is safe because we start from far
				// end of $sourceString.
				$sourceString = substr_replace($sourceString, $value, $matches['macros'][$i][1],
					strlen($matches['macros'][$i][0])
				);
			}

			// Advance to next matches for next $sourceString
			$matches = next($matchesList);
		}
		unset($sourceString);

		return $sourceStringList;
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

	/**
	 * Expand functional macros in given map label.
	 *
	 * @param string $label			label to expand
	 * @param array  $replaceHosts	list of hosts in order which they appear in trigger expression if trigger label is given,
	 * or single host when host label is given
	 *
	 * @return string
	 */
	public function resolveMapLabelMacros($label, $replaceHosts = null) {
		$functionsPattern = '(last|max|min|avg)\(([0-9]+['.ZBX_TIME_SUFFIXES.']?)?\)';

		// find functional macro pattern
		$pattern = ($replaceHosts === null)
			? '/{'.ZBX_PREG_HOST_FORMAT.':.+\.'.$functionsPattern.'}/Uu'
			: '/{('.ZBX_PREG_HOST_FORMAT.'|{HOSTNAME[0-9]?}|{HOST\.HOST[0-9]?}):.+\.'.$functionsPattern.'}/Uu';

		preg_match_all($pattern, $label, $matches);

		// for each functional macro
		foreach ($matches[0] as $expr) {
			$macro = $expr;

			if ($replaceHosts !== null) {
				// search for macros with all possible indices
				foreach ($replaceHosts as $i => $host) {
					$macroTmp = $macro;

					// replace only macro in first position
					$macro = preg_replace('/{({HOSTNAME'.$i.'}|{HOST\.HOST'.$i.'}):(.*)}/U', '{'.$host['host'].':$2}', $macro);

					// only one simple macro possible inside functional macro
					if ($macro !== $macroTmp) {
						break;
					}
				}
			}

			// try to create valid expression
			$expressionData = new CTriggerExpression();

			if (!$expressionData->parse($macro) || !isset($expressionData->expressions[0])) {
				continue;
			}

			// look in DB for corresponding item
			$itemHost = $expressionData->expressions[0]['host'];
			$key = $expressionData->expressions[0]['item'];
			$function = $expressionData->expressions[0]['functionName'];

			$item = API::Item()->get(array(
				'output' => array('itemid', 'value_type', 'units', 'valuemapid', 'lastvalue', 'lastclock'),
				'webitems' => true,
				'filter' => array(
					'host' => $itemHost,
					'key_' => $key
				)
			));

			$item = reset($item);

			// if no corresponding item found with functional macro key and host
			if (!$item) {
				$label = str_replace($expr, UNRESOLVED_MACRO_STRING, $label);

				continue;
			}

			// do function type (last, min, max, avg) related actions
			if ($function === 'last') {
				$value = $item['lastclock'] ? formatHistoryValue($item['lastvalue'], $item) : UNRESOLVED_MACRO_STRING;
			}
			else {
				$value = getItemFunctionalValue($item, $function, $expressionData->expressions[0]['functionParamList'][0]);
			}

			if (isset($value)) {
				$label = str_replace($expr, $value, $label);
			}
		}

		return $label;
	}

	/**
	 * Resolve all kinds of macros in map labels.
	 *
	 * @param array  $selement
	 * @param string $selement['label']						label to expand
	 * @param int    $selement['elementtype']				element type
	 * @param int    $selement['elementid']					element id
	 * @param string $selement['elementExpressionTrigger']	if type is trigger, then trigger expression
	 *
	 * @return string
	 */
	public function resolveMapLabelMacrosAll(array $selement) {
		$label = $selement['label'];

		// for host and trigger items expand macros if they exists
		if (($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST || $selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER)
				&& (strpos($label, 'HOST.NAME') !== false
						|| strpos($label, 'HOSTNAME') !== false /* deprecated */
						|| strpos($label, 'HOST.HOST') !== false
						|| strpos($label, 'HOST.DESCRIPTION') !== false
						|| strpos($label, 'HOST.DNS') !== false
						|| strpos($label, 'HOST.IP') !== false
						|| strpos($label, 'IPADDRESS') !== false /* deprecated */
						|| strpos($label, 'HOST.CONN') !== false)) {
			// priorities of interface types doesn't match interface type ids in DB
			$priorities = array(
				INTERFACE_TYPE_AGENT => 4,
				INTERFACE_TYPE_SNMP => 3,
				INTERFACE_TYPE_JMX => 2,
				INTERFACE_TYPE_IPMI => 1
			);

			// get host data if element is host
			if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST) {
				$res = DBselect(
					'SELECT hi.ip,hi.dns,hi.useip,h.host,h.name,h.description,hi.type AS interfacetype'.
					' FROM interface hi,hosts h'.
					' WHERE hi.hostid=h.hostid'.
						' AND hi.main=1 AND hi.hostid='.zbx_dbstr($selement['elementid'])
				);

				// process interface priorities
				$tmpPriority = 0;

				while ($dbHost = DBfetch($res)) {
					if ($priorities[$dbHost['interfacetype']] > $tmpPriority) {
						$resHost = $dbHost;
						$tmpPriority = $priorities[$dbHost['interfacetype']];
					}
				}

				$hostsByNr[''] = $resHost;
			}
			// get trigger host list if element is trigger
			else {
				$res = DBselect(
					'SELECT hi.ip,hi.dns,hi.useip,h.host,h.name,h.description,f.functionid,hi.type AS interfacetype'.
					' FROM interface hi,items i,functions f,hosts h'.
					' WHERE h.hostid=hi.hostid'.
						' AND hi.hostid=i.hostid'.
						' AND i.itemid=f.itemid'.
						' AND hi.main=1 AND f.triggerid='.zbx_dbstr($selement['elementid']).
					' ORDER BY f.functionid'
				);

				// process interface priorities, build $hostsByFunctionId array
				$tmpFunctionId = -1;

				while ($dbHost = DBfetch($res)) {
					if ($dbHost['functionid'] != $tmpFunctionId) {
						$tmpPriority = 0;
						$tmpFunctionId = $dbHost['functionid'];
					}

					if ($priorities[$dbHost['interfacetype']] > $tmpPriority) {
						$hostsByFunctionId[$dbHost['functionid']] = $dbHost;
						$tmpPriority = $priorities[$dbHost['interfacetype']];
					}
				}

				// get all function ids from expression and link host data against position in expression
				preg_match_all('/\{([0-9]+)\}/', $selement['elementExpressionTrigger'], $matches);

				$hostsByNr = array();

				foreach ($matches[1] as $i => $functionid) {
					if (isset($hostsByFunctionId[$functionid])) {
						$hostsByNr[$i + 1] = $hostsByFunctionId[$functionid];
					}
				}

				// for macro without numeric index
				if (isset($hostsByNr[1])) {
					$hostsByNr[''] = $hostsByNr[1];
				}
			}

			// resolve functional macros like: {{HOST.HOST}:log[{HOST.HOST}.log].last(0)}
			$label = $this->resolveMapLabelMacros($label, $hostsByNr);

			// resolves basic macros
			// $hostsByNr possible keys: '' and 1-9
			foreach ($hostsByNr as $i => $host) {
				$replace = array(
					'{HOST.NAME'.$i.'}' => $host['name'],
					'{HOSTNAME'.$i.'}' => $host['host'],
					'{HOST.HOST'.$i.'}' => $host['host'],
					'{HOST.DESCRIPTION'.$i.'}' => $host['description'],
					'{HOST.DNS'.$i.'}' => $host['dns'],
					'{HOST.IP'.$i.'}' => $host['ip'],
					'{IPADDRESS'.$i.'}' => $host['ip'],
					'{HOST.CONN'.$i.'}' => $host['useip'] ? $host['ip'] : $host['dns']
				);

				$label = str_replace(array_keys($replace), $replace, $label);
			}
		}
		else {
			// resolve functional macros like: {sampleHostName:log[{HOST.HOST}.log].last(0)}, if no host provided
			$label = $this->resolveMapLabelMacros($label);
		}

		// resolve map specific processing consuming macros
		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_HOST:
			case SYSMAP_ELEMENT_TYPE_MAP:
			case SYSMAP_ELEMENT_TYPE_TRIGGER:
			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				if (strpos($label, '{TRIGGERS.UNACK}') !== false) {
					$label = str_replace('{TRIGGERS.UNACK}', get_triggers_unacknowledged($selement), $label);
				}
				if (strpos($label, '{TRIGGERS.PROBLEM.UNACK}') !== false) {
					$label = str_replace('{TRIGGERS.PROBLEM.UNACK}', get_triggers_unacknowledged($selement, true), $label);
				}
				if (strpos($label, '{TRIGGER.EVENTS.UNACK}') !== false) {
					$label = str_replace('{TRIGGER.EVENTS.UNACK}', get_events_unacknowledged($selement), $label);
				}
				if (strpos($label, '{TRIGGER.EVENTS.PROBLEM.UNACK}') !== false) {
					$label = str_replace('{TRIGGER.EVENTS.PROBLEM.UNACK}',
						get_events_unacknowledged($selement, null, TRIGGER_VALUE_TRUE), $label);
				}
				if (strpos($label, '{TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK}') !== false) {
					$label = str_replace('{TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK}',
						get_events_unacknowledged($selement, TRIGGER_VALUE_TRUE, TRIGGER_VALUE_TRUE), $label);
				}
				if (strpos($label, '{TRIGGERS.ACK}') !== false) {
					$label = str_replace('{TRIGGERS.ACK}',
						get_triggers_unacknowledged($selement, null, true), $label);
				}
				if (strpos($label, '{TRIGGERS.PROBLEM.ACK}') !== false) {
					$label = str_replace('{TRIGGERS.PROBLEM.ACK}',
						get_triggers_unacknowledged($selement, true, true), $label);
				}
				if (strpos($label, '{TRIGGER.EVENTS.ACK}') !== false) {
					$label = str_replace('{TRIGGER.EVENTS.ACK}',
						get_events_unacknowledged($selement, null, null, true), $label);
				}
				if (strpos($label, '{TRIGGER.EVENTS.PROBLEM.ACK}') !== false) {
					$label = str_replace('{TRIGGER.EVENTS.PROBLEM.ACK}',
						get_events_unacknowledged($selement, null, TRIGGER_VALUE_TRUE, true), $label);
				}
				if (strpos($label, '{TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK}') !== false) {
					$label = str_replace('{TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK}',
						get_events_unacknowledged($selement, TRIGGER_VALUE_TRUE, TRIGGER_VALUE_TRUE, true), $label);
				}
				break;
		}

		return $label;
	}
}
