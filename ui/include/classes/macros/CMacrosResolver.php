<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CMacrosResolver extends CMacrosResolverGeneral {

	/**
	 * Supported macros resolving scenarios.
	 *
	 * @const array
	 */
	const CONFIGS = [
		'httpTestName' => ['host', 'interfaceWithPort', 'user'],
		'hostInterfaceIpDns' => ['host', 'agentInterface', 'user'],
		'hostInterfaceIpDnsAgentPrimary' => ['host', 'user'],
		'hostInterfaceDetailsSecurityname' => ['user'],
		'hostInterfaceDetailsAuthPassphrase' => ['user'],
		'hostInterfaceDetailsPrivPassphrase' => ['user'],
		'hostInterfaceDetailsContextName' => ['user'],
		'hostInterfaceDetailsCommunity' => ['user'],
		'hostInterfacePort' => ['user'],
		'widgetURL' => ['host', 'hostId', 'interfaceWithPort', 'user'],
		'widgetURLUser' => ['user']
	];

	/**
	 * Resolve macros with or without macro functions.
	 *
	 * Macros examples:
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
	public static function resolve(array $options) {
		return self::resolveTexts($options['data'], self::CONFIGS[$options['config']]);
	}

	/**
	 * Batch resolving macros in text using host id.
	 *
	 * @param array $data	(as $hostid => array(texts))
	 * @param array $config
	 *
	 * @return array		(as $hostid => array(texts))
	 */
	private static function resolveTexts(array $data, array $config) {
		$types = [];

		if (in_array('host', $config)) {
			$types['macros']['host'] = ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'];
		}
		if (in_array('hostId', $config)) {
			$types['macros']['host'][] = '{HOST.ID}';
		}
		if (in_array('agentInterface', $config)) {
			$types['macros']['interface'] = ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}'];
		}
		if (in_array('interfaceWithPort', $config)) {
			$types['macros']['interface_with_port'] = ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}',
				'{HOST.PORT}'
			];
		}
		if (in_array('user', $config)) {
			$types['usermacros'] = true;
		}

		$macro_values = [];
		$macros = ['host' => [], 'interface' => [], 'interface_with_port' => [], 'usermacros' => []];

		foreach ($data as $hostid => $texts) {
			$matched_macros = self::extractMacros($texts, $types);

			if (array_key_exists('macros', $matched_macros)) {
				foreach ($matched_macros['macros'] as $sub_type => $macro_data) {
					foreach ($macro_data as $token => $_data) {
						$macro_values[$hostid][$token] = UNRESOLVED_MACRO_STRING;
						$macros[$sub_type][$hostid][$_data['macro']][] =
							['token' => $token] + array_intersect_key($_data, ['macrofunc' => null]);
					}
				}
			}

			if (array_key_exists('usermacros', $matched_macros) && $matched_macros['usermacros']) {
				$macros['usermacros'][$hostid] = ['hostids' => [$hostid], 'macros' => $matched_macros['usermacros']];
			}
		}

		$macro_values = self::getHostMacrosByHostId($macros['host'], $macro_values);
		// Interface macros, macro should be resolved to main agent interface.
		$macro_values = self::getMainAgentInterfaceMacrosByHostId($macros['interface'], $macro_values);
		// Interface macros, macro should be resolved to interface with highest priority.
		$macro_values = self::getInterfaceMacrosByHostId($macros['interface_with_port'], $macro_values);
		$macro_values = self::getUserMacros($macros['usermacros'], $macro_values);

		foreach ($macro_values as $hostid => $values) {
			foreach ($data[$hostid] as &$text) {
				$text = strtr($text, $values);
			}
			unset($text);
		}

		return $data;
	}

	/**
	 * Resolve macros in trigger name.
	 *
	 * @param array  $triggers
	 * @param string $triggers[<triggerid>]['expression']
	 * @param string $triggers[<triggerid>]['description']
	 * @param array  $options
	 * @param bool   $options['references_only']           resolve only $1-$9 macros
	 *
	 * @return array
	 */
	public static function resolveTriggerNames(array $triggers, array $options) {
		$types = [
			'macros_n' => [
				'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
				'interface' => ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
				'item' => ['{ITEM.LASTVALUE}', '{ITEM.VALUE}'],
				'log' => ['{ITEM.LOG.DATE}', '{ITEM.LOG.TIME}', '{ITEM.LOG.TIMESTAMP}', '{ITEM.LOG.AGE}',
					'{ITEM.LOG.SOURCE}', '{ITEM.LOG.SEVERITY}', '{ITEM.LOG.NSEVERITY}', '{ITEM.LOG.EVENTID}'
				]
			],
			'references' => true,
			'usermacros' => true
		];

		$macro_values = [];
		$macros = ['host' => [], 'interface' => [], 'item' => [], 'references' => [], 'log' => [], 'usermacros' => []];

		$original_triggers = $triggers;
		$triggers = self::resolveTriggerExpressions($triggers,
			['resolve_usermacros' => true, 'resolve_functionids' => false]
		);

		// Find macros.
		foreach ($triggers as $triggerid => $trigger) {
			$matched_macros = self::extractMacros([$trigger['description']], $types);

			if (!$options['references_only']) {
				$functionids = self::findFunctions($trigger['expression']);

				foreach ($matched_macros['macros_n'] as $sub_type => $macro_data) {
					foreach ($macro_data as $token => $data) {
						$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;

						if (array_key_exists($data['f_num'], $functionids)) {
							$macros[$sub_type][$functionids[$data['f_num']]][$data['macro']][] =
								['token' => $token] + array_intersect_key($data, ['macrofunc' => null]);
						}
					}
				}

				if ($matched_macros['usermacros']) {
					$macros['usermacros'][$triggerid] = ['hostids' => [], 'macros' => $matched_macros['usermacros']];
				}
			}

			if ($matched_macros['references']) {
				$references = self::resolveTriggerReferences($trigger['expression'], $matched_macros['references']);

				$macro_values[$triggerid] = array_key_exists($triggerid, $macro_values)
					? array_merge($macro_values[$triggerid], $references)
					: $references;
			}

			$triggers[$triggerid]['expression'] = $original_triggers[$triggerid]['expression'];
		}

		if (!$options['references_only']) {
			// Get macro value.
			$macro_values = self::getHostMacros($macros['host'], $macro_values);
			$macro_values = self::getIpMacros($macros['interface'], $macro_values);
			$macro_values = self::getItemMacros($macros['item'], $macro_values);
			$macro_values = self::getItemLogMacros($macros['log'], $macro_values);
			$macro_values = self::getTriggerUserMacros($macros['usermacros'], $macro_values);
		}

		foreach ($macro_values as $triggerid => $values) {
			$triggers[$triggerid]['description'] = strtr($triggers[$triggerid]['description'], $values);
		}

		return $triggers;
	}

	/**
	 * Resolve macros in trigger description and operational data.
	 *
	 * @param array  $triggers
	 * @param string $triggers[<triggerid>]['expression']
	 * @param string $triggers[<triggerid>][<sources>]     See $options['sources'].
	 * @param int    $triggers[<triggerid>]['clock']       (optional)
	 * @param int    $triggers[<triggerid>]['ns']          (optional)
	 * @param array  $options
	 * @param bool   $options['events']                    Resolve {ITEM.VALUE} macro using 'clock' and 'ns' fields.
	 * @param bool   $options['html']
	 * @param array  $options['sources']                   An array of trigger field names: 'comments', 'opdata'.
	 *
	 * @return array
	 */
	public static function resolveTriggerDescriptions(array $triggers, array $options) {
		$types = [
			'macros_n' => [
				'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
				'interface' => ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
				'item' => ['{ITEM.LASTVALUE}', '{ITEM.VALUE}'],
				'log' => ['{ITEM.LOG.DATE}', '{ITEM.LOG.TIME}', '{ITEM.LOG.TIMESTAMP}', '{ITEM.LOG.AGE}',
					'{ITEM.LOG.SOURCE}', '{ITEM.LOG.SEVERITY}', '{ITEM.LOG.NSEVERITY}', '{ITEM.LOG.EVENTID}'
				]
			],
			'usermacros' => true
		];

		$macro_values = [];
		$macros = ['host' => [], 'interface' => [], 'item' => [], 'log' => [], 'usermacros' => []];

		// Find macros.
		foreach ($triggers as $triggerid => $trigger) {
			$functionids = self::findFunctions($trigger['expression']);

			$texts = [];
			foreach ($options['sources'] as $source) {
				$texts[] = $trigger[$source];
			}

			$matched_macros = self::extractMacros($texts, $types);

			foreach ($matched_macros['macros_n'] as $sub_type => $macro_data) {
				foreach ($macro_data as $token => $data) {
					$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;

					if (array_key_exists($data['f_num'], $functionids)) {
						$macros[$sub_type][$functionids[$data['f_num']]][$data['macro']][] =
							['token' => $token] + array_intersect_key($data, ['macrofunc' => null]);
					}
				}
			}

			if ($matched_macros['usermacros']) {
				$macros['usermacros'][$triggerid] = ['hostids' => [], 'macros' => $matched_macros['usermacros']];
			}
		}

		// Get macro value.
		$macro_values = self::getHostMacros($macros['host'], $macro_values);
		$macro_values = self::getIpMacros($macros['interface'], $macro_values);
		$macro_values = self::getItemMacros($macros['item'], $macro_values, $triggers, $options);
		$macro_values = self::getItemLogMacros($macros['log'], $macro_values);
		$macro_values = self::getTriggerUserMacros($macros['usermacros'], $macro_values);

		if ($options['html']) {
			$types = self::transformToPositionTypes($types);

			// Replace macros to value.
			foreach ($macro_values as $triggerid => $foo) {
				$trigger = &$triggers[$triggerid];

				foreach ($options['sources'] as $source) {
					$matched_macros = self::getMacroPositions($trigger[$source], $types);

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
			}
			unset($trigger);
		}
		else {
			foreach ($macro_values as $triggerid => $values) {
				foreach ($options['sources'] as $source) {
					$triggers[$triggerid][$source] = strtr($triggers[$triggerid][$source], $values);
				}
			}
		}

		return $triggers;
	}

	/**
	 * Resolve macros in trigger URL.
	 *
	 * @param array  $trigger
	 * @param string $trigger['triggerid']
	 * @param string $trigger['expression']
	 * @param string $trigger[<source>]      See $options['source'].
	 * @param string $trigger['eventid']     (optional)
	 * @param string $url
	 * @param array  $options
	 * @param string $options['source']      A field name to resolve macros: 'url', 'url_name'.
	 *
	 * @return bool
	 */
	public static function resolveTriggerUrl(array $trigger, &$url, array $options): bool {
		$types = [
			'macros' => [
				'trigger' => ['{TRIGGER.ID}', '{EVENT.ID}']
			],
			'macros_n' => [
				'host' => ['{HOST.ID}', '{HOST.HOST}', '{HOST.NAME}'],
				'interface' => ['{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
				'item' => ['{ITEM.LASTVALUE}', '{ITEM.VALUE}'],
				'log' => ['{ITEM.LOG.DATE}', '{ITEM.LOG.TIME}', '{ITEM.LOG.TIMESTAMP}', '{ITEM.LOG.AGE}',
					'{ITEM.LOG.SOURCE}', '{ITEM.LOG.SEVERITY}', '{ITEM.LOG.NSEVERITY}', '{ITEM.LOG.EVENTID}'
				]
			],
			'usermacros' => true
		];

		$macro_values = [];
		$macros = ['host' => [], 'interface' => [], 'item' => [], 'log' => [], 'usermacros' => []];

		$triggerid = $trigger['triggerid'];

		// Find macros.
		$functionids = self::findFunctions($trigger['expression']);
		$matched_macros = self::extractMacros([$trigger[$options['source']]], $types);

		foreach ($matched_macros['macros'] as $sub_type => $macro_data) {
			foreach ($macro_data as $token => $data) {
				if (!array_key_exists('eventid', $trigger) && $data['macro'] === 'EVENT.ID') {
					return false;
				}
				$value = $data['macro'] === 'EVENT.ID' ? $trigger['eventid'] : $trigger['triggerid'];
				$macro_values[$triggerid][$token] = array_key_exists('macrofunc', $data)
					? CMacroFunction::calcMacrofunc($value, $data['macrofunc'])
					: $value;
			}
		}

		foreach ($matched_macros['macros_n'] as $sub_type => $macro_data) {
			foreach ($macro_data as $token => $data) {
				$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;

				if (array_key_exists($data['f_num'], $functionids)) {
					$macros[$sub_type][$functionids[$data['f_num']]][$data['macro']][] =
						['token' => $token] + array_intersect_key($data, ['macrofunc' => null]);
				}
			}
		}

		if ($matched_macros['usermacros']) {
			$macros['usermacros'][$triggerid] = ['hostids' => [], 'macros' => $matched_macros['usermacros']];
		}

		// Get macro value.
		$macro_values = self::getHostMacros($macros['host'], $macro_values);
		$macro_values = self::getIpMacros($macros['interface'], $macro_values);
		$macro_values = self::getItemMacros($macros['item'], $macro_values);
		$macro_values = self::getItemLogMacros($macros['log'], $macro_values);
		$macro_values = self::getTriggerUserMacros($macros['usermacros'], $macro_values);

		$url = array_key_exists($triggerid, $macro_values)
			? strtr($trigger[$options['source']], $macro_values[$triggerid])
			: $trigger[$options['source']];

		return true;
	}

	/**
	 * Purpose: Translate {10}>10 to something like last(/localhost/system.cpu.load)>10
	 *
	 * @param array  $triggers
	 * @param string $triggers[][<sources>]			  See options['source']
	 * @param array  $options
	 * @param bool   $options['html']				  Returns formatted trigger expression. Default: false.
	 * @param bool   $options['resolve_usermacros']	  Resolve user macros. Default: false.
	 * @param bool   $options['resolve_macros']		  Resolve macros in item keys and functions. Default: false.
	 * @param bool   $options['resolve_functionids']  Resolve functionid macros. Default: true.
	 * @param array  $options['sources']			  An array of the field names. Default: ['expression'].
	 * @param string $options['context']              Additional parameter in URL to identify main section.
	 *                                                Default: 'host'.
	 *
	 * @return string|array
	 */
	public static function resolveTriggerExpressions(array $triggers, array $options) {
		$options += [
			'html' => false,
			'resolve_usermacros' => false,
			'resolve_macros' => false,
			'resolve_functionids' => true,
			'sources' => ['expression'],
			'context' => 'host'
		];

		$types = [
			'macros' => [
				'trigger' => ['{TRIGGER.VALUE}']
			],
			'lldmacros' => true,
			'usermacros' => true
		];

		$functionids = [];
		$usermacros = [];
		$macro_values = [];
		$usermacro_values = [];

		$expression_parser = new CExpressionParser([
			'usermacros' => true,
			'lldmacros' => true,
			'collapsed_expression' => true
		]);

		// Find macros.
		foreach ($triggers as $key => $trigger) {
			$functionid_macros = [];
			$texts = [];
			foreach ($options['sources'] as $source) {
				if ($trigger[$source] !== ''
						&& $expression_parser->parse($trigger[$source]) == CParser::PARSE_SUCCESS) {
					$tokens = $expression_parser->getResult()->getTokensOfTypes([
						CExpressionParserResult::TOKEN_TYPE_FUNCTIONID_MACRO,
						CExpressionParserResult::TOKEN_TYPE_USER_MACRO,
						CExpressionParserResult::TOKEN_TYPE_STRING
					]);

					foreach ($tokens as $token) {
						switch ($token['type']) {
							case CExpressionParserResult::TOKEN_TYPE_FUNCTIONID_MACRO:
								$functionid_macros[$token['match']] = null;
								break;

							case CExpressionParserResult::TOKEN_TYPE_USER_MACRO:
								$texts[] = $token['match'];
								break;

							case CExpressionParserResult::TOKEN_TYPE_STRING:
								$texts[] = CExpressionParser::unquoteString($token['match']);
								break;
						}
					}
				}
			}

			$matched_macros = self::extractMacros($texts, $types);

			$macro_values[$key] = $functionid_macros;
			$usermacro_values[$key] = [];

			foreach (array_keys($functionid_macros) as $macro) {
				$functionids[] = substr($macro, 1, -1); // strip curly braces
			}

			if ($options['resolve_usermacros'] && $matched_macros['usermacros']) {
				$usermacros[$key] = ['hostids' => [], 'macros' => $matched_macros['usermacros']];
			}
		}

		// Get macro values.
		if ($functionids) {
			$functions = [];

			if ($options['resolve_functionids']) {
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
					$items = self::resolveItemKeys($items);
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
					$functions = self::resolveFunctionParameters($functions);
				}

				foreach ($macro_values as &$macros) {
					foreach ($macros as $macro => &$value) {
						if (array_key_exists($macro, $functions)) {
							$function = $functions[$macro];

							if ($options['html']) {
								$style = ($function['status'] == ITEM_STATUS_ACTIVE)
									? ($function['state'] == ITEM_STATE_NORMAL) ? ZBX_STYLE_GREEN : ZBX_STYLE_GREY
									: ZBX_STYLE_RED;

								if ($function['type'] == ITEM_TYPE_HTTPTEST) {
									$link = (new CSpan('/'.$function['host'].'/'.$function['key_']))->addClass($style);
								}
								elseif ($function['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
									$item_url = (new CUrl('zabbix.php'))
										->setArgument('action', 'popup')
										->setArgument('popup', 'item.prototype.edit')
										->setArgument('context', $options['context'])
										->setArgument('itemid', $function['itemid'])
										->setArgument('parent_discoveryid', $function['parent_itemid'])
										->getUrl();

									$link = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
										? (new CLink('/'.$function['host'].'/'.$function['key_'], $item_url))
											->addClass($style)
											->addClass(ZBX_STYLE_LINK_ALT)
										: (new CSpan('/'.$function['host'].'/'.$function['key_']))
											->addClass($style);
								}
								else {
									$item_url = (new CUrl('zabbix.php'))
										->setArgument('action', 'popup')
										->setArgument('popup', 'item.edit')
										->setArgument('context', $options['context'])
										->setArgument('itemid', $function['itemid'])
										->getUrl();

									$link = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
										? (new CLink('/'.$function['host'].'/'.$function['key_'], $item_url))
											->addClass($style)
											->addClass(ZBX_STYLE_LINK_ALT)
										: (new CSpan('/'.$function['host'].'/'.$function['key_']))
											->addClass($style);
								}

								$value = [bold($function['function'].'(')];
								if (($pos = strpos($function['parameter'], TRIGGER_QUERY_PLACEHOLDER)) !== false) {
									if ($pos != 0) {
										$value[] = substr($function['parameter'], 0, $pos);
									}
									$value[] = $link;
									if (strlen($function['parameter']) > $pos + 1) {
										$value[] = substr($function['parameter'], $pos + 1);
									}
								}
								else {
									$value[] = $function['parameter'];
								}
								$value[] = bold(')');
							}
							else {
								$query = '/'.$function['host'].'/'.$function['key_'];
								$params = (($pos = strpos($function['parameter'], TRIGGER_QUERY_PLACEHOLDER)) !== false)
									? substr_replace($function['parameter'], $query, $pos, 1)
									: $function['parameter'];
								$value = $function['function'].'('.$params.')';
							}
						}
						else {
							$value = $options['html'] ? (new CSpan('*ERROR*'))->addClass(ZBX_STYLE_RED) : '*ERROR*';
						}
					}
					unset($value);
				}
				unset($macros);
			}
			else {
				// Selecting functions.
				$result = DBselect(
					'SELECT f.functionid,i.hostid'.
					' FROM functions f,items i'.
					' WHERE f.itemid=i.itemid'.
						' AND '.dbConditionInt('f.functionid', $functionids)
				);

				while ($row = DBfetch($result)) {
					$functions['{'.$row['functionid'].'}'] = ['hostid' => $row['hostid']];
				}
			}

			foreach ($usermacros as $key => &$usermacros_data) {
				foreach (array_keys($macro_values[$key]) as $macro) {
					if (array_key_exists($macro, $functions)) {
						$usermacros_data['hostids'][$functions[$macro]['hostid']] = true;
					}
				}
				$usermacros_data['hostids'] = array_keys($usermacros_data['hostids']);
			}
			unset($usermacros_data);

			$usermacro_values = self::getUserMacros($usermacros, $usermacro_values);
		}

		// Replace macros to value.
		foreach ($triggers as $key => $trigger) {
			foreach ($options['sources'] as $source) {
				if ($trigger[$source] === ''
						|| $expression_parser->parse($trigger[$source]) != CParser::PARSE_SUCCESS) {
					continue;
				}

				$expression = [];
				$pos_left = 0;

				$token_types = [
					CExpressionParserResult::TOKEN_TYPE_USER_MACRO,
					CExpressionParserResult::TOKEN_TYPE_STRING
				];
				if ($options['resolve_functionids']) {
					$token_types[] = CExpressionParserResult::TOKEN_TYPE_FUNCTIONID_MACRO;
				}
				if ($options['html']) {
					$token_types[] = CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION;
				}

				$right_parentheses = [];
				$tokens = $expression_parser->getResult()->getTokensOfTypes($token_types);

				foreach ($tokens as $token) {
					switch ($token['type']) {
						case CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION:
						case CExpressionParserResult::TOKEN_TYPE_FUNCTIONID_MACRO:
						case CExpressionParserResult::TOKEN_TYPE_USER_MACRO:
						case CExpressionParserResult::TOKEN_TYPE_STRING:
							foreach ($right_parentheses as $pos => $value) {
								if ($pos < $token['pos']) {
									if ($pos_left != $pos) {
										$expression[] = substr($trigger[$source], $pos_left, $pos - $pos_left);
									}
									$expression[] = bold($value);
									$pos_left = $pos + strlen($value);
									unset($right_parentheses[$pos]);
								}
							}
							if ($pos_left != $token['pos']) {
								$expression[] = substr($trigger[$source], $pos_left, $token['pos'] - $pos_left);
							}
							$pos_left = ($token['type'] == CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION)
								? $token['pos'] + strlen($token['data']['function']) + 1
								: $token['pos'] + $token['length'];
							break;
					}

					switch ($token['type']) {
						case CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION:
							$expression[] = bold($token['data']['function'].'(');
							$right_parentheses[$token['pos'] + $token['length'] - 1] = ')';
							ksort($right_parentheses, SORT_NUMERIC);
							break;

						case CExpressionParserResult::TOKEN_TYPE_FUNCTIONID_MACRO:
							$expression[] = $macro_values[$key][$token['match']];
							break;

						case CExpressionParserResult::TOKEN_TYPE_USER_MACRO:
							if (array_key_exists($token['match'], $usermacro_values[$key])) {
								$expression[] =
									CExpressionParser::quoteString($usermacro_values[$key][$token['match']], false);
							}
							else {
								$expression[] = ($options['resolve_usermacros'] && $options['html'])
									? (new CSpan('*ERROR*'))->addClass(ZBX_STYLE_RED)
									: $token['match'];
							}
							break;

						case CExpressionParserResult::TOKEN_TYPE_STRING:
							$string = strtr(CExpressionParser::unquoteString($token['match']), $usermacro_values[$key]);
							$expression[] = CExpressionParser::quoteString($string, false, true);
							break;
					}
				}

				$len = strlen($trigger[$source]);
				foreach ($right_parentheses as $pos => $value) {
					if ($pos_left != $pos) {
						$expression[] = substr($trigger[$source], $pos_left, $pos - $pos_left);
					}
					$expression[] = bold($value);
					$pos_left = $pos + strlen($value);
					unset($right_parentheses[$pos]);
				}
				if ($pos_left != $len) {
					$expression[] = substr($trigger[$source], $pos_left);
				}

				$triggers[$key][$source] = $options['html'] ? $expression : implode('', $expression);
			}
		}

		return $triggers;
	}

	/**
	 * Resolve {HOST.HOST<1-9} and empty placeholders in the expression macros.
	 *   For example:
	 *     {?last(/ /key)} => {?last(/Zabbix server/key)}
	 *     {?last(/MySQL server/key)} => {?last(/MySQL server/key)}
	 *     {?last(/{HOST.HOST}/key)} => {?last(/host/key)}
	 *
	 * @param string	$macro            [IN]     Original macro.
	 * @param array		$data             [IN/OUT] Data, returned by CHistFunctionParser.
	 * @param string	$data['host']
	 * @param array		$items            [IN]    The list of graph items.
	 * @param string	$items[]['host']
	 *
	 * @return string
	 */
	private static function resolveGraphNameExpressionMacroHost(string $macro, array &$data, array $items): string {
		if ($data['host'] === '' || $data['host'][0] == '{') {
			if ($data['host'] === '') {
				$reference = 0;
				$pattern = '#//#';
			}
			else {
				$macro_parser = new CMacroParser([
					'macros' => ['{HOST.HOST}'],
					'ref_type' => CMacroParser::REFERENCE_NUMERIC
				]);
				$macro_parser->parse($data['host']);
				$reference = $macro_parser->getReference();
				$reference = ($reference == 0) ? 0 : $reference - 1;
				$pattern = '#/\{HOST\.HOST[1-9]?\}/#';
			}

			if (!array_key_exists($reference, $items)) {
				return $macro;
			}

			$data['host'] = $items[$reference]['host'];

			// Replace {HOST.HOST<1-9>} macro with real host name.
			return preg_replace($pattern, '/'.$data['host'].'/', $macro, 1);
		}

		return $macro;
	}

	/**
	 * Resolve expression macros. For example, {?func(/host/key, param)} or {?func(/{HOST.HOST1}/key, param)}.
	 *
	 * @param array  $graphs
	 * @param string $graphs[]['name']
	 * @param array  $graphs[]['items']
	 * @param string $graphs[]['items'][]['host']
	 *
	 * @return array	Inputted data with resolved graph name.
	 */
	public static function resolveGraphNames(array $graphs): array {
		$types = ['expr_macros_host_n' => true];

		$macros = ['expr_macros' => []];
		$macro_values = [];

		foreach ($graphs as $key => $graph) {
			$matched_macros = self::extractMacros([$graph['name']], $types);

			foreach ($matched_macros['expr_macros_host_n'] as $macro => $data) {
				$macro_values[$key][$macro] = UNRESOLVED_MACRO_STRING;

				$_macro = self::resolveGraphNameExpressionMacroHost($macro, $data, $graph['items']);
				if (!array_key_exists($_macro, $macros['expr_macros'])) {
					$macros['expr_macros'][$_macro] = $data;
				}
				$macros['expr_macros'][$_macro]['links'][$macro][] = $key;
			}
		}

		foreach (self::getExpressionMacros($macros['expr_macros'], []) as $_macro => $value) {
			foreach ($macros['expr_macros'][$_macro]['links'] as $macro => $keys) {
				foreach ($keys as $key) {
					$macro_values[$key][$macro] = $value;
				}
			}
		}

		foreach ($graphs as $key => &$graph) {
			if (array_key_exists($key, $macro_values)) {
				$graph['name'] = strtr($graph['name'], $macro_values[$key]);
			}
		}
		unset($graph);

		return $graphs;
	}

	/**
	 * Resolve item key macros to "key_expanded" field.
	 *
	 * @param array  $items
	 * @param string $items[<itemid>]['hostid']
	 * @param string $items[<itemid>]['key_']
	 *
	 * @return array
	 */
	public static function resolveItemKeys(array $items) {
		$types = [
			'macros' => [
				'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
				'interface' => ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}']
			],
			'usermacros' => true
		];

		$macro_values = [];
		$macros = ['host' => [], 'interface' => [], 'usermacros' => []];

		foreach ($items as &$item) {
			$item['key_expanded'] = $item['key_'];
		}
		unset($item);

		foreach ($items as $itemid => $item) {
			$matched_macros = self::extractItemKeyMacros($item['key_expanded'], $types);

			foreach ($matched_macros['macros'] as $sub_type => $macro_data) {
				foreach ($macro_data as $token => $data) {
					$macro_values[$itemid][$token] = UNRESOLVED_MACRO_STRING;
					$macros[$sub_type][$itemid][$data['macro']][] =
						['token' => $token] + array_intersect_key($data, ['macrofunc' => null]);
				}
			}

			if ($matched_macros['usermacros']) {
				$macros['usermacros'][$itemid] =
					['hostids' => [$item['hostid']], 'macros' => $matched_macros['usermacros']];
			}
		}

		$macro_values = self::getHostMacrosByItemId($macros['host'], $macro_values);
		$macro_values = self::getInterfaceMacrosByItemId($macros['interface'], $macro_values);
		$macro_values = self::getUserMacros($macros['usermacros'], $macro_values);

		foreach ($macro_values as $itemid => $macro_value) {
			$items[$itemid]['key_expanded'] = self::resolveItemKeyMacros($items[$itemid]['key_expanded'], $macro_value);
		}

		return $items;
	}

	/**
	 * Resolve item description macros to "description_expanded" field.
	 *
	 * @param array  $items
	 * @param string $items[<itemid>]['hostid']
	 * @param string $items[<itemid>]['description']
	 *
	 * @return array
	 */
	public static function resolveItemDescriptions(array $items): array {
		$types = ['usermacros' => true];

		$macro_values = [];
		$macros = ['usermacros' => []];

		foreach ($items as &$item) {
			$item['description_expanded'] = $item['description'];
		}
		unset($item);

		foreach ($items as $itemid => $item) {
			$matched_macros = self::extractMacros([$item['description']], $types);

			if ($matched_macros['usermacros']) {
				$macros['usermacros'][$itemid] =
					['hostids' => [$item['hostid']], 'macros' => $matched_macros['usermacros']];
			}
		}

		$macro_values = self::getUserMacros($macros['usermacros'], $macro_values);

		foreach ($macro_values as $itemid => $values) {
			$items[$itemid]['description_expanded'] = strtr($items[$itemid]['description'], $values);
		}

		return $items;
	}

	/**
	 * Resolve macros in fields of item-based widgets.
	 *
	 * @param array  $items
	 *        string $items[<itemid>]['hostid']
	 *        string $items[<itemid>][<source_field>]  Particular source field, as referred by $fields.
	 *
	 * @param array  $fields                           Fields to resolve as [<source_field> => <resolved_field>].
	 *
	 * @return array
	 */
	public static function resolveItemBasedWidgetMacros(array $items, array $fields): array {
		$types = [
			'macros' => [
				'host' => ['{HOSTNAME}', '{HOST.ID}', '{HOST.NAME}', '{HOST.HOST}', '{HOST.DESCRIPTION}'],
				'interface' => ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
				'item' => ['{ITEM.DESCRIPTION}', '{ITEM.DESCRIPTION.ORIG}', '{ITEM.ID}', '{ITEM.KEY}',
					'{ITEM.KEY.ORIG}', '{ITEM.NAME}', '{ITEM.NAME.ORIG}', '{ITEM.STATE}', '{ITEM.VALUETYPE}'
				],
				'item_value' => ['{ITEM.LASTVALUE}', '{ITEM.VALUE}', '{ITEM.LOG.DATE}', '{ITEM.LOG.TIME}',
					'{ITEM.LOG.TIMESTAMP}', '{ITEM.LOG.AGE}', '{ITEM.LOG.SOURCE}', '{ITEM.LOG.SEVERITY}',
					'{ITEM.LOG.NSEVERITY}', '{ITEM.LOG.EVENTID}'
				],
				'inventory' => array_keys(self::getSupportedHostInventoryMacrosMap())
			],
			'usermacros' => true
		];

		$macro_values = [];
		$macros =
			['host' => [], 'interface' => [], 'item' => [], 'item_value' => [], 'inventory' => [], 'usermacros' => []];

		foreach ($items as $itemid => $item) {
			$matched_macros = self::extractMacros(array_intersect_key($item, $fields), $types);

			foreach ($matched_macros['macros'] as $sub_type => $macro_data) {
				foreach ($macro_data as $token => $data) {
					$macro_values[$itemid][$token] = UNRESOLVED_MACRO_STRING;
					$macros[$sub_type][$itemid][$data['macro']][] =
						['token' => $token] + array_intersect_key($data, ['macrofunc' => null]);
				}
			}

			if ($matched_macros['usermacros']) {
				$macros['usermacros'][$itemid] =
					['hostids' => [$item['hostid']], 'macros' => $matched_macros['usermacros']];
			}
		}

		$macro_values = self::getHostMacrosByItemId($macros['host'], $macro_values);
		$macro_values = self::getInterfaceMacrosByItemId($macros['interface'], $macro_values);
		$macro_values = self::getItemMacrosByItemId($macros['item'], $macro_values);
		$macro_values = self::getItemValueMacrosByItemId($macros['item_value'], $macro_values);
		$macro_values = self::getInventoryMacrosByItemId($macros['inventory'], $macro_values);
		$macro_values = self::getUserMacros($macros['usermacros'], $macro_values);

		foreach ($macro_values as $itemid => $values) {
			foreach ($fields as $from => $to) {
				$items[$itemid][$to] = strtr($items[$itemid][$from], $values);
			}
		}

		return $items;
	}

	/**
	 * Resolve text-type column macros for top-hosts widget.
	 *
	 * @param array $columns
	 * @param array $hostids
	 *
	 * @return array
	 */
	public static function resolveWidgetTopHostsTextColumns(array $columns, array $hostids): array {
		$types = [
			'macros' => [
				'host' => ['{HOSTNAME}', '{HOST.ID}', '{HOST.NAME}', '{HOST.HOST}', '{HOST.DESCRIPTION}'],
				'interface' => ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
				'inventory' => array_keys(self::getSupportedHostInventoryMacrosMap())
			],
			'usermacros' => true
		];

		$macro_values = [];
		$macros = ['host' => [], 'interface' => [], 'inventory' => [], 'usermacros' => []];

		$matched_macros = self::extractMacros($columns, $types);

		foreach ($hostids as $hostid) {
			$macro_values[$hostid] = [];

			foreach ($matched_macros['macros'] as $sub_type => $macro_data) {
				foreach ($macro_data as $token => $data) {
					$macro_values[$hostid][$token] = UNRESOLVED_MACRO_STRING;
					$macros[$sub_type][$hostid][$data['macro']][] =
						['token' => $token] + array_intersect_key($data, ['macrofunc' => null]);
				}
			}

			if ($matched_macros['usermacros']) {
				$macros['usermacros'][$hostid] = ['hostids' => [$hostid], 'macros' => $matched_macros['usermacros']];
			}
		}

		$macro_values = self::getHostMacrosByHostId($macros['host'], $macro_values);
		$macro_values = self::getInterfaceMacrosByHostId($macros['interface'], $macro_values);
		$macro_values = self::getInventoryMacrosByHostId($macros['inventory'], $macro_values);
		$macro_values = self::getUserMacros($macros['usermacros'], $macro_values);

		$data = [];

		foreach ($columns as $column => $value) {
			$data[$column] = [];

			foreach ($hostids as $hostid) {
				$data[$column][$hostid] = strtr($value, $macro_values[$hostid]);
			}
		}

		return $data;
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
	public static function resolveTimeUnitMacros(array $data, array $options) {
		$types = [
			'usermacros' => true
		];

		$macro_values = [];
		$macros = ['usermacros' => []];

		// Find macros.
		foreach ($data as $key => $value) {
			$texts = [];
			foreach ($options['sources'] as $source) {
				$texts[] = $value[$source];
			}

			$matched_macros = self::extractMacros($texts, $types);

			if ($matched_macros['usermacros']) {
				$macros['usermacros'][$key] = [
					'hostids' => array_key_exists('hostid', $value) ? [$value['hostid']] : [],
					'macros' => $matched_macros['usermacros']
				];
			}
		}

		$macro_values = self::getUserMacros($macros['usermacros'], $macro_values);

		foreach ($macro_values as $key => $values) {
			foreach ($options['sources'] as $source) {
				$data[$key][$source] = strtr($data[$key][$source], $values);
			}
		}

		return $data;
	}

	/**
	 * Resolve function parameter macros.
	 *
	 * @param array  $functions
	 * @param string $functions[n]['hostid']
	 * @param string $functions[n]['function']
	 * @param string $functions[n]['parameter']
	 *
	 * @return array
	 */
	private static function resolveFunctionParameters(array $functions) {
		$types = ['usermacros' => true];

		$macro_values = [];
		$macros = ['usermacros' => []];

		foreach ($functions as $key => $function) {
			$functions[$key]['function_string'] = $function['function'].'('.$function['parameter'].')';
			if (($pos = strpos($functions[$key]['function_string'], TRIGGER_QUERY_PLACEHOLDER)) !== false) {
				$functions[$key]['function_string'] = substr_replace($functions[$key]['function_string'], '/foo/bar',
					$pos, 1
				);
				$functions[$key]['function_query_pos'] = $pos;
			}

			$matched_macros = self::extractFunctionMacros($functions[$key]['function_string'], $types);

			if ($matched_macros['usermacros']) {
				$macros['usermacros'][$key] =
					['hostids' => [$function['hostid']], 'macros' => $matched_macros['usermacros']];
			}
		}

		$macro_values = self::getUserMacros($macros['usermacros'], $macro_values);

		foreach ($macro_values as $key => $values) {
			$function = self::resolveFunctionMacros($functions[$key]['function_string'], $values);
			$function = substr_replace($function, TRIGGER_QUERY_PLACEHOLDER, $functions[$key]['function_query_pos'], 8);
			$functions[$key]['parameter'] = substr($function, strlen($functions[$key]['function']) + 1, -1);
		}

		array_walk($functions, function (array &$function) {
			unset($function['function_string'], $function['function_query_pos']);
		});

		return $functions;
	}

	/**
	 * Expand functional macros in given map link labels.
	 *
	 * @param array  $links
	 * @param string $links[]['label']
	 * @param array  $fields            A mapping between source and destination fields.
	 *
	 * @return array
	 */
	public static function resolveMapLinkLabelMacros(array $links, array $fields): array {
		$types = ['expr_macros' => true];

		$macro_values = [];
		$macros = ['expr_macros' => []];

		foreach ($links as $link) {
			$matched_macros = self::extractMacros(array_intersect_key($link, $fields), $types);

			foreach ($matched_macros['expr_macros'] as $token => $data) {
				$macro_values[$token] = UNRESOLVED_MACRO_STRING;
			}
			$macros['expr_macros'] += $matched_macros['expr_macros'];
		}

		$macro_values = self::getExpressionMacros($macros['expr_macros'], $macro_values);

		foreach ($links as &$link) {
			foreach ($fields as $from => $to) {
				$link[$to] = strtr($link[$from], $macro_values);
			}
		}
		unset($link);

		return $links;
	}

	/**
	 * Expand functional macros in given map shape labels.
	 *
	 * @param string $map_name
	 * @param array  $shapes
	 * @param string $shapes[]['text']
	 * @param array  $fields            A mapping between source and destination fields.
	 *
	 * @return array
	 */
	public static function resolveMapShapeLabelMacros(string $map_name, array $shapes, array $fields): array {
		$types = [
			'macros' => [
				'map' => ['{MAP.NAME}']
			],
			'expr_macros' => true
		];

		$macro_values = [];
		$macros = ['expr_macros' => []];

		foreach ($shapes as $shape) {
			$matched_macros = self::extractMacros(array_intersect_key($shape, $fields), $types);

			foreach ($matched_macros['macros']['map'] as $token => $data) {
				$macro_values[$token] = array_key_exists('macrofunc', $data)
					? CMacroFunction::calcMacrofunc($map_name, $data['macrofunc'])
					: $map_name;
			}

			foreach ($matched_macros['expr_macros'] as $token => $data) {
				$macro_values[$token] = UNRESOLVED_MACRO_STRING;
			}
			$macros['expr_macros'] += $matched_macros['expr_macros'];
		}

		$macro_values = self::getExpressionMacros($macros['expr_macros'], $macro_values);

		foreach ($shapes as &$shape) {
			foreach ($fields as $from => $to) {
				$shape[$to] = strtr($shape[$from], $macro_values);
			}
		}
		unset($shape);

		return $shapes;
	}

	/**
	 * Resolve supported macros used in map element label as well as in URL names and values.
	 *
	 * @param array  $selements[]
	 * @param int    $selements[]['elementtype']
	 * @param int    $selements[]['elementsubtype']
	 * @param array  $selements[]['elemments']
	 * @param string $selements[]['label']
	 * @param array  $selements[]['urls']
	 * @param string $selements[]['urls'][]['name']
	 * @param string $selements[]['urls'][]['url']
	 * @param array  $options
	 * @param bool   $options[resolve_element_label]  Resolve macros in map element label.
	 * @param bool   $options[resolve_element_urls]   Resolve macros in map element url name and value.
	 *
	 * @return array
	 */
	public static function resolveMacrosInMapElements(array $selements, array $options) {
		$options += ['resolve_element_label' => false, 'resolve_element_urls' => false];

		$field_types = [];
		if ($options['resolve_element_label']) {
			$field_types[] = 'label';
		}
		if ($options['resolve_element_urls']) {
			$field_types[] = 'urls';
		}

		$inventory_macros = self::getSupportedHostInventoryMacrosMap();

		$types_by_elementtype = [
			'label' => [
				SYSMAP_ELEMENT_TYPE_IMAGE => [
					'expr_macros' => true
				],
				SYSMAP_ELEMENT_TYPE_MAP => [
					'expr_macros' => true,
					'macros' => [
						'map' => ['{MAP.ID}', '{MAP.NAME}'],
						'triggers' => self::aggr_triggers_macros
					]
				],
				SYSMAP_ELEMENT_TYPE_HOST_GROUP => [
					'expr_macros' => true,
					'macros' => [
						'hostgroup' => ['{HOSTGROUP.ID}'],
						'triggers' => self::aggr_triggers_macros
					]
				],
				SYSMAP_ELEMENT_TYPE_HOST => [
					'expr_macros_host' => true,
					'macros' => [
						'host' => ['{HOSTNAME}', '{HOST.ID}', '{HOST.NAME}', '{HOST.HOST}', '{HOST.DESCRIPTION}'],
						'interface' => ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
						'inventory' => array_keys($inventory_macros),
						'triggers' => self::aggr_triggers_macros
					]
				],
				SYSMAP_ELEMENT_TYPE_TRIGGER => [
					'expr_macros_host_n' => true,
					'macros' => [
						'trigger' => ['{TRIGGER.ID}'],
						'triggers' => self::aggr_triggers_macros
					],
					'macros_n' => [
						'host_n' => ['{HOSTNAME}', '{HOST.ID}', '{HOST.NAME}', '{HOST.HOST}', '{HOST.DESCRIPTION}'],
						'interface_n' => ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
						'inventory_n' => array_keys($inventory_macros)
					]
				]
			],
			'urls' => [
				SYSMAP_ELEMENT_TYPE_MAP => [
					'macros' => [
						'map' => ['{MAP.ID}', '{MAP.NAME}']
					]
				],
				SYSMAP_ELEMENT_TYPE_HOST_GROUP => [
					'macros' => [
						'hostgroup' => ['{HOSTGROUP.ID}']
					]
				],
				SYSMAP_ELEMENT_TYPE_HOST => [
					'macros' => [
						'host' => ['{HOSTNAME}', '{HOST.ID}', '{HOST.NAME}', '{HOST.HOST}'],
						'interface' => ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
						'inventory' => array_keys($inventory_macros)
					]
				],
				SYSMAP_ELEMENT_TYPE_TRIGGER => [
					'macros' => [
						'trigger' => ['{TRIGGER.ID}']
					],
					'macros_n' => [
						'host_n' => ['{HOSTNAME}', '{HOST.ID}', '{HOST.NAME}', '{HOST.HOST}'],
						'interface_n' => ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
						'inventory_n' => array_keys($inventory_macros)
					]
				]
			]
		];

		$macro_values = [];
		$macros = ['map' => [], 'triggers' => [], 'host' => [], 'interface' => [], 'inventory' => [], 'host_n' => [],
			'interface_n' => [], 'inventory_n' => [], 'expr_macros' => [], 'expr_macros_host' => [],
			'expr_macros_host_n' => []
		];

		foreach ($selements as $key => $selement) {
			$elementtype = ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP
					&& $selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS)
				? SYSMAP_ELEMENT_TYPE_HOST
				: $selement['elementtype'];

			foreach ($field_types as $field_type) {
				if (!array_key_exists($elementtype, $types_by_elementtype[$field_type])) {
					continue;
				}

				$texts = [];
				if ($field_type === 'label') {
					$texts[] = $selement['label'];
				}
				if ($field_type === 'urls') {
					foreach ($selement['urls'] as $url) {
						$texts[] = $url['name'];
						$texts[] = $url['url'];
					}
				}

				// Extract macros from collected strings.
				$matched_macros = self::extractMacros($texts, $types_by_elementtype[$field_type][$elementtype]);

				if (array_key_exists('macros', $matched_macros)) {
					if (array_key_exists('map', $matched_macros['macros'])) {
						foreach ($matched_macros['macros']['map'] as $token => $data) {
							$macro_values[$key][$token] = UNRESOLVED_MACRO_STRING;
							$macros['map'][$selement['elements'][0]['sysmapid']][$data['macro']][] =
								['token' => $token, 'key' => $key] + array_intersect_key($data, ['macrofunc' => null]);
						}
					}

					if (array_key_exists('triggers', $matched_macros['macros'])) {
						foreach ($matched_macros['macros']['triggers'] as $token => $data) {
							$macro_values[$key][$token] = UNRESOLVED_MACRO_STRING;
							$macros['triggers'][$key][$data['macro']][] =
								['token' => $token] + array_intersect_key($data, ['macrofunc' => null]);
						}
					}

					if (array_key_exists('hostgroup', $matched_macros['macros'])) {
						foreach ($matched_macros['macros']['hostgroup'] as $token => $data) {
							$macro_values[$key][$token] = array_key_exists('macrofunc', $data)
								? CMacroFunction::calcMacrofunc($selement['elements'][0]['groupid'], $data['macrofunc'])
								: $selement['elements'][0]['groupid'];
						}
					}

					foreach (['host', 'interface', 'inventory'] as $sub_type) {
						if (array_key_exists($sub_type, $matched_macros['macros'])) {
							foreach ($matched_macros['macros'][$sub_type] as $token => $data) {
								$macro_values[$key][$token] = UNRESOLVED_MACRO_STRING;
								if (array_key_exists('hostid', $selement['elements'][0])) {
									$hostid = $selement['elements'][0]['hostid'];
									$macros[$sub_type][$hostid][$data['macro']][] = ['token' => $token, 'key' => $key]
										+ array_intersect_key($data, ['macrofunc' => null]);
								}
							}
						}
					}

					if (array_key_exists('trigger', $matched_macros['macros'])) {
						foreach ($matched_macros['macros']['trigger'] as $token => $data) {
							$macro_values[$key][$token] = array_key_exists('macrofunc', $data)
								? CMacroFunction::calcMacrofunc($selement['elements'][0]['triggerid'], $data['macrofunc'])
								: $selement['elements'][0]['triggerid'];
						}
					}
				}

				if (array_key_exists('macros_n', $matched_macros)) {
					foreach ($matched_macros['macros_n'] as $sub_type => $macro_data) {
						foreach ($macro_data as $token => $data) {
							$macro_values[$key][$token] = UNRESOLVED_MACRO_STRING;
							$triggerid = $selement['elements'][0]['triggerid'];
							$macros[$sub_type][$triggerid][$data['macro']][$data['f_num']][] =
								['token' => $token, 'key' => $key] + array_intersect_key($data, ['macrofunc' => null]);
						}
					}
				}

				if (array_key_exists('expr_macros', $matched_macros)) {
					foreach ($matched_macros['expr_macros'] as $macro => $data) {
						$macro_values[$key][$macro] = UNRESOLVED_MACRO_STRING;

						if (!array_key_exists($macro, $macros['expr_macros'])) {
							$macros['expr_macros'][$macro] = $data;
						}
						$macros['expr_macros'][$macro]['links'][$macro][] = $key;
					}
				}

				if (array_key_exists('expr_macros_host', $matched_macros)) {
					foreach ($matched_macros['expr_macros_host'] as $macro => $data) {
						$macro_values[$key][$macro] = UNRESOLVED_MACRO_STRING;
						if ($data['host'] === '' || $data['host'][0] === '{') {
							if (array_key_exists('hostid', $selement['elements'][0])) {
								$macros['expr_macros_host'][$selement['elements'][0]['hostid']][$key][$macro] = $data;
							}
						}
						else {
							if (!array_key_exists($macro, $macros['expr_macros'])) {
								$macros['expr_macros'][$macro] = $data;
							}
							$macros['expr_macros'][$macro]['links'][$macro][] = $key;
						}
					}
				}

				if (array_key_exists('expr_macros_host_n', $matched_macros)) {
					foreach ($matched_macros['expr_macros_host_n'] as $macro => $data) {
						$macro_values[$key][$macro] = UNRESOLVED_MACRO_STRING;
						if ($data['host'] === '' || $data['host'][0] === '{') {
							$macros['expr_macros_host_n'][$selement['elements'][0]['triggerid']][$key][$macro] = $data;
						}
						else {
							if (!array_key_exists($macro, $macros['expr_macros'])) {
								$macros['expr_macros'][$macro] = $data;
							}
							$macros['expr_macros'][$macro]['links'][$macro][] = $key;
						}
					}
				}
			}
		}

		$macro_values = self::getMapMacros($macros['map'], $macro_values);
		$macro_values = self::getAggrTriggerMacros($macros['triggers'], $macro_values, $selements);
		$macro_values = self::getHostMacrosByHostId($macros['host'], $macro_values);
		$macro_values = self::getInterfaceMacrosByHostId($macros['interface'], $macro_values);
		$macro_values = self::getInventoryMacrosByHostId($macros['inventory'], $macro_values);

		$trigger_hosts_by_f_num = self::getExpressionHosts(
			array_keys($macros['host_n'] + $macros['interface_n'] + $macros['inventory_n'])
		);
		$macro_values = self::getHostNMacros($macros['host_n'], $macro_values, $trigger_hosts_by_f_num);
		$macro_values = self::getInterfaceNMacros($macros['interface_n'], $macro_values, $trigger_hosts_by_f_num);
		$macro_values = self::getInventoryNMacros($macros['inventory_n'], $macro_values, $trigger_hosts_by_f_num);

		$macro_values = self::getExpressionNMacros($macros['expr_macros_host_n'], $macros['expr_macros_host'],
			$macros['expr_macros'], $macro_values
		);

		foreach ($selements as $key => &$selement) {
			if (!array_key_exists($key, $macro_values)) {
				continue;
			}

			foreach ($field_types as $field_type) {
				if ($field_type === 'label') {
					$selement['label'] = strtr($selement['label'], $macro_values[$key]);
				}
				else {
					foreach ($selement['urls'] as &$url) {
						$url['name'] = strtr($url['name'], $macro_values[$key]);
						$url['url'] = strtr($url['url'], $macro_values[$key]);
					}
					unset($url);
				}
			}
		}
		unset($selement);

		return $selements;
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
	public static function sortItemsByExpressionOrder(array $triggers) {
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
			$matched_macros = self::extractMacros([$trigger['expression'].$trigger['recovery_expression']], $types);

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
	 * Extract macros from item property fields and apply effective value for each of extracted macros.
	 * Each type of macros are extracted separately because there are fields that support only LLD macros and doesn't
	 * support user macros.
	 *
	 * @param array  $data
	 * @param string $data['steps']                              Preprocessing steps details.
	 * @param string $data['steps'][]['params']                  Preprocessing step parameters.
	 * @param string $data['steps'][]['error_handler_params]     Preprocessing steps error handle parameters.
	 * @param string $data['delay']                              Update interval value.
	 * @param array  $data['supported_macros']                   Supported macros.
	 * @param bool   $data['support_lldmacros']                  Either LLD macros need to be extracted.
	 * @param array  $data['texts_support_macros']               List of texts potentially could contain macros.
	 * @param array  $data['texts_support_user_macros']          List of texts potentially could contain user macros.
	 * @param array  $data['texts_support_lld_macros']           List of texts potentially could contain LLD macros.
	 * @param int    $data['hostid']                             Hostid for which tested item belongs to.
	 * @param array  $data['macros_values']                      Values for supported macros.
	 *
	 * @return array
	 */
	public static function extractItemTestMacros(array $data) {
		$macros = [];
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

		// Extract macros.
		if ($data['supported_macros']) {
			$matched_macros = self::extractMacros($data['texts_support_macros'],
				['macros' => $data['supported_macros']]
			);

			foreach ($matched_macros['macros'] as $type => $matches) {
				foreach ($matches as $token => $_data) {
					$macros[$token] = $data['macros_values'][$type][$token];
				}
			}
		}

		// Extract user macros.
		$data['texts_support_user_macros'] = array_merge($texts, $data['texts_support_user_macros']);
		if ($data['texts_support_user_macros']) {
			$matched_macros = self::extractMacros($data['texts_support_user_macros'], ['usermacros' => true]);

			$usermacros = [[
				'hostids' => $data['hostid'] == 0 ? [] : [$data['hostid']],
				'macros' => $matched_macros['usermacros']
			]];

			$macros = array_merge($macros, self::getUserMacros($usermacros, [])[0]);
		}

		// Extract LLD macros.
		$data['texts_support_lld_macros'] = $data['support_lldmacros']
			? array_merge($texts, $data['texts_support_lld_macros'])
			: [];
		if ($data['texts_support_lld_macros']) {
			$matched_macros = self::extractMacros($data['texts_support_lld_macros'], ['lldmacros' => true]);

			foreach (array_keys($matched_macros['lldmacros']) as $lldmacro) {
				$macros[$lldmacro] = $lldmacro;
			}
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

	/**
	 * Return associative array of urls with resolved {EVENT.TAGS.*} macro in form
	 * [<eventid> => ['urls' => [['url' => .. 'name' => ..], ..]]].
	 *
	 * @param array  $events                                Array of event tags.
	 * @param string $events[<eventid>]['tags'][]['tag']    Event tag tag field value.
	 * @param string $events[<eventid>]['tags'][]['value']  Event tag value field value.
	 * @param array  $urls                                  Array of mediatype urls.
	 * @param string $urls[]['event_menu_url']              Media type url field value.
	 * @param string $urls[]['event_menu_name']             Media type url_name field value.
	 *
	 * @return array
	 */
	public static function resolveMediaTypeUrls(array $events, array $urls) {
		$types = [
			'macros_an' => [
				'event' => ['{EVENT.TAGS}']
			]
		];

		$macros = ['event' => []];

		$urls = CArrayHelper::renameObjectsKeys($urls, ['event_menu_url' => 'url', 'event_menu_name' => 'name']);
		$url_macros = [];

		foreach ($urls as $index => $url) {
			$matched_macros = self::extractMacros([$url['url'], $url['name']], $types);
			$url_macros[$index] = [];

			foreach ($matched_macros['macros_an']['event'] as $token => $data) {
				$url_macros[$index][$token] = true;

				foreach ($events as $eventid => $event) {
					$macro_values[$eventid][$token] = null;

					$macros['event'][$eventid][$data['f_num']][] =
						['token' => $token] + array_intersect_key($data, ['macrofunc' => null]);
				}
			}
		}

		foreach ($events as $eventid => $event) {
			if (!array_key_exists($eventid, $macros['event'])) {
				continue;
			}

			CArrayHelper::sort($event['tags'], ['tag', 'value']);

			$tag_value = [];
			foreach ($event['tags'] as $tag) {
				$tag_value += [$tag['tag'] => $tag['value']];
			}

			foreach ($macros['event'][$eventid] as $f_num => $tokens) {
				if (array_key_exists($f_num, $tag_value)) {
					foreach ($tokens as $token) {
						$macro_values[$eventid][$token['token']] = array_key_exists('macrofunc', $token)
							? CMacroFunction::calcMacrofunc($tag_value[$f_num], $token['macrofunc'])
							: $tag_value[$f_num];
					}
				}
			}
		}

		foreach ($events as $eventid => $event) {
			$events[$eventid]['urls'] = [];

			foreach ($urls as $index => $url) {
				if ($url_macros[$index]) {
					foreach ($url_macros[$index] as $macro => $foo) {
						if ($macro_values[$eventid][$macro] === null) {
							continue 2;
						}
					}

					foreach (['url', 'name'] as $field) {
						$url[$field] = strtr($url[$field], $macro_values[$eventid]);
					}
				}

				$events[$eventid]['urls'][] = $url;
			}
		}

		return $events;
	}

	/**
	 * Resolve macros for manual host action scripts. Resolves host macros, interface macros, inventory, user macros,
	 * user data macros and manual input macro.
	 *
	 * @param array  $data                          Array of unresolved macros.
	 * @param array  $data[<hostid>]                Array of scripts. Contains script ID as keys.
	 * @param array  $data[<hostid>][<scriptid>]    Script fields to resolve macros for.
	 * @param array  $manualinput_values
	 * @param string $manualinput_values[<hostid>]  Value for resolving {MANUALINPUT} macros.
	 *
	 * Example input:
	 *     $data = [
	 *         10084 => [
	 *             57 => [
	 *                 'confirmation' => 'Are you sure you want to edit {HOST.HOST} now?',
	 *                 'url' => 'http://zabbix/ui/zabbix.php?action=host.edit&hostid={HOST.ID}'
	 *             ],
	 *             61 => [
	 *                 'confirmation' => 'Hello, {USER.FULLNAME}! Execute script?',
	 *                 'manualinput_prompt' => 'Add manualinput value for script execution with {HOST.HOST}:'
	 *             ],
	 *             42 => [
	 *                 'manualinput_prompt' => 'Enter port number',
	 *                 'url' => 'http://localhost:{MANUALINPUT}'
	 *             ]
	 *         ]
	 *     ];
	 *
	 *     $manualinput_values = [
	 *         10084 => 8080
	 *     ];
	 *
	 * Output:
	 *     [
	 *         10084 => [
	 *             57 => [
	 *                 'confirmation' => 'Are you sure you want to edit Zabbix server now?',
	 *                 'url' => 'http://zabbix/ui/zabbix.php?action=host.edit&hostid=10084'
	 *             ],
	 *             61 => [
	 *                 'confirmation' => 'Hello, Zabbix Administrator! Execute script?',
	 *                 'manualinput_prompt' => 'Add manualinput value for script execution with Zabbix server:
	 *             ],
	 *             42 => [
	 *                 'manualinput_prompt' => 'Enter port number',
	 *                 'url' => 'http://localhost:8080'
	 *             ]
	 *         ]
	 *     ]
	 *
	 * @return array
	 */
	public static function resolveManualHostActionScripts(array $data, array $manualinput_values): array {
		$types = [
			'macros' => [
				'host' => ['{HOSTNAME}', '{HOST.ID}', '{HOST.NAME}', '{HOST.HOST}'],
				'interface' => ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
				'inventory' => array_keys(self::getSupportedHostInventoryMacrosMap()),
				'user_data' => ['{USER.ALIAS}', '{USER.USERNAME}', '{USER.FULLNAME}', '{USER.NAME}', '{USER.SURNAME}'],
				'manualinput' => ['{MANUALINPUT}']
			],
			'usermacros' => true
		];

		$macro_values = [];
		$macros = ['host' => [], 'interface' => [], 'inventory' => [], 'user_data' => [], 'usermacros' => [],
			'manualinput' => []
		];

		foreach ($data as $hostid => $script) {
			$texts = [];
			foreach ($script as $fields) {
				$texts = array_merge($texts, array_values($fields));
			}

			$matched_macros = self::extractMacros($texts, $types);

			foreach ($matched_macros['macros'] as $sub_type => $macro_data) {
				foreach ($macro_data as $token => $_data) {
					$macro_values[$hostid][$token] = UNRESOLVED_MACRO_STRING;
					$macros[$sub_type][$hostid][$_data['macro']][] =
						['token' => $token] + array_intersect_key($_data, ['macrofunc' => null]);
				}
			}

			if ($matched_macros['usermacros']) {
				$macros['usermacros'][$hostid] = ['hostids' => [$hostid], 'macros' => $matched_macros['usermacros']];
			}
		}

		$macro_values = self::getHostMacrosByHostId($macros['host'], $macro_values);
		$macro_values = self::getInterfaceMacrosByHostId($macros['interface'], $macro_values);
		$macro_values = self::getInventoryMacrosByHostId($macros['inventory'], $macro_values);
		$macro_values = self::getUserDataMacros($macros['user_data'], $macro_values);
		$macro_values = self::getUserMacros($macros['usermacros'], $macro_values);
		$macro_values = self::getManualInputMacros($macros['manualinput'], $macro_values, $manualinput_values);

		foreach ($data as $hostid => &$scripts) {
			if (array_key_exists($hostid, $macro_values)) {
				foreach ($scripts as &$fields) {
					foreach ($fields as &$value) {
						$value = strtr($value, $macro_values[$hostid]);
					}
					unset($value);
				}
				unset($fields);
			}
		}
		unset($scripts);

		return $data;
	}

	/**
	 * Resolve macros for manual event action scripts. Resolves host<1-9> macros, interface<1-9> macros,
	 * inventory<1-9> macros, user macros, event macros, user data macros and manual input macro.
	 *
	 * @param array  $data                                  Array of unresolved macros.
	 * @param array  $data[<eventid>]                       Array of scripts. Contains script ID as keys.
	 * @param array  $data[<eventid>][<scriptid>]           Script fields to resolve macros for.
	 * @param array  $events                                Array of events.
	 * @param array  $events[<eventid>]                     Event fields.
	 * @param array  $events[<eventid>][hosts]              Array of hosts that created the event.
	 * @param array  $events[<eventid>][hosts][][<hostid>]  Host ID.
	 * @param array  $events[<eventid>][objectid]           Trigger ID.
	 * @param array  $manualinput_values
	 * @param string $manualinput_values[<eventid>]         Value for resolving {MANUALINPUT} macros.
	 *
	 * Example input:
	 *     $data = [
	 *         19 => [
	 *             57 => [
	 *                 'confirmation' => 'Responsible hosts {HOST.HOST1}, {HOST.HOST2}! Navigate to triggers?',
	 *                 'url' => 'http://zabbix/ui/triggers.php?context=host&filter_hostids[]={HOST.ID1}&filter_hostids[]={HOST.ID2}&filter_set=1'
	 *             ],
	 *             61 => [
	 *                 'confirmation' => 'Hello, {USER.FULLNAME}! Execute script?',
	 *                 'manualinput_prompt' => 'Execute script for {HOST.HOST}?'
	 *             ],
	 *             42 => [
	 *                 'manualinput_prompt' => 'Enter port number',
	 *                 'url' => 'http://localhost:{MANUALINPUT}'
	 *             ]
	 *         ]
	 *     ];
	 *
	 *     $events = [
	 *         19 => [
	 *             'hosts' => [
	 *                 ['hostid' => 10084],
	 *                 ['hostid' => 10134]
	 *             ],
	 *             'objectid' => 23507
	 *         ]
	 *     ];
	 *
	 *     $manualinput_values = [
	 *         19 => 8080
	 *     ];
	 *
	 * Output:
	 *     [
	 *         19 => [
	 *             57 => [
	 *                 'confirmation' => 'Responsible hosts Zabbix server, Zabbix PC! Navigate to triggers?',
	 *                 'url' => 'http://zabbix/ui/triggers.php?context=host&filter_hostids[]=10084&filter_hostids[]=10134&filter_set=1'
	 *             ),
	 *             61 => [
	 *                 'confirmation' => 'Hello, Zabbix Administrator! Execute script?',
	 *                 'manualinput_prompt' => 'Execute script for Zabbix server?'
	 *             ],
	 *             42 => [
	 *                 'manualinput_prompt' => 'Enter port number',
	 *                 'url' => 'http://localhost:8080'
	 *             ]
	 *         ]
	 *     ]
	 *
	 * @return array
	 */
	public static function resolveManualEventActionScripts(array $data, array $events,
			array $manualinput_values): array {
		$types = [
			'macros' => [
				'event' => ['{EVENT.ID}', '{EVENT.NAME}', '{EVENT.NSEVERITY}', '{EVENT.SEVERITY}', '{EVENT.STATUS}',
					'{EVENT.VALUE}', '{EVENT.CAUSE.ID}', '{EVENT.CAUSE.NAME}', '{EVENT.CAUSE.NSEVERITY}',
					'{EVENT.CAUSE.SEVERITY}', '{EVENT.CAUSE.STATUS}', '{EVENT.CAUSE.VALUE}'
				],
				'user_data' => ['{USER.ALIAS}', '{USER.USERNAME}', '{USER.FULLNAME}', '{USER.NAME}', '{USER.SURNAME}'],
				'manualinput' => ['{MANUALINPUT}']
			],
			'macros_n' => [
				'host_n' => ['{HOSTNAME}', '{HOST.ID}', '{HOST.HOST}', '{HOST.NAME}'],
				'interface_n' => ['{IPADDRESS}', '{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
				'inventory_n' => array_keys(self::getSupportedHostInventoryMacrosMap())
			],
			'usermacros' => true
		];

		$macro_values = [];
		$macros = ['user_data' => [], 'host_n' => [], 'interface_n' => [], 'inventory_n' => [], 'usermacros' => [],
			'manualinput' => []
		];

		foreach ($data as $eventid => $script) {
			$texts = [];
			foreach ($script as $fields) {
				$texts = array_merge($texts, array_values($fields));
			}

			$matched_macros = self::extractMacros($texts, $types);
			$event = $events[$eventid];

			foreach (['user_data', 'manualinput'] as $sub_type) {
				foreach ($matched_macros['macros'][$sub_type] as $token => $_data) {
					$macro_values[$eventid][$token] = UNRESOLVED_MACRO_STRING;
					$macros[$sub_type][$eventid][$_data['macro']][] =
						['token' => $token] + array_intersect_key($_data, ['macrofunc' => null]);
				}
			}

			foreach ($matched_macros['macros_n'] as $sub_type => $macro_data) {
				foreach ($macro_data as $token => $_data) {
					$macro_values[$eventid][$token] = UNRESOLVED_MACRO_STRING;
					$macros[$sub_type][$event['objectid']][$_data['macro']][$_data['f_num']][] =
						['token' => $token, 'key' => $eventid] + array_intersect_key($_data, ['macrofunc' => null]);
				}
			}

			// Event macros.
			foreach ($matched_macros['macros']['event'] as $token => $_data) {
				switch ($_data['macro']) {
					case 'EVENT.ID':
						$value = $eventid;
						break;

					case 'EVENT.NAME':
						$value = $event['name'];
						break;

					case 'EVENT.NSEVERITY':
						$value = $event['severity'];
						break;

					case 'EVENT.SEVERITY':
						$value = CSeverityHelper::getName($event['severity']);
						break;

					case 'EVENT.STATUS':
						$value = trigger_value2str($event['value']);
						break;

					case 'EVENT.VALUE':
						$value = $event['value'];
						break;

					/*
					 * If event is already cause event, $event['cause'] does not exist or is empty, macros resolve to
					 * *UNKNOWN*.
					 */
					case 'EVENT.CAUSE.ID':
						$value = $event['cause_eventid'] != 0 ? $event['cause_eventid'] : null;
						break;

					case 'EVENT.CAUSE.NAME':
						$value = array_key_exists('cause', $event) && $event['cause'] ? $event['cause']['name'] : null;
						break;

					case 'EVENT.CAUSE.NSEVERITY':
						$value = array_key_exists('cause', $event) && $event['cause']
							? $event['cause']['severity']
							: null;
						break;

					case 'EVENT.CAUSE.SEVERITY':
						$value = array_key_exists('cause', $event) && $event['cause']
							? CSeverityHelper::getName($event['cause']['severity'])
							: null;
						break;

					case 'EVENT.CAUSE.STATUS':
						$value = array_key_exists('cause', $event) && $event['cause']
							? trigger_value2str($event['cause']['value'])
							: null;
						break;

					case 'EVENT.CAUSE.VALUE':
						$value = array_key_exists('cause', $event) && $event['cause'] ? $event['cause']['value'] : null;
						break;
				}

				if ($value !== null) {
					$macro_values[$eventid][$token] = array_key_exists('macrofunc', $_data)
						? CMacroFunction::calcMacrofunc($value, $_data['macrofunc'])
						: $value;
				}
				else {
					$macro_values[$eventid][$token] = UNRESOLVED_MACRO_STRING;
				}
			}

			if ($matched_macros['usermacros']) {
				$macros['usermacros'][$eventid] = [
					'hostids' => array_column($event['hosts'], 'hostid'),
					'macros' => $matched_macros['usermacros']
				];
			}
		}

		$macro_values = self::getUserDataMacros($macros['user_data'], $macro_values);

		$trigger_hosts_by_f_num = self::getExpressionHosts(
			array_keys($macros['host_n'] + $macros['interface_n'] + $macros['inventory_n'])
		);
		$macro_values = self::getHostNMacros($macros['host_n'], $macro_values, $trigger_hosts_by_f_num);
		$macro_values = self::getInterfaceNMacros($macros['interface_n'], $macro_values, $trigger_hosts_by_f_num);
		$macro_values = self::getInventoryNMacros($macros['inventory_n'], $macro_values, $trigger_hosts_by_f_num);
		$macro_values = self::getUserMacros($macros['usermacros'], $macro_values);
		$macro_values = self::getManualInputMacros($macros['manualinput'], $macro_values, $manualinput_values);

		foreach ($data as $eventid => &$scripts) {
			if (array_key_exists($eventid, $macro_values)) {
				foreach ($scripts as &$fields) {
					foreach ($fields as &$value) {
						$value = strtr($value, $macro_values[$eventid]);
					}
					unset($value);
				}
				unset($fields);
			}
		}
		unset($scripts);

		return $data;
	}
}
