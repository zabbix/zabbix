<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	 * Updates the children of the trigger on the given hosts and propagates the inheritance to all child hosts.
	 * If the given trigger was assigned to a different template or a host, all of the child triggers, that became
	 * obsolete will be deleted.
	 *
	 * @param array  $trigger
	 * @param string $trigger['triggerid']
	 * @param string $trigger['description']
	 * @param string $trigger['expression']                  exploded expression
	 * @param int    $trigger['recovery mode']
	 * @param string $trigger['recovery_expression']         exploded recovery expression
	 * @param array  $trigger['dependencies']                (optional)
	 * @param string $trigger['dependencies'][]['triggerid']
	 * @param array  $hostids
	 */
	protected function inherit(array $trigger, array $hostids = null) {
		$templates = API::Template()->get([
			'output' => [],
			'triggerids' => [$trigger['triggerid']],
			'preservekeys' => true,
			'nopermissions' => true
		]);

		if (!$templates) {
			// nothing to inherit, just exit
			return;
		}

		// fetch all of the child hosts
		$childHosts = API::Host()->get([
			'output' => ['hostid', 'host'],
			'hostids' => $hostids,
			'templateids' => array_keys($templates),
			'preservekeys' => true,
			'nopermissions' => true,
			'templated_hosts' => true
		]);

		foreach ($childHosts as $childHost) {
			// update the child trigger on the child host
			$new_trigger = $this->inheritOnHost($trigger, $childHost);

			// propagate the trigger inheritance to all child hosts
			$this->inherit($new_trigger);
		}
	}

	/**
	 * Updates the child of the templated trigger on the given host. Trigger inheritance will not propagate to
	 * child hosts.
	 *
	 * @param array  $trigger
	 * @param string $trigger['triggerid']
	 * @param string $trigger['description']
	 * @param string $trigger['expression']
	 * @param int    $trigger['recovery mode']
	 * @param string $trigger['recovery_expression']
	 * @param array  $trigger['dependencies']                (optional)
	 * @param string $trigger['dependencies'][]['triggerid']
	 * @param array  $host
	 * @param string $host['hostid']
	 * @param string $host['host']
	 *
	 * @return array|mixed  the updated child trigger
	 */
	protected function inheritOnHost(array $trigger, array $host) {
		$class = get_class($this);

		$triggerid = $trigger['triggerid'];
		$trigger['templateid'] = $trigger['triggerid'];
		unset($trigger['triggerid']);

		if (array_key_exists('dependencies', $trigger)) {
			$deps = zbx_objectValues($trigger['dependencies'], 'triggerid');
			$trigger['dependencies'] = replace_template_dependencies($deps, $host['hostid']);
		}

		$expressionData = new CTriggerExpression();

		// expression: {template:item.func()} => {host:item.func()}
		if (!$expressionData->parse($trigger['expression'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $expressionData->error);
		}

		$exprPart = end($expressionData->expressions);
		do {
			$trigger['expression'] = substr_replace($trigger['expression'],
					'{'.$host['host'].':'.$exprPart['item'].'.'.$exprPart['function'].'}',
					$exprPart['pos'], strlen($exprPart['expression'])
			);
		}
		while ($exprPart = prev($expressionData->expressions));

		// recovery_expression: {template:item.func()} => {host:item.func()}
		if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
			if (!$expressionData->parse($trigger['recovery_expression'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $expressionData->error);
			}

			$exprPart = end($expressionData->expressions);
			do {
				$trigger['recovery_expression'] = substr_replace($trigger['recovery_expression'],
						'{'.$host['host'].':'.$exprPart['item'].'.'.$exprPart['function'].'}',
						$exprPart['pos'], strlen($exprPart['expression'])
				);
			}
			while ($exprPart = prev($expressionData->expressions));
		}

		$options = [
			'output' => ['triggerid', 'description', 'expression', 'recovery_mode', 'recovery_expression', 'url',
				'status', 'priority', 'comments', 'type', 'templateid', 'correlation_mode', 'correlation_tag',
				'manual_close'
			],
			'hostids' => $host['hostid'],
			'filter' => ['templateid' => $trigger['templateid']],
			'nopermissions' => true
		];

		if ($class === 'CTriggerPrototype') {
			$options['selectDiscoveryRule'] = ['itemid'];
		}

		// check if a child trigger already exists on the host
		$_db_triggers = CMacrosResolverHelper::resolveTriggerExpressions($this->get($options),
			['sources' => ['expression', 'recovery_expression']]
		);

		// yes we have a child trigger, just update it
		if ($_db_triggers) {
			$trigger['triggerid'] = $_db_triggers[0]['triggerid'];

			$this->checkIfExistsOnHost($trigger);
			$db_trigger = $_db_triggers[0];
		}
		// no child trigger found
		else {
			$options['filter'] = ['description' => $trigger['description']];

			// look for a trigger with the same description and expression
			$_db_triggers = CMacrosResolverHelper::resolveTriggerExpressions($this->get($options),
				['sources' => ['expression', 'recovery_expression']]
			);

			foreach ($_db_triggers as $_db_trigger) {
				if ($_db_trigger['expression'] === $trigger['expression']
						&& $_db_trigger['recovery_expression'] === $trigger['recovery_expression']) {
					// we have a trigger with the same description and expressions as the parent
					// convert it to a template trigger
					$trigger['triggerid'] = $_db_trigger['triggerid'];
					$db_trigger = $_db_trigger;
					break;
				}
			}
		}

		if (array_key_exists('triggerid', $trigger)) {
			$db_trigger['tags'] = API::getApiService()->select('trigger_tag', [
				'output' => ['triggertagid', 'tag', 'value'],
				'filter' => ['triggerid' => $db_trigger['triggerid']]
			]);

			$this->updateReal([$trigger], [$db_trigger]);
		}
		else {
			$_db_triggers = $this->get([
				'output' => ['url', 'status', 'priority', 'comments', 'type', 'correlation_mode', 'correlation_tag'],
				'triggerids' => [$triggerid]
			]);

			foreach ($_db_triggers[0] as $field_name => $value) {
				if ($field_name !== 'triggerid' && !array_key_exists($field_name, $trigger)) {
					$trigger[$field_name] = $value;
				}
			}

			$triggers = [$trigger];
			$this->createReal($triggers);
			$trigger = $triggers[0];
		}

		return $trigger;
	}

	/**
	 * Validate trigger tags.
	 *
	 * @param array $trigger	Trigger.
	 *
	 * @return array
	 *
	 * @throws APIException if at least one trigger exists.
	 */
	protected function checkTriggerTags(array $trigger) {
		if (!array_key_exists('tags', $trigger)) {
			return $trigger;
		}

		foreach ($trigger['tags'] as &$tag) {
			if (!array_key_exists('tag', $tag)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Field "%1$s" is mandatory.', 'tag'));
			}

			if (!is_string($tag['tag'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'tag', _('a character string is expected'))
				);
			}

			if ($tag['tag'] === '') {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'tag', _('cannot be empty'))
				);
			}

			if (!array_key_exists('value', $tag)) {
				$tag['value'] = '';
			}

			if (!is_string($tag['value'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'value', _('a character string is expected'))
				);
			}
		}
		unset($tag);

		// Check tag and value duplicates in input data.
		$tag = CArrayHelper::findDuplicate($trigger['tags'], 'tag', 'value');
		if ($tag !== null) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Tag "%1$s" with value "%2$s" already exists.', $tag['tag'], $tag['value'])
			);
		}

		return $trigger;
	}

	/**
	 * Checks that no trigger with the same description and expression as $trigger exist on the given host.
	 * Assumes the given trigger is valid.
	 *
	 * @param array  $trigger
	 * @param string $trigger['triggerid']           (optional)
	 * @param string $trigger['description']
	 * @param string $trigger['expression']
	 * @param string $trigger['recovery_expression']
	 *
	 * @throws APIException if at least one trigger exists
	 */
	protected function checkIfExistsOnHost($trigger) {
		switch (get_class($this)) {
			case 'CTrigger':
				$expressionData = new CTriggerExpression(['lldmacros' => false]);
				$error_already_exists = _('Trigger "%1$s" already exists on "%2$s".');
				break;

			case 'CTriggerPrototype':
				$expressionData = new CTriggerExpression();
				$error_already_exists = _('Trigger prototype "%1$s" already exists on "%2$s".');
				break;

			default:
				self::exception(ZBX_API_ERROR_INTERNAL, _('Internal error.'));
		}

		$expressionData->parse($trigger['expression']);

		$_db_triggers = $this->get([
			'output' => ['expression', 'recovery_expression'],
			'filter' => [
				'host' => $expressionData->getHosts()[0],
				'description' => $trigger['description'],
				'flags' => null
			],
			'preservekeys' => true,
			'nopermissions' => true
		]);

		$_db_triggers = CMacrosResolverHelper::resolveTriggerExpressions($_db_triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		if (array_key_exists('triggerid', $trigger)) {
			unset($_db_triggers[$trigger['triggerid']]);
		}

		foreach ($_db_triggers as $_db_trigger) {
			if ($_db_trigger['expression'] === $trigger['expression']
					&& $_db_trigger['recovery_expression'] === $trigger['recovery_expression']) {

				self::exception(ZBX_API_ERROR_PARAMETERS,
					_params($error_already_exists, [$trigger['description'], $expressionData->getHosts()[0]])
				);
			}
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$triggerids = array_keys($result);

		// adding groups
		if ($options['selectGroups'] !== null && $options['selectGroups'] != API_OUTPUT_COUNT) {
			$res = DBselect(
				'SELECT f.triggerid,hg.groupid'.
					' FROM functions f,items i,hosts_groups hg'.
					' WHERE '.dbConditionInt('f.triggerid', $triggerids).
					' AND f.itemid=i.itemid'.
					' AND i.hostid=hg.hostid'
			);
			$relationMap = new CRelationMap();
			while ($relation = DBfetch($res)) {
				$relationMap->addRelation($relation['triggerid'], $relation['groupid']);
			}

			$groups = API::HostGroup()->get([
				'output' => $options['selectGroups'],
				'groupids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $groups, 'groups');
		}

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
	 * Validate trigger expressions.
	 *
	 * @param array  $trigger
	 * @param string $trigger['expression']
	 * @param int    $trigger['recovery_mode']
	 * @param string $trigger['recovery_expression']
	 *
	 * @throws APIException if validation failed.
	 */
	protected function checkTriggerExpressions(array $trigger) {
		switch (get_class($this)) {
			case 'CTrigger':
				$expressionData = new CTriggerExpression(['lldmacros' => false]);
				break;

			case 'CTriggerPrototype':
				$expressionData = new CTriggerExpression();
				break;

			default:
				self::exception(ZBX_API_ERROR_INTERNAL, _('Internal error.'));
		}

		// expression
		if (!$expressionData->parse($trigger['expression'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $expressionData->error);
		}

		if (!$expressionData->expressions) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_('Trigger expression must contain at least one host:key reference.')
			);
		}

		// recovery_expression
		if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
			if (!$expressionData->parse($trigger['recovery_expression'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $expressionData->error);
			}

			if (!$expressionData->expressions) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_('Trigger recovery expression must contain at least one host:key reference.')
				);
			}
		}
	}

	/**
	 * Validate trigger to be created.
	 *
	 * @param array  $triggers                          [IN/OUT]
	 * @param array  $triggers[]['description']         [IN] (optional)
	 * @param string $triggers[]['expression']          [IN] (optional)
	 * @param int    $triggers[]['recovery_mode']       [IN/OUT] (optional)
	 * @param string $triggers[]['recovery_expression'] [IN/OUT] (optional)
	 * @param string $triggers[]['url']                 [IN] (optional)
	 * @param int    $triggers[]['status']              [IN] (optional)
	 * @param int    $triggers[]['priority']            [IN] (optional)
	 * @param string $triggers[]['comments']            [IN] (optional)
	 * @param int    $triggers[]['type']                [IN] (optional)
	 * @param int    $triggers[]['correlation_mode']    [IN/OUT] (optional)
	 * @param string $triggers[]['correlation_tag']     [IN/OUT] (optional)
	 *
	 * @throws APIException if validation failed.
	 */
	protected function validateCreate(array &$triggers) {
		if (!$triggers) {
			return;
		}

		switch (get_class($this)) {
			case 'CTrigger':
				$error_wrong_fields = _('Wrong fields for trigger.');
				$error_cannot_set = _('Cannot set "%1$s" for trigger "%2$s".');
				break;

			case 'CTriggerPrototype':
				$error_wrong_fields = _('Wrong fields for trigger prototype.');
				$error_cannot_set = _('Cannot set "%1$s" for trigger prototype "%2$s".');
				break;

			default:
				self::exception(ZBX_API_ERROR_INTERNAL, _('Internal error.'));
		}

		$triggerDbFields = [
			'description' => null,
			'expression' => null
		];
		$read_only_fields = ['triggerid', 'value', 'lastchange', 'error', 'templateid', 'state', 'flags'];

		foreach ($triggers as &$trigger) {
			if (!check_db_fields($triggerDbFields, $trigger)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error_wrong_fields);
			}

			if (array_key_exists('url', $trigger) && $trigger['url'] && !CHtmlUrlValidator::validate($trigger['url'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong value for url field.'));
			}

			$this->checkNoParameters($trigger, $read_only_fields, $error_cannot_set, $trigger['description']);

			if (!array_key_exists('recovery_mode', $trigger)) {
				$trigger['recovery_mode'] = ZBX_RECOVERY_MODE_EXPRESSION;
			}

			switch ($trigger['recovery_mode']) {
				case ZBX_RECOVERY_MODE_EXPRESSION:
				case ZBX_RECOVERY_MODE_NONE:
					if (!array_key_exists('recovery_expression', $trigger)) {
						$trigger['recovery_expression'] = '';
					}

					if ($trigger['recovery_expression'] !== '') {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'recovery_expression', _('should be empty'))
						);
					}
					break;

				case ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION:
					if (!array_key_exists('recovery_expression', $trigger) || $trigger['recovery_expression'] === '') {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'recovery_expression', _('cannot be empty'))
						);
					}
					break;
			}

			if (!array_key_exists('correlation_mode', $trigger)) {
				$trigger['correlation_mode'] = ZBX_TRIGGER_CORRELATION_NONE;
			}

			switch ($trigger['correlation_mode']) {
				case ZBX_TRIGGER_CORRELATION_NONE:
					if (!array_key_exists('correlation_tag', $trigger)) {
						$trigger['correlation_tag'] = '';
					}

					if ($trigger['correlation_tag'] !== '') {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'correlation_tag', _('should be empty'))
						);
					}
					break;

				case ZBX_TRIGGER_CORRELATION_TAG:
					if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_NONE) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
							'correlation_mode', _s('unexpected value "%1$s"', $trigger['correlation_mode'])
						));
					}

					if (!array_key_exists('correlation_tag', $trigger) || $trigger['correlation_tag'] === '') {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'correlation_tag', _('cannot be empty'))
						);
					}
					break;

				default:
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'correlation_mode', _s('unexpected value "%1$s"', $trigger['correlation_mode'])
					));
			}

			if (array_key_exists('manual_close', $trigger)
					&& $trigger['manual_close'] != ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED
					&& $trigger['manual_close'] != ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'manual_close', _s('unexpected value "%1$s"', $trigger['manual_close'])
				));
			}

			$this->checkTriggerExpressions($trigger);
			$this->checkIfExistsOnHost($trigger);
			$trigger = $this->checkTriggerTags($trigger);
		}
		unset($trigger);
	}

	/**
	 * Validate trigger to be updated.
	 *
	 * @param array  $triggers                                   [IN/OUT]
	 * @param array  $triggers[]['triggerid']                    [IN]
	 * @param array  $triggers[]['description']                  [IN/OUT] (optional)
	 * @param string $triggers[]['expression']                   [IN/OUT] (optional)
	 * @param int    $triggers[]['recovery_mode']                [IN/OUT] (optional)
	 * @param string $triggers[]['recovery_expression']          [IN/OUT] (optional)
	 * @param string $triggers[]['url']                          [IN] (optional)
	 * @param int    $triggers[]['status']                       [IN] (optional)
	 * @param int    $triggers[]['priority']                     [IN] (optional)
	 * @param string $triggers[]['comments']                     [IN] (optional)
	 * @param int    $triggers[]['type']                         [IN] (optional)
	 * @param int    $triggers[]['correlation_mode']             [IN/OUT] (optional)
	 * @param string $triggers[]['correlation_tag']              [IN/OUT] (optional)
	 * @param array  $db_triggers                                [OUT]
	 * @param array  $db_triggers[<tnum>]['triggerid']           [OUT]
	 * @param array  $db_triggers[<tnum>]['description']         [OUT]
	 * @param string $db_triggers[<tnum>]['expression']          [OUT]
	 * @param int    $db_triggers[<tnum>]['recovery_mode']       [OUT]
	 * @param string $db_triggers[<tnum>]['recovery_expression'] [OUT]
	 * @param string $db_triggers[<tnum>]['url']                 [OUT]
	 * @param int    $db_triggers[<tnum>]['status']              [OUT]
	 * @param int    $db_triggers[<tnum>]['priority']            [OUT]
	 * @param string $db_triggers[<tnum>]['comments']            [OUT]
	 * @param int    $db_triggers[<tnum>]['type']                [OUT]
	 * @param string $db_triggers[<tnum>]['templateid']          [OUT]
	 * @param int    $db_triggers[<tnum>]['correlation_mode']    [IN/OUT]
	 * @param string $db_triggers[<tnum>]['correlation_tag']     [IN/OUT]
	 *
	 * @throws APIException if validation failed.
	 */
	protected function validateUpdate(array &$triggers, array &$db_triggers) {
		if (!$triggers) {
			return;
		}

		$class = get_class($this);

		switch ($class) {
			case 'CTrigger':
				$error_wrong_fields = _('Wrong fields for trigger.');
				$error_cannot_update = _('Cannot update "%1$s" for trigger "%2$s".');
				$error_cannot_update_tmpl = _('Cannot update "%1$s" for templated trigger "%2$s".');
				break;

			case 'CTriggerPrototype':
				$error_wrong_fields = _('Wrong fields for trigger prototype.');
				$error_cannot_update = _('Cannot update "%1$s" for trigger prototype "%2$s".');
				$error_cannot_update_tmpl = _('Cannot update "%1$s" for templated trigger prototype "%2$s".');
				break;

			default:
				self::exception(ZBX_API_ERROR_INTERNAL, _('Internal error.'));
		}

		$triggerDbFields = ['triggerid' => null];
		$read_only_fields = ['value', 'lastchange', 'error', 'templateid', 'state', 'flags'];
		$read_only_fields_tmpl = ['description', 'expression', 'recovery_mode', 'recovery_expression',
			'correlation_mode', 'correlation_tag', 'manual_close'
		];

		foreach ($triggers as $trigger) {
			if (!check_db_fields($triggerDbFields, $trigger)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error_wrong_fields);
			}

			if (array_key_exists('url', $trigger) && $trigger['url'] && !CHtmlUrlValidator::validate($trigger['url'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong value for url field.'));
			}
		}

		$options = [
			'output' => ['triggerid', 'description', 'expression', 'url', 'status', 'priority', 'comments', 'type',
				'templateid', 'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag',
				'manual_close'
			],
			'selectDependencies' => ['triggerid'],
			'triggerids' => zbx_objectValues($triggers, 'triggerid'),
			'editable' => true,
			'preservekeys' => true
		];

		if ($class === 'CTrigger') {
			$options['output'][] = 'flags';
		}

		if ($class === 'CTriggerPrototype') {
			$options['selectDiscoveryRule'] = ['itemid'];
		}

		$_db_triggers = CMacrosResolverHelper::resolveTriggerExpressions($this->get($options),
			['sources' => ['expression', 'recovery_expression']]
		);

		if ($class === 'CTrigger') {
			// Discovered fields, except status, cannot be updated.
			$updateDiscoveredValidator = new CUpdateDiscoveredValidator([
				'allowed' => ['triggerid', 'status'],
				'messageAllowedField' => _('Cannot update "%2$s" for a discovered trigger "%1$s".')
			]);
		}

		$_db_trigger_tags = API::getApiService()->select('trigger_tag', [
			'output' => ['triggertagid', 'triggerid', 'tag', 'value'],
			'filter' => ['triggerid' => array_keys($_db_triggers)],
			'preservekeys' => true
		]);

		$_db_triggers = $this->createRelationMap($_db_trigger_tags, 'triggerid', 'triggertagid')
			->mapMany($_db_triggers, $_db_trigger_tags, 'tags');

		foreach ($triggers as $tnum => &$trigger) {
			// check permissions
			if (!array_key_exists($trigger['triggerid'], $_db_triggers)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
			$_db_trigger = $_db_triggers[$trigger['triggerid']];

			$description = array_key_exists('description', $trigger)
				? $trigger['description']
				: $_db_trigger['description'];

			$this->checkNoParameters($trigger, $read_only_fields, $error_cannot_update, $description);
			if ($_db_trigger['templateid'] != 0) {
				$this->checkNoParameters($trigger, $read_only_fields_tmpl, $error_cannot_update_tmpl, $description);
			}

			if ($class === 'CTrigger') {
				$updateDiscoveredValidator->setObjectName($description);
				$this->checkPartialValidator($trigger, $updateDiscoveredValidator, $_db_trigger);
			}

			if (array_key_exists('recovery_mode', $trigger)) {
				switch ($trigger['recovery_mode']) {
					case ZBX_RECOVERY_MODE_NONE:
						if (!array_key_exists('correlation_mode', $trigger)) {
							$trigger['correlation_mode'] = ZBX_TRIGGER_CORRELATION_NONE;
						}
						// break; is not missing here

					case ZBX_RECOVERY_MODE_EXPRESSION:
						if (!array_key_exists('recovery_expression', $trigger)) {
							$trigger['recovery_expression'] = '';
						}
						break;
				}
			}

			if (array_key_exists('correlation_mode', $trigger) && !array_key_exists('correlation_tag', $trigger)) {
				switch ($trigger['correlation_mode']) {
					case ZBX_TRIGGER_CORRELATION_NONE:
						$trigger['correlation_tag'] = '';
						break;
				}
			}

			$field_names = ['description', 'expression', 'recovery_mode', 'recovery_expression', 'correlation_mode',
				'correlation_tag', 'manual_close'
			];
			foreach ($field_names as $field_name) {
				if (!array_key_exists($field_name, $trigger)) {
					$trigger[$field_name] = $_db_trigger[$field_name];
				}
			}

			switch ($trigger['recovery_mode']) {
				case ZBX_RECOVERY_MODE_NONE:
					if ($trigger['correlation_mode'] != ZBX_TRIGGER_CORRELATION_NONE) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
							'correlation_mode', _s('unexpected value "%1$s"', $trigger['correlation_mode'])
						));
					}
					// break; is not missing here

				case ZBX_RECOVERY_MODE_EXPRESSION:
					if ($trigger['recovery_expression'] !== '') {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'recovery_expression', _('should be empty'))
						);
					}
					break;

				case ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION:
					if ($trigger['recovery_expression'] === '') {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'recovery_expression', _('cannot be empty'))
						);
					}
					break;
			}

			switch ($trigger['correlation_mode']) {
				case ZBX_TRIGGER_CORRELATION_NONE:
					if ($trigger['correlation_tag'] !== '') {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'correlation_tag', _('should be empty'))
						);
					}
					break;

				case ZBX_TRIGGER_CORRELATION_TAG:
					if ($trigger['correlation_tag'] === '') {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'correlation_tag', _('cannot be empty'))
						);
					}
					break;

				default:
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'correlation_mode', _s('unexpected value "%1$s"', $trigger['correlation_mode'])
					));
			}

			if (array_key_exists('manual_close', $trigger)
					&& $trigger['manual_close'] != ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED
					&& $trigger['manual_close'] != ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'manual_close', _s('unexpected value "%1$s"', $trigger['manual_close'])
				));
			}

			$expressions_changed = ($trigger['expression'] !== $_db_trigger['expression']
				|| $trigger['recovery_expression'] !== $_db_trigger['recovery_expression']);

			if ($expressions_changed) {
				$this->checkTriggerExpressions($trigger);
			}

			if ($expressions_changed || $trigger['description'] !== $_db_trigger['description']) {
				$this->checkIfExistsOnHost($trigger);
			}

			$db_triggers[$tnum] = $_db_trigger;

			$trigger = $this->checkTriggerTags($trigger);
		}
		unset($trigger);
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
	 *
	 * @throws APIException
	 */
	protected function createReal(array &$triggers) {
		if (!$triggers) {
			return;
		}

		$class = get_class($this);

		switch ($class) {
			case 'CTrigger':
				$resource = AUDIT_RESOURCE_TRIGGER;
				break;

			case 'CTriggerPrototype':
				$resource = AUDIT_RESOURCE_TRIGGER_PROTOTYPE;
				break;

			default:
				self::exception(ZBX_API_ERROR_INTERNAL, _('Internal error.'));
		}

		$new_triggers = $triggers;
		$new_functions = [];
		$triggers_functions = [];
		$new_tags = [];
		$this->implode_expressions($new_triggers, null, $triggers_functions);

		$triggerid = DB::reserveIds('triggers', count($new_triggers));

		foreach ($new_triggers as $tnum => &$new_trigger) {
			$new_trigger['triggerid'] = $triggerid;
			$triggers[$tnum]['triggerid'] = $triggerid;

			foreach ($triggers_functions[$tnum] as $trigger_function) {
				$trigger_function['triggerid'] = $triggerid;
				$new_functions[] = $trigger_function;
			}

			if ($class === 'CTriggerPrototype') {
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
		DB::insert('functions', $new_functions, false);

		if ($new_tags) {
			DB::insert('trigger_tag', $new_tags);
		}

		foreach ($triggers as $trigger) {
			add_audit_ext(AUDIT_ACTION_ADD, $resource, $trigger['triggerid'], $trigger['description'], null, null,
				null
			);
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
	 * @param array  $db_triggers[<tnum>]['discoveryRule']           [IN] For trigger prorotypes only.
	 * @param string $db_triggers[<tnum>]['discoveryRule']['itemid'] [IN]
	 * @param array  $db_triggers[<tnum>]['tags']                    [IN]
	 * @param string $db_triggers[<tnum>]['tags'][]['tag']           [IN]
	 * @param string $db_triggers[<tnum>]['tags'][]['value']         [IN]
	 * @param int    $db_triggers[<tnum>]['correlation_mode']        [IN]
	 * @param string $db_triggers[<tnum>]['correlation_tag']         [IN]
	 *
	 * @throws APIException
	 */
	protected function updateReal(array $triggers, array $db_triggers) {
		if (!$triggers) {
			return;
		}

		$class = get_class($this);

		switch ($class) {
			case 'CTrigger':
				$resource = AUDIT_RESOURCE_TRIGGER;
				break;

			case 'CTriggerPrototype':
				$resource = AUDIT_RESOURCE_TRIGGER_PROTOTYPE;
				break;

			default:
				self::exception(ZBX_API_ERROR_INTERNAL, _('Internal error.'));
		}

		$upd_triggers = [];
		$new_functions = [];
		$del_functions_triggerids = [];
		$triggers_functions = [];
		$new_tags = [];
		$del_triggertagids = [];
		$save_triggers = $triggers;
		$this->implode_expressions($triggers, $db_triggers, $triggers_functions);

		if ($class === 'CTrigger') {
			// The list of the triggers with changed priority.
			$changed_priority_triggerids = [];
		}

		foreach ($triggers as $tnum => $trigger) {
			$db_trigger = $db_triggers[$tnum];
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

			if ($trigger['description'] !== $db_trigger['description']) {
				$upd_trigger['values']['description'] = $trigger['description'];
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
			if (array_key_exists('priority', $trigger) && $trigger['priority'] != $db_trigger['priority']) {
				$upd_trigger['values']['priority'] = $trigger['priority'];

				if ($class === 'CTrigger') {
					$changed_priority_triggerids[] = $trigger['triggerid'];
				}
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
			DB::insert('functions', $new_functions, false);
		}
		if ($del_triggertagids) {
			DB::delete('trigger_tag', ['triggertagid' => $del_triggertagids]);
		}
		if ($new_tags) {
			DB::insert('trigger_tag', $new_tags);
		}

		if ($class === 'CTrigger' && $changed_priority_triggerids
				&& $this->usedInItServices($changed_priority_triggerids)) {
			updateItServices();
		}

		foreach ($save_triggers as $tnum => $trigger) {
			add_audit_ext(AUDIT_ACTION_UPDATE, $resource, $trigger['triggerid'], $db_triggers[$tnum]['description'],
				null, $db_triggers[$tnum], $trigger
			);
		}
	}

	/**
	 * Implodes expression and recovery_expression for each trigger. Also returns array of functions and
	 * array of hostnames for each trigger.
	 *
	 * For example: {localhost:system.cpu.load.last(0)}>10 will be translated to {12}>10 and
	 *              created database representation.
	 *
	 * Note: All expresions must be already validated and exploded.
	 *
	 * @param array      $triggers                                   [IN]
	 * @param string     $triggers[<tnum>]['description']            [IN]
	 * @param string     $triggers[<tnum>]['expression']             [IN/OUT]
	 * @param int        $triggers[<tnum>]['recovery_mode']          [IN]
	 * @param string     $triggers[<tnum>]['recovery_expression']    [IN/OUT]
	 * @param array|null $db_triggers                                [IN]
	 * @param string     $db_triggers[<tnum>]['triggerid']           [IN]
	 * @param string     $db_triggers[<tnum>]['expression']          [IN]
	 * @param string     $db_triggers[<tnum>]['recovery_expression'] [IN]
	 * @param array      $triggers_functions                         [OUT] array of the new functions which must be
	 *                                                                     inserted into DB
	 * @param string     $triggers_functions[<tnum>][]['functionid'] [OUT]
	 * @param null       $triggers_functions[<tnum>][]['triggerid']  [OUT] must be initialized before insertion into DB
	 * @param string     $triggers_functions[<tnum>][]['itemid']     [OUT]
	 * @param string     $triggers_functions[<tnum>][]['function']   [OUT]
	 * @param string     $triggers_functions[<tnum>][]['parameter']  [OUT]
	 *
	 * @throws APIException if error occurred
	 */
	function implode_expressions(array &$triggers, array $db_triggers = null, array &$triggers_functions) {
		$class = get_class($this);

		switch ($class) {
			case 'CTrigger':
				$expressionData = new CTriggerExpression(['lldmacros' => false]);
				$error_wrong_host = _('Incorrect trigger expression. Host "%1$s" does not exist or you have no access to this host.');
				$error_host_and_template = _('Incorrect trigger expression. Trigger expression elements should not belong to a template and a host simultaneously.');
				$triggerFunctionValidator = new CFunctionValidator(['lldmacros' => false]);
				break;

			case 'CTriggerPrototype':
				$expressionData = new CTriggerExpression();
				$error_wrong_host = _('Incorrect trigger prototype expression. Host "%1$s" does not exist or you have no access to this host.');
				$error_host_and_template = _('Incorrect trigger prototype expression. Trigger prototype expression elements should not belong to a template and a host simultaneously.');
				$triggerFunctionValidator = new CFunctionValidator();
				break;

			default:
				self::exception(ZBX_API_ERROR_INTERNAL, _('Internal error.'));
		}

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

		foreach ($triggers as $tnum => $trigger) {
			$expressions_changed = ($db_triggers === null
				|| ($trigger['expression'] !== $db_triggers[$tnum]['expression']
					|| $trigger['recovery_expression'] !== $db_triggers[$tnum]['recovery_expression']));

			if (!$expressions_changed) {
				continue;
			}

			$expressionData->parse($trigger['expression']);
			$expressions = $expressionData->expressions;

			if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
				$expressionData->parse($trigger['recovery_expression']);
				$expressions = array_merge($expressions, $expressionData->expressions);
			}

			foreach ($expressions as $exprPart) {
				if (!array_key_exists($exprPart['host'], $hosts_keys)) {
					$hosts_keys[$exprPart['host']] = [
						'hostid' => null,
						'host' => $exprPart['host'],
						'status' => null,
						'keys' => []
					];
				}

				$hosts_keys[$exprPart['host']]['keys'][$exprPart['item']] = [
					'itemid' => null,
					'key' => $exprPart['item'],
					'value_type' => null,
					'flags' => null
				];
			}
		}

		if (!$hosts_keys) {
			return;
		}

		$_db_hosts = API::Host()->get([
			'output' => ['hostid', 'host', 'status'],
			'filter' => ['host' => array_keys($hosts_keys)],
			'editable' => true
		]);

		if (count($hosts_keys) != count($_db_hosts)) {
			$_db_templates = API::Template()->get([
				'output' => ['templateid', 'host', 'status'],
				'filter' => ['host' => array_keys($hosts_keys)],
				'editable' => true
			]);

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
			$expressions_changed = $db_triggers === null
				|| ($trigger['expression'] !== $db_triggers[$tnum]['expression']
				|| $trigger['recovery_expression'] !== $db_triggers[$tnum]['recovery_expression']);

			if (!$expressions_changed) {
				continue;
			}

			$expressionData->parse($trigger['expression']);
			$expressions1 = $expressionData->expressions;
			$expressions2 = [];

			if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
				$expressionData->parse($trigger['recovery_expression']);
				$expressions2 = $expressionData->expressions;
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
			foreach (array_merge($expressions1, $expressions2) as $exprPart) {
				$host_keys = $hosts_keys[$exprPart['host']];
				$key = $host_keys['keys'][$exprPart['item']];

				if ($host_keys['hostid'] === null) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _params($error_wrong_host, [$host_keys['host']]));
				}

				if ($key['itemid'] === null) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Incorrect item key "%1$s" provided for trigger expression on "%2$s".', $key['key'],
						$host_keys['host']
					));
				}

				if (!$triggerFunctionValidator->validate([
						'function' => $exprPart['function'],
						'functionName' => $exprPart['functionName'],
						'functionParamList' => $exprPart['functionParamList'],
						'valueType' => $key['value_type']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $triggerFunctionValidator->getError());
				}

				if (!array_key_exists($exprPart['expression'], $triggers_functions[$tnum])) {
					$triggers_functions[$tnum][$exprPart['expression']] = [
						'functionid' => null,
						'triggerid' => null,
						'itemid' => $key['itemid'],
						'function' => $exprPart['functionName'],
						'parameter' => $exprPart['functionParam']
					];
					$functions_num++;
				}

				if ($class === 'CTriggerPrototype' && $key['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
					$lld_ruleids[$key['lld_ruleid']] = true;
				}

				$status_mask |= ($host_keys['status'] == HOST_STATUS_TEMPLATE ? 0x01 : 0x02);
				$hostids[$host_keys['hostid']] = true;
				$hosts[$exprPart['host']] = true;
			}

			// When both templates and hosts are referenced in expressions.
			if ($status_mask == 0x03) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error_host_and_template);
			}

			// Triggers with children cannot be moved from one template to another host or template.
			if ($class === 'CTrigger' && $db_triggers !== null && $expressions_changed) {
				$expressionData->parse($db_triggers[$tnum]['expression']);
				$old_hosts1 = $expressionData->getHosts();
				$old_hosts2 = [];

				if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
					$expressionData->parse($db_triggers[$tnum]['recovery_expression']);
					$old_hosts2 = $expressionData->getHosts();
				}

				$is_moved = true;
				foreach (array_merge($old_hosts1, $old_hosts2) as $old_host) {
					if (array_key_exists($old_host, $hosts)) {
						$is_moved = false;
						break;
					}
				}

				if ($is_moved) {
					$moved_triggers[$db_triggers[$tnum]['triggerid']] = ['description' => $trigger['description']];
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
						&& !idcmp($lld_ruleids[0], $db_triggers[$tnum]['discoveryRule']['itemid'])) {
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

		// Replace {host:item.func()} macros with {<functionid>}.
		foreach ($triggers as $tnum => &$trigger) {
			$expressions_changed = $db_triggers === null
				|| ($trigger['expression'] !== $db_triggers[$tnum]['expression']
				|| $trigger['recovery_expression'] !== $db_triggers[$tnum]['recovery_expression']);

			if (!$expressions_changed) {
				continue;
			}

			foreach ($triggers_functions[$tnum] as &$trigger_function) {
				$trigger_function['functionid'] = $functionid;
				$functionid = bcadd($functionid, 1, 0);
			}
			unset($function);

			$expressionData->parse($trigger['expression']);
			$exprPart = end($expressionData->expressions);
			do {
				$trigger['expression'] = substr_replace($trigger['expression'],
					'{'.$triggers_functions[$tnum][$exprPart['expression']]['functionid'].'}',
					$exprPart['pos'], strlen($exprPart['expression'])
				);
			}
			while ($exprPart = prev($expressionData->expressions));

			if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
				$expressionData->parse($trigger['recovery_expression']);
				$exprPart = end($expressionData->expressions);
				do {
					$trigger['recovery_expression'] = substr_replace($trigger['recovery_expression'],
						'{'.$triggers_functions[$tnum][$exprPart['expression']]['functionid'].'}',
						$exprPart['pos'], strlen($exprPart['expression'])
					);
				}
				while ($exprPart = prev($expressionData->expressions));
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
	 * Adds triggers and trigger prorotypes from template to hosts.
	 *
	 * @param array $data
	 */
	public function syncTemplates(array $data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$triggers = $this->get([
			'output' => [
				'triggerid', 'description', 'expression', 'recovery_mode', 'recovery_expression', 'url', 'status',
				'priority', 'comments', 'type', 'correlation_mode', 'correlation_tag', 'manual_close'
			],
			'selectTags' => ['tag', 'value'],
			'hostids' => $data['templateids'],
			'preservekeys' => true
		]);

		$triggers = CMacrosResolverHelper::resolveTriggerExpressions($triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		foreach ($triggers as $trigger) {
			$this->inherit($trigger, $data['hostids']);
		}
	}
}
