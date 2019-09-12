<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
		'widgetURL' => [
			'types' => ['host', 'hostId', 'interfaceWithoutPort', 'user'],
			'source' => 'url',
			'method' => 'resolveTexts'
		],
		'widgetURLUser' => [
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
	 * @param array  $triggers
	 * @param string $triggers[$triggerid]['expression']
	 * @param string $triggers[$triggerid]['description']
	 * @param array  $options
	 * @param bool   $options['references_only']           resolve only $1-$9 macros
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
			'macro_funcs_n' => [
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

				foreach ($matched_macros['macros_n']['host'] as $token => $data) {
					$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;

					if (array_key_exists($data['f_num'], $functionids)) {
						$macros['host'][$functionids[$data['f_num']]][$data['macro']][] = ['token' => $token];
					}
				}

				foreach ($matched_macros['macros_n']['interface'] as $token => $data) {
					$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;

					if (array_key_exists($data['f_num'], $functionids)) {
						$macros['interface'][$functionids[$data['f_num']]][$data['macro']][] = ['token' => $token];
					}
				}

				foreach ($matched_macros['macros_n']['item'] as $token => $data) {
					$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;

					if (array_key_exists($data['f_num'], $functionids)) {
						$macros['item'][$functionids[$data['f_num']]][$data['macro']][] = ['token' => $token];
					}
				}

				foreach ($matched_macros['macro_funcs_n']['item'] as $token => $data) {
					$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;

					if (array_key_exists($data['f_num'], $functionids)) {
						$macros['item'][$functionids[$data['f_num']]][$data['macro']][] = [
							'token' => $token,
							'function' => $data['function'],
							'parameters' => $data['parameters']
						];
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
			$macro_values = $this->getIpMacros($macros['interface'], $macro_values);
			$macro_values = $this->getItemMacros($macros['item'], $macro_values);

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
		foreach ($macro_values as $triggerid => $foo) {
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
	 * Resolve macros in trigger description and operational data.
	 *
	 * @param array  $triggers
	 * @param string $triggers[$triggerid]['expression']
	 * @param string $triggers[$triggerid][<sources>]     See $options['sources'].
	 * @param int    $triggers[$triggerid]['clock']       (optional)
	 * @param int    $triggers[$triggerid]['ns']          (optional)
	 * @param array  $options
	 * @param bool   $options['events']                   Resolve {ITEM.VALUE} macro using 'clock' and 'ns' fields.
	 * @param bool   $options['html']
	 * @param array  $options['sources']                  An array of trigger field names: 'comments', 'opdata'.
	 *
	 * @return array
	 */
	public function resolveTriggerDescriptions(array $triggers, array $options) {
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
			'macro_funcs_n' => [
				'item' => ['{ITEM.LASTVALUE}', '{ITEM.VALUE}']
			],
			'usermacros' => true
		];

		// Find macros.
		foreach ($triggers as $triggerid => $trigger) {
			$functionids = $this->findFunctions($trigger['expression']);

			$texts = [];
			foreach ($options['sources'] as $source) {
				$texts[] = $trigger[$source];
			}

			$matched_macros = $this->extractMacros($texts, $types);

			foreach ($matched_macros['macros_n']['host'] as $token => $data) {
				$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;

				if (array_key_exists($data['f_num'], $functionids)) {
					$macros['host'][$functionids[$data['f_num']]][$data['macro']][] = ['token' => $token];
				}
			}

			foreach ($matched_macros['macros_n']['interface'] as $token => $data) {
				$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;

				if (array_key_exists($data['f_num'], $functionids)) {
					$macros['interface'][$functionids[$data['f_num']]][$data['macro']][] = ['token' => $token];
				}
			}

			foreach ($matched_macros['macros_n']['item'] as $token => $data) {
				$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;

				if (array_key_exists($data['f_num'], $functionids)) {
					$macros['item'][$functionids[$data['f_num']]][$data['macro']][] = ['token' => $token];
				}
			}

			foreach ($matched_macros['macro_funcs_n']['item'] as $token => $data) {
				$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;

				if (array_key_exists($data['f_num'], $functionids)) {
					$macros['item'][$functionids[$data['f_num']]][$data['macro']][] = [
						'token' => $token,
						'function' => $data['function'],
						'parameters' => $data['parameters']
					];
				}
			}

			if ($matched_macros['usermacros']) {
				$usermacros[$triggerid] = ['hostids' => [], 'macros' => $matched_macros['usermacros']];
			}
		}

		// Get macro value.
		$macro_values = $this->getHostMacros($macros['host'], $macro_values);
		$macro_values = $this->getIpMacros($macros['interface'], $macro_values);
		$macro_values = $this->getItemMacros($macros['item'], $macro_values, $triggers, $options);

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
		foreach ($macro_values as $triggerid => $foo) {
			$trigger = &$triggers[$triggerid];

			foreach ($options['sources'] as $source) {
				$matched_macros = $this->getMacroPositions($trigger[$source], $types);

				if ($options['html']) {
					$macro_string = [];
					$pos_left = 0;

					foreach ($matched_macros as $pos => $macro) {
						if (array_key_exists($macro, $macro_values[$triggerid])) {
							if ($pos_left != $pos) {
								$macro_string[] = substr($trigger[$source], $pos_left, $pos - $pos_left);
							}

							$macro_string[] = $macro_values[$triggerid][$macro];
							$pos_left = $pos + strlen($macro);
						}
					}
					$macro_string[] = substr($trigger[$source], $pos_left);

					$trigger[$source] = $macro_string;
				}
				else {
					foreach (array_reverse($matched_macros, true) as $pos => $macro) {
						$trigger[$source] = substr_replace($trigger[$source], $macro_values[$triggerid][$macro], $pos,
							strlen($macro)
						);
					}
				}
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
			'interface' => [],
			'item' => []
		];
		$usermacros = [];
		$macro_values = [];

		$types = [
			'macros' => [
				'trigger' => ['{TRIGGER.ID}']
			],
			'macros_n' => [
				'host' => ['{HOST.ID}', '{HOST.HOST}', '{HOST.NAME}'],
				'interface' => ['{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
				'item' => ['{ITEM.LASTVALUE}', '{ITEM.VALUE}']
			],
			'macro_funcs_n' => [
				'item' => ['{ITEM.LASTVALUE}', '{ITEM.VALUE}']
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

			foreach ($matched_macros['macros_n']['host'] as $token => $data) {
				$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;

				if (array_key_exists($data['f_num'], $functionids)) {
					$macros['host'][$functionids[$data['f_num']]][$data['macro']][] = ['token' => $token];
				}
			}

			foreach ($matched_macros['macros_n']['interface'] as $token => $data) {
				$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;

				if (array_key_exists($data['f_num'], $functionids)) {
					$macros['interface'][$functionids[$data['f_num']]][$data['macro']][] = ['token' => $token];
				}
			}

			foreach ($matched_macros['macros_n']['item'] as $token => $data) {
				$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;

				if (array_key_exists($data['f_num'], $functionids)) {
					$macros['item'][$functionids[$data['f_num']]][$data['macro']][] = ['token' => $token];
				}
			}

			foreach ($matched_macros['macro_funcs_n']['item'] as $token => $data) {
				$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;

				if (array_key_exists($data['f_num'], $functionids)) {
					$macros['item'][$functionids[$data['f_num']]][$data['macro']][] = [
						'token' => $token,
						'function' => $data['function'],
						'parameters' => $data['parameters']
					];
				}
			}

			if ($matched_macros['usermacros']) {
				$usermacros[$triggerid] = ['hostids' => [], 'macros' => $matched_macros['usermacros']];
			}
		}

		// Get macro value.
		$macro_values = $this->getHostMacros($macros['host'], $macro_values);
		$macro_values = $this->getIpMacros($macros['interface'], $macro_values);
		$macro_values = $this->getItemMacros($macros['item'], $macro_values);

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
	 * @param string $triggers[][<sources>]			see options['source']
	 * @param array  $options
	 * @param bool   $options['html']				returns formatted trigger expression
	 * @param bool   $options['resolve_usermacros']	resolve user macros
	 * @param bool   $options['resolve_macros']		resolve macros in item keys and functions
	 * @param array  $options['sources']			an array of the field names
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
			$texts = [];
			foreach ($options['sources'] as $source) {
				$texts[] = $trigger[$source];
			}

			$matched_macros = $this->extractMacros($texts, $types);

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
				'SELECT f.functionid,f.itemid,f.name,f.parameter'.
				' FROM functions f'.
				' WHERE '.dbConditionInt('f.functionid', $functionids)
			);

			$hostids = [];
			$itemids = [];
			$hosts = [];
			$items = [];

			while ($row = DBfetch($result)) {
				$itemids[$row['itemid']] = true;
				$row['function'] = $row['name'];
				unset($row['name']);

				$functions['{'.$row['functionid'].'}'] = $row;
				unset($functions['{'.$row['functionid'].'}']['functionid']);
			}

			// Selecting items.
			if ($itemids) {
				if ($options['html']) {
					$sql = 'SELECT i.itemid,i.hostid,i.key_,i.type,i.flags,i.status,ir.state,id.parent_itemid'.
						' FROM items i'.
							' LEFT JOIN item_rtdata ir ON i.itemid=ir.itemid'.
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

							if ($function['type'] == ITEM_TYPE_HTTPTEST) {
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
									->setAttribute('data-itemid', $function['itemid'])
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
			foreach ($options['sources'] as $source) {
				$matched_macros = $this->getMacroPositions($trigger[$source], $types);

				if ($options['html']) {
					$expression = [];
					$pos_left = 0;

					foreach ($matched_macros as $pos => $macro) {
						if (array_key_exists($macro, $macro_values[$key])) {
							if ($pos_left != $pos) {
								$expression[] = substr($trigger[$source], $pos_left, $pos - $pos_left);
							}

							$expression[] = $macro_values[$key][$macro];

							$pos_left = $pos + strlen($macro);
						}
					}
					$expression[] = substr($trigger[$source], $pos_left);

					$trigger[$source] = $expression;
				}
				else {
					foreach (array_reverse($matched_macros, true) as $pos => $macro) {
						if (array_key_exists($macro, $macro_values[$key])) {
							$trigger[$source] =
								substr_replace($trigger[$source], $macro_values[$key][$macro], $pos, strlen($macro));
						}
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
	 * Resolve item delay macros, item history and item trend macros.
	 *
	 * @param array  $data
	 * @param string $data[n]['hostid']
	 * @param string $data[n][<sources>]  see options['source']
	 * @param array  $options
	 * @param array  $options['sources']  an array of the field names
	 *
	 * @return array
	 */
	public function resolveTimeUnitMacros(array $data, array $options) {
		$usermacros = [];
		$macro_values = [];

		$types = [
			'usermacros' => true
		];

		// Find macros.
		foreach ($data as $key => $value) {
			$texts = [];
			foreach ($options['sources'] as $source) {
				$texts[] = $value[$source];
			}

			$matched_macros = $this->extractMacros($texts, $types);

			if ($matched_macros['usermacros']) {
				$usermacros[$key] = [
					'hostids' => array_key_exists('hostid', $value) ? [$value['hostid']] : [],
					'macros' => $matched_macros['usermacros']
				];
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
			foreach ($options['sources'] as $source) {
				$matched_macros = $this->getMacroPositions($data[$key][$source], $types);

				foreach (array_reverse($matched_macros, true) as $pos => $macro) {
					$data[$key][$source] =
						substr_replace($data[$key][$source], $macro_values[$key][$macro], $pos, strlen($macro));
				}
			}
		}

		return $data;
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
		$pattern = '/(?P<macros>{'.
				'('.ZBX_PREG_HOST_FORMAT.(($replaceHosts !== null)
						? '|({('.self::PATTERN_HOST_INTERNAL.')'.self::PATTERN_MACRO_PARAM.'})' : '').'):'.
				ZBX_PREG_ITEM_KEY_FORMAT.'\.'.
				'(last|max|min|avg)\('.
				'([0-9]+['.ZBX_TIME_SUFFIXES.']?)?'.
				'\)}{1})/Uux';

		if (preg_match_all($pattern, $label, $matches) !== false && array_key_exists('macros', $matches)) {
			// $replaceHosts with key '0' is used for macros without reference.
			if ($replaceHosts !== null && array_key_exists(0, $replaceHosts)) {
				$replaceHosts[''] = $replaceHosts[0];
				unset($replaceHosts[0]);
			}

			// For each functional macro.
			foreach ($matches['macros'] as $expr) {
				$macro = $expr;

				if ($replaceHosts !== null) {
					// Search for macros with all possible indices.
					foreach ($replaceHosts as $i => $host) {
						$macroTmp = $macro;

						// Replace only macro in first position.
						$macro = preg_replace('/{({HOSTNAME'.$i.'}|{HOST\.HOST'.$i.'}):(.*)}/U',
								'{'.$host['host'].':$2}', $macro
						);

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
					$value = $item['lastclock']
						? formatHistoryValue($item['lastvalue'], $item)
						: UNRESOLVED_MACRO_STRING;
				}
				else {
					$value = getItemFunctionalValue($item, $function,
							$expressionData->expressions[0]['functionParamList'][0]
					);
				}

				if (isset($value)) {
					$label = str_replace($expr, $value, $label);
				}
			}
		}

		return $label;
	}

	/**
	 * Resolve supported macros used in map element label as well as in URL names and values.
	 *
	 * @param array        $selements[]
	 * @param int          $selements[]['elementtype']          Map element type.
	 * @param int          $selements[]['elementsubtype']       Map element subtype.
	 * @param string       $selements[]['label']                Map element label.
	 * @param array        $selements[]['urls']                 Map element urls.
	 * @param string       $selements[]['urls'][]['name']       Map element url name.
	 * @param string       $selements[]['urls'][]['url']        Map element url value.
	 * @param int | array  $selements[]['elementid']            Element id linked to map element.
	 * @param array        $options
	 * @param bool         $options[resolve_element_label]      Resolve macros in map element label.
	 * @param bool         $options[resolve_element_urls]       Resolve macros in map element url name and value.
	 *
	 * @return array
	 */
	public function resolveMacrosInMapElements(array $selements, array $options) {
		$options += ['resolve_element_label' => false, 'resolve_element_urls' => false];

		$supported_inventory_macros = $this->getSupportedHostInventoryMacrosMap();

		// Macros supported by location.
		$supported_macros = [
			'host' => [
				'label' => ['{HOSTNAME}', '{HOST.ID}', '{HOST.NAME}', '{HOST.HOST}', '{HOST.DESCRIPTION}'],
				'urls' => ['{HOSTNAME}', '{HOST.ID}', '{HOST.NAME}', '{HOST.HOST}']
			],
			'map' => [
				'label' => ['{MAP.ID}', '{MAP.NAME}'],
				'urls' => ['{MAP.ID}', '{MAP.NAME}']
			],
			'trigger' => [
				'label' => ['{TRIGGER.ID}'],
				'urls' => ['{TRIGGER.ID}']
			],
			'triggers' => [
				'label' => [
					'{TRIGGER.EVENTS.ACK}', '{TRIGGER.EVENTS.PROBLEM.ACK}', '{TRIGGER.EVENTS.PROBLEM.UNACK}',
					'{TRIGGER.EVENTS.UNACK}', '{TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK}',
					'{TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK}', '{TRIGGERS.UNACK}',
					'{TRIGGERS.PROBLEM.UNACK}', '{TRIGGERS.ACK}', '{TRIGGERS.PROBLEM.ACK}'
				]
			],
			'hostgroup' => [
				'label' => ['{HOSTGROUP.ID}'],
				'urls' => ['{HOSTGROUP.ID}']
			],
			'interface' => [
				'label' => ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}'],
				'urls' => ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}']
			],
			'inventory' => [
				'label' => array_keys($supported_inventory_macros),
				'urls' => array_keys($supported_inventory_macros)
			]
		];

		// Define what macros are supported for each type of map elements.
		$types_by_selement_type = [
			SYSMAP_ELEMENT_TYPE_MAP => [
				'macros' => [
					'map' => [
						'label' => $options['resolve_element_label'] ? $supported_macros['map']['label'] : [],
						'urls' => $options['resolve_element_urls'] ? $supported_macros['map']['urls'] : []
					],
					'triggers' => [
						'label' => $options['resolve_element_label'] ? $supported_macros['triggers']['label'] : []
					]
				]
			],
			SYSMAP_ELEMENT_TYPE_HOST_GROUP => [
				'macros' => [
					'hostgroup' => [
						'label' => $options['resolve_element_label'] ? $supported_macros['hostgroup']['label'] : [],
						'urls' => $options['resolve_element_urls'] ? $supported_macros['hostgroup']['urls'] : []
					],
					'triggers' => [
						'label' => $options['resolve_element_label'] ? $supported_macros['triggers']['label'] : []
					]
				]
			],
			SYSMAP_ELEMENT_TYPE_HOST => [
				'macros' => [
					'host' => [
						'label' => $options['resolve_element_label'] ? $supported_macros['host']['label'] : [],
						'urls' => $options['resolve_element_urls'] ? $supported_macros['host']['urls'] : []
					],
					'triggers' => [
						'label' => $options['resolve_element_label'] ? $supported_macros['triggers']['label'] : []
					],
					'interface' => [
						'label' => $options['resolve_element_label'] ? $supported_macros['interface']['label'] : [],
						'urls' => $options['resolve_element_urls'] ? $supported_macros['interface']['urls'] : []
					],
					'inventory' => [
						'label' => $options['resolve_element_label'] ? $supported_macros['inventory']['label'] : [],
						'urls' => $options['resolve_element_urls'] ? $supported_macros['inventory']['urls'] : []
					]
				]
			],
			SYSMAP_ELEMENT_TYPE_TRIGGER => [
				'macros' => [
					'trigger' => [
						'label' => $options['resolve_element_label'] ? $supported_macros['trigger']['label'] : [],
						'urls' => $options['resolve_element_urls'] ? $supported_macros['trigger']['urls'] : []
					],
					'triggers' => [
						'label' => $options['resolve_element_label'] ? $supported_macros['triggers']['label'] : []
					]
				],
				'macros_n' => [
					'host' => [
						'label' => $options['resolve_element_label'] ? $supported_macros['host']['label'] : [],
						'urls' => $options['resolve_element_urls'] ? $supported_macros['host']['urls'] : []
					],
					'interface' => [
						'label' => $options['resolve_element_label'] ? $supported_macros['interface']['label'] : [],
						'urls' => $options['resolve_element_urls'] ? $supported_macros['interface']['urls'] : []
					],
					'inventory' => [
						'label' => $options['resolve_element_label'] ? $supported_macros['inventory']['label'] : [],
						'urls' => $options['resolve_element_urls'] ? $supported_macros['inventory']['urls'] : []
					]
				]
			]
		];

		$selements_to_resolve = [
			SYSMAP_ELEMENT_TYPE_HOST => [],
			SYSMAP_ELEMENT_TYPE_MAP => [],
			SYSMAP_ELEMENT_TYPE_TRIGGER => []
		];

		$elementid_field_by_type = [
			SYSMAP_ELEMENT_TYPE_HOST => 'hostid',
			SYSMAP_ELEMENT_TYPE_MAP => 'sysmapid',
			SYSMAP_ELEMENT_TYPE_TRIGGER => 'triggerid',
			SYSMAP_ELEMENT_TYPE_HOST_GROUP => 'groupid'
		];

		$supported_label_macros = [];
		$supported_urls_macros = [];
		$itemids_by_functionids = [];
		$hosts_by_itemids = [];
		$query_interfaces = false;
		$query_inventories = false;
		$query_trigger_hosts = false;
		$triggers = [];
		$maps = [];
		$hosts = [];
		$macros_by_selementid = [];

		foreach ($types_by_selement_type as $selement_type => &$types) {
			$supported_label_macros[$selement_type] = [];
			$supported_urls_macros[$selement_type] = [];

			foreach ($types as $macros_type_key => $supported_macros_types) {
				if (!array_key_exists($macros_type_key, $supported_label_macros[$selement_type])) {
					$supported_label_macros[$selement_type][$macros_type_key] = [];
				}
				if (!array_key_exists($macros_type_key, $supported_urls_macros[$selement_type])) {
					$supported_urls_macros[$selement_type][$macros_type_key] = [];
				}

				foreach ($supported_macros_types as $macros_supported) {
					if (array_key_exists('label', $macros_supported)) {
						$supported_label_macros[$selement_type][$macros_type_key] = array_merge(
							$supported_label_macros[$selement_type][$macros_type_key], $macros_supported['label']
						);
					}

					if (array_key_exists('urls', $macros_supported)) {
						$supported_urls_macros[$selement_type][$macros_type_key] = array_merge(
							$supported_urls_macros[$selement_type][$macros_type_key], $macros_supported['urls']
						);
					}
				}
			}

			foreach ($types as &$macro_types) {
				$macro_types = array_map(function($macros_by_location) {
					return array_keys(array_flip(call_user_func_array('array_merge', $macros_by_location)));
				}, $macro_types);
			}
			unset($macro_types);
		}
		unset($types);

		foreach ($selements as $selid => $sel) {
			$selement_type = ($sel['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP
					&& $sel['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS)
				? SYSMAP_ELEMENT_TYPE_HOST
				: $sel['elementtype'];

			if (!array_key_exists($selement_type, $types_by_selement_type)) {
				continue;
			}

			// Collect strings to test for macros.
			$texts = $options['resolve_element_label'] ? [$sel['label']] : [];
			if ($options['resolve_element_urls']) {
				foreach ($sel['urls'] as $url) {
					$texts[] = $url['name'];
					$texts[] = $url['url'];
				}
			}

			if (!$texts) {
				continue;
			}

			// Extract macros from collected strings.
			$matched_macros = $this->extractMacros($texts, $types_by_selement_type[$selement_type]);

			// Map extracted macros to map elements.
			if (array_key_exists('macros', $matched_macros)) {
				// Check if inventory or interface details was requested.
				if ($selement_type == SYSMAP_ELEMENT_TYPE_HOST) {
					if ($matched_macros['macros']['interface']) {
						$query_interfaces = true;
					}

					if ($matched_macros['macros']['inventory']) {
						$query_inventories = true;
					}
				}

				foreach ($matched_macros['macros'] as $macros) {
					foreach ($macros as $macro) {
						$macros_by_selementid[$selid][$macro] = [
							'macro' => substr($macro, 1, -1), // strip curly braces
							'f_num' => 0
						];
					}
				}
			}

			// Do the same with indexed macros.
			if (array_key_exists('macros_n', $matched_macros)) {
				if (!array_key_exists($selid, $macros_by_selementid)) {
					$macros_by_selementid[$selid] = [];
				}

				foreach ($matched_macros['macros_n'] as $macro) {
					$macros_by_selementid[$selid] += $macro;
				}

				// Check if inventory or interface details was requested.
				if ($selement_type == SYSMAP_ELEMENT_TYPE_TRIGGER) {
					if ($matched_macros['macros_n']['interface']) {
						$query_trigger_hosts = true;
						$query_interfaces = true;
					}

					if ($matched_macros['macros_n']['inventory']) {
						$query_trigger_hosts = true;
						$query_inventories = true;
					}

					if ($matched_macros['macros_n']['host']) {
						$query_trigger_hosts = true;
					}
				}
			}

			/*
			 * If macros are found, put elementid to list of elements to fetch API.
			 * Since only supported host-group macro is {HOSTGROUP.ID}, it's useless to collect host group id-s in order
			 * to fetch additional details from database.
			 */
			if ($sel['elementtype'] != SYSMAP_ELEMENT_TYPE_HOST_GROUP && array_key_exists($selid, $macros_by_selementid)
					&& $macros_by_selementid[$selid]) {
				if (array_key_exists('elementid', $sel)) {
					$selements_to_resolve[$sel['elementtype']][$sel['elementid']] = $sel['elementid'];
				}
				elseif (($element = reset($sel['elements'])) !== false) {
					$elementid = $element[$elementid_field_by_type[$sel['elementtype']]];
					$selements_to_resolve[$sel['elementtype']][$elementid] = $elementid;
				}
			}
		}

		// Query details about resolvable maps.
		if ($selements_to_resolve[SYSMAP_ELEMENT_TYPE_MAP]) {
			$maps = API::Map()->get([
				'output' => ['sysmapid', 'name'],
				'sysmapids' => $selements_to_resolve[SYSMAP_ELEMENT_TYPE_MAP],
				'preservekeys' => true
			]);
		}

		// Get details about resolvable triggers.
		if ($selements_to_resolve[SYSMAP_ELEMENT_TYPE_TRIGGER]) {
			$triggers = API::Trigger()->get([
				'output' => ['expression'],
				'triggerids' => $selements_to_resolve[SYSMAP_ELEMENT_TYPE_TRIGGER],
				'selectFunctions' => ['functionid', 'itemid'],
				'selectItems' => ['itemid', 'hostid'],
				'preservekeys' => true
			]);

			foreach ($triggers as $trigger) {
				foreach ($trigger['items'] as $item) {
					$hosts_by_itemids[$item['itemid']] = $item['hostid'];

					if ($query_trigger_hosts) {
						$selements_to_resolve[SYSMAP_ELEMENT_TYPE_HOST][$item['hostid']] = $item['hostid'];
					}
				}
				foreach ($trigger['functions'] as $fn) {
					$itemids_by_functionids[$fn['functionid']] = $fn['itemid'];
				}
			}
		}

		// Query details about resolvable hosts.
		if ($selements_to_resolve[SYSMAP_ELEMENT_TYPE_HOST]) {
			$hosts = API::Host()->get([
				'output' => ['host', 'name', 'description'],
				'hostids' => $selements_to_resolve[SYSMAP_ELEMENT_TYPE_HOST],
				'selectInterfaces' => $query_interfaces ? ['main', 'type', 'useip', 'ip', 'dns'] : null,
				'selectInventory' => $query_inventories ? API_OUTPUT_EXTEND : null,
				'preservekeys' => true
			]);

			// Find highest priority interface from available.
			if ($query_interfaces) {
				foreach ($hosts as &$host) {
					$top_priority_interface = null;
					$tmp_priority = 0;

					foreach ($host['interfaces'] as $interface) {
						if ($interface['main'] == INTERFACE_PRIMARY
								&& $this->interfacePriorities[$interface['type']] > $tmp_priority) {
							$tmp_priority = $this->interfacePriorities[$interface['type']];
							$top_priority_interface = [
								'conn' => $interface['useip'] ? $interface['ip'] : $interface['dns'],
								'ip' => $interface['ip'],
								'dns' => $interface['dns']
							];
						}
					}

					$host['interface'] = $top_priority_interface;
					unset($host['interfaces']);
				}
				unset($host);
			}
		}

		foreach ($types_by_selement_type as &$types) {
			$types = $this->transformToPositionTypes($types);
		}
		unset($types);

		// Find value for each extracted macro.
		foreach ($selements as $selid => &$sel) {
			if (!array_key_exists($selid, $macros_by_selementid) || !$macros_by_selementid[$selid]) {
				// Resolve functional macros like: {sampleHostName:log[{HOST.HOST}.log].last(0)}, if no host provided.
				if ($options['resolve_element_label']) {
					$sel['label'] = $this->resolveMapLabelMacros($sel['label']);
				}

				// Continue since there is nothing more to resolve.
				continue;
			}

			$selement_type = ($sel['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP
					&& $sel['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS)
				? SYSMAP_ELEMENT_TYPE_HOST
				: $sel['elementtype'];

			// Get element id.
			if (array_key_exists('elementid', $sel)) {
				$elementid = $sel['elementid'];
			}
			elseif (($element = reset($sel['elements'])) !== false) {
				$elementid = $element[$elementid_field_by_type[$sel['elementtype']]];
			}
			else {
				continue;
			}

			$matched_macros = $macros_by_selementid[$selid];
			$hosts_by_nr = null;
			$trigger = null;
			$host = null;
			$map = null;
			$hostgroup = null;

			switch ($sel['elementtype']) {
				case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
					$hostgroup = [
						'hostgroupid' => $elementid
					];
					break;

				case SYSMAP_ELEMENT_TYPE_TRIGGER:
					if (array_key_exists($elementid, $triggers)) {
						$trigger = $triggers[$elementid];

						// Must be here for correct counting.
						$hosts_by_nr = [0 => null];

						/**
						 * Get all function ids from expression and link host data against position in expression.
						 *
						 * Warning: although 'usermacros' and 'macros' are not used, they still are mandatory here to
						 * ensure correct result parsing 'functionids'.
						 *
						 * For example, having a trigger like: '{host:item.change()}=1 or {$MACRO: "{1234} ..."}' gives
						 * incorrect result of 'functionids' without requesting to parse macros and user macros as well.
						 */
						$matched_functionids = $this->extractMacros([$trigger['expression']], [
							'macros' => [
								'trigger' => ['{TRIGGER.VALUE}']
							],
							'functionids' => true,
							'usermacros' => true
						]);

						foreach (array_keys($matched_functionids['functionids']) as $functionid) {
							$functionid = substr($functionid, 1, -1); // strip curly braces
							$itemid = $itemids_by_functionids[$functionid];
							$hostid = $hosts_by_itemids[$itemid];

							if (array_key_exists($hostid, $hosts)) {
								$hosts_by_nr[count($hosts_by_nr)] = $hosts[$hostid];
							}
						}

						// Add host reference for macro without numeric index.
						if (array_key_exists(1, $hosts_by_nr)) {
							$hosts_by_nr[0] = $hosts_by_nr[1];
						}
					}
					break;

				case SYSMAP_ELEMENT_TYPE_MAP:
					if (array_key_exists($elementid, $maps)) {
						$map = $maps[$elementid];
					}
					break;

				case SYSMAP_ELEMENT_TYPE_HOST:
					if (array_key_exists($elementid, $hosts)) {
						$host = $hosts[$elementid];
						$hosts_by_nr = [0 => $host];
					}
					break;
			}

			foreach ($matched_macros as &$matched_macro) {
				// Since trigger elements support indexed macros, $host must be rewritten with n-th host.
				if ($sel['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
					$host = array_key_exists($matched_macro['f_num'], $hosts_by_nr)
						? $hosts_by_nr[$matched_macro['f_num']]
						: null;
				}

				switch ($matched_macro['macro']) {
					case 'HOSTNAME': // deprecated
					case 'HOST.NAME':
						if ($host) {
							$matched_macro['value'] = $host['name'];
						}
						break;

					case 'HOST.ID':
						if ($host) {
							$matched_macro['value'] = $host['hostid'];
						}
						break;

					case 'HOST.HOST':
						if ($host) {
							$matched_macro['value'] = $host['host'];
						}
						break;

					case 'HOST.DESCRIPTION':
						if ($host) {
							$matched_macro['value'] = $host['description'];
						}
						break;

					case 'MAP.ID':
						if ($map) {
							$matched_macro['value'] = $map['sysmapid'];
						}
						break;

					case 'MAP.NAME':
						if ($map) {
							$matched_macro['value'] = $map['name'];
						}
						break;

					case 'HOSTGROUP.ID':
						if ($hostgroup) {
							$matched_macro['value'] = $hostgroup['hostgroupid'];
						}
						break;

					case 'HOST.IP':
					case 'IPADDRESS': // deprecated
						if ($host) {
							$matched_macro['value'] = $host['interface']['ip'];
						}
						break;

					case 'HOST.DNS':
						if ($host) {
							$matched_macro['value'] = $host['interface']['dns'];
						}
						break;

					case 'HOST.CONN':
						if ($host) {
							$matched_macro['value'] = $host['interface']['conn'];
						}
						break;

					case 'TRIGGER.EVENTS.ACK':
						$matched_macro['value'] = get_events_unacknowledged($sel, null, null, true);
						break;

					case 'TRIGGER.EVENTS.PROBLEM.ACK':
						$matched_macro['value'] = get_events_unacknowledged($sel, null, TRIGGER_VALUE_TRUE, true);
						break;

					case 'TRIGGER.EVENTS.PROBLEM.UNACK':
						$matched_macro['value'] = get_events_unacknowledged($sel, null, TRIGGER_VALUE_TRUE);
						break;

					case 'TRIGGER.EVENTS.UNACK':
						$matched_macro['value'] = get_events_unacknowledged($sel);
						break;

					case 'TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK':
						$matched_macro['value'] = get_events_unacknowledged($sel, TRIGGER_VALUE_TRUE, TRIGGER_VALUE_TRUE, true);
						break;

					case 'TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK':
						$matched_macro['value'] = get_events_unacknowledged($sel, TRIGGER_VALUE_TRUE, TRIGGER_VALUE_TRUE);
						break;

					case 'TRIGGER.ID':
						if ($trigger) {
							$matched_macro['value'] = $trigger['triggerid'];
						}
						break;

					case 'TRIGGERS.UNACK':
						$matched_macro['value'] = get_triggers_unacknowledged($sel);
						break;

					case 'TRIGGERS.PROBLEM.UNACK':
						$matched_macro['value'] = get_triggers_unacknowledged($sel, true);
						break;

					case 'TRIGGERS.ACK':
						$matched_macro['value'] = get_triggers_unacknowledged($sel, null, true);
						break;

					case 'TRIGGERS.PROBLEM.ACK':
						$matched_macro['value'] = get_triggers_unacknowledged($sel, true, true);
						break;

					default:
						// Inventories:
						if (array_key_exists('{'.$matched_macro['macro'].'}', $supported_inventory_macros) && $host
								&& $host['inventory']['inventory_mode'] != HOST_INVENTORY_DISABLED) {
							$matched_macro['value']
								= $host['inventory'][$supported_inventory_macros['{'.$matched_macro['macro'].'}']];
						}
						break;
				}
			}
			unset($matched_macro);

			// Replace macros in selement label.
			if ($options['resolve_element_label']) {
				// Subtract unsupported macros from $types.
				$types = $types_by_selement_type[$selement_type];
				foreach (['macros', 'macros_n'] as $macros_type_key) {
					if (array_key_exists($macros_type_key, $types)
							&& array_key_exists($macros_type_key, $supported_label_macros[$selement_type])) {
						$types[$macros_type_key] = array_intersect($types[$macros_type_key],
							$supported_label_macros[$selement_type][$macros_type_key]
						);

						if (!$types[$macros_type_key]) {
							unset($types[$macros_type_key]);
						}
					}
				}

				// Resolve functional macros like: {{HOST.HOST}:log[{HOST.HOST}.log].last(0)}.
				$sel['label'] = $this->resolveMapLabelMacros($sel['label'], $hosts_by_nr);

				// Replace macros by resolved values in selement label.
				$macros_position = $this->getMacroPositions($sel['label'], $types);
				foreach (array_reverse($macros_position, true) as $pos => $macro) {
					$value = array_key_exists('value', $matched_macros[$macro])
						? $matched_macros[$macro]['value']
						: UNRESOLVED_MACRO_STRING;
					$sel['label'] = substr_replace($sel['label'], $value, $pos, strlen($macro));
				}
			}

			// Replace macros in selement URLs.
			if ($options['resolve_element_urls']) {
				// Subtract unsupported macros from $types.
				$types = $types_by_selement_type[$selement_type];
				foreach (['macros', 'macros_n'] as $macros_type_key) {
					if (array_key_exists($macros_type_key, $types)
							&& array_key_exists($macros_type_key, $supported_urls_macros[$selement_type])) {
						$types[$macros_type_key] = array_intersect($types[$macros_type_key],
							$supported_urls_macros[$selement_type][$macros_type_key]
						);

						if (!$types[$macros_type_key]) {
							unset($types[$macros_type_key]);
						}
					}
				}

				foreach ($sel['urls'] as &$url) {
					foreach (['name', 'url'] as $url_field) {
						$macros_position = $this->getMacroPositions($url[$url_field], $types);
						foreach (array_reverse($macros_position, true) as $pos => $macro) {
							$value = array_key_exists('value', $matched_macros[$macro])
								? $matched_macros[$macro]['value']
								: UNRESOLVED_MACRO_STRING;

							$url[$url_field] = substr_replace($url[$url_field], $value, $pos, strlen($macro));
						}
					}
				}
				unset($url);
			}
		}
		unset($sel);

		return $selements;
	}

	/**
	 * Function returns array holding of inventory macros as a keys and corresponding database fields as value.
	 *
	 * @static
	 *
	 * @return array
	 */
	private static function getSupportedHostInventoryMacrosMap() {
		return [
			'{INVENTORY.ALIAS}' => 'alias',
			'{INVENTORY.ASSET.TAG}' => 'asset_tag',
			'{INVENTORY.CHASSIS}' => 'chassis',
			'{INVENTORY.CONTACT}' => 'contact',
			'{PROFILE.CONTACT}' => 'contact', // deprecated
			'{INVENTORY.CONTRACT.NUMBER}' => 'contract_number',
			'{INVENTORY.DEPLOYMENT.STATUS}' => 'deployment_status',
			'{INVENTORY.HARDWARE}' => 'hardware',
			'{PROFILE.HARDWARE}' => 'hardware', // deprecated
			'{INVENTORY.HARDWARE.FULL}' => 'hardware_full',
			'{INVENTORY.HOST.NETMASK}' => 'host_netmask',
			'{INVENTORY.HOST.NETWORKS}' => 'host_networks',
			'{INVENTORY.HOST.ROUTER}' => 'host_router',
			'{INVENTORY.HW.ARCH}' => 'hw_arch',
			'{INVENTORY.HW.DATE.DECOMM}' => 'date_hw_decomm',
			'{INVENTORY.HW.DATE.EXPIRY}' => 'date_hw_expiry',
			'{INVENTORY.HW.DATE.INSTALL}' => 'date_hw_install',
			'{INVENTORY.HW.DATE.PURCHASE}' => 'date_hw_purchase',
			'{INVENTORY.INSTALLER.NAME}' => 'installer_name',
			'{INVENTORY.LOCATION}' => 'location',
			'{PROFILE.LOCATION}' => 'location', // deprecated
			'{INVENTORY.LOCATION.LAT}' => 'location_lat',
			'{INVENTORY.LOCATION.LON}' => 'location_lon',
			'{INVENTORY.MACADDRESS.A}' => 'macaddress_a',
			'{PROFILE.MACADDRESS}' => 'macaddress_a', // deprecated
			'{INVENTORY.MACADDRESS.B}' => 'macaddress_b',
			'{INVENTORY.MODEL}' => 'model',
			'{INVENTORY.NAME}' => 'name',
			'{PROFILE.NAME}' => 'name', // deprecated
			'{INVENTORY.NOTES}' => 'notes',
			'{PROFILE.NOTES}' => 'notes', // deprecated
			'{INVENTORY.OOB.IP}' => 'oob_ip',
			'{INVENTORY.OOB.NETMASK}' => 'oob_netmask',
			'{INVENTORY.OOB.ROUTER}' => 'oob_router',
			'{INVENTORY.OS}' => 'os',
			'{PROFILE.OS}' => 'os', // deprecated
			'{INVENTORY.OS.FULL}' => 'os_full',
			'{INVENTORY.OS.SHORT}' => 'os_short',
			'{INVENTORY.POC.PRIMARY.CELL}' => 'poc_1_cell',
			'{INVENTORY.POC.PRIMARY.EMAIL}' => 'poc_1_email',
			'{INVENTORY.POC.PRIMARY.NAME}' => 'poc_1_name',
			'{INVENTORY.POC.PRIMARY.NOTES}' => 'poc_1_notes',
			'{INVENTORY.POC.PRIMARY.PHONE.A}' => 'poc_1_phone_a',
			'{INVENTORY.POC.PRIMARY.PHONE.B}' => 'poc_1_phone_b',
			'{INVENTORY.POC.PRIMARY.SCREEN}' => 'poc_1_screen',
			'{INVENTORY.POC.SECONDARY.CELL}' => 'poc_2_cell',
			'{INVENTORY.POC.SECONDARY.EMAIL}' => 'poc_2_email',
			'{INVENTORY.POC.SECONDARY.NAME}' => 'poc_2_name',
			'{INVENTORY.POC.SECONDARY.NOTES}' => 'poc_2_notes',
			'{INVENTORY.POC.SECONDARY.PHONE.A}' => 'poc_2_phone_a',
			'{INVENTORY.POC.SECONDARY.PHONE.B}' => 'poc_2_phone_b',
			'{INVENTORY.POC.SECONDARY.SCREEN}' => 'poc_2_screen',
			'{INVENTORY.SERIALNO.A}' => 'serialno_a',
			'{PROFILE.SERIALNO}' => 'serialno_a', // deprecated
			'{INVENTORY.SERIALNO.B}' => 'serialno_b',
			'{INVENTORY.SITE.ADDRESS.A}' => 'site_address_a',
			'{INVENTORY.SITE.ADDRESS.B}' => 'site_address_b',
			'{INVENTORY.SITE.ADDRESS.C}' => 'site_address_c',
			'{INVENTORY.SITE.CITY}' => 'site_city',
			'{INVENTORY.SITE.COUNTRY}' => 'site_country',
			'{INVENTORY.SITE.NOTES}' => 'site_notes',
			'{INVENTORY.SITE.RACK}' => 'site_rack',
			'{INVENTORY.SITE.STATE}' => 'site_state',
			'{INVENTORY.SITE.ZIP}' => 'site_zip',
			'{INVENTORY.SOFTWARE}' => 'software',
			'{PROFILE.SOFTWARE}' => 'software', // deprecated
			'{INVENTORY.SOFTWARE.APP.A}' => 'software_app_a',
			'{INVENTORY.SOFTWARE.APP.B}' => 'software_app_b',
			'{INVENTORY.SOFTWARE.APP.C}' => 'software_app_c',
			'{INVENTORY.SOFTWARE.APP.D}' => 'software_app_d',
			'{INVENTORY.SOFTWARE.APP.E}' => 'software_app_e',
			'{INVENTORY.SOFTWARE.FULL}' => 'software_full',
			'{INVENTORY.TAG}' => 'tag',
			'{PROFILE.TAG}' => 'tag', // deprecated
			'{INVENTORY.TYPE}' => 'type',
			'{PROFILE.DEVICETYPE}' => 'type', // deprecated
			'{INVENTORY.TYPE.FULL}' => 'type_full',
			'{INVENTORY.URL.A}' => 'url_a',
			'{INVENTORY.URL.B}' => 'url_b',
			'{INVENTORY.URL.C}' => 'url_c',
			'{INVENTORY.VENDOR}' => 'vendor'
		];
	}

	/**
	 * Set every trigger items array elements order by item usage order in trigger expression and recovery expression.
	 *
	 * @param array  $triggers                            Array of triggers.
	 * @param string $triggers[]['expression']            Trigger expression used to define order of trigger items.
	 * @param string $triggers[]['recovery_expression']   Trigger expression used to define order of trigger items.
	 * @param array  $triggers[]['items]                  Items to be sorted.
	 * @param string $triggers[]['items][]['itemid']      Item id.
	 *
	 * @return array
	 */
	public function sortItemsByExpressionOrder(array $triggers) {
		$functionids = [];

		$types = [
			'macros' => [
				'trigger' => ['{TRIGGER.VALUE}']
			],
			'functionids' => true,
			'lldmacros' => true,
			'usermacros' => true
		];

		foreach ($triggers as $key => $trigger) {
			if (count($trigger['items']) < 2) {
				continue;
			}

			$num = 0;
			$matched_macros = $this->extractMacros([$trigger['expression'].$trigger['recovery_expression']], $types);

			foreach (array_keys($matched_macros['functionids']) as $macro) {
				$functionid = substr($macro, 1, -1); // strip curly braces

				if (!array_key_exists($functionid, $functionids)) {
					$functionids[$functionid] = ['num' => $num++, 'key' => $key];
				}
			}
		}

		if (!$functionids) {
			return $triggers;
		}

		$result = DBselect(
			'SELECT f.functionid,f.itemid'.
			' FROM functions f'.
			' WHERE '.dbConditionInt('f.functionid', array_keys($functionids))
		);

		$item_order = [];

		while ($row = DBfetch($result)) {
			$key = $functionids[$row['functionid']]['key'];
			$num = $functionids[$row['functionid']]['num'];
			if (!array_key_exists($key, $item_order) || !array_key_exists($row['itemid'], $item_order[$key])) {
				$item_order[$key][$row['itemid']] = $num;
			}
		}

		foreach ($triggers as $key => &$trigger) {
			if (count($trigger['items']) > 1) {
				$key_item_order = $item_order[$key];
				uasort($trigger['items'], function ($item1, $item2) use ($key_item_order) {
					return $key_item_order[$item1['itemid']] - $key_item_order[$item2['itemid']];
				});
			}
		}
		unset($trigger);

		return $triggers;
	}

	/**
	 * Extract macros from properties used for preprocessing step test and find effective values.
	 *
	 * @param array  $data
	 * @param string $data['steps']                              Preprocessing steps details.
	 * @param string $data['steps'][]['params']                  Preprocessing step parameters.
	 * @param string $data['steps'][]['error_handler_params]     Preprocessing steps error handle parameters.
	 * @param string $data['delay']                              Update interval value.
	 * @param string $data['hostids']                            Hostid for which tested item belongs to.
	 * @param bool   $support_lldmacros                          Enable or disable LLD macro selection.
	 *
	 * @return array
	 */
	public function extractMacrosFromPreprocessingSteps(array $data, $support_lldmacros = false) {
		$types = $support_lldmacros
			? ['usermacros' => true, 'lldmacros' => true]
			: ['usermacros' => true];
		$delay_macro = $data['delay'];

		$texts = [];
		foreach ($data['steps'] as $step) {
			if ($step['params'] !== '') {
				$texts[] = $step['params'];
			}
			if ($step['error_handler_params'] !== '') {
				$texts[] = $step['error_handler_params'];
			}
		}

		$delay_dual_usage = false;
		if ($delay_macro !== '') {
			if (in_array($delay_macro, $texts)) {
				$delay_dual_usage = true;
			}
			else {
				$texts[] = $delay_macro;
			}
		}

		$matched_macros = $this->extractMacros($texts, $types);
		$usermacros = [[
			'macros' => $matched_macros['usermacros'],
			'hostids' =>  $data['hostid']
				? [$data['hostid']]
				: []
		]];

		$macros = [];
		if ($support_lldmacros) {
			foreach (array_keys($matched_macros['lldmacros']) as $lldmacro) {
				$macros[$lldmacro] = $lldmacro;
			}
		}

		$usermacros = $this->getUserMacros($usermacros)[0]['macros'];
		foreach ($usermacros as $macro => $value) {
			$macros[$macro] = $value;
		}

		if (array_key_exists($delay_macro, $macros)) {
			$data['delay'] = $macros[$delay_macro];

			if (!$delay_dual_usage) {
				unset($macros[$delay_macro]);
			}
		}

		return [
			'delay' => $data['delay'],
			'macros' => $macros
		];
	}
}
