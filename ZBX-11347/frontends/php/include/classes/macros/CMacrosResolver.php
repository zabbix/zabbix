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


class CMacrosResolver extends CMacrosResolverGeneral {

	/**
	 * Supported macros resolving scenarios.
	 *
	 * @var array
	 */
	protected $configs = [
		'scriptConfirmation' => [
			'types' => ['host', 'interfaceWithoutPort', 'user'],
			'method' => 'resolveTexts'
		],
		'httpTestName' => [
			'types' => ['host', 'interfaceWithoutPort', 'user'],
			'method' => 'resolveTexts'
		],
		'hostInterfaceIpDns' => [
			'types' => ['host', 'agentInterface', 'user'],
			'method' => 'resolveTexts'
		],
		'hostInterfaceIpDnsAgentPrimary' => [
			'types' => ['host', 'user'],
			'method' => 'resolveTexts'
		],
		'hostInterfacePort' => [
			'types' => ['user'],
			'method' => 'resolveTexts'
		],
		'graphName' => [
			'types' => ['graphFunctionalItem'],
			'source' => 'name',
			'method' => 'resolveGraph'
		],
		'screenElementURL' => [
			'types' => ['host', 'hostId', 'interfaceWithoutPort', 'user'],
			'source' => 'url',
			'method' => 'resolveTexts'
		],
		'screenElementURLUser' => [
			'types' => ['user'],
			'source' => 'url',
			'method' => 'resolveTexts'
		]
	];

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
			return [];
		}

		$this->config = $options['config'];

		// Call method.
		$method = $this->configs[$this->config]['method'];

		return $this->$method($options['data']);
	}

	/**
	 * Batch resolving macros in text using host id.
	 *
	 * @param array $data	(as $hostid => array(texts))
	 *
	 * @return array		(as $hostid => array(texts))
	 */
	private function resolveTexts(array $data) {
		$types = [];

		if ($this->isTypeAvailable('host')) {
			$types['macros']['host'] = ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'];
		}

		if ($this->isTypeAvailable('hostId')) {
			$types['macros']['hostId'] = ['{HOST.ID}'];
		}

		if ($this->isTypeAvailable('agentInterface') || $this->isTypeAvailable('interfaceWithoutPort')) {
			$types['macros']['interface'] = ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}'];
		}

		if ($this->isTypeAvailable('user')) {
			$types['usermacros'] = true;
		}

		$macros = [];
		$usermacros = [];
		$host_hostids = [];
		$interface_hostids = [];

		foreach ($data as $hostid => $texts) {
			$matched_macros = $this->extractMacros($texts, $types);

			if (array_key_exists('macros', $matched_macros)) {
				if (array_key_exists('host', $matched_macros['macros']) && $matched_macros['macros']['host']) {
					foreach ($matched_macros['macros']['host'] as $macro) {
						$macros[$hostid][$macro] = UNRESOLVED_MACRO_STRING;
					}
					$host_hostids[$hostid] = true;
				}

				if (array_key_exists('hostId', $matched_macros['macros']) && $hostid != 0) {
					foreach ($matched_macros['macros']['hostId'] as $macro) {
						$macros[$hostid][$macro] = $hostid;
					}
				}

				if (array_key_exists('interface', $matched_macros['macros'])
						&& $matched_macros['macros']['interface']) {
					foreach ($matched_macros['macros']['interface'] as $macro) {
						$macros[$hostid][$macro] = UNRESOLVED_MACRO_STRING;
					}
					$interface_hostids[$hostid] = true;
				}
			}

			if ($this->isTypeAvailable('user') && $matched_macros['usermacros']) {
				$usermacros[$hostid] = ['hostids' => [$hostid], 'macros' => $matched_macros['usermacros']];
			}
		}

		// Host macros.
		if ($host_hostids) {
			$dbHosts = DBselect(
				'SELECT h.hostid,h.name,h.host'.
				' FROM hosts h'.
				' WHERE '.dbConditionInt('h.hostid', array_keys($host_hostids))
			);

			while ($dbHost = DBfetch($dbHosts)) {
				$hostid = $dbHost['hostid'];

				if (array_key_exists($hostid, $macros)) {
					foreach ($macros[$hostid] as $macro => &$value) {
						switch ($macro) {
							case '{HOSTNAME}':
							case '{HOST.HOST}':
								$value = $dbHost['host'];
								break;

							case '{HOST.NAME}':
								$value = $dbHost['name'];
								break;
						}
					}
					unset($value);
				}
			}
		}

		// Interface macros, macro should be resolved to main agent interface.
		if ($this->isTypeAvailable('agentInterface') && $interface_hostids) {
			$dbInterfaces = DBselect(
				'SELECT i.hostid,i.ip,i.dns,i.useip'.
				' FROM interface i'.
				' WHERE i.main='.INTERFACE_PRIMARY.
					' AND i.type='.INTERFACE_TYPE_AGENT.
					' AND '.dbConditionInt('i.hostid', array_keys($interface_hostids))
			);

			while ($dbInterface = DBfetch($dbInterfaces)) {
				$hostid = $dbInterface['hostid'];

				$dbInterfaceTexts = [$dbInterface['ip'], $dbInterface['dns']];

				if ($this->hasMacros($dbInterfaceTexts,
						['macros' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'], 'usermacros' => true])) {
					$saveCurrentConfig = $this->config;

					$dbInterfaceMacros = $this->resolve([
						'config' => 'hostInterfaceIpDnsAgentPrimary',
						'data' => [$hostid => $dbInterfaceTexts]
					]);

					$dbInterfaceMacros = reset($dbInterfaceMacros);
					$dbInterface['ip'] = $dbInterfaceMacros[0];
					$dbInterface['dns'] = $dbInterfaceMacros[1];

					$this->config = $saveCurrentConfig;
				}

				if (array_key_exists($hostid, $macros)) {
					foreach ($macros[$hostid] as $macro => &$value) {
						switch ($macro) {
							case '{IPADDRESS}':
							case '{HOST.IP}':
								$value = $dbInterface['ip'];
								break;

							case '{HOST.DNS}':
								$value = $dbInterface['dns'];
								break;

							case '{HOST.CONN}':
								$value = $dbInterface['useip'] ? $dbInterface['ip'] : $dbInterface['dns'];
								break;
						}
					}
					unset($value);
				}
			}
		}

		// Interface macros, macro should be resolved to interface with highest priority.
		if ($this->isTypeAvailable('interfaceWithoutPort') && $interface_hostids) {
			$interfaces_by_priority = [];

			$interfaces = DBfetchArray(DBselect(
				'SELECT i.hostid,i.interfaceid,i.ip,i.dns,i.useip,i.port,i.type,i.main'.
				' FROM interface i'.
				' WHERE i.main='.INTERFACE_PRIMARY.
					' AND '.dbConditionInt('i.hostid', array_keys($interface_hostids)).
					' AND '.dbConditionInt('i.type', $this->interfacePriorities)
			));

			$interfaces = CMacrosResolverHelper::resolveHostInterfaces($interfaces);

			// Items with no interfaces must collect interface data from host.
			foreach ($interfaces as $interface) {
				$hostid = $interface['hostid'];
				$priority = $this->interfacePriorities[$interface['type']];

				if (!array_key_exists($hostid, $interfaces_by_priority)
						|| $priority > $this->interfacePriorities[$interfaces_by_priority[$hostid]['type']]) {
					$interfaces_by_priority[$hostid] = $interface;
				}
			}

			foreach ($interfaces_by_priority as $hostid => $interface) {
				foreach ($macros[$hostid] as $macro => &$value) {
					switch ($macro) {
						case '{IPADDRESS}':
						case '{HOST.IP}':
							$value = $interface['ip'];
							break;
						case '{HOST.DNS}':
							$value = $interface['dns'];
							break;
						case '{HOST.CONN}':
							$value = $interface['useip'] ? $interface['ip'] : $interface['dns'];
							break;
					}
				}
				unset($value);
			}
		}

		// Get user macros.
		if ($this->isTypeAvailable('user')) {
			foreach ($this->getUserMacros($usermacros) as $hostid => $usermacros_data) {
				$macros[$hostid] = array_key_exists($hostid, $macros)
					? array_merge($macros[$hostid], $usermacros_data['macros'])
					: $usermacros_data['macros'];
			}
		}

		$types = $this->transformToPositionTypes($types);

		// Replace macros to value.
		foreach (array_keys($macros) as $hostid) {
			foreach ($data[$hostid] as &$text) {
				$matched_macros = $this->getMacroPositions($text, $types);

				foreach (array_reverse($matched_macros, true) as $pos => $macro) {
					$text = substr_replace($text, $macros[$hostid][$macro], $pos, strlen($macro));
				}
			}
			unset($text);
		}

		return $data;
	}

	/**
	 * Resolve macros in trigger name.
	 *
	 * @param string $triggers[$triggerid]['expression']
	 * @param string $triggers[$triggerid]['description']
	 * @param int    $triggers[$triggerid]['clock']			(optional)
	 * @param int    $triggers[$triggerid]['ns']			(optional)
	 * @param array  $options
	 * @param bool   $options['references_only']			resolve only $1-$9 macros
	 * @param bool   $options['events']						resolve {ITEM.VALUE} macro using 'clock' and 'ns' fields
	 *
	 * @return array
	 */
	public function resolveTriggerNames(array $triggers, array $options) {
		$macros = [
			'host' => [],
			'interface' => [],
			'item' => [],
			'references' => []
		];
		$usermacros = [];
		$macro_values = [];

		$types = [
			'macros_n' => [
				'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
				'interface' => ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
				'item' => ['{ITEM.LASTVALUE}', '{ITEM.VALUE}']
			],
			'references' => true,
			'usermacros' => true
		];

		$original_triggers = $triggers;
		$triggers = $this->resolveTriggerExpressionUserMacro($triggers);

		// Find macros.
		foreach ($triggers as $triggerid => $trigger) {
			$matched_macros = $this->extractMacros([$trigger['description']], $types);

			if (!$options['references_only']) {
				$functionids = $this->findFunctions($trigger['expression']);

				foreach ($matched_macros['macros_n']['host'] as $macro => $f_nums) {
					foreach ($f_nums as $f_num) {
						$macro_values[$triggerid][$this->getFunctionMacroName($macro, $f_num)] =
							UNRESOLVED_MACRO_STRING;

						if (array_key_exists($f_num, $functionids)) {
							$macros['host'][$functionids[$f_num]][$macro][] = $f_num;
						}
					}
				}

				foreach ($matched_macros['macros_n']['interface'] as $macro => $f_nums) {
					foreach ($f_nums as $f_num) {
						$macro_values[$triggerid][$this->getFunctionMacroName($macro, $f_num)] =
							UNRESOLVED_MACRO_STRING;

						if (array_key_exists($f_num, $functionids)) {
							$macros['interface'][$functionids[$f_num]][$macro][] = $f_num;
						}
					}
				}

				foreach ($matched_macros['macros_n']['item'] as $macro => $f_nums) {
					foreach ($f_nums as $f_num) {
						$macro_values[$triggerid][$this->getFunctionMacroName($macro, $f_num)] =
							UNRESOLVED_MACRO_STRING;

						if (array_key_exists($f_num, $functionids)) {
							$macros['item'][$functionids[$f_num]][$macro][] = $f_num;
						}
					}
				}

				if ($matched_macros['usermacros']) {
					$usermacros[$triggerid] = ['hostids' => [], 'macros' => $matched_macros['usermacros']];
				}
			}

			if ($matched_macros['references']) {
				$references = $this->resolveTriggerReferences($trigger['expression'], $matched_macros['references']);

				$macro_values[$triggerid] = array_key_exists($triggerid, $macro_values)
					? array_merge($macro_values[$triggerid], $references)
					: $references;
			}

			$triggers[$triggerid]['expression'] = $original_triggers[$triggerid]['expression'];
		}

		if (!$options['references_only']) {
			// Get macro value.
			$macro_values = $this->getHostMacros($macros['host'], $macro_values);
			$macro_values = $this->getIpMacros($macros['interface'], $macro_values, true);
			$macro_values = $this->getItemMacros($macros['item'], $triggers, $macro_values, $options['events']);

			if ($usermacros) {
				// Get hosts for triggers.
				$db_triggers = API::Trigger()->get([
					'output' => [],
					'selectHosts' => ['hostid'],
					'triggerids' => array_keys($usermacros),
					'preservekeys' => true
				]);

				foreach ($usermacros as $triggerid => &$usermacros_data) {
					if (array_key_exists($triggerid, $db_triggers)) {
						$usermacros_data['hostids'] = zbx_objectValues($db_triggers[$triggerid]['hosts'], 'hostid');
					}
				}
				unset($usermacros_data);

				// Get user macros values.
				foreach ($this->getUserMacros($usermacros) as $triggerid => $usermacros_data) {
					$macro_values[$triggerid] = array_key_exists($triggerid, $macro_values)
						? array_merge($macro_values[$triggerid], $usermacros_data['macros'])
						: $usermacros_data['macros'];
				}
			}
		}

		$types = $this->transformToPositionTypes($types);

		// Replace macros to value.
		foreach ($macro_values as $triggerid => $macro) {
			$trigger = &$triggers[$triggerid];

			$matched_macros = $this->getMacroPositions($trigger['description'], $types);

			foreach (array_reverse($matched_macros, true) as $pos => $macro) {
				if (array_key_exists($macro, $macro_values[$triggerid])) {
					$trigger['description'] = substr_replace($trigger['description'], $macro_values[$triggerid][$macro],
						$pos, strlen($macro)
					);
				}
			}
		}
		unset($trigger);

		return $triggers;
	}

	/**
	 * Resolve macros in trigger description.
	 *
	 * @param string $triggers[$triggerid]['expression']
	 * @param string $triggers[$triggerid]['comments']
	 *
	 * @return array
	 */
	public function resolveTriggerDescriptions(array $triggers) {
		$macros = [
			'host' => [],
			'interface' => [],
			'item' => []
		];
		$usermacros = [];
		$macro_values = [];

		$types = [
			'macros_n' => [
				'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
				'interface' => ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
				'item' => ['{ITEM.LASTVALUE}', '{ITEM.VALUE}']
			],
			'usermacros' => true
		];

		// Find macros.
		foreach ($triggers as $triggerid => $trigger) {
			$functionids = $this->findFunctions($trigger['expression']);

			$matched_macros = $this->extractMacros([$trigger['comments']], $types);

			foreach ($matched_macros['macros_n']['host'] as $macro => $f_nums) {
				foreach ($f_nums as $f_num) {
					$macro_values[$triggerid][$this->getFunctionMacroName($macro, $f_num)] = UNRESOLVED_MACRO_STRING;

					if (array_key_exists($f_num, $functionids)) {
						$macros['host'][$functionids[$f_num]][$macro][] = $f_num;
					}
				}
			}

			foreach ($matched_macros['macros_n']['interface'] as $macro => $f_nums) {
				foreach ($f_nums as $f_num) {
					$macro_values[$triggerid][$this->getFunctionMacroName($macro, $f_num)] = UNRESOLVED_MACRO_STRING;

					if (array_key_exists($f_num, $functionids)) {
						$macros['interface'][$functionids[$f_num]][$macro][] = $f_num;
					}
				}
			}

			foreach ($matched_macros['macros_n']['item'] as $macro => $f_nums) {
				foreach ($f_nums as $f_num) {
					$macro_values[$triggerid][$this->getFunctionMacroName($macro, $f_num)] = UNRESOLVED_MACRO_STRING;

					if (array_key_exists($f_num, $functionids)) {
						$macros['item'][$functionids[$f_num]][$macro][] = $f_num;
					}
				}
			}

			if ($matched_macros['usermacros']) {
				$usermacros[$triggerid] = ['hostids' => [], 'macros' => $matched_macros['usermacros']];
			}
		}

		// Get macro value.
		$macro_values = $this->getHostMacros($macros['host'], $macro_values);
		$macro_values = $this->getIpMacros($macros['interface'], $macro_values, true);
		$macro_values = $this->getItemMacros($macros['item'], $triggers, $macro_values, false);

		if ($usermacros) {
			// Get hosts for triggers.
			$db_triggers = API::Trigger()->get([
				'output' => [],
				'selectHosts' => ['hostid'],
				'triggerids' => array_keys($usermacros),
				'preservekeys' => true
			]);

			foreach ($usermacros as $triggerid => &$usermacros_data) {
				if (array_key_exists($triggerid, $db_triggers)) {
					$usermacros_data['hostids'] = zbx_objectValues($db_triggers[$triggerid]['hosts'], 'hostid');
				}
			}
			unset($usermacros_data);

			// Get user macros values.
			foreach ($this->getUserMacros($usermacros) as $triggerid => $usermacros_data) {
				$macro_values[$triggerid] = array_key_exists($triggerid, $macro_values)
					? array_merge($macro_values[$triggerid], $usermacros_data['macros'])
					: $usermacros_data['macros'];
			}
		}

		$types = $this->transformToPositionTypes($types);

		// Replace macros to value
		foreach ($macro_values as $triggerid => $macro) {
			$trigger = &$triggers[$triggerid];

			$matched_macros = $this->getMacroPositions($trigger['comments'], $types);

			foreach (array_reverse($matched_macros, true) as $pos => $macro) {
				$trigger['comments'] =
					substr_replace($trigger['comments'], $macro_values[$triggerid][$macro], $pos, strlen($macro));
			}
		}
		unset($trigger);

		return $triggers;
	}

	/**
	 * Resolve macros in trigger URL.
	 *
	 * @param string $triggers[$triggerid]['expression']
	 * @param string $triggers[$triggerid]['url']
	 *
	 * @return array
	 */
	public function resolveTriggerUrls(array $triggers) {
		$macros = [
			'host' => [],
			'interface' => []
		];
		$usermacros = [];
		$macro_values = [];

		$types = [
			'macros' => [
				'trigger' => ['{TRIGGER.ID}']
			],
			'macros_n' => [
				'host' => ['{HOST.ID}', '{HOST.HOST}', '{HOST.NAME}'],
				'interface' => ['{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}']
			],
			'usermacros' => true
		];

		// Find macros.
		foreach ($triggers as $triggerid => $trigger) {
			$functionids = $this->findFunctions($trigger['expression']);

			$matched_macros = $this->extractMacros([$trigger['url']], $types);

			foreach ($matched_macros['macros']['trigger'] as $macro) {
				$macro_values[$triggerid][$macro] = $triggerid;
			}

			foreach ($matched_macros['macros_n']['host'] as $macro => $f_nums) {
				foreach ($f_nums as $f_num) {
					$macro_values[$triggerid][$this->getFunctionMacroName($macro, $f_num)] = UNRESOLVED_MACRO_STRING;

					if (array_key_exists($f_num, $functionids)) {
						$macros['host'][$functionids[$f_num]][$macro][] = $f_num;
					}
				}
			}

			foreach ($matched_macros['macros_n']['interface'] as $macro => $f_nums) {
				foreach ($f_nums as $f_num) {
					$macro_values[$triggerid][$this->getFunctionMacroName($macro, $f_num)] = UNRESOLVED_MACRO_STRING;

					if (array_key_exists($f_num, $functionids)) {
						$macros['interface'][$functionids[$f_num]][$macro][] = $f_num;
					}
				}
			}

			if ($matched_macros['usermacros']) {
				$usermacros[$triggerid] = ['hostids' => [], 'macros' => $matched_macros['usermacros']];
			}
		}

		// Get macro value.
		$macro_values = $this->getHostMacros($macros['host'], $macro_values);
		$macro_values = $this->getIpMacros($macros['interface'], $macro_values, true);

		if ($usermacros) {
			// Get hosts for triggers.
			$db_triggers = API::Trigger()->get([
				'output' => [],
				'selectHosts' => ['hostid'],
				'triggerids' => array_keys($usermacros),
				'preservekeys' => true
			]);

			foreach ($usermacros as $triggerid => &$usermacros_data) {
				if (array_key_exists($triggerid, $db_triggers)) {
					$usermacros_data['hostids'] = zbx_objectValues($db_triggers[$triggerid]['hosts'], 'hostid');
				}
			}
			unset($usermacros_data);

			// Get user macros values.
			foreach ($this->getUserMacros($usermacros) as $triggerid => $usermacros_data) {
				$macro_values[$triggerid] = array_key_exists($triggerid, $macro_values)
					? array_merge($macro_values[$triggerid], $usermacros_data['macros'])
					: $usermacros_data['macros'];
			}
		}

		$types = $this->transformToPositionTypes($types);

		// Replace macros to value.
		foreach ($triggers as $triggerid => &$trigger) {
			$matched_macros = $this->getMacroPositions($trigger['url'], $types);

			foreach (array_reverse($matched_macros, true) as $pos => $macro) {
				$trigger['url'] =
					substr_replace($trigger['url'], $macro_values[$triggerid][$macro], $pos, strlen($macro));
			}
		}
		unset($trigger);

		return $triggers;
	}

	/**
	 * Purpose: Translate {10}>10 to something like {localhost:system.cpu.load.last()}>10
	 *
	 * @param array  $triggers
	 * @param string $triggers[]['expression']
	 * @param array  $options
	 * @param bool   $options['html']				returns formatted trigger expression
	 * @param bool   $options['resolve_usermacros']	resolve user macros
	 * @param bool   $options['resolve_macros']		resolve macros in item keys and functions
	 *
	 * @return string|array
	 */
	public function resolveTriggerExpressions(array $triggers, array $options) {
		$functionids = [];
		$usermacros = [];
		$macro_values = [];

		$types = [
			'macros' => [
				'trigger' => ['{TRIGGER.VALUE}']
			],
			'functionids' => true,
			'lldmacros' => true,
			'usermacros' => true
		];

		// Find macros.
		foreach ($triggers as $key => $trigger) {
			$matched_macros = $this->extractMacros([$trigger['expression']], $types);

			$macro_values[$key] = $matched_macros['functionids'];

			foreach (array_keys($matched_macros['functionids']) as $macro) {
				$functionids[] = substr($macro, 1, -1); // strip curly braces
			}

			if ($options['resolve_usermacros'] && $matched_macros['usermacros']) {
				$usermacros[$key] = ['hostids' => [], 'macros' => $matched_macros['usermacros']];
			}
		}

		// Get macro values.
		if ($functionids) {
			$functions = [];

			// Selecting functions.
			$result = DBselect(
				'SELECT f.functionid,f.itemid,f.function,f.parameter'.
				' FROM functions f'.
				' WHERE '.dbConditionInt('f.functionid', $functionids)
			);

			$hostids = [];
			$itemids = [];
			$hosts = [];
			$items = [];

			while ($row = DBfetch($result)) {
				$itemids[$row['itemid']] = true;

				$functions['{'.$row['functionid'].'}'] = $row;
				unset($functions['{'.$row['functionid'].'}']['functionid']);
			}

			// Selecting items.
			if ($itemids) {
				if ($options['html']) {
					$sql = 'SELECT i.itemid,i.hostid,i.key_,i.type,i.flags,i.status,i.state,id.parent_itemid'.
						' FROM items i'.
							' LEFT JOIN item_discovery id ON i.itemid=id.itemid'.
						' WHERE '.dbConditionInt('i.itemid', array_keys($itemids));
				}
				else {
					$sql = 'SELECT i.itemid,i.hostid,i.key_'.
						' FROM items i'.
						' WHERE '.dbConditionInt('i.itemid', array_keys($itemids));
				}
				$result = DBselect($sql);

				while ($row = DBfetch($result)) {
					$hostids[$row['hostid']] = true;
					$items[$row['itemid']] = $row;
				}
			}

			// Selecting hosts.
			if ($hostids) {
				$result = DBselect(
					'SELECT h.hostid,h.host FROM hosts h WHERE '.dbConditionInt('h.hostid', array_keys($hostids))
				);

				while ($row = DBfetch($result)) {
					$hosts[$row['hostid']] = $row;
				}
			}

			if ($options['resolve_macros']) {
				$items = $this->resolveItemKeys($items);
				foreach ($items as &$item) {
					$item['key_'] = $item['key_expanded'];
					unset($item['key_expanded']);
				}
				unset($item);
			}

			foreach ($functions as $macro => &$function) {
				if (!array_key_exists($function['itemid'], $items)) {
					unset($functions[$macro]);
					continue;
				}
				$item = $items[$function['itemid']];

				if (!array_key_exists($item['hostid'], $hosts)) {
					unset($functions[$macro]);
					continue;
				}
				$host = $hosts[$item['hostid']];

				$function['hostid'] = $item['hostid'];
				$function['host'] = $host['host'];
				$function['key_'] = $item['key_'];
				if ($options['html']) {
					$function['type'] = $item['type'];
					$function['flags'] = $item['flags'];
					$function['status'] = $item['status'];
					$function['state'] = $item['state'];
					$function['parent_itemid'] = $item['parent_itemid'];
				}
			}
			unset($function);

			if ($options['resolve_macros']) {
				$functions = $this->resolveFunctionParameters($functions);
				foreach ($functions as &$function) {
					$function['parameter'] = $function['parameter_expanded'];
					unset($function['parameter_expanded']);
				}
				unset($function);
			}

			foreach ($macro_values as &$macros) {
				foreach ($macros as $macro => &$value) {
					if (array_key_exists($macro, $functions)) {
						$function = $functions[$macro];

						if ($options['html']) {
							$style = ($function['status'] == ITEM_STATUS_ACTIVE)
								? ($function['state'] == ITEM_STATE_NORMAL) ? ZBX_STYLE_GREEN : ZBX_STYLE_GREY
								: $style = ZBX_STYLE_RED;

							if ($function['flags'] == ZBX_FLAG_DISCOVERY_CREATED
									|| $function['type'] == ITEM_TYPE_HTTPTEST) {
								$link = (new CSpan($function['host'].':'.$function['key_']))->addClass($style);
							}
							elseif ($function['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
								$link = (new CLink($function['host'].':'.$function['key_'],
									'disc_prototypes.php?form=update&itemid='.$function['itemid'].
									'&parent_discoveryid='.$function['parent_itemid']
								))
									->addClass(ZBX_STYLE_LINK_ALT)
									->addClass($style);
							}
							else {
								$link = (new CLink($function['host'].':'.$function['key_'],
									'items.php?form=update&itemid='.$function['itemid']
								))
									->addClass(ZBX_STYLE_LINK_ALT)
									->addClass($style);
							}

							$value = [
								'{', $link, '.', bold($function['function'].'('), $function['parameter'], bold(')'), '}'
							];
						}
						else {
							$value = '{'.
								$function['host'].':'.
								$function['key_'].'.'.
								$function['function'].'('.$function['parameter'].')'.
							'}';
						}
					}
					else {
						$value = $options['html'] ? (new CSpan('*ERROR*'))->addClass(ZBX_STYLE_RED) : '*ERROR*';
					}
				}
				unset($value);
			}
			unset($macros);

			foreach ($usermacros as $key => &$usermacros_data) {
				foreach (array_keys($macro_values[$key]) as $macro) {
					if (array_key_exists($macro, $functions)) {
						$usermacros_data['hostids'][$functions[$macro]['hostid']] = true;
					}
				}
				$usermacros_data['hostids'] = array_keys($usermacros_data['hostids']);
			}
			unset($usermacros_data);

			// Get user macros values.
			foreach ($this->getUserMacros($usermacros) as $key => $usermacros_data) {
				$macro_values[$key] = array_key_exists($key, $macro_values)
					? array_merge($macro_values[$key], $usermacros_data['macros'])
					: $usermacros_data['macros'];
			}
		}

		$types = $this->transformToPositionTypes($types);

		// Replace macros to value.
		foreach ($triggers as $key => &$trigger) {
			$matched_macros = $this->getMacroPositions($trigger['expression'], $types);

			if ($options['html']) {
				$expression = [];
				$pos_left = 0;

				foreach ($matched_macros as $pos => $macro) {
					if (array_key_exists($macro, $macro_values[$key])) {
						if ($pos_left != $pos) {
							$expression[] = substr($trigger['expression'], $pos_left, $pos - $pos_left);
						}

						$expression[] = $macro_values[$key][$macro];

						$pos_left = $pos + strlen($macro);
					}
				}
				$expression[] = substr($trigger['expression'], $pos_left);

				$trigger['expression'] = $expression;
			}
			else {
				foreach (array_reverse($matched_macros, true) as $pos => $macro) {
					if (array_key_exists($macro, $macro_values[$key])) {
						$trigger['expression'] = substr_replace($trigger['expression'],
							$macro_values[$key][$macro], $pos, strlen($macro));
					}
				}
			}
		}
		unset($trigger);

		return $triggers;
	}

	/**
	 * Resolve user macros in trigger expression.
	 *
	 * @param string $triggers[$triggerid]['expression']
	 *
	 * @return array
	 */
	public function resolveTriggerExpressionUserMacro(array $triggers) {
		$usermacros = [];
		$macro_values = [];

		$types = ['usermacros' => true];

		// Find macros.
		foreach ($triggers as $triggerid => $trigger) {
			$matched_macros = $this->extractMacros([$trigger['expression']], $types);

			if ($matched_macros['usermacros']) {
				$usermacros[$triggerid] = ['hostids' => [], 'macros' => $matched_macros['usermacros']];
			}
		}

		if ($usermacros) {
			// Get hosts for triggers.
			$db_triggers = API::Trigger()->get([
				'output' => [],
				'selectHosts' => ['hostid'],
				'triggerids' => array_keys($usermacros),
				'preservekeys' => true
			]);

			foreach ($usermacros as $triggerid => &$usermacros_data) {
				if (array_key_exists($triggerid, $db_triggers)) {
					$usermacros_data['hostids'] = zbx_objectValues($db_triggers[$triggerid]['hosts'], 'hostid');
				}
			}
			unset($usermacros_data);

			// Get user macros values.
			foreach ($this->getUserMacros($usermacros) as $triggerid => $usermacros_data) {
				$macro_values[$triggerid] = array_key_exists($triggerid, $macro_values)
					? array_merge($macro_values[$triggerid], $usermacros_data['macros'])
					: $usermacros_data['macros'];
			}
		}

		$types = $this->transformToPositionTypes($types);

		// Replace macros to value.
		foreach ($triggers as $triggerid => &$trigger) {
			$matched_macros = $this->getMacroPositions($trigger['expression'], $types);

			foreach (array_reverse($matched_macros, true) as $pos => $macro) {
				$trigger['expression'] =
					substr_replace($trigger['expression'], $macro_values[$triggerid][$macro], $pos, strlen($macro));
			}
		}
		unset($trigger);

		return $triggers;
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

			$sourceStringList = [];
			$itemsList = [];

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
		$hostKeyPairs = [];
		$matchesList = [];

		$items = reset($itemsList);
		foreach ($sourceStringList as $sourceString) {

			/*
			 * Extract all macros into $matches - keys: macros, hosts, keys, functions and parameters are used
			 * searches for macros, for example, "{somehost:somekey["param[123]"].min(10m)}"
			 */
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
						$hostKeyPairs[$host[0]] = [];
					}
					$hostKeyPairs[$host[0]][$matches['keys'][$i][0]] = true;
				}
			}
			unset($host);

			// Remember match for later use.
			$matchesList[] = $matches;

			$items = next($itemsList);
		}

		/*
		 * If no host/key pairs found in macro-like parts of source string then there is nothing to do but return
		 * source strings as they are.
		 */
		if (!$hostKeyPairs) {
			return $sourceStringList;
		}

		// Build item retrieval query from host-key pairs and get all necessary items for all source strings.
		$queryParts = [];
		foreach ($hostKeyPairs as $host => $keys) {
			$queryParts[] = '(h.host='.zbx_dbstr($host).' AND '.dbConditionString('i.key_', array_keys($keys)).')';
		}
		$items = DBfetchArrayAssoc(DBselect(
			'SELECT h.host,i.key_,i.itemid,i.value_type,i.units,i.valuemapid'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND ('.join(' OR ', $queryParts).')'
		), 'itemid');

		// Get items for which user has permission.
		$allowedItems = API::Item()->get([
			'itemids' => array_keys($items),
			'webitems' => true,
			'output' => ['itemid', 'value_type', 'lastvalue', 'lastclock'],
			'preservekeys' => true
		]);

		// Get map item data only for those allowed items and set "value_type" for allowed items.
		foreach ($items as $item) {
			if (isset($allowedItems[$item['itemid']])) {
				$item['lastvalue'] = $allowedItems[$item['itemid']]['lastvalue'];
				$item['lastclock'] = $allowedItems[$item['itemid']]['lastclock'];
				$hostKeyPairs[$item['host']][$item['key_']] = $item;
			}
		}

		/*
		 * Replace macros with their corresponding values in graph strings and replace macros with their resolved
		 * values in source strings.
		 */
		$matches = reset($matchesList);
		foreach ($sourceStringList as &$sourceString) {

			/*
			 * We iterate array backwards so that replacing unresolved macro string (see lower) with actual value
			 * does not mess up originally captured offsets.
			 */
			$i = count($matches['macros']);

			while ($i--) {
				$host = $matches['hosts'][$i][0];
				$key = $matches['keys'][$i][0];
				$function = $matches['functions'][$i][0];
				$parameter = $matches['parameters'][$i][0];

				// If host is real and item exists and has permissions.
				if ($host !== UNRESOLVED_MACRO_STRING && is_array($hostKeyPairs[$host][$key])) {
					$item = $hostKeyPairs[$host][$key];

					// Macro function is "last".
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
				// Or if there is no item with given key in given host, or there is no permissions to that item.
				else {
					$value = UNRESOLVED_MACRO_STRING;
				}

				/*
				 * Replace macro string with actual, resolved string value. This is safe because we start from far
				 * end of $sourceString.
				 */
				$sourceString = substr_replace($sourceString, $value, $matches['macros'][$i][1],
					strlen($matches['macros'][$i][0])
				);
			}

			// Advance to next matches for next $sourceString.
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
		// Extract all macros into $matches.
		preg_match_all('/{(('.self::PATTERN_HOST_INTERNAL.')('.self::PATTERN_MACRO_PARAM.'))\}/', $str, $matches);

		// Match found groups if ever regexp should change.
		$matches['macroType'] = $matches[2];
		$matches['position'] = $matches[3];

		// Build structure of macros: $macroList['HOST.HOST'][2] = 'host name';
		$macroList = [];

		// $matches[3] contains positions, e.g., '',1,2,2,3,...
		foreach ($matches['position'] as $i => $position) {
			// Take care of macro without positional index.
			$posInItemList = ($position === '') ? 0 : $position - 1;

			// Init array.
			if (!isset($macroList[$matches['macroType'][$i]])) {
				$macroList[$matches['macroType'][$i]] = [];
			}

			// Skip computing for duplicate macros.
			if (isset($macroList[$matches['macroType'][$i]][$position])) {
				continue;
			}

			// Positional index larger than item count, resolve to UNKNOWN.
			if (!isset($items[$posInItemList])) {
				$macroList[$matches['macroType'][$i]][$position] = UNRESOLVED_MACRO_STRING;

				continue;
			}

			// Retrieve macro replacement data.
			switch ($matches['macroType'][$i]) {
				case 'HOSTNAME':
				case 'HOST.HOST':
					$macroList[$matches['macroType'][$i]][$position] = $items[$posInItemList]['host'];
					break;
			}
		}

		// Replace macros with values in $str.
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
		foreach ($items as &$item) {
			$item['name_expanded'] = $item['name'];
		}
		unset($item);

		$types = ['usermacros' => true, 'references' => true];
		$macro_values = [];
		$usermacros = [];

		foreach ($items as $key => $item) {
			$matched_macros = $this->extractMacros([$item['name_expanded']], $types);

			if ($matched_macros['usermacros']) {
				$usermacros[$key] = ['hostids' => [$item['hostid']], 'macros' => $matched_macros['usermacros']];
			}

			if ($matched_macros['references']) {
				$macro_values[$key] = $matched_macros['references'];
			}
		}

		if ($macro_values) {
			$items_with_unresolved_keys = [];
			$expanded_keys = [];

			// Resolve macros in item key.
			foreach ($macro_values as $key => $macros) {
				if (!array_key_exists('key_expanded', $items[$key])) {
					$items_with_unresolved_keys[$key] = [
						'itemid' => $items[$key]['itemid'],
						'hostid' => $items[$key]['hostid'],
						'key_' => $items[$key]['key_']
					];
				}
				else {
					$expanded_keys[$key] = $items[$key]['key_expanded'];
				}
			}

			if ($items_with_unresolved_keys) {
				foreach ($this->resolveItemKeys($items_with_unresolved_keys) as $key => $item) {
					$expanded_keys[$key] = $item['key_expanded'];
				}
			}

			$item_key_parser = new CItemKey();

			foreach ($expanded_keys as $key => $expanded_key) {
				if ($item_key_parser->parse($expanded_key) == CParser::PARSE_SUCCESS) {
					foreach ($macro_values[$key] as $macro => &$value) {
						if (($param = $item_key_parser->getParam($macro[1] - 1)) !== null) {
							$value = $param;
						}
					}
					unset($value);
				}
			}
		}

		foreach ($this->getUserMacros($usermacros) as $key => $usermacros_data) {
			$macro_values[$key] = array_key_exists($key, $macro_values)
				? array_merge($macro_values[$key], $usermacros_data['macros'])
				: $usermacros_data['macros'];
		}

		$types = $this->transformToPositionTypes($types);

		// Replace macros to value.
		foreach (array_keys($macro_values) as $key) {
			$matched_macros = $this->getMacroPositions($items[$key]['name_expanded'], $types);

			foreach (array_reverse($matched_macros, true) as $pos => $macro) {
				$items[$key]['name_expanded'] =
					substr_replace($items[$key]['name_expanded'], $macro_values[$key][$macro], $pos, strlen($macro));
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
		foreach ($items as &$item) {
			$item['key_expanded'] = $item['key_'];
		}
		unset($item);

		$types = [
			'macros' => [
				'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
				'interface' => ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}']
			],
			'usermacros' => true
		];
		$macro_values = [];
		$usermacros = [];
		$itemids = [];
		$host_macros = false;
		$interface_macros = false;

		foreach ($items as $key => $item) {
			$matched_macros = $this->extractItemKeyMacros($item['key_expanded'], $types);

			if ($matched_macros['macros']['host'] || $matched_macros['macros']['interface']) {
				$itemids[$item['itemid']] = true;

				if ($matched_macros['macros']['host']) {
					$host_macros = true;

					foreach ($matched_macros['macros']['host'] as $macro) {
						$macro_values[$key][$macro] = UNRESOLVED_MACRO_STRING;
					}
				}

				if ($matched_macros['macros']['interface']) {
					$interface_macros = true;

					foreach ($matched_macros['macros']['interface'] as $macro) {
						$macro_values[$key][$macro] = UNRESOLVED_MACRO_STRING;
					}
				}
			}

			if ($matched_macros['usermacros']) {
				$usermacros[$key] = ['hostids' => [$item['hostid']], 'macros' => $matched_macros['usermacros']];
			}
		}

		if ($itemids) {
			$options = [
				'output' => ['hostid', 'interfaceid'],
				'itemids' => array_keys($itemids),
				'webitems' => true,
				'filter' => ['flags' => null],
				'preservekeys' => true
			];
			if ($host_macros) {
				$options['selectHosts'] = ['hostid', 'host', 'name'];
			}

			$db_items = API::Item()->get($options);

			if ($interface_macros) {
				$hostids = [];

				foreach ($macro_values as $key => $macros) {
					if (array_key_exists('{HOST.IP}', $macros) || array_key_exists('{IPADDRESS}', $macros)
							|| array_key_exists('{HOST.DNS}', $macros) || array_key_exists('{HOST.CONN}', $macros)) {
						$itemid = $items[$key]['itemid'];

						if (array_key_exists($itemid, $db_items)) {
							$hostids[$db_items[$itemid]['hostid']] = true;
							break;
						}
					}
				}

				$interfaces = [];
				$interfaces_by_priority = [];

				if ($hostids) {
					$interfaces = DBfetchArray(DBselect(
						'SELECT i.hostid,i.interfaceid,i.ip,i.dns,i.useip,i.port,i.type,i.main'.
						' FROM interface i'.
						' WHERE '.dbConditionInt('i.hostid', array_keys($hostids)).
							' AND '.dbConditionInt('i.type', $this->interfacePriorities)
					));

					$interfaces = CMacrosResolverHelper::resolveHostInterfaces($interfaces);
					$interfaces = zbx_toHash($interfaces, 'interfaceid');

					// Items with no interfaces must collect interface data from host.
					foreach ($interfaces as $interface) {
						$hostid = $interface['hostid'];
						$priority = $this->interfacePriorities[$interface['type']];

						if ($interface['main'] == INTERFACE_PRIMARY && (!array_key_exists($hostid, $interfaces_by_priority)
								|| $priority > $this->interfacePriorities[$interfaces_by_priority[$hostid]['type']])) {
							$interfaces_by_priority[$hostid] = $interface;
						}
					}
				}
			}

			foreach ($macro_values as $key => &$macros) {
				$itemid = $items[$key]['itemid'];

				if (array_key_exists($itemid, $db_items)) {
					$db_item = $db_items[$itemid];
					$interface = null;

					if ($interface_macros) {
						if ($db_item['interfaceid'] != 0 && array_key_exists($db_item['interfaceid'], $interfaces)) {
							$interface = $interfaces[$db_item['interfaceid']];
						}
						elseif (array_key_exists($db_item['hostid'], $interfaces_by_priority)) {
							$interface = $interfaces_by_priority[$db_item['hostid']];
						}
					}

					foreach ($macros as $macro => &$value) {
						if ($host_macros) {
							switch ($macro) {
								case '{HOST.NAME}':
									$value = $db_item['hosts'][0]['name'];
									continue 2;

								case '{HOST.HOST}':
								case '{HOSTNAME}': // deprecated
									$value = $db_item['hosts'][0]['host'];
									continue 2;
							}
						}

						if ($interface !== null) {
							switch ($macro) {
								case '{HOST.IP}':
								case '{IPADDRESS}': // deprecated
									$value = $interface['ip'];
									break;

								case '{HOST.DNS}':
									$value = $interface['dns'];
									break;

								case '{HOST.CONN}':
									$value = $interface['useip'] ? $interface['ip'] : $interface['dns'];
									break;
							}
						}
					}
					unset($value);
				}
			}
			unset($macros);
		}

		foreach ($this->getUserMacros($usermacros) as $key => $usermacros_data) {
			$macro_values[$key] = array_key_exists($key, $macro_values)
				? array_merge($macro_values[$key], $usermacros_data['macros'])
				: $usermacros_data['macros'];
		}

		$types = $this->transformToPositionTypes($types);

		// Replace macros to value.
		foreach ($macro_values as $key => $macros) {
			$items[$key]['key_expanded'] = $this->resolveItemKeyMacros($items[$key]['key_expanded'], $macros, $types);
		}

		return $items;
	}

	/**
	 * Resolve function parameter macros to "parameter_expanded" field.
	 *
	 * @param array  $functions
	 * @param string $functions[n]['hostid']
	 * @param string $functions[n]['function']
	 * @param string $functions[n]['parameter']
	 *
	 * @return array
	 */
	public function resolveFunctionParameters(array $functions) {
		foreach ($functions as &$function) {
			$function['parameter_expanded'] = $function['parameter'];
		}
		unset($function);

		$types = ['usermacros' => true];
		$macro_values = [];
		$usermacros = [];

		foreach ($functions as $key => $function) {
			$matched_macros = $this->extractFunctionMacros($function['function'].'('.$function['parameter'].')',
				$types
			);

			if ($matched_macros['usermacros']) {
				$usermacros[$key] = ['hostids' => [$function['hostid']], 'macros' => $matched_macros['usermacros']];
			}
		}

		foreach ($this->getUserMacros($usermacros) as $key => $usermacros_data) {
			$macro_values[$key] = array_key_exists($key, $macro_values)
				? array_merge($macro_values[$key], $usermacros_data['macros'])
				: $usermacros_data['macros'];
		}

		$types = $this->transformToPositionTypes($types);

		// Replace macros to value.
		foreach ($macro_values as $key => $macros) {
			$function = $functions[$key]['function'].'('.$functions[$key]['parameter'].')';
			$function = $this->resolveFunctionMacros($function, $macros, $types);
			$functions[$key]['parameter_expanded'] = substr($function, strlen($functions[$key]['function']) + 1, -1);
		}

		return $functions;
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

		// Find functional macro pattern.
		$pattern = ($replaceHosts === null)
			? '/{'.ZBX_PREG_HOST_FORMAT.':.+\.'.$functionsPattern.'}/Uu'
			: '/{('.ZBX_PREG_HOST_FORMAT.'|{HOSTNAME[0-9]?}|{HOST\.HOST[0-9]?}):.+\.'.$functionsPattern.'}/Uu';

		preg_match_all($pattern, $label, $matches);

		// For each functional macro.
		foreach ($matches[0] as $expr) {
			$macro = $expr;

			if ($replaceHosts !== null) {
				// Search for macros with all possible indices.
				foreach ($replaceHosts as $i => $host) {
					$macroTmp = $macro;

					// Replace only macro in first position.
					$macro = preg_replace('/{({HOSTNAME'.$i.'}|{HOST\.HOST'.$i.'}):(.*)}/U', '{'.$host['host'].':$2}', $macro);

					// Only one simple macro possible inside functional macro.
					if ($macro !== $macroTmp) {
						break;
					}
				}
			}

			// Try to create valid expression.
			$expressionData = new CTriggerExpression();

			if (!$expressionData->parse($macro) || !isset($expressionData->expressions[0])) {
				continue;
			}

			// Look in DB for corresponding item.
			$itemHost = $expressionData->expressions[0]['host'];
			$key = $expressionData->expressions[0]['item'];
			$function = $expressionData->expressions[0]['functionName'];

			$item = API::Item()->get([
				'output' => ['itemid', 'value_type', 'units', 'valuemapid', 'lastvalue', 'lastclock'],
				'webitems' => true,
				'filter' => [
					'host' => $itemHost,
					'key_' => $key
				]
			]);

			$item = reset($item);

			// If no corresponding item found with functional macro key and host.
			if (!$item) {
				$label = str_replace($expr, UNRESOLVED_MACRO_STRING, $label);

				continue;
			}

			// Do function type (last, min, max, avg) related actions.
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

		// For host and trigger items expand macros if they exists.
		if (($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST || $selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER)
				&& (strpos($label, 'HOST.NAME') !== false
						|| strpos($label, 'HOSTNAME') !== false /* deprecated */
						|| strpos($label, 'HOST.HOST') !== false
						|| strpos($label, 'HOST.DESCRIPTION') !== false
						|| strpos($label, 'HOST.DNS') !== false
						|| strpos($label, 'HOST.IP') !== false
						|| strpos($label, 'IPADDRESS') !== false /* deprecated */
						|| strpos($label, 'HOST.CONN') !== false)) {
			// Priorities of interface types doesn't match interface type ids in DB.
			$priorities = [
				INTERFACE_TYPE_AGENT => 4,
				INTERFACE_TYPE_SNMP => 3,
				INTERFACE_TYPE_JMX => 2,
				INTERFACE_TYPE_IPMI => 1
			];

			// Get host data if element is host.
			if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST) {
				$res = DBselect(
					'SELECT hi.ip,hi.dns,hi.useip,h.host,h.name,h.description,hi.type AS interfacetype'.
					' FROM interface hi,hosts h'.
					' WHERE hi.hostid=h.hostid'.
						' AND hi.main=1 AND hi.hostid='.zbx_dbstr($selement['elementid'])
				);

				// Process interface priorities.
				$tmpPriority = 0;

				while ($dbHost = DBfetch($res)) {
					if ($priorities[$dbHost['interfacetype']] > $tmpPriority) {
						$resHost = $dbHost;
						$tmpPriority = $priorities[$dbHost['interfacetype']];
					}
				}

				$hostsByNr[''] = $resHost;
			}
			// Get trigger host list if element is trigger.
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

				// Process interface priorities, build $hostsByFunctionId array.
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

				// Get all function ids from expression and link host data against position in expression.
				preg_match_all('/\{([0-9]+)\}/', $selement['elementExpressionTrigger'], $matches);

				$hostsByNr = [];

				foreach ($matches[1] as $i => $functionid) {
					if (isset($hostsByFunctionId[$functionid])) {
						$hostsByNr[$i + 1] = $hostsByFunctionId[$functionid];
					}
				}

				// For macro without numeric index.
				if (isset($hostsByNr[1])) {
					$hostsByNr[''] = $hostsByNr[1];
				}
			}

			// Resolve functional macros like: {{HOST.HOST}:log[{HOST.HOST}.log].last(0)}.
			$label = $this->resolveMapLabelMacros($label, $hostsByNr);

			// Resolves basic macros.
			// $hostsByNr possible keys: '' and 1-9.
			foreach ($hostsByNr as $i => $host) {
				$replace = [
					'{HOST.NAME'.$i.'}' => $host['name'],
					'{HOSTNAME'.$i.'}' => $host['host'],
					'{HOST.HOST'.$i.'}' => $host['host'],
					'{HOST.DESCRIPTION'.$i.'}' => $host['description'],
					'{HOST.DNS'.$i.'}' => $host['dns'],
					'{HOST.IP'.$i.'}' => $host['ip'],
					'{IPADDRESS'.$i.'}' => $host['ip'],
					'{HOST.CONN'.$i.'}' => $host['useip'] ? $host['ip'] : $host['dns']
				];

				$label = str_replace(array_keys($replace), $replace, $label);
			}
		}
		else {
			// Resolve functional macros like: {sampleHostName:log[{HOST.HOST}.log].last(0)}, if no host provided.
			$label = $this->resolveMapLabelMacros($label);
		}

		// Resolve map specific processing consuming macros.
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
