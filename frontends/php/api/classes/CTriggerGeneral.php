<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
/**
 * Class containing common methods for operations with triggers.
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
	protected function inherit(array $trigger, array $hostids = null) {
		$triggerTemplates = API::Template()->get(array(
			'triggerids' => $trigger['triggerid'],
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => true
		));

		// fetch the existing child triggers
		$templatedTriggers = $this->get(array(
			'output' => array('triggerid', 'description'),
			'filter' => array(
				'templateid' => $trigger['triggerid']
			),
			'selectHosts' => array('name'),
			'preservekeys' => true
		));

		// no templates found, which means, that the trigger is no longer a templated trigger
		if (empty($triggerTemplates)) {
			// delete all of the former child triggers that may exist
			if ($templatedTriggers) {
				foreach ($templatedTriggers as $trigger) {
					info(_s('Deleted: Trigger "%1$s" on "%2$s".', $trigger['description'],
						implode(', ', zbx_objectValues($trigger['hosts'], 'name'))));
				}
				$this->deleteByPks(zbx_objectValues($templatedTriggers, 'triggerid'));
			}

			// nothing to inherit, just exit
			return true;
		}

		if (!isset($trigger['expression']) || !isset($trigger['description'])) {
			$options = array(
				'triggerids' => $trigger['triggerid'],
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => true,
				'nopermissions' => true
			);
			$dbTrigger = $this->get($options);
			$dbTrigger = reset($dbTrigger);

			if (!isset($trigger['description'])) {
				$trigger['description'] = $dbTrigger['description'];
			}
			if (!isset($trigger['expression'])) {
				$trigger['expression'] = explode_exp($dbTrigger['expression']);
			}
		}

		// fetch all of the child hosts
		$chdHosts = API::Host()->get(array(
			'templateids' => zbx_objectValues($triggerTemplates, 'templateid'),
			'output' => array(
				'hostid',
				'host'
			),
			'preservekeys' => true,
			'hostids' => $hostids,
			'nopermissions' => true,
			'templated_hosts' => true
		));

		foreach ($chdHosts as $childHost) {
			// update the child trigger on the child host
			$newTrigger = $this->inheritOnHost($trigger, $childHost, $triggerTemplates);

			// propagate the trigger inheritance to all child hosts
			$this->inherit($newTrigger);

			unset($templatedTriggers[$newTrigger['triggerid']]);
		}

		// if we've updated the children of the trigger on all of the host, and there are still some children left,
		// we must delete them
		if ($templatedTriggers && !$hostids) {
			foreach ($templatedTriggers as $trigger) {
				info(_s('Deleted: Trigger "%1$s" on "%2$s".', $trigger['description'],
					implode(', ', zbx_objectValues($trigger['hosts'], 'name'))));
			}
			$this->deleteByPks(zbx_objectValues($templatedTriggers, 'triggerid'));
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

		$newTrigger['expression'] = $expressionData->expressionShort;
		// replace template separately in each expression, only in beginning (host part)
		foreach ($expressionData->expressions as $exprPart) {
			foreach ($triggerTemplates as $triggerTemplate) {
				if ($triggerTemplate['host'] == $exprPart['host']) {
					$exprPart['host'] = $chdHost['host'];
					break;
				}
			}
			$newTrigger['expression'] = str_replace(
					'{'.$exprPart['index'].'}',
					'{'.$exprPart['host'].':'.$exprPart['item'].'.'.$exprPart['function'].'}',
					$newTrigger['expression']
			);
		}

		// check if a child trigger already exists on the host
		$childTriggers = $this->get(array(
			'filter' => array('templateid' => $newTrigger['templateid']),
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1,
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
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1,
				'nopermissions' => 1,
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
	protected function checkIfExistsOnHost(array $trigger, $hostid = null) {
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
		if ($hostid) {
			$filter['hostid'] = $hostid;
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
					$options['hostids'] = $hostid;
				}
				$host = API::Host()->get($options);
				$host = reset($host);

				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Trigger "%1$s" already exists on "%2$s".', $trigger['description'], $host['name']));
			}
		}
	}
}
