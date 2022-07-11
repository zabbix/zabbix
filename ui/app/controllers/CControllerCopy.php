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


/**
 * @var array
 */

class CControllerCopy extends CController {
	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'copy_targetids' => 'array|not_empty',
			'itemids' =>  'array_id',
			'triggerids' => 'array_id',
			'graphids' => 'array_id',
			'copy_type' => 'required|in '.implode(',', [
				COPY_TYPE_TO_HOST_GROUP, COPY_TYPE_TO_HOST, COPY_TYPE_TO_TEMPLATE, COPY_TYPE_TO_TEMPLATE_GROUP
			])
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
		$action = $this->getAction();
		$copy_type = $this->getInput('copy_type');

		if (($copy_type == COPY_TYPE_TO_HOST && !$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS))) {
			return false;
		}
		elseif ($copy_type == COPY_TYPE_TO_TEMPLATE
				&& !$this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)) {
			return false;
		}
		elseif ($copy_type == COPY_TYPE_TO_TEMPLATE_GROUP
				&& !$this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATE_GROUPS)) {
			return false;
		}
		elseif ($copy_type == COPY_TYPE_TO_HOST_GROUP
				&& !$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOST_GROUPS)) {
			return false;
		}

		if ($action === 'copy.items' && $this->hasInput('itemids')) {
			$items_count = API::Item()->get([
				'countOutput' => true,
				'itemids' => $this->getInput('itemids')
			]);

			return $items_count == count($this->getInput('itemids'));
		}
		elseif ($action === 'copy.triggers' && $this->hasInput('triggerids')) {
			$triggers_count = API::Trigger()->get([
				'countOutput' => true,
				'triggerids' => $this->getInput('triggerids')
			]);

			return $triggers_count == count($this->getInput('triggerids'));
		}
		elseif ($action === 'copy.graphs' && $this->hasInput('graphids')) {
			$graphs_count = API::Graph()->get([
				'countOutput' => true,
				'graphids' => $this->getInput('graphids')
			]);

			return $graphs_count == count($this->getInput('graphids'));
		}

		return false;
	}

	protected function doAction() {
		if ($this->hasInput('copy_targetids')) {
			$copy_targetids = $this->getInput('copy_targetids', []);

			if ($this->getAction() === 'copy.items') {
				$items_count = count($this->getInput('itemids'));

				if ($this->copyItems($copy_targetids)) {
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
			elseif ($this->getAction() === 'copy.triggers') {
				$triggers_count = count($this->getInput('triggerids'));

				if ($this->copyTriggers($copy_targetids)) {
					$output['success']['title'] = _n('Trigger copied', 'Triggers copied', $triggers_count);

					if ($messages = get_and_clear_messages()) {
						$output['success']['messages'] = array_column($messages, 'message');
					}
				}
				else {
					$output['error'] = [
						'title' => _n('Cannot copy trigger', 'Cannot copy triggers', $triggers_count),
						'messages' => array_column(get_and_clear_messages(), 'message')
					];
				}
			}
			elseif ($this->getAction() === 'copy.graphs') {
				$graphs_count = count($this->getInput('graphids'));

				if ($this->copyGraphs($copy_targetids)) {
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
		}
		else {
			$output['error'] = [
				'title' => _('No target selected.')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	protected function copyItems($copy_targetids): bool {
		$copy_type = $this->getInput('copy_type');
		$itemids = $this->getInput('itemids');
			// hosts or templates
		if ($copy_type == COPY_TYPE_TO_HOST || $copy_type == COPY_TYPE_TO_TEMPLATE) {
			$hostids = $copy_targetids;
		} // host groups
		elseif ($copy_type == COPY_TYPE_TO_HOST_GROUP) {
			$hostids = [];
			$db_hosts = API::Host()->get([
				'groupids' => $copy_targetids
			]);
			foreach ($db_hosts as $db_host) {
				$hostids[] = $db_host['hostid'];
			}
		} // template groups
		elseif ($copy_type == COPY_TYPE_TO_TEMPLATE_GROUP) {
			$hostids = array_keys(API::Template()->get([
				'output' => [],
				'groupids' => $copy_targetids,
				'editable' => true,
				'preservekeys' => true
			]));
		}

		return copyItemsToHosts($itemids, $hostids);
	}

	protected function copyTriggers($copy_targetids): bool {
		$copy_type = $this->getInput('copy_type');
		$triggerids = $this->getInput('triggerids');

		if ($copy_targetids) {
			// hosts or templates
			if ($copy_type == COPY_TYPE_TO_HOST || $copy_type == COPY_TYPE_TO_TEMPLATE) {
				$hostids = $copy_targetids;
			} // host groups
			elseif ($copy_type == COPY_TYPE_TO_HOST_GROUP) {
				$hostids = [];
				$db_hosts = API::Host()->get([
					'groupids' => $copy_targetids
				]);
				foreach ($db_hosts as $db_host) {
					$hostids[] = $db_host['hostid'];
				}
			} // template groups
			elseif ($copy_type == COPY_TYPE_TO_TEMPLATE_GROUP) {
				$hostids = array_keys(API::Template()->get([
					'output' => [],
					'groupids' => $copy_targetids,
					'editable' => true,
					'preservekeys' => true
				]));
			}
		}

		return copyTriggersToHosts($hostids, getRequest('hostid'), $triggerids);
	}

	protected function copyGraphs($copy_targetids): bool {
		$result = true;
		$copy_type = $this->getInput('copy_type');
		$graphids = $this->getInput('graphids');

		$options = [
			'output' => ['hostid'],
			'editable' => true,
			'preservekeys' => true,
			'templated_hosts' => true
		];
		// hosts or templates
		if ($copy_type == COPY_TYPE_TO_HOST || $copy_type == COPY_TYPE_TO_TEMPLATE) {
			$options['hostids'] = $copy_targetids;
		} // host groups or template groups
		else {
			$options['groupids'] = $copy_targetids;
		}
		$dbHosts = API::Host()->get($options);

		foreach ($graphids as $graphid) {
			foreach ($dbHosts as $host) {
				if (!copyGraphToHost($graphid, $host['hostid'])) {
					$result = false;
				}
			}
		}

		return $result;
	}
}
