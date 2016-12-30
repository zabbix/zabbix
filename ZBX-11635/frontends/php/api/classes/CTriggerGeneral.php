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
abstract class CTriggerGeneral extends CZBXAPI {

	/**
	 * @abstract
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	abstract public function get(array $options = array());

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
		$triggerTemplates = API::Template()->get(array(
			'triggerids' => $trigger['triggerid'],
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => true
		));

		if (empty($triggerTemplates)) {
			// nothing to inherit, just exit
			return true;
		}

		if (!isset($trigger['expression']) || !isset($trigger['description'])) {
			$dbTriggers = $this->get(array(
				'triggerids' => $trigger['triggerid'],
				'output' => array('expression', 'description'),
				'nopermissions' => true
			));
			$dbTrigger = reset($dbTriggers);

			if (!isset($trigger['description'])) {
				$trigger['description'] = $dbTrigger['description'];
			}
			if (!isset($trigger['expression'])) {
				$trigger['expression'] = explode_exp($dbTrigger['expression']);
			}
		}

		// fetch all of the child hosts
		$childHosts = API::Host()->get(array(
			'templateids' => zbx_objectValues($triggerTemplates, 'templateid'),
			'output' => array('hostid', 'host'),
			'preservekeys' => true,
			'hostids' => $hostIds,
			'nopermissions' => true,
			'templated_hosts' => true
		));

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
		$expressionData->parse($trigger['expression']);

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
		$childTriggers = $this->get(array(
			'filter' => array('templateid' => $newTrigger['templateid']),
			'output' => array('triggerid'),
			'hostids' => $chdHost['hostid']
		));

		// yes we have a child trigger, just update it
		if ($childTrigger = reset($childTriggers)) {
			$newTrigger['triggerid'] = $childTrigger['triggerid'];
		}
		// no child trigger found
		else {
			// look for a trigger with the same description and expression
			$childTriggers = $this->get(array(
				'filter' => array(
					'description' => $newTrigger['description'],
					'flags' => null
				),
				'output' => array('triggerid', 'expression'),
				'nopermissions' => true,
				'hostids' => $chdHost['hostid']
			));

			foreach ($childTriggers as $childTrigger) {
				$tmpExp = explode_exp($childTrigger['expression']);
				if (strcmp($tmpExp, $newTrigger['expression']) == 0) {
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
			$oldTrigger = $this->get(array(
				'triggerids' => $trigger['triggerid'],
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => true
			));
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
			$trigger = $this->extendObject($this->tableName(), $trigger, array('description', 'expression'));

			if ($explodeExpression) {
				$trigger['expression'] = explode_exp($trigger['expression']);
			}
		}

		$filter = array('description' => $trigger['description']);

		if ($hostId) {
			$filter['hostid'] = $hostId;
		}
		else {
			$expressionData = new CTriggerExpression($trigger['expression']);
			$expressionData->parse($trigger['expression']);
			$expressionHosts = $expressionData->getHosts();
			$filter['host'] = reset($expressionHosts);
		}

		$triggers = $this->get(array(
			'filter' => $filter,
			'output' => array('expression', 'triggerid'),
			'nopermissions' => true
		));

		foreach ($triggers as $dbTrigger) {
			$tmpExp = explode_exp($dbTrigger['expression']);

			// check if the expressions are also equal and that this is a different trigger
			$differentTrigger = (!isset($trigger['triggerid']) || !idcmp($trigger['triggerid'], $dbTrigger['triggerid']));

			if (strcmp($tmpExp, $trigger['expression']) == 0 && $differentTrigger) {
				$options = array(
					'output' => array('name'),
					'templated_hosts' => true,
					'nopermissions' => true,
					'limit' => 1
				);
				if (isset($filter['host'])) {
					$options['filter'] = array('host' => $filter['host']);
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

			$groups = API::HostGroup()->get(array(
				'nodeids' => $options['nodeids'],
				'output' => $options['selectGroups'],
				'groupids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			));
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

			$hosts = API::Host()->get(array(
				'output' => $options['selectHosts'],
				'nodeids' => $options['nodeids'],
				'hostids' => $relationMap->getRelatedIds(),
				'templated_hosts' => true,
				'nopermissions' => true,
				'preservekeys' => true
			));
			if (!is_null($options['limitSelects'])) {
				order_result($hosts, 'host');
			}
			$result = $relationMap->mapMany($result, $hosts, 'hosts', $options['limitSelects']);
		}

		// adding functions
		if ($options['selectFunctions'] !== null && $options['selectFunctions'] != API_OUTPUT_COUNT) {
			$functions = API::getApi()->select('functions', array(
				'output' => $this->outputExtend('functions', array('triggerid', 'functionid'), $options['selectFunctions']),
				'filter' => array('triggerid' => $triggerids),
				'preservekeys' => true,
				'nodeids' => get_current_nodeid(true)
			));
			$relationMap = $this->createRelationMap($functions, 'triggerid', 'functionid');

			$functions = $this->unsetExtraFields($functions, array('triggerid', 'functionid'), $options['selectFunctions']);
			$result = $relationMap->mapMany($result, $functions, 'functions');
		}

		return $result;
	}
}
