<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * Class containing common methods for operations with triggers.
 */
abstract class CTriggerGeneral extends CApiService {

	/**
	 * @abstract
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	abstract public function get(array $options = []);

	/**
	 * Prepares and returns an array of child triggers, inherited from triggers $tpl_triggers on the given hosts.
	 *
	 * @param array  $tpl_triggers
	 * @param string $tpl_triggers[<tnum>]['triggerid']
	 */
	private function prepareInheritedTriggers(array $tpl_triggers, array $hostids = null, array &$ins_triggers = null,
			array &$upd_triggers = null, array &$db_triggers = null) {
		$ins_triggers = [];
		$upd_triggers = [];
		$db_triggers = [];

		$result = DBselect(
			'SELECT DISTINCT t.triggerid,h.hostid'.
			' FROM triggers t,functions f,items i,hosts h'.
			' WHERE t.triggerid=f.triggerid'.
				' AND f.itemid=i.itemid'.
				' AND i.hostid=h.hostid'.
				' AND '.dbConditionInt('t.triggerid', zbx_objectValues($tpl_triggers, 'triggerid')).
				' AND '.dbConditionInt('h.status', [HOST_STATUS_TEMPLATE])
		);

		$tpl_hostids_by_triggerid = [];
		$tpl_hostids = [];

		while ($row = DBfetch($result)) {
			$tpl_hostids_by_triggerid[$row['triggerid']][] = $row['hostid'];
			$tpl_hostids[$row['hostid']] = true;
		}

		// Unset host-level triggers.
		foreach ($tpl_triggers as $tnum => $tpl_trigger) {
			if (!array_key_exists($tpl_trigger['triggerid'], $tpl_hostids_by_triggerid)) {
				unset($tpl_triggers[$tnum]);
			}
		}

		if (!$tpl_triggers) {
			// Nothing to inherit, just exit.
			return;
		}

		$hosts_by_tpl_hostid = self::getLinkedHosts(array_keys($tpl_hostids), $hostids);
		$chd_triggers_tpl = $this->getHostTriggersByTemplateId(array_keys($tpl_hostids_by_triggerid), $hostids);
		$tpl_triggers_by_description = [];

		// Preparing list of missing triggers on linked hosts.
		foreach ($tpl_triggers as $tpl_trigger) {
			$hostids = [];

			foreach ($tpl_hostids_by_triggerid[$tpl_trigger['triggerid']] as $tpl_hostid) {
				if (array_key_exists($tpl_hostid, $hosts_by_tpl_hostid)) {
					foreach ($hosts_by_tpl_hostid[$tpl_hostid] as $host) {
						if (array_key_exists($host['hostid'], $chd_triggers_tpl)
								&& array_key_exists($tpl_trigger['triggerid'], $chd_triggers_tpl[$host['hostid']])) {
							continue;
						}

						$hostids[$host['hostid']] = true;
					}
				}
			}

			if ($hostids) {
				$tpl_triggers_by_description[$tpl_trigger['description']][] = [
					'triggerid' => $tpl_trigger['triggerid'],
					'expression' => $tpl_trigger['expression'],
					'recovery_mode' => $tpl_trigger['recovery_mode'],
					'recovery_expression' => $tpl_trigger['recovery_expression'],
					'hostids' => $hostids
				];
			}
		}

		$chd_triggers_all = array_replace_recursive($chd_triggers_tpl,
			$this->getHostTriggersByDescription($tpl_triggers_by_description)
		);

		$expression_parser = new CExpressionParser([
			'usermacros' => true,
			'lldmacros' => $this instanceof CTriggerPrototype
		]);

		$recovery_expression_parser = new CExpressionParser([
			'usermacros' => true,
			'lldmacros' => $this instanceof CTriggerPrototype
		]);

		// List of triggers to check for duplicates. Grouped by description.
		$descriptions = [];
		$triggerids = [];

		$output = ['url', 'status', 'priority', 'comments', 'type', 'correlation_mode', 'correlation_tag',
			'manual_close', 'opdata', 'event_name'
		];
		if ($this instanceof CTriggerPrototype) {
			$output[] = 'discover';
		}

		$db_tpl_triggers = DB::select('triggers', [
			'output' => $output,
			'triggerids' => array_keys($tpl_hostids_by_triggerid),
			'preservekeys' => true
		]);

		foreach ($tpl_triggers as $tpl_trigger) {
			$db_tpl_trigger = $db_tpl_triggers[$tpl_trigger['triggerid']];

			$tpl_hostid = $tpl_hostids_by_triggerid[$tpl_trigger['triggerid']][0];

			// expression: func(/template/item) => func(/host/item)
			if ($expression_parser->parse($tpl_trigger['expression']) != CParser::PARSE_SUCCESS) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'expression', $expression_parser->getError()
				));
			}

			// recovery_expression: func(/template/item) => func(/host/item)
			if ($tpl_trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
				if ($recovery_expression_parser->parse($tpl_trigger['recovery_expression']) != CParser::PARSE_SUCCESS) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'recovery_expression', $recovery_expression_parser->getError()
					));
				}
			}

			$new_trigger = $tpl_trigger;
			$new_trigger['uuid'] = '';
			unset($new_trigger['triggerid'], $new_trigger['templateid']);

			if (array_key_exists($tpl_hostid, $hosts_by_tpl_hostid)) {
				foreach ($hosts_by_tpl_hostid[$tpl_hostid] as $host) {
					$new_trigger['expression'] = $tpl_trigger['expression'];
					$hist_functions = $expression_parser->getResult()->getTokensOfTypes(
						[CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
					);
					$hist_function = end($hist_functions);
					do {
						$query_parameter = $hist_function['data']['parameters'][0];
						$new_trigger['expression'] = substr_replace($new_trigger['expression'],
							'/'.$host['host'].'/'.$query_parameter['data']['item'], $query_parameter['pos'],
							$query_parameter['length']
						);
					}
					while ($hist_function = prev($hist_functions));

					if ($tpl_trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
						$new_trigger['recovery_expression'] = $tpl_trigger['recovery_expression'];
						$hist_functions = $recovery_expression_parser->getResult()->getTokensOfTypes(
							[CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
						);
						$hist_function = end($hist_functions);
						do {
							$query_parameter = $hist_function['data']['parameters'][0];
							$new_trigger['recovery_expression'] = substr_replace($new_trigger['recovery_expression'],
								'/'.$host['host'].'/'.$query_parameter['data']['item'], $query_parameter['pos'],
								$query_parameter['length']
							);
						}
						while ($hist_function = prev($hist_functions));
					}

					if (array_key_exists($host['hostid'], $chd_triggers_all)
							&& array_key_exists($tpl_trigger['triggerid'], $chd_triggers_all[$host['hostid']])) {
						$chd_trigger = $chd_triggers_all[$host['hostid']][$tpl_trigger['triggerid']];

						$upd_triggers[] = $new_trigger + [
							'triggerid' => $chd_trigger['triggerid'],
							'templateid' => $tpl_trigger['triggerid']
						];
						$db_triggers[$chd_trigger['triggerid']] = $chd_trigger;
						$triggerids[] = $chd_trigger['triggerid'];

						$check_duplicates = ($chd_trigger['description'] !== $new_trigger['description']
							|| $chd_trigger['expression'] !== $new_trigger['expression']
							|| $chd_trigger['recovery_expression'] !== $new_trigger['recovery_expression']);
					}
					else {
						$ins_triggers[] = $new_trigger + $db_tpl_trigger + ['templateid' => $tpl_trigger['triggerid']];
						$check_duplicates = true;
					}

					if ($check_duplicates) {
						$descriptions[$new_trigger['description']][] = [
							'expression' => $new_trigger['expression'],
							'recovery_expression' => $new_trigger['recovery_expression'],
							'host' => [
								'hostid' => $host['hostid'],
								'status' => $host['status']
							]
						];
					}
				}
			}
		}

		if ($triggerids) {
			// Add trigger tags.
			$result = DBselect(
				'SELECT tt.triggertagid,tt.triggerid,tt.tag,tt.value'.
				' FROM trigger_tag tt'.
				' WHERE '.dbConditionInt('tt.triggerid', $triggerids)
			);

			$trigger_tags = [];

			while ($row = DBfetch($result)) {
				$trigger_tags[$row['triggerid']][] = [
					'triggertagid' => $row['triggertagid'],
					'tag' => $row['tag'],
					'value' => $row['value']
				];
			}

			foreach ($db_triggers as &$db_trigger) {
				$db_trigger['tags'] = array_key_exists($db_trigger['triggerid'], $trigger_tags)
					? $trigger_tags[$db_trigger['triggerid']]
					: [];
			}
			unset($db_trigger);

			// Add discovery rule IDs.
			if ($this instanceof CTriggerPrototype) {
				$result = DBselect(
					'SELECT id.parent_itemid,f.triggerid'.
						' FROM item_discovery id,functions f'.
						' WHERE '.dbConditionInt('f.triggerid', $triggerids).
						' AND f.itemid=id.itemid'
				);

				$drule_by_triggerid = [];

				while ($row = DBfetch($result)) {
					$drule_by_triggerid[$row['triggerid']] = $row['parent_itemid'];
				}

				foreach ($db_triggers as &$db_trigger) {
					$db_trigger['discoveryRule']['itemid'] = $drule_by_triggerid[$db_trigger['triggerid']];
				}
				unset($db_trigger);
			}
		}

		$this->checkDuplicates($descriptions);
	}

	/**
	 * Returns list of linked hosts.
	 *
	 * Output format:
	 *   [
	 *     <tpl_hostid> => [
	 *       [
	 *         'hostid' => <hostid>,
	 *         'host' => <host>
	 *       ],
	 *       ...
	 *     ],
	 *     ...
	 *   ]
	 *
	 * @param array  $tpl_hostids
	 * @param array  $hostids      The function will return a list of all linked hosts if no hostids are specified.
	 *
	 * @return array
	 */
	private static function getLinkedHosts(array $tpl_hostids, array $hostids = null) {
		// Fetch all child hosts and templates
		$sql = 'SELECT ht.hostid,ht.templateid,h.host,h.status'.
			' FROM hosts_templates ht,hosts h'.
			' WHERE ht.hostid=h.hostid'.
				' AND '.dbConditionInt('ht.templateid', $tpl_hostids).
				' AND '.dbConditionInt('h.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]);
		if ($hostids !== null) {
			$sql .= ' AND '.dbConditionInt('ht.hostid', $hostids);
		}
		$result = DBselect($sql);

		$hosts_by_tpl_hostid = [];

		while ($row = DBfetch($result)) {
			$hosts_by_tpl_hostid[$row['templateid']][] = [
				'hostid' => $row['hostid'],
				'host' => $row['host'],
				'status' => $row['status']
			];
		}

		return $hosts_by_tpl_hostid;
	}

	/**
	 * Returns list of already linked triggers.
	 *
	 * Output format:
	 *   [
	 *     <hostid> => [
	 *       <tpl_triggerid> => ['triggerid' => <triggerid>],
	 *       ...
	 *     ],
	 *     ...
	 *   ]
	 *
	 * @param array  $tpl_triggerids
	 * @param array  $hostids         The function will return a list of all linked triggers if no hosts are specified.
	 *
	 * @return array
	 */
	private function getHostTriggersByTemplateId(array $tpl_triggerids, array $hostids = null) {
		$output = 't.triggerid,t.expression,t.description,t.url,t.status,t.priority,t.comments,t.type,t.recovery_mode,'.
			't.recovery_expression,t.correlation_mode,t.correlation_tag,t.manual_close,t.opdata,t.templateid,'.
			't.event_name,i.hostid';
		if ($this instanceof CTriggerPrototype) {
			$output .= ',t.discover';
		}

		// Preparing list of triggers by templateid.
		$sql = 'SELECT DISTINCT '.$output.
			' FROM triggers t,functions f,items i'.
			' WHERE t.triggerid=f.triggerid'.
				' AND f.itemid=i.itemid'.
				' AND '.dbConditionInt('t.templateid', $tpl_triggerids);
		if ($hostids !== null) {
			$sql .= ' AND '.dbConditionInt('i.hostid', $hostids);
		}

		$chd_triggers = DBfetchArray(DBselect($sql));
		$chd_triggers = CMacrosResolverHelper::resolveTriggerExpressions($chd_triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		$chd_triggers_tpl = [];

		foreach ($chd_triggers as $chd_trigger) {
			$hostid = $chd_trigger['hostid'];
			unset($chd_trigger['hostid']);

			$chd_triggers_tpl[$hostid][$chd_trigger['templateid']] = $chd_trigger;
		}

		return $chd_triggers_tpl;
	}

	/**
	 * Returns list of not inherited triggers with same name and expression.
	 *
	 * Output format:
	 *   [
	 *     <hostid> => [
	 *       <tpl_triggerid> => ['triggerid' => <triggerid>],
	 *       ...
	 *     ],
	 *     ...
	 *   ]
	 *
	 * @param array $tpl_triggers_by_description  The list of hostids, grouped by trigger description and expression.
	 *
	 * @return array
	 */
	private function getHostTriggersByDescription(array $tpl_triggers_by_description) {
		$chd_triggers_description = [];

		$expression_parser = new CExpressionParser([
			'usermacros' => true,
			'lldmacros' => $this instanceof CTriggerPrototype
		]);

		$recovery_expression_parser = new CExpressionParser([
			'usermacros' => true,
			'lldmacros' => $this instanceof CTriggerPrototype
		]);

		$output = 't.triggerid,t.expression,t.description,t.url,t.status,t.priority,t.comments,t.type,t.recovery_mode,'.
			't.recovery_expression,t.correlation_mode,t.correlation_tag,t.manual_close,t.opdata,t.event_name,i.hostid,'.
			'h.host';
		if ($this instanceof CTriggerPrototype) {
			$output .= ',t.discover';
		}

		foreach ($tpl_triggers_by_description as $description => $tpl_triggers) {
			$hostids = [];

			foreach ($tpl_triggers as $tpl_trigger) {
				$hostids += $tpl_trigger['hostids'];
			}

			$chd_triggers = DBfetchArray(DBselect(
				'SELECT DISTINCT '.$output.
				' FROM triggers t,functions f,items i,hosts h'.
				' WHERE t.triggerid=f.triggerid'.
					' AND f.itemid=i.itemid'.
					' AND i.hostid=h.hostid'.
					' AND '.dbConditionString('t.description', [$description]).
					' AND '.dbConditionInt('i.hostid', array_keys($hostids))
			));

			$chd_triggers = CMacrosResolverHelper::resolveTriggerExpressions($chd_triggers,
				['sources' => ['expression', 'recovery_expression']]
			);

			foreach ($tpl_triggers as $tpl_trigger) {
				// expression: func(/template/item) => func(/host/item)
				if ($expression_parser->parse($tpl_trigger['expression']) != CParser::PARSE_SUCCESS) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'expression', $expression_parser->getError()
					));
				}

				// recovery_expression: func(/template/item) => func(/host/item)
				if ($tpl_trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
					if ($recovery_expression_parser->parse($tpl_trigger['recovery_expression']) !=
							CParser::PARSE_SUCCESS) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
							'recovery_expression', $recovery_expression_parser->getError()
						));
					}
				}

				foreach ($chd_triggers as $chd_trigger) {
					if (!array_key_exists($chd_trigger['hostid'], $tpl_trigger['hostids'])) {
						continue;
					}

					if ($chd_trigger['recovery_mode'] != $tpl_trigger['recovery_mode']) {
						continue;
					}

					// Replace template name in /host/key reference to target host name.
					$expression = $tpl_trigger['expression'];
					$hist_functions = $expression_parser->getResult()->getTokensOfTypes(
						[CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
					);
					$hist_function = end($hist_functions);
					do {
						$query_parameter = $hist_function['data']['parameters'][0];
						$expression = substr_replace($expression,
							'/'.$chd_trigger['host'].'/'.$query_parameter['data']['item'], $query_parameter['pos'],
							$query_parameter['length']
						);
					}
					while ($hist_function = prev($hist_functions));

					if ($chd_trigger['expression'] !== $expression) {
						continue;
					}

					if ($tpl_trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
						$recovery_expression = $tpl_trigger['recovery_expression'];
						$hist_functions = $recovery_expression_parser->getResult()->getTokensOfTypes(
							[CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
						);
						$hist_function = end($hist_functions);
						do {
							$query_parameter = $hist_function['data']['parameters'][0];
							$recovery_expression = substr_replace($recovery_expression,
								'/'.$chd_trigger['host'].'/'.$query_parameter['data']['item'], $query_parameter['pos'],
								$query_parameter['length']
							);
						}
						while ($hist_function = prev($hist_functions));

						if ($chd_trigger['recovery_expression'] !== $recovery_expression) {
							continue;
						}
					}

					$hostid = $chd_trigger['hostid'];
					unset($chd_trigger['hostid'], $chd_trigger['host']);
					$chd_triggers_description[$hostid][$tpl_trigger['triggerid']] = $chd_trigger + ['templateid' => 0];
				}
			}
		}

		return $chd_triggers_description;
	}

	/**
	 * Updates children of triggers on the given hosts and propagates the inheritance to all child hosts.
	 * All of the child triggers that became obsolete will be deleted if the given triggers were assigned to a different
	 * template or host.
	 *
	 * @param array  $triggers
	 * @param string $triggers[]['triggerid']
	 * @param string $triggers[]['description']
	 * @param string $triggers[]['expression']
	 * @param int    $triggers[]['recovery mode']
	 * @param string $triggers[]['recovery_expression']
	 * @param array  $hostids
	 */
	protected function inherit(array $triggers, array $hostids = null) {
		$this->prepareInheritedTriggers($triggers, $hostids, $ins_triggers, $upd_triggers, $db_triggers);

		if ($ins_triggers) {
			$this->createReal($ins_triggers, true);
		}

		if ($upd_triggers) {
			$this->updateReal($upd_triggers, $db_triggers, true);
		}

		if ($ins_triggers || $upd_triggers) {
			$this->inherit(array_merge($ins_triggers + $upd_triggers));
		}
	}

	/**
	 * Populate an array by "hostid" keys.
	 *
	 * @param array  $descriptions
	 * @param string $descriptions[<description>][]['expression']
	 *
	 * @throws APIException  If host or template does not exists.
	 *
	 * @return array
	 */
	protected function populateHostIds($descriptions) {
		$expression_parser = new CExpressionParser([
			'usermacros' => true,
			'lldmacros' => $this instanceof CTriggerPrototype
		]);

		$hosts = [];

		foreach ($descriptions as $description => $triggers) {
			foreach ($triggers as $index => $trigger) {
				$expression_parser->parse($trigger['expression']);
				$hosts[$expression_parser->getResult()->getHosts()[0]][$description][] = $index;
			}
		}

		$db_hosts = DBselect(
			'SELECT h.hostid,h.host,h.status'.
			' FROM hosts h'.
			' WHERE '.dbConditionString('h.host', array_keys($hosts)).
				' AND '.dbConditionInt('h.status',
					[HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE]
				)
		);

		while ($db_host = DBfetch($db_hosts)) {
			foreach ($hosts[$db_host['host']] as $description => $indexes) {
				foreach ($indexes as $index) {
					$descriptions[$description][$index]['host'] = [
						'hostid' => $db_host['hostid'],
						'status' => $db_host['status']
					];
				}
			}
			unset($hosts[$db_host['host']]);
		}

		if ($hosts) {
			$error_wrong_host = ($this instanceof CTrigger)
				? _('Incorrect trigger expression. Host "%1$s" does not exist or you have no access to this host.')
				: _('Incorrect trigger prototype expression. Host "%1$s" does not exist or you have no access to this host.');
			self::exception(ZBX_API_ERROR_PARAMETERS, _params($error_wrong_host, [key($hosts)]));
		}

		return $descriptions;
	}

	/**
	 * Checks triggers for duplicates.
	 *
	 * @param array  $descriptions
	 * @param string $descriptions[<description>][]['expression']
	 * @param string $descriptions[<description>][]['recovery_expression']
	 * @param string $descriptions[<description>][]['hostid']
	 *
	 * @throws APIException if at least one trigger exists
	 */
	protected function checkDuplicates(array $descriptions) {
		foreach ($descriptions as $description => $triggers) {
			$hostids = [];
			$expressions = [];

			foreach ($triggers as $trigger) {
				$hostids[$trigger['host']['hostid']] = true;
				$expressions[$trigger['expression']][$trigger['recovery_expression']] = $trigger['host']['hostid'];
			}

			$db_triggers = DBfetchArray(DBselect(
				'SELECT DISTINCT t.expression,t.recovery_expression'.
				' FROM triggers t,functions f,items i,hosts h'.
				' WHERE t.triggerid=f.triggerid'.
					' AND f.itemid=i.itemid'.
					' AND i.hostid=h.hostid'.
					' AND '.dbConditionString('t.description', [$description]).
					' AND '.dbConditionInt('i.hostid', array_keys($hostids))
			));

			$db_triggers = CMacrosResolverHelper::resolveTriggerExpressions($db_triggers,
				['sources' => ['expression', 'recovery_expression']]
			);

			foreach ($db_triggers as $db_trigger) {
				$expression = $db_trigger['expression'];
				$recovery_expression = $db_trigger['recovery_expression'];

				if (array_key_exists($expression, $expressions)
						&& array_key_exists($recovery_expression, $expressions[$expression])) {
					$error_already_exists = ($this instanceof CTrigger)
						? _('Trigger "%1$s" already exists on "%2$s".')
						: _('Trigger prototype "%1$s" already exists on "%2$s".');

					$db_hosts = DB::select('hosts', [
						'output' => ['name'],
						'hostids' => $expressions[$expression][$recovery_expression]
					]);

					self::exception(ZBX_API_ERROR_PARAMETERS,
						_params($error_already_exists, [$description, $db_hosts[0]['name']])
					);
				}
			}
		}
	}

	/**
	 * Check that only triggers on templates have UUID. Add UUID to all triggers on templates, if it does not exists.
	 *
	 * @param array $triggers_to_create
	 * @param array $descriptions
	 *
	 * @throws APIException
	 */
	protected function checkAndAddUuid(array &$triggers_to_create, array $descriptions): void {
		foreach ($descriptions as $triggers) {
			foreach ($triggers as $trigger) {
				if ($trigger['host']['status'] != HOST_STATUS_TEMPLATE && $trigger['uuid'] !== null) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', '/'.($trigger['index'] + 1),
							_s('unexpected parameter "%1$s"', 'uuid')
						)
					);
				}

				if ($trigger['host']['status'] == HOST_STATUS_TEMPLATE && $trigger['uuid'] === null) {
					$triggers_to_create[$trigger['index']]['uuid'] = generateUuidV4();
				}
			}
		}

		$db_uuid = DB::select('triggers', [
			'output' => ['uuid'],
			'filter' => ['uuid' => array_column($triggers_to_create, 'uuid')],
			'limit' => 1
		]);

		if ($db_uuid) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Entry with UUID "%1$s" already exists.', $db_uuid[0]['uuid'])
			);
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$triggerids = array_keys($result);

		// adding groups
		$this->addRelatedGroups($options, $result, 'selectGroups');
		$this->addRelatedGroups($options, $result, 'selectHostGroups');
		$this->addRelatedGroups($options, $result, 'selectTemplateGroups');

		// adding hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			$res = DBselect(
				'SELECT f.triggerid,i.hostid'.
					' FROM functions f,items i'.
					' WHERE '.dbConditionInt('f.triggerid', $triggerids).
					' AND f.itemid=i.itemid'
			);
			$relationMap = new CRelationMap();
			while ($relation = DBfetch($res)) {
				$relationMap->addRelation($relation['triggerid'], $relation['hostid']);
			}

			$hosts = API::Host()->get([
				'output' => $options['selectHosts'],
				'hostids' => $relationMap->getRelatedIds(),
				'templated_hosts' => true,
				'nopermissions' => true,
				'preservekeys' => true
			]);
			if (!is_null($options['limitSelects'])) {
				order_result($hosts, 'host');
			}
			$result = $relationMap->mapMany($result, $hosts, 'hosts', $options['limitSelects']);
		}

		// adding functions
		if ($options['selectFunctions'] !== null && $options['selectFunctions'] != API_OUTPUT_COUNT) {
			$functions = API::getApiService()->select('functions', [
				'output' => $this->outputExtend($options['selectFunctions'], ['triggerid', 'functionid']),
				'filter' => ['triggerid' => $triggerids],
				'preservekeys' => true
			]);

			// Rename column 'name' to 'function'.
			$function = reset($functions);
			if ($function && array_key_exists('name', $function)) {
				$functions = CArrayHelper::renameObjectsKeys($functions, ['name' => 'function']);
			}

			$relationMap = $this->createRelationMap($functions, 'triggerid', 'functionid');

			$functions = $this->unsetExtraFields($functions, ['triggerid', 'functionid'], $options['selectFunctions']);
			$result = $relationMap->mapMany($result, $functions, 'functions');
		}

		// Adding trigger tags.
		if ($options['selectTags'] !== null && $options['selectTags'] != API_OUTPUT_COUNT) {
			$tags = API::getApiService()->select('trigger_tag', [
				'output' => $this->outputExtend($options['selectTags'], ['triggerid']),
				'filter' => ['triggerid' => $triggerids],
				'preservekeys' => true
			]);

			$relationMap = $this->createRelationMap($tags, 'triggerid', 'triggertagid');
			$tags = $this->unsetExtraFields($tags, ['triggertagid', 'triggerid'], []);
			$result = $relationMap->mapMany($result, $tags, 'tags');
		}

		return $result;
	}

	/**
	 * Adds related host or template groups requested by "select*" options to the resulting object set.
	 *
	 * @param array  $options [IN] Original input options.
	 * @param array  $result  [IN/OUT] Result output.
	 * @param string $option  [IN] Possible values:
	 *                               - "selectGroups" (deprecated);
	 *                               - "selectHostGroups";
	 *                               - "selectTemplateGroups".
	 */
	private function addRelatedGroups(array $options, array &$result, string $option): void {
		if ($options[$option] === null || $options[$option] === API_OUTPUT_COUNT) {
			return;
		}

		$res = DBselect(
			'SELECT f.triggerid,hg.groupid'.
			' FROM functions f,items i,hosts_groups hg'.
			' WHERE '.dbConditionInt('f.triggerid', array_keys($result)).
				' AND f.itemid=i.itemid'.
				' AND i.hostid=hg.hostid'
		);
		$relationMap = new CRelationMap();
		while ($relation = DBfetch($res)) {
			$relationMap->addRelation($relation['triggerid'], $relation['groupid']);
		}

		switch ($option) {
			case 'selectGroups':
				$output_tag = 'groups';
				$entities = [API::HostGroup(), API::TemplateGroup()];
				break;

			case 'selectHostGroups':
				$entities = [API::HostGroup()];
				$output_tag = 'hostgroups';
				break;

			case 'selectTemplateGroups':
				$entities = [API::TemplateGroup()];
				$output_tag = 'templategroups';
				break;
		}

		$groups = [];
		foreach ($entities as $entity) {
			$groups += $entity->get([
				'output' => $options[$option],
				'groupids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
		}

		$result = $relationMap->mapMany($result, $groups, $output_tag);
	}

	/**
	 * Validate integrity of trigger recovery properties.
	 *
	 * @static
	 *
	 * @param array  $trigger
	 * @param int    $trigger['recovery_mode']
	 * @param string $trigger['recovery_expression']
	 *
	 * @throws APIException if validation failed.
	 */
	private static function checkTriggerRecoveryMode(array $trigger) {
		if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
			if ($trigger['recovery_expression'] === '') {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'recovery_expression', _('cannot be empty'))
				);
			}
		}
		elseif ($trigger['recovery_expression'] !== '') {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect value for field "%1$s": %2$s.', 'recovery_expression', _('should be empty'))
			);
		}
	}

	/**
	 * Validate trigger correlation mode and related properties.
	 *
	 * @static
	 *
	 * @param array  $trigger
	 * @param int    $trigger['correlation_mode']
	 * @param string $trigger['correlation_tag']
	 * @param int    $trigger['recovery_mode']
	 *
	 * @throws APIException if validation failed.
	 */
	private static function checkTriggerCorrelationMode(array $trigger) {
		if ($trigger['correlation_mode'] == ZBX_TRIGGER_CORRELATION_TAG) {
			if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_NONE) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'correlation_mode', _s('unexpected value "%1$s"', $trigger['correlation_mode'])
				));
			}

			if ($trigger['correlation_tag'] === '') {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'correlation_tag', _('cannot be empty'))
				);
			}
		}
		elseif ($trigger['correlation_tag'] !== '') {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect value for field "%1$s": %2$s.', 'correlation_tag', _('should be empty'))
			);
		}
	}

	/**
	 * Validate trigger to be created.
	 *
	 * @param array  $triggers                                   [IN/OUT]
	 * @param array  $triggers[]['description']                  [IN]
	 * @param string $triggers[]['expression']                   [IN]
	 * @param string $triggers[]['opdata']                       [IN]
	 * @param string $triggers[]['event_name']                   [IN]
	 * @param string $triggers[]['comments']                     [IN] (optional)
	 * @param int    $triggers[]['priority']                     [IN] (optional)
	 * @param int    $triggers[]['status']                       [IN] (optional)
	 * @param int    $triggers[]['type']                         [IN] (optional)
	 * @param string $triggers[]['url']                          [IN] (optional)
	 * @param int    $triggers[]['recovery_mode']                [IN/OUT] (optional)
	 * @param string $triggers[]['recovery_expression']          [IN/OUT] (optional)
	 * @param int    $triggers[]['correlation_mode']             [IN/OUT] (optional)
	 * @param string $triggers[]['correlation_tag']              [IN/OUT] (optional)
	 * @param int    $triggers[]['manual_close']                 [IN] (optional)
	 * @param int    $triggers[]['discover']                     [IN] (optional) for trigger prototypes only
	 * @param array  $triggers[]['tags']                         [IN] (optional)
	 * @param string $triggers[]['tags'][]['tag']                [IN]
	 * @param string $triggers[]['tags'][]['value']              [IN/OUT] (optional)
	 * @param array  $triggers[]['dependencies']                 [IN] (optional)
	 * @param string $triggers[]['dependencies'][]['triggerid']  [IN]
	 *
	 * @throws APIException if validation failed.
	 */
	protected function validateCreate(array &$triggers) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['uuid'], ['description', 'expression']], 'fields' => [
			'uuid' =>					['type' => API_UUID],
			'description' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('triggers', 'description')],
			'expression' =>				['type' => API_TRIGGER_EXPRESSION, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_LLD_MACRO],
			'event_name' =>				['type' => API_EVENT_NAME, 'length' => DB::getFieldLength('triggers', 'event_name')],
			'opdata' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('triggers', 'opdata')],
			'comments' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('triggers', 'comments')],
			'priority' =>				['type' => API_INT32, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1))],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [TRIGGER_STATUS_ENABLED, TRIGGER_STATUS_DISABLED])],
			'type' =>					['type' => API_INT32, 'in' => implode(',', [TRIGGER_MULT_EVENT_DISABLED, TRIGGER_MULT_EVENT_ENABLED])],
			'url' =>					['type' => API_URL, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('triggers', 'url')],
			'recovery_mode' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_RECOVERY_MODE_EXPRESSION, ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION, ZBX_RECOVERY_MODE_NONE]), 'default' => DB::getDefault('triggers', 'recovery_mode')],
			'recovery_expression' =>	['type' => API_TRIGGER_EXPRESSION, 'flags' => API_ALLOW_LLD_MACRO, 'default' => DB::getDefault('triggers', 'recovery_expression')],
			'correlation_mode' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_TRIGGER_CORRELATION_NONE, ZBX_TRIGGER_CORRELATION_TAG]), 'default' => DB::getDefault('triggers', 'correlation_mode')],
			'correlation_tag' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('triggers', 'correlation_tag'), 'default' => DB::getDefault('triggers', 'correlation_tag')],
			'manual_close' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED, ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED])],
			'tags' =>					['type' => API_OBJECTS, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('trigger_tag', 'tag')],
				'value' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('trigger_tag', 'value'), 'default' => DB::getDefault('trigger_tag', 'value')]
			]],
			'dependencies' =>			['type' => API_OBJECTS, 'uniq' => [['triggerid']], 'fields'=> [
				'triggerid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];
		if ($this instanceof CTriggerPrototype) {
			$api_input_rules['fields']['discover'] = ['type' => API_INT32, 'in' => implode(',', [TRIGGER_DISCOVER, TRIGGER_NO_DISCOVER])];
		}
		else {
			$api_input_rules['fields']['expression']['flags'] &= ~API_ALLOW_LLD_MACRO;
			$api_input_rules['fields']['recovery_expression']['flags'] &= ~API_ALLOW_LLD_MACRO;
		}
		if (!CApiInputValidator::validate($api_input_rules, $triggers, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$descriptions = [];
		foreach ($triggers as $index => $trigger) {
			self::checkTriggerRecoveryMode($trigger);
			self::checkTriggerCorrelationMode($trigger);

			$descriptions[$trigger['description']][] = [
				'index' => $index,
				'uuid' => array_key_exists('uuid', $trigger) ? $trigger['uuid'] : null,
				'expression' => $trigger['expression'],
				'recovery_expression' => $trigger['recovery_expression']
			];
		}

		$descriptions = $this->populateHostIds($descriptions);
		$this->checkAndAddUuid($triggers, $descriptions);
		$this->checkDuplicates($descriptions);
		$this->checkDependencies($triggers);
	}

	/**
	 * Validate trigger to be updated.
	 *
	 * @param array  $triggers                                   [IN/OUT]
	 * @param array  $triggers[]['triggerid']                    [IN]
	 * @param array  $triggers[]['description']                  [IN/OUT] (optional)
	 * @param string $triggers[]['expression']                   [IN/OUT] (optional)
	 * @param string $triggers[]['event_name']                   [IN] (optional)
	 * @param string $triggers[]['opdata']                       [IN] (optional)
	 * @param string $triggers[]['comments']                     [IN] (optional)
	 * @param int    $triggers[]['priority']                     [IN] (optional)
	 * @param int    $triggers[]['status']                       [IN] (optional)
	 * @param int    $triggers[]['type']                         [IN] (optional)
	 * @param string $triggers[]['url']                          [IN] (optional)
	 * @param int    $triggers[]['recovery_mode']                [IN/OUT] (optional)
	 * @param string $triggers[]['recovery_expression']          [IN/OUT] (optional)
	 * @param int    $triggers[]['correlation_mode']             [IN/OUT] (optional)
	 * @param string $triggers[]['correlation_tag']              [IN/OUT] (optional)
	 * @param int    $triggers[]['manual_close']                 [IN] (optional)
	 * @param int    $triggers[]['discover']                     [IN] (optional) for trigger prototypes only
	 * @param array  $triggers[]['tags']                         [IN] (optional)
	 * @param string $triggers[]['tags'][]['tag']                [IN]
	 * @param string $triggers[]['tags'][]['value']              [IN/OUT] (optional)
	 * @param array  $triggers[]['dependencies']                 [IN] (optional)
	 * @param string $triggers[]['dependencies'][]['triggerid']  [IN]
	 * @param array  $db_triggers                                [OUT]
	 * @param array  $db_triggers[<tnum>]['triggerid']           [OUT]
	 * @param array  $db_triggers[<tnum>]['description']         [OUT]
	 * @param string $db_triggers[<tnum>]['expression']          [OUT]
	 * @param string $db_triggers[<tnum>]['event_name']          [OUT]
	 * @param string $db_triggers[<tnum>]['opdata']              [OUT]
	 * @param int    $db_triggers[<tnum>]['recovery_mode']       [OUT]
	 * @param string $db_triggers[<tnum>]['recovery_expression'] [OUT]
	 * @param string $db_triggers[<tnum>]['url']                 [OUT]
	 * @param int    $db_triggers[<tnum>]['status']              [OUT]
	 * @param int    $db_triggers[<tnum>]['discover']            [OUT]
	 * @param int    $db_triggers[<tnum>]['priority']            [OUT]
	 * @param string $db_triggers[<tnum>]['comments']            [OUT]
	 * @param int    $db_triggers[<tnum>]['type']                [OUT]
	 * @param string $db_triggers[<tnum>]['templateid']          [OUT]
	 * @param int    $db_triggers[<tnum>]['correlation_mode']    [OUT]
	 * @param string $db_triggers[<tnum>]['correlation_tag']     [OUT]
	 * @param int    $db_triggers[<tnum>]['discover']            [OUT] for trigger prototypes only
	 *
	 * @throws APIException if validation failed.
	 */
	protected function validateUpdate(array &$triggers, array &$db_triggers = null) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['description', 'expression']], 'fields' => [
			'triggerid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
			'description' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('triggers', 'description')],
			'expression' =>				['type' => API_TRIGGER_EXPRESSION, 'flags' => API_NOT_EMPTY | API_ALLOW_LLD_MACRO],
			'event_name' =>				['type' => API_EVENT_NAME, 'length' => DB::getFieldLength('triggers', 'event_name')],
			'opdata' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('triggers', 'opdata')],
			'comments' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('triggers', 'comments')],
			'priority' =>				['type' => API_INT32, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1))],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [TRIGGER_STATUS_ENABLED, TRIGGER_STATUS_DISABLED])],
			'type' =>					['type' => API_INT32, 'in' => implode(',', [TRIGGER_MULT_EVENT_DISABLED, TRIGGER_MULT_EVENT_ENABLED])],
			'url' =>					['type' => API_URL, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('triggers', 'url')],
			'recovery_mode' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_RECOVERY_MODE_EXPRESSION, ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION, ZBX_RECOVERY_MODE_NONE])],
			'recovery_expression' =>	['type' => API_TRIGGER_EXPRESSION, 'flags' => API_ALLOW_LLD_MACRO],
			'correlation_mode' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_TRIGGER_CORRELATION_NONE, ZBX_TRIGGER_CORRELATION_TAG])],
			'correlation_tag' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('triggers', 'correlation_tag')],
			'manual_close' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED, ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED])],
			'tags' =>					['type' => API_OBJECTS, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('trigger_tag', 'tag')],
				'value' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('trigger_tag', 'value'), 'default' => DB::getDefault('trigger_tag', 'value')]
			]],
			'dependencies' =>			['type' => API_OBJECTS, 'uniq' => [['triggerid']], 'fields'=> [
				'triggerid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];
		if ($this instanceof CTriggerPrototype) {
			$api_input_rules['fields']['discover'] = ['type' => API_INT32, 'in' => implode(',', [TRIGGER_DISCOVER, TRIGGER_NO_DISCOVER])];
		}
		else {
			$api_input_rules['fields']['expression']['flags'] &= ~API_ALLOW_LLD_MACRO;
			$api_input_rules['fields']['recovery_expression']['flags'] &= ~API_ALLOW_LLD_MACRO;
		}
		if (!CApiInputValidator::validate($api_input_rules, $triggers, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$options = [
			'output' => ['triggerid', 'description', 'expression', 'url', 'status', 'priority', 'comments', 'type',
				'templateid', 'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag',
				'manual_close', 'opdata', 'event_name'
			],
			'triggerids' => zbx_objectValues($triggers, 'triggerid'),
			'editable' => true,
			'preservekeys' => true
		];

		$class = get_class($this);

		switch ($class) {
			case 'CTrigger':
				$error_cannot_update = _('Cannot update "%1$s" for templated trigger "%2$s".');
				$options['output'][] = 'flags';

				// Discovered fields, except status, cannot be updated.
				$update_discovered_validator = new CUpdateDiscoveredValidator([
					'allowed' => ['triggerid', 'status'],
					'messageAllowedField' => _('Cannot update "%2$s" for a discovered trigger "%1$s".')
				]);
				break;

			case 'CTriggerPrototype':
				$error_cannot_update = _('Cannot update "%1$s" for templated trigger prototype "%2$s".');
				$options['output'][] = 'discover';
				$options['selectDiscoveryRule'] = ['itemid'];
				break;

			default:
				self::exception(ZBX_API_ERROR_INTERNAL, _('Internal error.'));
		}

		$db_triggers = $this->get($options);

		if (count($db_triggers) != count($triggers)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$db_triggers = CMacrosResolverHelper::resolveTriggerExpressions($db_triggers, [
			'sources' => ['expression', 'recovery_expression']
		]);

		$this->addAffectedObjects($triggers, $db_triggers);

		$read_only_fields = ['description', 'expression', 'recovery_mode', 'recovery_expression', 'correlation_mode',
			'correlation_tag', 'manual_close'
		];

		$descriptions = [];

		foreach ($triggers as &$trigger) {
			$db_trigger = $db_triggers[$trigger['triggerid']];
			$description = array_key_exists('description', $trigger)
				? $trigger['description']
				: $db_trigger['description'];

			if ($class === 'CTrigger') {
				$update_discovered_validator->setObjectName($description);
				$this->checkPartialValidator($trigger, $update_discovered_validator, $db_trigger);
			}

			if ($db_trigger['templateid'] != 0) {
				$this->checkNoParameters($trigger, $read_only_fields, $error_cannot_update, $description);
			}

			$field_names = ['description', 'expression', 'recovery_mode', 'manual_close'];
			foreach ($field_names as $field_name) {
				if (!array_key_exists($field_name, $trigger)) {
					$trigger[$field_name] = $db_trigger[$field_name];
				}
			}

			if (!array_key_exists('recovery_expression', $trigger)) {
				$trigger['recovery_expression'] = ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION)
					? $db_trigger['recovery_expression']
					: '';
			}
			if (!array_key_exists('correlation_mode', $trigger)) {
				$trigger['correlation_mode'] = ($trigger['recovery_mode'] != ZBX_RECOVERY_MODE_NONE)
					? $db_trigger['correlation_mode']
					: ZBX_TRIGGER_CORRELATION_NONE;
			}
			if (!array_key_exists('correlation_tag', $trigger)) {
				$trigger['correlation_tag'] = ($trigger['correlation_mode'] == ZBX_TRIGGER_CORRELATION_TAG)
					? $db_trigger['correlation_tag']
					: '';
			}

			self::checkTriggerRecoveryMode($trigger);
			self::checkTriggerCorrelationMode($trigger);

			if ($trigger['expression'] !== $db_trigger['expression']
					|| $trigger['recovery_expression'] !== $db_trigger['recovery_expression']
					|| $trigger['description'] !== $db_trigger['description']) {
				$descriptions[$trigger['description']][] = [
					'expression' => $trigger['expression'],
					'recovery_expression' => $trigger['recovery_expression']
				];
			}
		}
		unset($trigger);

		if ($descriptions) {
			$descriptions = $this->populateHostIds($descriptions);
			$this->checkDuplicates($descriptions);
		}

		$this->checkDependencies($triggers, $db_triggers);
		$this->checkDependenciesLinks($triggers, $db_triggers);
	}

	/**
	 * @param array $triggers
	 * @param array $db_triggers
	 */
	private function addAffectedObjects(array $triggers, array &$db_triggers): void {
		if ($this instanceof CTrigger) {
			self::addAffectedHosts($triggers, $db_triggers);
		}

		self::addAffectedTags($triggers, $db_triggers);
		self::addAffectedDependencies($triggers, $db_triggers);
	}

	/**
	 * @param array $triggers
	 * @param array $db_triggers
	 */
	protected static function addAffectedHosts(array $triggers, array &$db_triggers): void {
		$triggerids = [];

		foreach ($triggers as $trigger) {
			$db_trigger = $db_triggers[$trigger['triggerid']];

			if ((array_key_exists('expression', $trigger) && $trigger['expression'] !== $db_trigger['expression'])
					|| (array_key_exists('recovery_expression', $trigger)
						&& $trigger['recovery_expression'] !== $db_trigger['recovery_expression'])) {
				$triggerids[] = $trigger['triggerid'];
				$db_triggers[$trigger['triggerid']]['hosts'] = [];
			}
		}

		if (!$triggerids) {
			return;
		}

		$result = DBselect(
			'SELECT DISTINCT f.triggerid,i.hostid'.
			' FROM functions f,items i'.
			' WHERE f.itemid=i.itemid'.
				' AND '.dbConditionId('f.triggerid', $triggerids)
		);

		while ($row = DBfetch($result)) {
			$db_triggers[$row['triggerid']]['hosts'][$row['hostid']] = true;
		}
	}

	/**
	 * @param array $triggers
	 * @param array $db_triggers
	 */
	private static function addAffectedTags(array $triggers, array &$db_triggers): void {
		$triggerids = [];

		foreach ($triggers as $trigger) {
			if (array_key_exists('tags', $trigger)) {
				$triggerids[] = $trigger['triggerid'];
				$db_triggers[$trigger['triggerid']]['tags'] = [];
			}
		}

		if (!$triggerids) {
			return;
		}

		$options = [
			'output' => ['triggertagid', 'triggerid', 'tag', 'value'],
			'filter' => ['triggerid' => $triggerids]
		];
		$db_tags = DBselect(DB::makeSql('trigger_tag', $options));

		while ($db_tag = DBfetch($db_tags)) {
			$db_triggers[$db_tag['triggerid']]['tags'][$db_tag['triggertagid']] =
				array_diff_key($db_tag, array_flip(['triggerid']));
		}
	}

	/**
	 * @param array $triggers
	 * @param array $db_triggers
	 */
	protected static function addAffectedDependencies(array $triggers, array &$db_triggers): void {
		$triggerids = [];

		foreach ($triggers as $trigger) {
			$db_trigger = $db_triggers[$trigger['triggerid']];

			if (array_key_exists('dependencies', $trigger) || array_key_exists('hosts', $db_trigger)) {
				$triggerids[] = $trigger['triggerid'];
				$db_triggers[$trigger['triggerid']]['dependencies'] = [];
			}
		}

		if (!$triggerids) {
			return;
		}

		$options = [
			'output' => ['triggerdepid', 'triggerid_down', 'triggerid_up'],
			'filter' => ['triggerid_down' => $triggerids]
		];
		$db_deps = DBselect(DB::makeSql('trigger_depends', $options));

		while ($db_dep = DBfetch($db_deps)) {
			$db_triggers[$db_dep['triggerid_down']]['dependencies'][$db_dep['triggerdepid']] = [
				'triggerdepid' => $db_dep['triggerdepid'],
				'triggerid' => $db_dep['triggerid_up']
			];
		}
	}

	/**
	 * Check trigger dependencies of the given triggers.
	 *
	 * @param array      $triggers
	 * @param array|null $db_triggers
	 *
	 * @throws APIException
	 */
	private function checkDependencies(array $triggers, array $db_triggers = null): void {
		$edit_triggerids_up = [];

		foreach ($triggers as $trigger) {
			if (!array_key_exists('dependencies', $trigger)) {
				continue;
			}

			$triggerids_up = array_column($trigger['dependencies'], 'triggerid');

			if ($db_triggers === null) {
				$edit_triggerids_up += array_flip($triggerids_up);
			}
			else {
				$db_triggerids_up = array_column($db_triggers[$trigger['triggerid']]['dependencies'], 'triggerid');

				$ins_triggerids_up = array_flip(array_diff($triggerids_up, $db_triggerids_up));
				$del_triggerids_up = array_flip(array_diff($db_triggerids_up, $triggerids_up));

				$edit_triggerids_up += $ins_triggerids_up + $del_triggerids_up;
			}
		}

		if (!$edit_triggerids_up) {
			return;
		}

		$count = API::Trigger()->get([
			'countOutput' => true,
			'triggerids' => array_keys($edit_triggerids_up)
		]);

		if ($this instanceof CTriggerPrototype) {
			$trigger_prototypes_up = API::TriggerPrototype()->get([
				'output' => [],
				'triggerids' => array_keys($edit_triggerids_up),
				'preservekeys' => true
			]);

			$count += count($trigger_prototypes_up);
		}

		if ($count != count($edit_triggerids_up)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		if ($this instanceof CTriggerPrototype) {
			$ins_dependencies = [];
			$triggerids = [];

			foreach ($triggers as $trigger) {
				if (!array_key_exists('dependencies', $triggers)) {
					continue;
				}

				$db_triggers_up = ($db_triggers !== null)
					? array_column($db_triggers[$trigger['triggerid']]['dependencies'], null, 'triggerid')
					: [];

				foreach ($trigger['dependencies'] as $trigger_up) {
					if (!array_key_exists($trigger_up['triggerid'], $db_triggers_up)
							&& array_key_exists($trigger_up['triggerid'], $trigger_prototypes_up)) {
						$ins_dependencies[$trigger_up['triggerid']][$trigger['triggerid']] = true;
						$triggerids[$trigger['triggerid']] = true;
					}
				}
			}

			if (!$ins_dependencies) {
				return;
			}

			$result = DBselect(
				'SELECT DISTINCT f.triggerid,id.parent_itemid'.
				' FROM functions f,item_discovery id'.
				' WHERE f.itemid=id.itemid'.
					' AND '.dbConditionId('f.triggerid', array_keys($ins_dependencies + $triggerids))
			);

			$lld_rules = [];

			while ($row = DBfetch($result)) {
				$lld_rules[$row['triggerid']] = $row['parent_itemid'];
			}

			foreach ($ins_dependencies as $triggerid_up => $triggerids) {
				foreach ($triggerids as $triggerid) {
					if (bccomp($lld_rules[$triggerid_up], $lld_rules[$triggerid]) != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('Trigger prototype "%1$s" cannot depend on the trigger prototype "%2$s", because dependencies on trigger prototypes from another LLD rule are not allowed.')
						);
					}
				}
			}
		}
	}

	/**
	 * Check linkage of dependencies.
	 *
	 * @param array      $triggers
	 * @param array|null $db_triggers
	 */
	protected function checkDependenciesLinks(array $triggers, array $db_triggers = null): void {
		$ins_dependencies = [];
		$del_dependencies = [];

		foreach ($triggers as $trigger) {
			if (!array_key_exists('dependencies', $trigger)) {
				continue;
			}

			$db_triggers_up = ($db_triggers !== null)
				? array_column($db_triggers[$trigger['triggerid']]['dependencies'], null, 'triggerid')
				: [];

			foreach ($trigger['dependencies'] as $trigger_up) {
				if (array_key_exists($trigger_up['triggerid'], $db_triggers_up)) {
					unset($db_triggers_up[$trigger_up['triggerid']]);
				}
				else {
					$ins_dependencies[$trigger_up['triggerid']][$trigger['triggerid']] = true;
				}
			}

			foreach ($db_triggers_up as $db_trigger_up) {
				$del_dependencies[$db_trigger_up['triggerid']][$trigger['triggerid']] = true;
			}
		}

		if ($ins_dependencies) {
			if ($this instanceof CTriggerPrototype) {
				$options = [
					'output' => ['triggerid', 'flags'],
					'triggerids' => array_keys($ins_dependencies)
				];
				$result = DBselect(DB::makeSql('triggers', $options));

				$ins_dependencies_prototypes = [];

				while ($row = DBfetch($result)) {
					if ($row['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {

						/*
						 * Trigger cannot depend on trigger prototypes, so it's enough to check circular dependencies
						 * only among the trigger prototypes.
						 */
						$ins_dependencies_prototypes[$row['triggerid']] = $ins_dependencies[$row['triggerid']];

						/*
						 * Since the trigger prototypes can depend only on trigger prototypes from the same LLD rule,
						 * for such dependencies it is not necessary to check the dependencies of host and template
						 * triggers. Only dependencies on triggers are required to be checked.
						 */
						unset($ins_dependencies[$row['triggerid']]);
					}
				}
			}

			if ($db_triggers !== null) {
				if ($this instanceof CTriggerPrototype && $ins_dependencies_prototypes) {
					self::checkCircularDependencies($ins_dependencies_prototypes, $del_dependencies);
				}
				else {
					self::checkCircularDependencies($ins_dependencies, $del_dependencies);
				}
			}

			$trigger_hosts = self::getTriggerHosts($ins_dependencies);

			self::checkDependenciesOfHostTriggers($ins_dependencies, $trigger_hosts);
			self::checkDependenciesOfTemplateTriggers($ins_dependencies, $trigger_hosts);
		}
	}

	/**
	 * Inserts trigger or trigger prototypes records into the database.
	 *
	 * @param array  $triggers                          [IN/OUT]
	 * @param array  $triggers[]['triggerid']           [OUT]
	 * @param array  $triggers[]['description']         [IN]
	 * @param string $triggers[]['expression']          [IN]
	 * @param int    $triggers[]['recovery_mode']       [IN]
	 * @param string $triggers[]['recovery_expression'] [IN]
	 * @param string $triggers[]['url']                 [IN] (optional)
	 * @param int    $triggers[]['status']              [IN] (optional)
	 * @param int    $triggers[]['priority']            [IN] (optional)
	 * @param string $triggers[]['comments']            [IN] (optional)
	 * @param int    $triggers[]['type']                [IN] (optional)
	 * @param string $triggers[]['templateid']          [IN] (optional)
	 * @param array  $triggers[]['tags']                [IN] (optional)
	 * @param string $triggers[]['tags'][]['tag']       [IN]
	 * @param string $triggers[]['tags'][]['value']     [IN]
	 * @param int    $triggers[]['correlation_mode']    [IN] (optional)
	 * @param string $triggers[]['correlation_tag']     [IN] (optional)
	 * @param bool   $inherited                         [IN] (optional)  If set to true, trigger will be created for
	 *                                                                   non-editable host/template.
	 *
	 * @throws APIException
	 */
	protected function createReal(array &$triggers, $inherited = false) {
		$new_triggers = $triggers;
		$new_functions = [];
		$triggers_functions = [];
		$new_tags = [];
		$this->implode_expressions($new_triggers, null, $triggers_functions, $inherited);

		$triggerid = DB::reserveIds('triggers', count($new_triggers));

		foreach ($new_triggers as $tnum => &$new_trigger) {
			$new_trigger['triggerid'] = $triggerid;
			$triggers[$tnum]['triggerid'] = $triggerid;

			foreach ($triggers_functions[$tnum] as $trigger_function) {
				$trigger_function['triggerid'] = $triggerid;
				$new_functions[] = $trigger_function;
			}

			if ($this instanceof CTriggerPrototype) {
				$new_trigger['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;
			}

			if (array_key_exists('tags', $new_trigger)) {
				foreach ($new_trigger['tags'] as $tag) {
					$tag['triggerid'] = $triggerid;
					$new_tags[] = $tag;
				}
			}

			$triggerid = bcadd($triggerid, 1, 0);
		}
		unset($new_trigger);

		DB::insert('triggers', $new_triggers, false);
		DB::insertBatch('functions', $new_functions, false);

		if ($new_tags) {
			DB::insert('trigger_tag', $new_tags);
		}

		if (!$inherited) {
			$resource = ($this instanceof CTrigger) ? CAudit::RESOURCE_TRIGGER : CAudit::RESOURCE_TRIGGER_PROTOTYPE;
			$this->addAuditBulk(CAudit::ACTION_ADD, $resource, $triggers);
		}
	}

	/**
	 * Update trigger or trigger prototypes records in the database.
	 *
	 * @param array  $triggers                                       [IN] list of triggers to be updated
	 * @param array  $triggers[<tnum>]['triggerid']                  [IN]
	 * @param array  $triggers[<tnum>]['description']                [IN]
	 * @param string $triggers[<tnum>]['expression']                 [IN]
	 * @param int    $triggers[<tnum>]['recovery_mode']              [IN]
	 * @param string $triggers[<tnum>]['recovery_expression']        [IN]
	 * @param string $triggers[<tnum>]['url']                        [IN] (optional)
	 * @param int    $triggers[<tnum>]['status']                     [IN] (optional)
	 * @param int    $triggers[<tnum>]['priority']                   [IN] (optional)
	 * @param string $triggers[<tnum>]['comments']                   [IN] (optional)
	 * @param int    $triggers[<tnum>]['type']                       [IN] (optional)
	 * @param string $triggers[<tnum>]['templateid']                 [IN] (optional)
	 * @param array  $triggers[<tnum>]['tags']                       [IN]
	 * @param string $triggers[<tnum>]['tags'][]['tag']              [IN]
	 * @param string $triggers[<tnum>]['tags'][]['value']            [IN]
	 * @param int    $triggers[<tnum>]['correlation_mode']           [IN]
	 * @param string $triggers[<tnum>]['correlation_tag']            [IN]
	 * @param array  $db_triggers                                    [IN]
	 * @param array  $db_triggers[<tnum>]['triggerid']               [IN]
	 * @param array  $db_triggers[<tnum>]['description']             [IN]
	 * @param string $db_triggers[<tnum>]['expression']              [IN]
	 * @param int    $db_triggers[<tnum>]['recovery_mode']           [IN]
	 * @param string $db_triggers[<tnum>]['recovery_expression']     [IN]
	 * @param string $db_triggers[<tnum>]['url']                     [IN]
	 * @param int    $db_triggers[<tnum>]['status']                  [IN]
	 * @param int    $db_triggers[<tnum>]['priority']                [IN]
	 * @param string $db_triggers[<tnum>]['comments']                [IN]
	 * @param int    $db_triggers[<tnum>]['type']                    [IN]
	 * @param string $db_triggers[<tnum>]['templateid']              [IN]
	 * @param array  $db_triggers[<tnum>]['discoveryRule']           [IN] For trigger prototypes only.
	 * @param string $db_triggers[<tnum>]['discoveryRule']['itemid'] [IN]
	 * @param array  $db_triggers[<tnum>]['tags']                    [IN]
	 * @param string $db_triggers[<tnum>]['tags'][]['tag']           [IN]
	 * @param string $db_triggers[<tnum>]['tags'][]['value']         [IN]
	 * @param int    $db_triggers[<tnum>]['correlation_mode']        [IN]
	 * @param string $db_triggers[<tnum>]['correlation_tag']         [IN]
	 * @param bool   $inherited                                      [IN] (optional)  If set to true, trigger will be
	 *                                                                                created for non-editable
	 *                                                                                host/template.
	 *
	 * @throws APIException
	 */
	protected function updateReal(array $triggers, array $db_triggers, $inherited = false) {
		$upd_triggers = [];
		$new_functions = [];
		$del_functions_triggerids = [];
		$triggers_functions = [];
		$new_tags = [];
		$del_triggertagids = [];
		$save_triggers = $triggers;
		$this->implode_expressions($triggers, $db_triggers, $triggers_functions, $inherited);

		foreach ($triggers as $tnum => $trigger) {
			$db_trigger = $db_triggers[$trigger['triggerid']];
			$upd_trigger = ['values' => [], 'where' => ['triggerid' => $trigger['triggerid']]];

			if (array_key_exists($tnum, $triggers_functions)) {
				$del_functions_triggerids[] = $trigger['triggerid'];

				foreach ($triggers_functions[$tnum] as $trigger_function) {
					$trigger_function['triggerid'] = $trigger['triggerid'];
					$new_functions[] = $trigger_function;
				}

				$upd_trigger['values']['expression'] = $trigger['expression'];
				$upd_trigger['values']['recovery_expression'] = $trigger['recovery_expression'];
			}

			if (array_key_exists('uuid', $trigger)) {
				$upd_trigger['values']['uuid'] = $trigger['uuid'];
			}
			if ($trigger['description'] !== $db_trigger['description']) {
				$upd_trigger['values']['description'] = $trigger['description'];
			}
			if (array_key_exists('event_name', $trigger) && $trigger['event_name'] !== $db_trigger['event_name']) {
				$upd_trigger['values']['event_name'] = $trigger['event_name'];
			}
			if (array_key_exists('opdata', $trigger) && $trigger['opdata'] !== $db_trigger['opdata']) {
				$upd_trigger['values']['opdata'] = $trigger['opdata'];
			}
			if ($trigger['recovery_mode'] != $db_trigger['recovery_mode']) {
				$upd_trigger['values']['recovery_mode'] = $trigger['recovery_mode'];
			}
			if (array_key_exists('url', $trigger) && $trigger['url'] !== $db_trigger['url']) {
				$upd_trigger['values']['url'] = $trigger['url'];
			}
			if (array_key_exists('status', $trigger) && $trigger['status'] != $db_trigger['status']) {
				$upd_trigger['values']['status'] = $trigger['status'];
			}
			if ($this instanceof CTriggerPrototype
					&& array_key_exists('discover', $trigger) && $trigger['discover'] != $db_trigger['discover']) {
				$upd_trigger['values']['discover'] = $trigger['discover'];
			}
			if (array_key_exists('priority', $trigger) && $trigger['priority'] != $db_trigger['priority']) {
				$upd_trigger['values']['priority'] = $trigger['priority'];
			}
			if (array_key_exists('comments', $trigger) && $trigger['comments'] !== $db_trigger['comments']) {
				$upd_trigger['values']['comments'] = $trigger['comments'];
			}
			if (array_key_exists('type', $trigger) && $trigger['type'] != $db_trigger['type']) {
				$upd_trigger['values']['type'] = $trigger['type'];
			}
			if (array_key_exists('templateid', $trigger) && $trigger['templateid'] != $db_trigger['templateid']) {
				$upd_trigger['values']['templateid'] = $trigger['templateid'];
			}
			if ($trigger['correlation_mode'] != $db_trigger['correlation_mode']) {
				$upd_trigger['values']['correlation_mode'] = $trigger['correlation_mode'];
			}
			if ($trigger['correlation_tag'] !== $db_trigger['correlation_tag']) {
				$upd_trigger['values']['correlation_tag'] = $trigger['correlation_tag'];
			}
			if ($trigger['manual_close'] != $db_trigger['manual_close']) {
				$upd_trigger['values']['manual_close'] = $trigger['manual_close'];
			}

			if ($upd_trigger['values']) {
				$upd_triggers[] = $upd_trigger;
			}

			if (array_key_exists('tags', $trigger)) {
				// Add new trigger tags and replace changed ones.

				CArrayHelper::sort($db_trigger['tags'], ['tag', 'value']);
				CArrayHelper::sort($trigger['tags'], ['tag', 'value']);

				$tags_delete = $db_trigger['tags'];
				$tags_add = $trigger['tags'];

				foreach ($tags_delete as $dt_key => $tag_delete) {
					foreach ($tags_add as $nt_key => $tag_add) {
						if ($tag_delete['tag'] === $tag_add['tag'] && $tag_delete['value'] === $tag_add['value']) {
							unset($tags_delete[$dt_key], $tags_add[$nt_key]);
							continue 2;
						}
					}
				}

				foreach ($tags_delete as $tag_delete) {
					$del_triggertagids[] = $tag_delete['triggertagid'];
				}

				foreach ($tags_add as $tag_add) {
					$tag_add['triggerid'] = $trigger['triggerid'];
					$new_tags[] = $tag_add;
				}
			}
		}

		if ($upd_triggers) {
			DB::update('triggers', $upd_triggers);
		}
		if ($del_functions_triggerids) {
			DB::delete('functions', ['triggerid' => $del_functions_triggerids]);
		}
		if ($new_functions) {
			DB::insertBatch('functions', $new_functions, false);
		}
		if ($del_triggertagids) {
			DB::delete('trigger_tag', ['triggertagid' => $del_triggertagids]);
		}
		if ($new_tags) {
			DB::insert('trigger_tag', $new_tags);
		}

		if (!$inherited) {
			$resource = ($this instanceof CTrigger) ? CAudit::RESOURCE_TRIGGER : CAudit::RESOURCE_TRIGGER_PROTOTYPE;
			$this->addAuditBulk(CAudit::ACTION_UPDATE, $resource, $save_triggers, $db_triggers);
		}
	}

	/**
	 * Implodes expression and recovery_expression for each trigger. Also returns array of functions and
	 * array of hostnames for each trigger.
	 *
	 * For example: last(/host/system.cpu.load)>10 will be translated to {12}>10 and created database representation.
	 *
	 * Note: All expressions must be already validated and exploded.
	 *
	 * @param array      $triggers                                   [IN]
	 * @param string     $triggers[<tnum>]['description']            [IN]
	 * @param string     $triggers[<tnum>]['expression']             [IN/OUT]
	 * @param int        $triggers[<tnum>]['recovery_mode']          [IN]
	 * @param string     $triggers[<tnum>]['recovery_expression']    [IN/OUT]
	 * @param array|null $db_triggers                                [IN]
	 * @param string     $db_triggers[<triggerid>]['triggerid']           [IN]
	 * @param string     $db_triggers[<triggerid>]['expression']          [IN]
	 * @param string     $db_triggers[<triggerid>]['recovery_expression'] [IN]
	 * @param array      $triggers_functions                         [OUT] array of the new functions which must be
	 *                                                                     inserted into DB
	 * @param string     $triggers_functions[<tnum>][]['functionid'] [OUT]
	 * @param null       $triggers_functions[<tnum>][]['triggerid']  [OUT] must be initialized before insertion into DB
	 * @param string     $triggers_functions[<tnum>][]['itemid']     [OUT]
	 * @param string     $triggers_functions[<tnum>][]['name']       [OUT]
	 * @param string     $triggers_functions[<tnum>][]['parameter']  [OUT]
	 * @param bool       $inherited                                  [IN] (optional)  If set to true, triggers will be
	 *                                                                                created for non-editable
	 *                                                                                hosts/templates.
	 *
	 * @throws APIException if error occurred
	 */
	private function implode_expressions(array &$triggers, ?array $db_triggers, array &$triggers_functions, $inherited) {
		$class = get_class($this);

		switch ($class) {
			case 'CTrigger':
				$expression_parser = new CExpressionParser(['usermacros' => true]);
				$error_wrong_host = _('Incorrect trigger expression. Host "%1$s" does not exist or you have no access to this host.');
				$error_host_and_template = _('Incorrect trigger expression. Trigger expression elements should not belong to a template and a host simultaneously.');
				break;

			case 'CTriggerPrototype':
				$expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);
				$error_wrong_host = _('Incorrect trigger prototype expression. Host "%1$s" does not exist or you have no access to this host.');
				$error_host_and_template = _('Incorrect trigger prototype expression. Trigger prototype expression elements should not belong to a template and a host simultaneously.');
				break;

			default:
				self::exception(ZBX_API_ERROR_INTERNAL, _('Internal error.'));
		}

		$hist_function_value_types = (new CHistFunctionData())->getValueTypes();

		/*
		 * [
		 *     <host> => [
		 *         'hostid' => <hostid>,
		 *         'host' => <host>,
		 *         'status' => <status>,
		 *         'keys' => [
		 *             <key> => [
		 *                 'itemid' => <itemid>,
		 *                 'key' => <key>,
		 *                 'value_type' => <value_type>,
		 *                 'flags' => <flags>,
		 *                 'lld_ruleid' => <itemid> (CTriggerProrotype only)
		 *             ]
		 *         ]
		 *     ]
		 * ]
		 */
		$hosts_keys = [];
		$functions_num = 0;

		foreach ($triggers as $trigger) {
			$expressions_changed = ($db_triggers === null
				|| ($trigger['expression'] !== $db_triggers[$trigger['triggerid']]['expression']
				|| $trigger['recovery_expression'] !== $db_triggers[$trigger['triggerid']]['recovery_expression']));

			if (!$expressions_changed) {
				continue;
			}

			$expression_parser->parse($trigger['expression']);
			$hist_functions = $expression_parser->getResult()->getTokensOfTypes(
				[CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
			);

			if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
				$expression_parser->parse($trigger['recovery_expression']);
				$hist_functions = array_merge($hist_functions, $expression_parser->getResult()->getTokensOfTypes(
					[CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
				));
			}

			foreach ($hist_functions as $hist_function) {
				$host = $hist_function['data']['parameters'][0]['data']['host'];
				$item = $hist_function['data']['parameters'][0]['data']['item'];

				if (!array_key_exists($host, $hosts_keys)) {
					$hosts_keys[$host] = [
						'hostid' => null,
						'host' => $host,
						'status' => null,
						'keys' => []
					];
				}

				$hosts_keys[$host]['keys'][$item] = [
					'itemid' => null,
					'key' => $item,
					'value_type' => null,
					'flags' => null
				];
			}
		}

		if (!$hosts_keys) {
			return;
		}

		$permission_check = $inherited
			? ['nopermissions' => true]
			: ['editable' => true];

		$_db_hosts = API::Host()->get([
			'output' => ['hostid', 'host', 'status'],
			'filter' => ['host' => array_keys($hosts_keys)]
		] + $permission_check);

		if (count($hosts_keys) != count($_db_hosts)) {
			$_db_templates = API::Template()->get([
				'output' => ['templateid', 'host', 'status'],
				'filter' => ['host' => array_keys($hosts_keys)]
			] + $permission_check);

			foreach ($_db_templates as &$_db_template) {
				$_db_template['hostid'] = $_db_template['templateid'];
				unset($_db_template['templateid']);
			}
			unset($_db_template);

			$_db_hosts = array_merge($_db_hosts, $_db_templates);
		}

		foreach ($_db_hosts as $_db_host) {
			$host_keys = &$hosts_keys[$_db_host['host']];

			$host_keys['hostid'] = $_db_host['hostid'];
			$host_keys['status'] = $_db_host['status'];

			if ($class === 'CTriggerPrototype') {
				$sql = 'SELECT i.itemid,i.key_,i.value_type,i.flags,id.parent_itemid'.
					' FROM items i'.
						' LEFT JOIN item_discovery id ON i.itemid=id.itemid'.
					' WHERE i.hostid='.$host_keys['hostid'].
						' AND '.dbConditionString('i.key_', array_keys($host_keys['keys'])).
						' AND '.dbConditionInt('i.flags',
							[ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED]
						);
			}
			else {
				$sql = 'SELECT i.itemid,i.key_,i.value_type,i.flags'.
					' FROM items i'.
					' WHERE i.hostid='.$host_keys['hostid'].
						' AND '.dbConditionString('i.key_', array_keys($host_keys['keys'])).
						' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]);
			}

			$_db_items = DBselect($sql);

			while ($_db_item = DBfetch($_db_items)) {
				$host_keys['keys'][$_db_item['key_']]['itemid'] = $_db_item['itemid'];
				$host_keys['keys'][$_db_item['key_']]['value_type'] = $_db_item['value_type'];
				$host_keys['keys'][$_db_item['key_']]['flags'] = $_db_item['flags'];

				if ($class === 'CTriggerPrototype' && $_db_item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
					$host_keys['keys'][$_db_item['key_']]['lld_ruleid'] = $_db_item['parent_itemid'];
				}
			}

			unset($host_keys);
		}

		/*
		 * The list of triggers with multiple templates.
		 *
		 * [
		 *     [
		 *         'description' => <description>,
		 *         'templateids' => [<templateid>, ...]
		 *     ],
		 *     ...
		 * ]
		 */
		$mt_triggers = [];

		if ($class === 'CTrigger') {
			/*
			 * The list of triggers which are moved from one host or template to another.
			 *
			 * [
			 *     <triggerid> => [
			 *         'description' => <description>
			 *     ],
			 *     ...
			 * ]
			 */
			$moved_triggers = [];
		}

		foreach ($triggers as $tnum => &$trigger) {
			$expressions_changed = ($db_triggers === null
				|| ($trigger['expression'] !== $db_triggers[$trigger['triggerid']]['expression']
				|| $trigger['recovery_expression'] !== $db_triggers[$trigger['triggerid']]['recovery_expression']));

			if (!$expressions_changed) {
				continue;
			}

			$expression_parser->parse($trigger['expression']);

			$hist_functions1 = $expression_parser->getResult()->getTokensOfTypes(
				[CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
			);

			$hist_functions2 = [];

			if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
				$expression_parser->parse($trigger['recovery_expression']);

				$hist_functions2 = $expression_parser->getResult()->getTokensOfTypes(
					[CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
				);
			}

			$triggers_functions[$tnum] = [];
			if ($class === 'CTriggerPrototype') {
				$lld_ruleids = [];
			}

			/*
			 * 0x01 - with templates
			 * 0x02 - with hosts
			 */
			$status_mask = 0x00;
			// The lists of hostids and hosts which are used in the current trigger.
			$hostids = [];
			$hosts = [];

			// Common checks.
			foreach (array_merge($hist_functions1, $hist_functions2) as $hist_function) {
				$host = $hist_function['data']['parameters'][0]['data']['host'];
				$item = $hist_function['data']['parameters'][0]['data']['item'];

				$host_keys = $hosts_keys[$host];
				$key = $host_keys['keys'][$item];

				if ($host_keys['hostid'] === null) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _params($error_wrong_host, [$host_keys['host']]));
				}

				if ($key['itemid'] === null) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Incorrect item key "%1$s" provided for trigger expression on "%2$s".', $key['key'],
						$host_keys['host']
					));
				}

				if (!in_array($key['value_type'], $hist_function_value_types[$hist_function['data']['function']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Incorrect item value type "%1$s" provided for trigger function "%2$s".',
						itemValueTypeString($key['value_type']), $hist_function['data']['function']
					));
				}

				if (!array_key_exists($hist_function['match'], $triggers_functions[$tnum])) {
					$query_parameter = $hist_function['data']['parameters'][0];
					$parameter = substr_replace($hist_function['match'], TRIGGER_QUERY_PLACEHOLDER,
						$query_parameter['pos'] - $hist_function['pos'], $query_parameter['length']
					);
					$triggers_functions[$tnum][$hist_function['match']] = [
						'functionid' => null,
						'triggerid' => null,
						'itemid' => $key['itemid'],
						'name' => $hist_function['data']['function'],
						'parameter' => substr($parameter, strlen($hist_function['data']['function']) + 1, -1)
					];
					$functions_num++;
				}

				if ($class === 'CTriggerPrototype' && $key['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
					$lld_ruleids[$key['lld_ruleid']] = true;
				}

				$status_mask |= ($host_keys['status'] == HOST_STATUS_TEMPLATE ? 0x01 : 0x02);

				$hostids[$host_keys['hostid']] = true;
				$hosts[$host] = true;
			}

			// When both templates and hosts are referenced in expressions.
			if ($status_mask == 0x03) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error_host_and_template);
			}

			// Triggers with children cannot be moved from one template to another host or template.
			if ($class === 'CTrigger' && $db_triggers !== null && $expressions_changed) {
				$expression_parser->parse($db_triggers[$trigger['triggerid']]['expression']);
				$old_hosts1 = $expression_parser->getResult()->getHosts();
				$old_hosts2 = [];

				if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
					$expression_parser->parse($db_triggers[$trigger['triggerid']]['recovery_expression']);
					$old_hosts2 = $expression_parser->getResult()->getHosts();
				}

				$is_moved = true;
				foreach (array_merge($old_hosts1, $old_hosts2) as $old_host) {
					if (array_key_exists($old_host, $hosts)) {
						$is_moved = false;
						break;
					}
				}

				if ($is_moved) {
					$moved_triggers[$trigger['triggerid']] = ['description' => $trigger['description']];
				}
			}

			// The trigger with multiple templates.
			if ($status_mask == 0x01 && count($hostids) > 1) {
				$mt_triggers[] = [
					'description' => $trigger['description'],
					'templateids' => array_keys($hostids)
				];
			}

			if ($class === 'CTriggerPrototype') {
				$lld_ruleids = array_keys($lld_ruleids);

				if (!$lld_ruleids) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Trigger prototype "%1$s" must contain at least one item prototype.', $trigger['description']
					));
				}
				elseif (count($lld_ruleids) > 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Trigger prototype "%1$s" contains item prototypes from multiple discovery rules.',
						$trigger['description']
					));
				}
				elseif ($db_triggers !== null
						&& !idcmp($lld_ruleids[0], $db_triggers[$trigger['triggerid']]['discoveryRule']['itemid'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot update trigger prototype "%1$s": %2$s.',
						$trigger['description'], _('trigger prototype cannot be moved to another template or host')
					));
				}
			}
		}
		unset($trigger);

		if ($mt_triggers) {
			$this->validateTriggersWithMultipleTemplates($mt_triggers);
		}

		if ($class === 'CTrigger' && $moved_triggers) {
			$this->validateMovedTriggers($moved_triggers);
		}

		$functionid = DB::reserveIds('functions', $functions_num);

		$expression_max_length = DB::getFieldLength('triggers', 'expression');
		$recovery_expression_max_length = DB::getFieldLength('triggers', 'recovery_expression');

		// Replace func(/host/item) macros with {<functionid>}.
		foreach ($triggers as $tnum => &$trigger) {
			$expressions_changed = ($db_triggers === null
				|| ($trigger['expression'] !== $db_triggers[$trigger['triggerid']]['expression']
				|| $trigger['recovery_expression'] !== $db_triggers[$trigger['triggerid']]['recovery_expression']));

			if (!$expressions_changed) {
				continue;
			}

			foreach ($triggers_functions[$tnum] as &$trigger_function) {
				$trigger_function['functionid'] = $functionid;
				$functionid = bcadd($functionid, 1, 0);
			}
			unset($trigger_function);

			$expression_parser->parse($trigger['expression']);
			$hist_functions = $expression_parser->getResult()->getTokensOfTypes(
				[CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
			);
			$hist_function = end($hist_functions);
			do {
				$trigger['expression'] = substr_replace($trigger['expression'],
					'{'.$triggers_functions[$tnum][$hist_function['match']]['functionid'].'}',
					$hist_function['pos'], $hist_function['length']
				);
			}
			while ($hist_function = prev($hist_functions));

			if (mb_strlen($trigger['expression']) > $expression_max_length) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Invalid parameter "%1$s": %2$s.', '/'.($tnum + 1).'/expression', _('value is too long')
				));
			}

			if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
				$expression_parser->parse($trigger['recovery_expression']);
				$hist_functions = $expression_parser->getResult()->getTokensOfTypes(
					[CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
				);
				$hist_function = end($hist_functions);
				do {
					$trigger['recovery_expression'] = substr_replace($trigger['recovery_expression'],
						'{'.$triggers_functions[$tnum][$hist_function['match']]['functionid'].'}',
						$hist_function['pos'], $hist_function['length']
					);
				}
				while ($hist_function = prev($hist_functions));

				if (mb_strlen($trigger['recovery_expression']) > $recovery_expression_max_length) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/'.($tnum + 1).'/recovery_expression', _('value is too long')
					));
				}
			}
		}
		unset($trigger);
	}

	/**
	 * Check if all templates trigger belongs to are linked to same hosts.
	 *
	 * @param array  $mt_triggers
	 * @param string $mt_triggers[]['description']
	 * @param array  $mt_triggers[]['templateids']
	 *
	 * @throws APIException
	 */
	protected function validateTriggersWithMultipleTemplates(array $mt_triggers) {
		switch (get_class($this)) {
			case 'CTrigger':
				$error_different_linkages = _('Trigger "%1$s" belongs to templates with different linkages.');
				break;

			case 'CTriggerPrototype':
				$error_different_linkages = _('Trigger prototype "%1$s" belongs to templates with different linkages.');
				break;

			default:
				self::exception(ZBX_API_ERROR_INTERNAL, _('Internal error.'));
		}

		$templateids = [];

		foreach ($mt_triggers as $mt_trigger) {
			foreach ($mt_trigger['templateids'] as $templateid) {
				$templateids[$templateid] = true;
			}
		}

		$templates = API::Template()->get([
			'output' => [],
			'selectHosts' => ['hostid'],
			'selectTemplates' => ['templateid'],
			'templateids' => array_keys($templateids),
			'nopermissions' => true,
			'preservekeys' => true
		]);

		foreach ($templates as &$template) {
			$template = array_merge(
				zbx_objectValues($template['hosts'], 'hostid'),
				zbx_objectValues($template['templates'], 'templateid')
			);
		}
		unset($template);

		foreach ($mt_triggers as $mt_trigger) {
			$compare_links = null;

			foreach ($mt_trigger['templateids'] as $templateid) {
				if ($compare_links === null) {
					$compare_links = $templates[$templateid];
					continue;
				}

				$linked_to = $templates[$templateid];

				if (array_diff($compare_links, $linked_to) || array_diff($linked_to, $compare_links)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_params($error_different_linkages, [$mt_trigger['description']])
					);
				}
			}
		}
	}

	/**
	 * Check if moved triggers does not have children.
	 *
	 * @param array  $moved_triggers
	 * @param string $moved_triggers[<triggerid>]['description']
	 *
	 * @throws APIException
	 */
	protected function validateMovedTriggers(array $moved_triggers) {
		$_db_triggers = DBselect(
			'SELECT t.templateid'.
			' FROM triggers t'.
			' WHERE '.dbConditionInt('t.templateid', array_keys($moved_triggers)),
			1
		);

		if ($_db_trigger = DBfetch($_db_triggers)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot update trigger "%1$s": %2$s.',
				$moved_triggers[$_db_trigger['templateid']]['description'],
				_('trigger with linkages cannot be moved to another template or host')
			));
		}
	}

	/**
	 * Adds triggers and trigger prototypes from template to hosts.
	 *
	 * @param array $data
	 */
	public function syncTemplates(array $data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$output = ['triggerid', 'description', 'expression', 'recovery_mode', 'recovery_expression', 'url', 'status',
			'priority', 'comments', 'type', 'correlation_mode', 'correlation_tag', 'manual_close', 'opdata',
			'event_name'
		];
		if ($this instanceof CTriggerPrototype) {
			$output[] = 'discover';
		}

		$triggers = $this->get([
			'output' => $output,
			'selectTags' => ['tag', 'value'],
			'hostids' => $data['templateids'],
			'preservekeys' => true,
			'nopermissions' => true
		]);

		$triggers = CMacrosResolverHelper::resolveTriggerExpressions($triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		$this->inherit($triggers, $data['hostids']);
	}

	/**
	 * Check whether circular linkage occurs as a result of the given changes in trigger dependencies.
	 *
	 * @param array $ins_dependencies[<triggerid_up>][<triggerid>]
	 * @param array $del_dependencies[<triggerid_up>][<triggerid>]
	 * @param bool  $inherited  Whether the check gets performed during inherit.
	 *
	 * @throws APIException
	 */
	protected static function checkCircularDependencies(array $ins_dependencies, array $del_dependencies = [],
			bool $inherited = false): void {
		$links = [];
		$_triggerids_down = $ins_dependencies;

		do {
			$options = [
				'output' => ['triggerid_up', 'triggerid_down'],
				'filter' => [
					'triggerid_down' => array_keys($_triggerids_down)
				]
			];
			$result = DBselect(DB::makeSql('trigger_depends', $options));

			$_triggerids_down = [];

			while ($row = DBfetch($result)) {
				if (array_key_exists($row['triggerid_up'], $del_dependencies)
						&& array_key_exists($row['triggerid_down'], $del_dependencies[$row['triggerid_up']])) {
					continue;
				}

				if (!array_key_exists($row['triggerid_up'], $links)) {
					$_triggerids_down[$row['triggerid_up']] = true;
				}

				$links[$row['triggerid_up']][$row['triggerid_down']] = true;
			}
		} while ($_triggerids_down);

		foreach ($ins_dependencies as $triggerid_up => $triggerids) {
			if (array_key_exists($triggerid_up, $links)) {
				$links[$triggerid_up] += $triggerids;
			}
			else {
				$links[$triggerid_up] = $triggerids;
			}
		}

		foreach ($ins_dependencies as $triggerid_up => $triggerids) {
			foreach ($triggerids as $triggerid => $foo) {
				if (array_key_exists($triggerid, $links)) {
					$links_path = [$triggerid => true];

					if (self::circularLinkageExists($links, $triggerid_up, $links[$triggerid], $links_path)) {
						$trigger_up_name = '';

						$triggers = DB::select('triggers', [
							'output' => ['description', 'flags'],
							'triggerids' => array_keys($links_path + [$triggerid_up => true]),
							'preservekeys' => true
						]);

						foreach ($triggers as $_triggerid => $trigger) {
							$description = '"'.$trigger['description'].'"';

							if (bccomp($_triggerid, $triggerid_up) == 0) {
								$trigger_up_name = $description;
							}
							else {
								$links_path[$_triggerid] = $description;
							}
						}

						$circular_linkage = (bccomp($triggerid_up, $triggerid) == 0)
							? $trigger_up_name.' -> '.$trigger_up_name
							: $trigger_up_name.' -> '.implode(' -> ', $links_path).' -> '.$trigger_up_name;

						if ($inherited) {
							$host = DBfetch(DBselect(
								'SELECT DISTINCT h.host,h.status'.
								' FROM functions f,items i,hosts h'.
								' WHERE f.itemid=i.itemid'.
									' AND i.hostid=h.hostid'.
									' AND '.dbConditionId('f.triggerid', [$triggerid_up])
							));

							if ($host['status'] == HOST_STATUS_TEMPLATE) {
								$error = ($triggers[$triggerid]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL)
									? _('Trigger "%1$s" cannot depend on the trigger "%2$s", because a circular linkage (%3$s) would occur for template "%4$s".')
									: _('Trigger prototype "%1$s" cannot depend on the trigger prototype "%2$s", because a circular linkage (%3$s) would occur for template "%4$s".');
							}
							else {
								$error = ($triggers[$triggerid]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL)
									? _('Trigger "%1$s" cannot depend on the trigger "%2$s", because a circular linkage (%3$s) would occur for host "%4$s".')
									: _('Trigger prototype "%1$s" cannot depend on the trigger prototype "%2$s", because a circular linkage (%3$s) would occur for host "%4$s".');
							}

							self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error,
								$triggers[$triggerid]['description'], $triggers[$triggerid_up]['description'],
								$circular_linkage, $host['host']
							));
						}
						else {
							$error = ($triggers[$triggerid]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL)
								? _('Trigger "%1$s" cannot depend on the trigger "%2$s", because a circular linkage (%3$s) would occur.')
								: _('Trigger prototype "%1$s" cannot depend on the trigger prototype "%2$s", because a circular linkage (%3$s) would occur.');

							self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error,
								$triggers[$triggerid]['description'], $triggers[$triggerid_up]['description'],
								$circular_linkage
							));
						}
					}
				}
			}
		}
	}

	/**
	 * Recursively check whether the trigger, which a dependency is being set on, produces a circular linkage.
	 *
	 * @param array  $links[<triggerid_up>][<triggerid>]
	 * @param string $triggerid_up
	 * @param array  $triggerids[<triggerid>]
	 * @param array  $links_path                          Circular linkage path, collected performing the check.
	 *
	 * @return bool
	 */
	private static function circularLinkageExists(array $links, string $triggerid_up, array $triggerids,
			array &$links_path): bool {
		if (array_key_exists($triggerid_up, $triggerids)) {
			return true;
		}

		$_links_path = $links_path;

		foreach ($triggerids as $triggerid => $foo) {
			if (array_key_exists($triggerid, $links)) {
				$links_path = $_links_path;
				$triggerid_links = array_diff_key($links[$triggerid], $links_path);

				if ($triggerid_links) {
					$links_path[$triggerid] = true;

					if (self::circularLinkageExists($links, $triggerid_up, $triggerid_links, $links_path)) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Get hosts data of all triggers given in trigger dependencies array.
	 *
	 * @param array $trigger_dependencies[<triggerid_up>][<triggerid>]
	 *
	 * @return array
	 */
	public static function getTriggerHosts(array $trigger_dependencies): array {
		$all_triggerids = $trigger_dependencies;

		foreach ($trigger_dependencies as $triggerids) {
			$all_triggerids += $triggerids;
		}

		$result = DBselect(
			'SELECT DISTINCT f.triggerid,h.hostid,h.status'.
			' FROM functions f,items i,hosts h'.
			' WHERE f.itemid=i.itemid'.
				' AND i.hostid=h.hostid'.
				' AND '.dbConditionId('f.triggerid', array_keys($all_triggerids))
		);

		$trigger_hosts = [];

		while ($row = DBfetch($result)) {
			// Each trigger can have either only templateids or only hostids.
			if ($row['status'] == HOST_STATUS_TEMPLATE) {
				$trigger_hosts[$row['triggerid']]['templateids'][$row['hostid']] = true;
			}
			else {
				$trigger_hosts[$row['triggerid']]['hostids'][$row['hostid']] = true;
			}
		}

		return $trigger_hosts;
	}

	/**
	 * Check whether the trigger dependencies are correctly set for host triggers.
	 *
	 * @param array $trigger_dependencies[<triggerid_up>][<triggerid>]
	 * @param array $trigger_hosts
	 *
	 * @throws APIException
	 */
	public static function checkDependenciesOfHostTriggers(array $trigger_dependencies, array $trigger_hosts): void {
		foreach ($trigger_dependencies as $triggerid_up => $triggerids) {
			if (array_key_exists('templateids', $trigger_hosts[$triggerid_up])) {
				foreach ($triggerids as $triggerid => $foo) {
					if (array_key_exists('hostids', $trigger_hosts[$triggerid])) {
						$triggers = DB::select('triggers', [
							'output' => ['description', 'flags'],
							'triggerids' => [$triggerid, $triggerid_up],
							'preservekeys' => true
						]);

						$error = ($triggers[$triggerid]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL)
							? _('Trigger "%1$s" cannot depend on the trigger "%2$s", because dependencies of host triggers on template triggers are not allowed.')
							: _('Trigger prototype "%1$s" cannot depend on the trigger "%2$s", because dependencies of host triggers on template triggers are not allowed.');

						self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $triggers[$triggerid]['description'],
							$triggers[$triggerid_up]['description']
						));
					}
				}
			}
		}
	}

	/**
	 * Check whether the trigger dependencies are correctly set for template triggers.
	 *
	 * @param array $trigger_dependencies[<triggerid_up>][<triggerid>]
	 * @param array $trigger_hosts
	 *
	 * @throws APIException
	 */
	public static function checkDependenciesOfTemplateTriggers(array $trigger_dependencies,
			array $trigger_hosts): void {
		/*
		 * From the given trigger dependencies we should keep only the dependencies of the template triggers. There is
		 * also no need to check the dependencies on triggers from the same template, because that is considered a
		 * valid case. Thus, among the triggers that the template triggers depends on, we should only keep triggers from
		 * other templates and hosts.
		 */
		foreach ($trigger_dependencies as $triggerid_up => $triggerids) {
			$templateids_up = array_key_exists('templateids', $trigger_hosts[$triggerid_up])
				? $trigger_hosts[$triggerid_up]['templateids']
				: [];

			foreach ($triggerids as $triggerid => $foo) {
				/*
				 * If trigger-up and dependent trigger have at least one common template, even if they also have
				 * other templates, it is important to understand that at the moment of the trigger dependency
				 * validation all of those different templates are already linked to all child templates,
				 * so there is no need to check them again.
				 */
				if (!array_key_exists('templateids', $trigger_hosts[$triggerid])
						|| array_intersect_key($templateids_up, $trigger_hosts[$triggerid]['templateids'])) {
					unset($trigger_dependencies[$triggerid_up][$triggerid]);
				}
			}

			if (!$trigger_dependencies[$triggerid_up]) {
				unset($trigger_dependencies[$triggerid_up]);
			}
		}

		if (!$trigger_dependencies) {
			return;
		}

		self::checkTriggersUpNotFromParentTemplates($trigger_dependencies, $trigger_hosts);
		self::checkTriggersUpNotFromChildTemplatesOrHosts($trigger_dependencies, $trigger_hosts);
		self::checkTriggersUpTemplatesAreLinkedToChildTemplates($trigger_dependencies, $trigger_hosts);
	}

	/**
	 * Check that the triggers-up of the given trigger dependencies do not come from parent templates of dependent
	 * triggers.
	 *
	 * @param array $trigger_dependencies[<triggerid_up>][<triggerid>]
	 * @param array $trigger_hosts
	 *
	 * @throws APIException
	 */
	private static function checkTriggersUpNotFromParentTemplates(array $trigger_dependencies, array $trigger_hosts): void {
		$templateids = [];
		$template_triggers_up = [];
		$dependency_templates = [];

		foreach ($trigger_dependencies as $triggerid_up => $triggerids) {
			if (!array_key_exists('templateids', $trigger_hosts[$triggerid_up])) {
				continue;
			}

			$templateids_up = [];

			foreach ($trigger_hosts[$triggerid_up]['templateids'] as $templateid => $foo) {
				$template_triggers_up[$templateid][] = $triggerid_up;
				$templateids_up[$templateid] = true;
			}

			foreach ($triggerids as $triggerid => $foo) {
				foreach ($trigger_hosts[$triggerid]['templateids'] as $templateid => $foo) {
					if (!array_key_exists($templateid, $dependency_templates)) {
						$dependency_templates[$templateid] = [];
					}

					$dependency_templates[$templateid] += $templateids_up;
				}

				$templateids += $trigger_hosts[$triggerid]['templateids'];
			}
		}

		$_templateids = $templateids;
		$template_links = [];

		do {
			$options = [
				'output' => ['hostid', 'templateid'],
				'filter' => ['hostid' => array_keys($_templateids)]
			];
			$result = DBselect(DB::makeSql('hosts_templates', $options));

			$_templateids = [];

			while ($row = DBfetch($result)) {
				$template_links[$row['hostid']][$row['templateid']] = true;

				if (!array_key_exists($row['templateid'], $template_links)) {
					$_templateids[$row['templateid']] = true;
				}
			}
		} while ($_templateids);

		if (!$template_links) {
			return;
		}

		// Check if the trigger-up is a trigger from the parent template of the dependent trigger.
		foreach ($dependency_templates as $templateid => $templateids_up) {
			if (array_key_exists($templateid, $template_links)) {
				foreach ($templateids_up as $templateid_up => $foo) {
					if (self::checkTemplateUpExistsInTemplateLinks($template_links, $templateid, $templateid_up)) {
						foreach ($template_triggers_up[$templateid_up] as $triggerid_up) {
							foreach ($trigger_dependencies[$triggerid_up] as $triggerid => $foo) {
								if (array_key_exists($templateid, $trigger_hosts[$triggerid]['templateids'])) {
									break 2;
								}
							}
						}

						$triggers = DB::select('triggers', [
							'output' => ['description', 'flags'],
							'triggerids' => [$triggerid, $triggerid_up],
							'preservekeys' => true
						]);

						$templates = DB::select('hosts', [
							'output' => ['host'],
							'hostids' => $templateid_up
						]);

						$error = ($triggers[$triggerid]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL)
							? _('Trigger "%1$s" cannot depend on the trigger "%2$s" from the template "%3$s", because dependencies on triggers from the parent template are not allowed.')
							: _('Trigger prototype "%1$s" cannot depend on the trigger "%2$s" from the template "%3$s", because dependencies on triggers from the parent template are not allowed.');

						self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $triggers[$triggerid]['description'],
							$triggers[$triggerid_up]['description'], $templates[0]['host']
						));
					}
				}
			}
		}
	}

	/**
	 * Check that the triggers-up of the given trigger dependencies are not from the child templates or hosts of the
	 * dependent triggers.
	 *
	 * @param array $trigger_dependencies[<triggerid_up>][<triggerid>]
	 * @param array $trigger_hosts
	 *
	 * @throws APIException
	 */
	private static function checkTriggersUpNotFromChildTemplatesOrHosts(array $trigger_dependencies,
			array $trigger_hosts): void {
		$templateids = [];
		$template_triggers_up = [];
		$dependency_templates = [];

		foreach ($trigger_dependencies as $triggerid_up => $triggerids) {
			$templateids_up = [];

			if (array_key_exists('templateids', $trigger_hosts[$triggerid_up])) {
				foreach ($trigger_hosts[$triggerid_up]['templateids'] as $templateid => $foo) {
					$template_triggers_up[$templateid][] = $triggerid_up;
					$templateids_up[$templateid] = true;
				}
			}
			else {
				foreach ($trigger_hosts[$triggerid_up]['hostids'] as $hostid => $foo) {
					$template_triggers_up[$hostid][] = $triggerid_up;
					$templateids_up[$hostid] = true;
				}
			}

			foreach ($triggerids as $triggerid => $foo) {
				foreach ($trigger_hosts[$triggerid]['templateids'] as $templateid => $foo) {
					if (!array_key_exists($templateid, $dependency_templates)) {
						$dependency_templates[$templateid] = [];
					}

					$dependency_templates[$templateid] += $templateids_up;
				}

				$templateids += $trigger_hosts[$triggerid]['templateids'];
			}
		}

		$template_links = [];

		do {
			$options = [
				'output' => ['hostid', 'templateid'],
				'filter' => ['templateid' => array_keys($templateids)]
			];
			$result = DBselect(DB::makeSql('hosts_templates', $options));

			$templateids = [];

			while ($row = DBfetch($result)) {
				$template_links[$row['templateid']][$row['hostid']] = true;

				if (!array_key_exists($row['hostid'], $template_links)) {
					$templateids[$row['hostid']] = true;
				}
			}
		} while ($templateids);

		if (!$template_links) {
			return;
		}

		// Check if each trigger-up is a trigger from the child template or host of the dependent trigger.
		foreach ($dependency_templates as $templateid => $templateids_up) {
			if (array_key_exists($templateid, $template_links)) {
				foreach ($templateids_up as $templateid_up => $foo) {
					if (self::checkTemplateUpExistsInTemplateLinks($template_links, $templateid, $templateid_up)) {
						foreach ($template_triggers_up[$templateid_up] as $triggerid_up) {
							foreach ($trigger_dependencies[$triggerid_up] as $triggerid => $foo) {
								if (array_key_exists($templateid, $trigger_hosts[$triggerid]['templateids'])) {
									break 2;
								}
							}
						}

						$triggers = DB::select('triggers', [
							'output' => ['description', 'flags'],
							'triggerids' => [$triggerid, $triggerid_up],
							'preservekeys' => true
						]);

						$templates = DB::select('hosts', [
							'output' => ['host', 'status'],
							'hostids' => $templateid_up
						]);

						if ($triggers[$triggerid]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
							$error = ($templates[0]['status'] == HOST_STATUS_TEMPLATE)
								? _('Trigger "%1$s" cannot depend on the trigger "%2$s" from the template "%3$s", because dependencies on triggers from a child template or host are not allowed.')
								: _('Trigger "%1$s" cannot depend on the trigger "%2$s" from the host "%3$s", because dependencies on triggers from a child template or host are not allowed.');
						}
						else {
							$error = ($templates[0]['status'] == HOST_STATUS_TEMPLATE)
								? _('Trigger prototype "%1$s" cannot depend on the trigger "%2$s" from the template "%3$s", because dependencies on triggers from a child template or host are not allowed.')
								: _('Trigger prototype "%1$s" cannot depend on the trigger "%2$s" from the host "%3$s", because dependencies on triggers from a child template or host are not allowed.');
						}

						self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $triggers[$triggerid]['description'],
							$triggers[$triggerid_up]['description'], $templates[0]['host']
						));
					}
				}
			}
		}
	}

	/**
	 * Recursively check if the given template-up exists in the chain of the given template links.
	 *
	 * @param array  $template_links[<templateid>][<templateid_up>]
	 * @param string $templateid
	 * @param string $templateid_up
	 *
	 * @return bool
	 */
	private static function checkTemplateUpExistsInTemplateLinks(array $template_links, string $templateid,
			string $templateid_up): bool {
		if (array_key_exists($templateid_up, $template_links[$templateid])) {
			return true;
		}

		foreach ($template_links[$templateid] as $_templateid => $foo) {
			if (array_key_exists($_templateid, $template_links)
					&& self::checkTemplateUpExistsInTemplateLinks($template_links, $_templateid, $templateid_up)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the triggers-up templates of the given trigger dependencies are linked to child templates of the
	 * dependent triggers.
	 *
	 * @param array $trigger_dependencies[<triggerid_up>][<triggerid>]
	 * @param array $trigger_hosts
	 *
	 * @throws APIException
	 */
	private static function checkTriggersUpTemplatesAreLinkedToChildTemplates(array $trigger_dependencies,
			array $trigger_hosts): void {
		$templateids_up = [];
		$templateids = [];

		foreach ($trigger_dependencies as $triggerid_up => $triggerids) {
			if (!array_key_exists('templateids', $trigger_hosts[$triggerid_up])) {
				unset($trigger_dependencies[$triggerid_up]);
				continue;
			}

			$templateids_up += $trigger_hosts[$triggerid_up]['templateids'];

			foreach ($triggerids as $triggerid => $foo) {
				$templateids += $trigger_hosts[$triggerid]['templateids'];
			}
		}

		$options = [
			'output' => ['templateid', 'hostid'],
			'filter' => [
				'templateid' => array_keys($templateids)
			]
		];
		$result = DBselect(DB::makeSql('hosts_templates', $options));

		$template_links = [];

		while ($row = DBfetch($result)) {
			$template_links[$row['templateid']][$row['hostid']] = [];
		}

		$result = DBselect(
			'SELECT ht.templateid,ht.hostid,htt.templateid AS host_templateid'.
			' FROM hosts_templates ht,hosts_templates htt'.
			' WHERE ht.hostid=htt.hostid'.
				' AND ht.templateid!=htt.templateid'.
				' AND '.dbConditionId('ht.templateid', array_keys($templateids)).
				' AND '.dbConditionId('htt.templateid', array_keys($templateids_up))
		);

		while ($row = DBfetch($result)) {
			$template_links[$row['templateid']][$row['hostid']][$row['host_templateid']] = true;
		}

		foreach ($trigger_dependencies as $triggerid_up => $triggerids) {
			/*
			 * If trigger belongs to more than one template, then it is not possible to link only part of them to
			 * another host or template. That means each template ID of that trigger would have the same hosts
			 * in template links. And vice versa, if at least one of the trigger templates was not found in template
			 * links, then the trigger is not inherited further.
			 */
			$templateid_up = key($trigger_hosts[$triggerid_up]['templateids']);

			foreach ($triggerids as $triggerid => $foo) {
				$templateid = key($trigger_hosts[$triggerid]['templateids']);

				if (!array_key_exists($templateid, $template_links)) {
					continue;
				}

				foreach ($template_links[$templateid] as $hostid => $host_templateids) {
					if (!array_key_exists($templateid_up, $host_templateids)) {
						$triggers = DB::select('triggers', [
							'output' => ['description', 'flags'],
							'triggerids' => [$triggerid, $triggerid_up],
							'preservekeys' => true
						]);

						$hosts = DB::select('hosts', [
							'output' => ['host', 'status'],
							'hostids' => [$templateid_up, $hostid],
							'preservekeys' => true
						]);

						if ($triggers[$triggerid]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
							$error = ($hosts[$hostid]['status'] == HOST_STATUS_TEMPLATE)
								? _('Trigger "%1$s" cannot depend on the trigger "%2$s", because the template "%3$s" is not linked to the template "%4$s".')
								: _('Trigger "%1$s" cannot depend on the trigger "%2$s", because the template "%3$s" is not linked to the host "%4$s".');
						}
						else {
							$error = ($hosts[$hostid]['status'] == HOST_STATUS_TEMPLATE)
								? _('Trigger prototype "%1$s" cannot depend on the trigger "%2$s", because the template "%3$s" is not linked to the template "%4$s".')
								: _('Trigger prototype "%1$s" cannot depend on the trigger "%2$s", because the template "%3$s" is not linked to the host "%4$s".');
						}

						self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $triggers[$triggerid]['description'],
							$triggers[$triggerid_up]['description'], $hosts[$templateid_up]['host'],
							$hosts[$hostid]['host']
						));
					}
				}
			}
		}
	}

	/**
	 * Update the trigger dependencies.
	 *
	 * @param array      $triggers
	 * @param array|null $db_triggers
	 */
	public static function updateDependencies(array &$triggers, array $db_triggers = null): void {
		$ins_trigger_deps = [];
		$del_triggerdepids = [];
		$edit_dependencies = [];

		foreach ($triggers as &$trigger) {
			if (!array_key_exists('dependencies', $trigger)) {
				continue;
			}

			$db_triggers_up = ($db_triggers !== null)
				? array_column($db_triggers[$trigger['triggerid']]['dependencies'], null, 'triggerid')
				: [];

			foreach ($trigger['dependencies'] as &$trigger_up) {
				if (array_key_exists($trigger_up['triggerid'], $db_triggers_up)) {
					$trigger_up['triggerdepid'] = $db_triggers_up[$trigger_up['triggerid']]['triggerdepid'];
					unset($db_triggers_up[$trigger_up['triggerid']]);
				}
				else {
					$ins_trigger_deps[] = [
						'triggerid_down' => $trigger['triggerid'],
						'triggerid_up' => $trigger_up['triggerid']
					];

					$edit_dependencies[$trigger['triggerid']][$trigger_up['triggerid']] = true;
				}
			}
			unset($trigger_up);

			foreach ($db_triggers_up as $db_trigger_up) {
				$del_triggerdepids[] = $db_trigger_up['triggerdepid'];

				$edit_dependencies[$trigger['triggerid']][$db_trigger_up['triggerid']] = false;
			}
		}
		unset($trigger);

		if ($del_triggerdepids) {
			DB::delete('trigger_depends', ['triggerdepid' => $del_triggerdepids]);
		}

		if ($ins_trigger_deps) {
			$triggerdepids = DB::insertBatch('trigger_depends', $ins_trigger_deps);
		}

		foreach ($triggers as &$trigger) {
			if (!array_key_exists('dependencies', $trigger)) {
				continue;
			}

			foreach ($trigger['dependencies'] as &$trigger_up) {
				if (!array_key_exists('triggerdepid', $trigger_up)) {
					$trigger_up['triggerdepid'] = array_shift($triggerdepids);
				}
			}
			unset($trigger_up);
		}
		unset($trigger);

		if ($edit_dependencies) {
			$edit_dependencies = self::getTemplatedDependencies($edit_dependencies);

			if ($edit_dependencies) {
				self::inheritDependencies($edit_dependencies);
			}
		}
	}

	/**
	 * Filter for dependencies whose dependent triggers belong to templates that are further linked
	 * to some templates or hosts.
	 *
	 * @param array $edit_dependencies[<triggerid>][<triggerid_up>]
	 *
	 * @return array
	 */
	protected static function getTemplatedDependencies(array $edit_dependencies): array {
		$triggerids = DBfetchColumn(DBselect(
			'SELECT DISTINCT f.triggerid'.
			' FROM functions f,items i,hosts h'.
			' WHERE f.itemid=i.itemid'.
				' AND i.hostid=h.hostid'.
				' AND '.dbConditionId('f.triggerid', array_keys($edit_dependencies)).
				' AND '.dbConditionInt('h.status', [HOST_STATUS_TEMPLATE]).
				' AND EXISTS('.
					'SELECT NULL'.
					' FROM hosts_templates ht'.
					' WHERE ht.templateid=i.hostid'.
				')'
		), 'triggerid');

		return array_intersect_key($edit_dependencies, array_flip($triggerids));
	}

	/**
	 * Inherit the given trigger dependencies.
	 *
	 * @param array $edit_dependencies[<triggerid>][<triggerid_up>]
	 * @param array $hostids
	 */
	protected static function inheritDependencies(array $edit_dependencies, array $hostids = null): void {
		$all_triggerids = $edit_dependencies;
		$all_triggerids_up = [];

		foreach ($edit_dependencies as $triggerids_up) {
			$all_triggerids += $triggerids_up;
			$all_triggerids_up += $triggerids_up;
		}

		$hostids_condition = ($hostids !== null) ? ' AND '.dbConditionId('i.hostid', $hostids) : '';

		$result = DBselect(
			'SELECT DISTINCT t.templateid,t.triggerid,t.flags,i.hostid'.
			' FROM triggers t,functions f,items i'.
			' WHERE t.triggerid=f.triggerid'.
				' AND f.itemid=i.itemid'.
				' AND '.dbConditionId('t.templateid', array_keys($all_triggerids)).
				$hostids_condition
		);

		$trigger_flags = [];
		$trigger_links = [];
		$tpl_triggerids_up = [];

		while ($row = DBfetch($result)) {
			$trigger_links[$row['templateid']][$row['hostid']] = $row['triggerid'];
			$trigger_flags[$row['triggerid']] = $row['flags'];

			if (array_key_exists($row['templateid'], $all_triggerids_up)) {
				$tpl_triggerids_up[$row['templateid']] = true;
			}
		}

		$del_triggerdepids = [];
		$_edit_dependencies = [];

		if ($tpl_triggerids_up) {
			if ($hostids === null) {
				$result = DBselect(
					'SELECT DISTINCT t.triggerid,td.triggerid_up,td.triggerdepid,i.hostid'.
					' FROM triggers t,trigger_depends td,triggers tt,functions f,items i'.
					' WHERE t.triggerid=td.triggerid_down'.
						' AND td.triggerid_up=tt.triggerid'.
						' AND tt.triggerid=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND '.dbConditionId('t.templateid', array_keys($edit_dependencies)).
						' AND '.dbConditionId('tt.templateid', array_keys($tpl_triggerids_up))
				);
			}
			else {
				$result = DBselect(
					'SELECT DISTINCT t.triggerid,td.triggerid_up,td.triggerdepid,ii.hostid'.
					' FROM triggers t,functions f,items i,trigger_depends td,triggers tt,functions ff,items ii'.
					' WHERE t.triggerid=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND t.triggerid=td.triggerid_down'.
						' AND td.triggerid_up=tt.triggerid'.
						' AND tt.triggerid=ff.triggerid'.
						' AND ff.itemid=ii.itemid'.
						' AND '.dbConditionId('t.templateid', array_keys($edit_dependencies)).
						' AND '.dbConditionId('i.hostid', $hostids).
						' AND '.dbConditionId('tt.templateid', array_keys($tpl_triggerids_up))
				);
			}

			$tpl_child_dependencies = [];

			while ($row = DBfetch($result)) {
				$tpl_child_dependencies[$row['triggerid']][$row['triggerid_up']][$row['hostid']] = $row['triggerdepid'];
			}

			foreach ($edit_dependencies as $triggerid => $triggerids_up) {
				$triggerids_up = array_intersect_key($triggerids_up, $tpl_triggerids_up);

				if (!$triggerids_up) {
					continue;
				}

				foreach ($trigger_links[$triggerid] as $hostid => $child_triggerid) {
					$upd_child_triggerids_up = [];

					if (array_key_exists($child_triggerid, $tpl_child_dependencies)) {
						foreach ($tpl_child_dependencies[$child_triggerid] as $child_triggerid_up => $hostids_up) {
							$hostid_up = key($hostids_up);
							$triggerdepid = reset($hostids_up);

							if (in_array($child_triggerid_up, $upd_child_triggerids_up)
									|| in_array($triggerdepid, $del_triggerdepids)) {
								continue;
							}

							foreach ($triggerids_up as $triggerid_up => $add) {
								if (array_key_exists($hostid_up, $trigger_links[$triggerid_up])
										&& bccomp($child_triggerid_up, $trigger_links[$triggerid_up][$hostid_up]) == 0) {
									if ($add) {
										$upd_child_triggerids_up[] = $child_triggerid_up;
									}
									else {
										$del_triggerdepids[] = $hostids_up[$hostid_up];

										$_edit_dependencies[$child_triggerid][$child_triggerid_up] = false;
									}
								}
							}
						}
					}

					foreach ($triggerids_up as $triggerid_up => $add) {
						if ($add) {
							$child_triggerid_up = $trigger_links[$triggerid_up][$hostid];

							if (!in_array($child_triggerid_up, $upd_child_triggerids_up)) {
								$_edit_dependencies[$child_triggerid][$child_triggerid_up] = true;
							}
						}
					}
				}
			}
		}

		$host_triggerids_up = array_diff_key($all_triggerids_up, $tpl_triggerids_up);

		if ($host_triggerids_up) {
			if ($hostids === null) {
				$result = DBselect(
					'SELECT DISTINCT t.triggerid,td.triggerid_up,td.triggerdepid'.
					' FROM triggers t,trigger_depends td'.
					' WHERE t.triggerid=td.triggerid_down'.
						' AND '.dbConditionId('t.templateid', array_keys($edit_dependencies)).
						' AND '.dbConditionId('td.triggerid_up', array_keys($host_triggerids_up))
				);
			}
			else {
				$result = DBselect(
					'SELECT DISTINCT t.triggerid,td.triggerid_up,td.triggerdepid'.
					' FROM triggers t,functions f,items i,trigger_depends td'.
					' WHERE t.triggerid=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND t.triggerid=td.triggerid_down'.
						' AND '.dbConditionId('t.templateid', array_keys($edit_dependencies)).
						' AND '.dbConditionId('i.hostid', $hostids).
						' AND '.dbConditionId('td.triggerid_up', array_keys($host_triggerids_up))
				);
			}

			$host_child_dependencies = [];

			while ($row = DBfetch($result)) {
				$host_child_dependencies[$row['triggerid']][$row['triggerid_up']] = $row['triggerdepid'];
			}

			foreach ($edit_dependencies as $triggerid => $triggerids_up) {
				$triggerids_up = array_intersect_key($triggerids_up, $host_triggerids_up);

				if (!$triggerids_up) {
					continue;
				}

				foreach ($trigger_links[$triggerid] as $hostid => $child_triggerid) {
					$upd_child_triggerids_up = [];

					if (array_key_exists($child_triggerid, $host_child_dependencies)) {
						foreach ($host_child_dependencies[$child_triggerid] as $child_triggerid_up => $triggerdepid) {
							if (array_key_exists($child_triggerid_up, $triggerids_up)) {
								$add = $triggerids_up[$child_triggerid_up];

								if ($add) {
									$upd_child_triggerids_up[] = $child_triggerid_up;
								}
								else {
									$del_triggerdepids[] = $triggerdepid;

									$_edit_dependencies[$child_triggerid][$child_triggerid_up] = false;
								}
							}
						}
					}

					foreach ($triggerids_up as $triggerid_up => $add) {
						if ($add && !in_array($triggerid_up, $upd_child_triggerids_up)) {
							$_edit_dependencies[$child_triggerid][$triggerid_up] = true;
						}
					}
				}
			}
		}

		$ins_dependencies = [];
		$del_dependencies = [];
		$ins_trigger_deps = [];

		foreach ($_edit_dependencies as $triggerid => $triggerids_up) {
			foreach ($triggerids_up as $triggerid_up => $add) {
				if ($add) {
					if ($trigger_flags[$triggerid] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
						if (array_key_exists($triggerid_up, $trigger_flags)
								&& $trigger_flags[$triggerid_up] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
							$ins_dependencies[$triggerid_up][$triggerid] = true;
						}
					}
					else {
						$ins_dependencies[$triggerid_up][$triggerid] = true;
					}

					$ins_trigger_deps[] = [
						'triggerid_down' => $triggerid,
						'triggerid_up' => $triggerid_up
					];
				}
				else {
					$del_dependencies[$triggerid_up][$triggerid] = true;
				}
			}
		}

		if ($ins_dependencies) {
			self::checkCircularDependencies($ins_dependencies, $del_dependencies, true);
		}

		if ($del_triggerdepids) {
			DB::delete('trigger_depends', ['triggerdepid' => $del_triggerdepids]);
		}

		if ($ins_trigger_deps) {
			DB::insertBatch('trigger_depends', $ins_trigger_deps);
		}

		if ($_edit_dependencies) {
			$_edit_dependencies = self::getTemplatedDependencies($_edit_dependencies);

			if ($_edit_dependencies) {
				self::inheritDependencies($_edit_dependencies);
			}
		}
	}

	/**
	 * Inherit the trigger dependencies of the given templates to the given hosts.
	 *
	 * @param array $templateids
	 * @param array $hostids
	 */
	public static function syncTemplateDependencies(array $templateids, array $hostids): void {
		$result = DBselect(
			'SELECT DISTINCT f.triggerid,td.triggerid_up'.
			' FROM items i,functions f,trigger_depends td'.
			' WHERE i.itemid=f.itemid'.
				' AND f.triggerid=td.triggerid_down'.
				' AND '.dbConditionId('i.hostid', $templateids)
		);

		$edit_dependencies = [];

		while ($row = DBfetch($result)) {
			$edit_dependencies[$row['triggerid']][$row['triggerid_up']] = true;
		}

		if ($edit_dependencies) {
			$edit_dependencies = self::getTemplatedDependencies($edit_dependencies);

			if ($edit_dependencies) {
				self::inheritDependencies($edit_dependencies, $hostids);
			}
		}
	}
}
