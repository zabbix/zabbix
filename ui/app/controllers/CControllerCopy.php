<?php declare(strict_types = 0);
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


class CControllerCopy extends CController {

	/**
	 * @var array
	 */
	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'context' => 'string|in host,template',
			'copy_targetids' => 'array|not_empty',
			'itemids' =>  'array_id',
			'triggerids' => 'array_id',
			'graphids' => 'array_id',
			'copy_type' => 'in '.implode(',', [
				COPY_TYPE_TO_HOST_GROUP, COPY_TYPE_TO_HOST, COPY_TYPE_TO_TEMPLATE
				]),
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS) ||
			!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)) {
			return false;
		}

		$action = $this->getAction();

		if ($action == 'copy.items') {
			$entity = API::Item()->get([
				'output' => [],
				'itemids' => $this->getInput('itemids'),
				'editable' => true,
			]);
			$element_count = count($this->getInput('itemids'));
		}

		elseif ($action == 'copy.triggers') {
			$entity = API::Trigger()->get([
				'output' => [],
				'triggerids' => $this->getInput('triggerids'),
				'editable' => true,
			]);
			$element_count = count($this->getInput('triggerids'));
		}

		elseif ($action == 'copy.graphs') {
			$entity = API::Graph()->get([
				'output' => [],
				'graphids' => $this->getInput('graphids'),
				'editable' => true,
			]);
			$element_count = count($this->getInput('graphids'));
		}

		return $element_count === count($entity);
	}

	protected function doAction() {
		$output = '';
		// Item copy
		if($this->getAction() === 'copy.items') {
			$output = $this->copyItems();
		}

		// Trigger copy
		elseif($this->getAction() === 'copy.triggers'){
			$output = $this->copyTriggers();
		}

		// Graph copy
		elseif ($this->getAction() === 'copy.graphs') {
			$output = $this->copyGraphs();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	protected function copyItems() {
		$copy_targetids = $this->getInput('copy_targetids');
		$copy_type = $this->getInput('copy_type');
		$itemids = $this->getInput('itemids');

		if ($copy_targetids) {
			if ($copy_type == COPY_TYPE_TO_HOST || $copy_type == COPY_TYPE_TO_TEMPLATE) {
				$hosts_ids = $copy_targetids;
			}
			// host groups
			if ($copy_type == COPY_TYPE_TO_HOST_GROUP) {
				$hosts_ids = [];
				$db_hosts = API::Host()->get([
					'groupids' => $copy_targetids
				]);

				foreach ($db_hosts as $db_host) {
					$hosts_ids[] = $db_host['hostid'];
				}
			}

			$result = copyItemsToHosts($itemids, $hosts_ids);
			$output = [];
			$items_count = count($itemids);

			if ($copy_targetids > 0) {
				if ($result) {
					$output['success']['title'] = _n('Item copied', 'Items copied', $items_count);

					if ($messages = get_and_clear_messages()) {
						$output['success']['messages'] = array_column($messages, 'message');
					}
				}
				else {
					$output['error'] = [
						'title' => _n('Cannot copy item', 'Cannot copy items', $items_count),
						'messages' => array_column(get_and_clear_messages(), 'message')
					];
				}
			}
		}
		else {
			$output['error'] = [
				'title' => _('No target selected.')
			];
		}

		return $output;
	}

	protected function copyTriggers() {
		$copy_targetids = $this->getInput('copy_targetids');
		$copy_type = $this->getInput('copy_type');
		$triggerids = $this->getInput('triggerids');

		if ($copy_targetids) {
			if ($copy_type == COPY_TYPE_TO_HOST || $copy_type == COPY_TYPE_TO_TEMPLATE) {
				$hosts_ids = $copy_targetids;
			}
			// host groups
			if ($copy_type == COPY_TYPE_TO_HOST_GROUP) {
				$hosts_ids = [];
				$db_hosts = API::Host()->get([
					'groupids' => $copy_targetids
				]);

				foreach ($db_hosts as $db_host) {
					$hosts_ids[] = $db_host['hostid'];
				}
			}

			$result = copyTriggersToHosts($hosts_ids, getRequest('hostid'), $triggerids);
			$output = [];
			$triggers_count = count($triggerids);

			if ($copy_targetids > 0) {

				if ($result) {
					$output['success']['title'] = _n('Trigger copied', 'Triggers copied', $triggers_count);

					if ($messages = get_and_clear_messages()) {
						$output['success']['messages'] = array_column($messages, 'message');
					}
				} else {
					$output['error'] = [
						'title' => _n('Cannot copy trigger', 'Cannot copy triggers', $triggers_count),
						'messages' => array_column(get_and_clear_messages(), 'message')
					];
				}
			}
		}
		else {
			$output['error'] = [
				'title' => _('No target selected.')
			];
		}

		return $output;
	}

	protected function copyGraphs() {
		if ($this->getAction() === 'copy.graphs') {
			$copy_targetids = $this->getInput('copy_targetids');
			$copy_type = $this->getInput('copy_type');
			$graphids = $this->getInput('graphids');

			$result = true;

			$options = [
				'output' => ['hostid'],
				'editable' => true,
				'templated_hosts' => true
			];

			// hosts or templates
			if ($copy_type == COPY_TYPE_TO_HOST || $copy_type == COPY_TYPE_TO_TEMPLATE) {
				$options['hostids'] = $copy_targetids;
			}
			// host groups
			else {
				$groupids = $copy_targetids;
				zbx_value2array($groupids);

				$dbGroups = API::HostGroup()->get([
					'output' => ['groupid'],
					'groupids' => $groupids,
					'editable' => true
				]);
				$dbGroups = zbx_toHash($dbGroups, 'groupid');

				foreach ($groupids as $groupid) {
					if (!array_key_exists($groupid, $dbGroups)) {
						access_deny();
					}
				}
				$options['groupids'] = $groupids;
			}
			$dbHosts = API::Host()->get($options);

			foreach ($graphids as $graphid) {
				foreach ($dbHosts as $host) {
					if (!copyGraphToHost($graphid, $host['hostid'])) {
						$result = false;
					}
				}
			}
			$graphs_count = count($graphids);

			if ($copy_targetids > 0) {
				if ($result) {
					$output['success']['title'] = _n('Graph copied', 'Graphs copied', $graphs_count);

					if ($messages = get_and_clear_messages()) {
						$output['success']['messages'] = array_column($messages, 'message');
					}
				}
				else {
					$output['error'] = [
						'title' => _n('Cannot copy graph', 'Cannot copy graphs', $graphs_count),
						'messages' => array_column(get_and_clear_messages(), 'message')
					];
				}
			}
			else {
				$output['error'] = [
					'title' => _('No target selected.')
				];
			}

			return $output;
		}
	}
}
