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


/**
 * Class containing common methods for operations with triggers.
 *
 * @package API
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
	 * @abstract
	 *
	 * @param array $array
	 *
	 * @return array
	 */
	abstract protected function createReal(array &$array);

	/**
	 * @abstract
	 *
	 * @param array $array
	 *
	 * @return array
	 */
	abstract protected function updateReal(array $array);

	/**
	 * Updates the children of the trigger on the given hosts and propagates the inheritance to all child hosts.
	 * If the given trigger was assigned to a different template or a host, all of the child triggers, that became
	 * obsolete will be deleted.
	 *
	 * @param array $trigger    the trigger with an exploded expression
	 * @param array $hostids
	 *
	 * @return bool
	 */
	protected function inherit(array $trigger, array $hostIds = null) {
		$triggerTemplates = API::Template()->get([
			'triggerids' => $trigger['triggerid'],
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => true
		]);

		if (empty($triggerTemplates)) {
			// nothing to inherit, just exit
			return true;
		}

		if (!isset($trigger['expression']) || !isset($trigger['description'])) {
			$dbTriggers = $this->get([
				'triggerids' => $trigger['triggerid'],
				'output' => ['expression', 'description'],
				'nopermissions' => true
			]);
			$dbTrigger = reset($dbTriggers);

			if (!isset($trigger['description'])) {
				$trigger['description'] = $dbTrigger['description'];
			}
			if (!isset($trigger['expression'])) {
				$trigger['expression'] = CMacrosResolverHelper::resolveTriggerExpression($dbTrigger['expression']);
			}
		}

		// fetch all of the child hosts
		$childHosts = API::Host()->get([
			'templateids' => zbx_objectValues($triggerTemplates, 'templateid'),
			'output' => ['hostid', 'host'],
			'preservekeys' => true,
			'hostids' => $hostIds,
			'nopermissions' => true,
			'templated_hosts' => true
		]);

		foreach ($childHosts as $childHost) {
			// update the child trigger on the child host
			$newTrigger = $this->inheritOnHost($trigger, $childHost, $triggerTemplates);

			// propagate the trigger inheritance to all child hosts
			$this->inherit($newTrigger);
		}

		return true;
	}

	/**
	 * Updates the child of the templated trigger on the given host. Trigger inheritance will not propagate to
	 * child hosts.
	 *
	 * @param array $trigger            a templated trigger
	 * @param array $chdHost            the target host
	 * @param array $triggerTemplates   the templates, to which the templated trigger belongs
	 *
	 * @return array|mixed  the updated child trigger
	 */
	protected function inheritOnHost(array $trigger, array $chdHost, array $triggerTemplates) {
		$newTrigger = $trigger;
		$newTrigger['templateid'] = $trigger['triggerid'];
		unset($newTrigger['triggerid']);

		if (isset($trigger['dependencies'])) {
			$deps = zbx_objectValues($trigger['dependencies'], 'triggerid');
			$newTrigger['dependencies'] = replace_template_dependencies($deps, $chdHost['hostid']);
		}
		$expressionData = new CTriggerExpression();
		if (!$expressionData->parse($trigger['expression'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $expressionData->error);
		}

		$newTrigger['expression'] = $trigger['expression'];
		// replace template separately in each expression, only in beginning (host part)
		$exprPart = end($expressionData->expressions);
		do {
			foreach ($triggerTemplates as $triggerTemplate) {
				if ($triggerTemplate['host'] == $exprPart['host']) {
					$exprPart['host'] = $chdHost['host'];
					break;
				}
			}

			$newTrigger['expression'] = substr_replace($newTrigger['expression'],
					'{'.$exprPart['host'].':'.$exprPart['item'].'.'.$exprPart['function'].'}',
					$exprPart['pos'], strlen($exprPart['expression'])
			);
		}
		while ($exprPart = prev($expressionData->expressions));

		// check if a child trigger already exists on the host
		$childTriggers = $this->get([
			'filter' => ['templateid' => $newTrigger['templateid']],
			'output' => ['triggerid'],
			'hostids' => $chdHost['hostid']
		]);

		// yes we have a child trigger, just update it
		if ($childTrigger = reset($childTriggers)) {
			$newTrigger['triggerid'] = $childTrigger['triggerid'];
		}
		// no child trigger found
		else {
			// look for a trigger with the same description and expression
			$childTriggers = $this->get([
				'filter' => [
					'description' => $newTrigger['description'],
					'flags' => null
				],
				'output' => ['triggerid', 'expression'],
				'nopermissions' => true,
				'hostids' => $chdHost['hostid']
			]);

			$childTriggers = CMacrosResolverHelper::resolveTriggerExpressions($childTriggers);

			foreach ($childTriggers as $childTrigger) {
				if ($childTrigger['expression'] === $newTrigger['expression']) {
					// we have a trigger with the same description and expression as the parent
					// convert it to a template trigger
					$newTrigger['triggerid'] = $childTrigger['triggerid'];
					break;
				}
			}
		}

		$this->checkIfExistsOnHost($newTrigger, $chdHost['hostid']);

		if (isset($newTrigger['triggerid'])) {
			$this->updateReal($newTrigger);
		}
		else {
			$oldTrigger = $this->get([
				'triggerids' => $trigger['triggerid'],
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => true
			]);
			$oldTrigger = reset($oldTrigger);
			unset($oldTrigger['triggerid']);
			foreach ($oldTrigger as $key => $value) {
				if (!isset($newTrigger[$key])) {
					$newTrigger[$key] = $oldTrigger[$key];
				}
			}
			$this->createReal($newTrigger);
			$newTrigger = reset($newTrigger);
		}

		return $newTrigger;
	}

	/**
	 * Checks that no trigger with the same description and expression as $trigger exist on the given host.
	 * Assumes the given trigger is valid.
	 *
	 * @throws APIException if at least one trigger exists
	 *
	 * @param array $trigger a trigger with an exploded expression
	 * @param null  $hostid
	 *
	 * @return void
	 */
	protected function checkIfExistsOnHost(array $trigger, $hostId = null) {
		// skip the check if the description and expression haven't been changed
		if (!isset($trigger['description']) && !isset($trigger['expression'])) {
			return;
		}

		// make sure we have all the required data
		if (!isset($trigger['description']) || !isset($trigger['expression'])) {
			$explodeExpression = !isset($trigger['expression']);
			$trigger = $this->extendObject($this->tableName(), $trigger, ['description', 'expression']);

			if ($explodeExpression) {
				$trigger['expression'] = CMacrosResolverHelper::resolveTriggerExpression($trigger['expression']);
			}
		}

		$filter = ['description' => $trigger['description']];

		if ($hostId) {
			$filter['hostid'] = $hostId;
		}
		else {
			$expressionData = new CTriggerExpression($trigger['expression']);
			$expressionData->parse($trigger['expression']);
			$expressionHosts = $expressionData->getHosts();
			$filter['host'] = reset($expressionHosts);
		}

		$triggers = $this->get([
			'filter' => $filter,
			'output' => ['expression', 'triggerid'],
			'nopermissions' => true
		]);

		$triggers = CMacrosResolverHelper::resolveTriggerExpressions($triggers);

		foreach ($triggers as $dbTrigger) {
			// check if the expressions are also equal and that this is a different trigger
			$differentTrigger = (!isset($trigger['triggerid']) || !idcmp($trigger['triggerid'], $dbTrigger['triggerid']));

			if ($dbTrigger['expression'] === $trigger['expression'] && $differentTrigger) {
				$options = [
					'output' => ['name'],
					'templated_hosts' => true,
					'nopermissions' => true,
					'limit' => 1
				];
				if (isset($filter['host'])) {
					$options['filter'] = ['host' => $filter['host']];
				}
				else {
					$options['hostids'] = $hostId;
				}
				$host = API::Host()->get($options);
				$host = reset($host);

				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Trigger "%1$s" already exists on "%2$s".', $trigger['description'], $host['name']));
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
			$relationMap = $this->createRelationMap($functions, 'triggerid', 'functionid');

			$functions = $this->unsetExtraFields($functions, ['triggerid', 'functionid'], $options['selectFunctions']);
			$result = $relationMap->mapMany($result, $functions, 'functions');
		}

		return $result;
	}
}
