<?php declare(strict_types = 0);
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


class CControllerCopyCreate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'copy_targetids' =>	'required|array|not_empty',
			'itemids' =>		'array_db items.itemid',
			'triggerids' =>		'array_db triggers.triggerid',
			'graphids' =>		'array_db graphs.graphid',
			'src_hostid' =>		'db hosts.hostid',
			'copy_type' =>		'required|in '.implode(',', [
									COPY_TYPE_TO_HOST_GROUP, COPY_TYPE_TO_HOST, COPY_TYPE_TO_TEMPLATE,
									COPY_TYPE_TO_TEMPLATE_GROUP
								]),
			'source' => 		'required|in '.implode(',', ['items', 'triggers', 'graphs'])
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
		$source = $this->getInput('source');
		$copy_type = $this->getInput('copy_type');

		if (($copy_type == COPY_TYPE_TO_HOST || $copy_type == COPY_TYPE_TO_HOST_GROUP)
				&& !$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)) {
			return false;
		}
		elseif (($copy_type == COPY_TYPE_TO_TEMPLATE || $copy_type == COPY_TYPE_TO_TEMPLATE_GROUP)
				&& !$this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)) {
			return false;
		}

		if (!$this->checkTargetPermissions($copy_type)) {
			return false;
		}

		if ($source === 'items' && $this->hasInput('itemids')) {
			$items_count = API::Item()->get([
				'countOutput' => true,
				'itemids' => $this->getInput('itemids')
			]);

			return $items_count == count($this->getInput('itemids'));
		}
		elseif ($source === 'triggers' && $this->hasInput('triggerids')) {
			$triggers_count = API::Trigger()->get([
				'countOutput' => true,
				'triggerids' => $this->getInput('triggerids')
			]);

			return $triggers_count == count($this->getInput('triggerids'));
		}
		elseif ($source === 'graphs' && $this->hasInput('graphids')) {
			$graphs_count = API::Graph()->get([
				'countOutput' => true,
				'graphids' => $this->getInput('graphids')
			]);

			return $graphs_count == count($this->getInput('graphids'));
		}

		return false;
	}

	private function checkTargetPermissions(int $copy_type): bool {
		$copy_targetids = $this->getInput('copy_targetids');

		switch ($copy_type) {
			case COPY_TYPE_TO_HOST:
				$copy_targets = API::Host()->get([
					'countOutput' => true,
					'hostids' => $copy_targetids,
					'editable' => true
				]);

				return $copy_targets == count($copy_targetids);

			case COPY_TYPE_TO_TEMPLATE:
				$copy_targets = API::Template()->get([
					'countOutput' => true,
					'templateids' => $copy_targetids,
					'editable' => true
				]);

				return $copy_targets == count($copy_targetids);

			case COPY_TYPE_TO_HOST_GROUP:
				$copy_targets = API::HostGroup()->get([
					'countOutput' => true,
					'groupids' => $copy_targetids
				]);

				return $copy_targets == count($copy_targetids);

			case COPY_TYPE_TO_TEMPLATE_GROUP:
				$copy_targets = API::TemplateGroup()->get([
					'countOutput' => true,
					'groupids' => $copy_targetids
				]);

				return $copy_targets == count($copy_targetids);
		}

		return false;
	}

	protected function doAction(): void {
		$dst_hosts = $this->getTargets();
		$dst_options = in_array($this->getInput('copy_type'), [COPY_TYPE_TO_TEMPLATE, COPY_TYPE_TO_TEMPLATE_GROUP])
			? ['templateids' => array_keys($dst_hosts)]
			: ['hostids' => array_keys($dst_hosts)];
		$success = false;
		DBstart();

		switch ($this->getInput('source')) {
			case 'items':
				$src_items = $dst_hosts ? CItemHelper::getSourceItems(['itemids' => $this->getInput('itemids')]) : [];
				$success = !$src_items || !$dst_hosts || CItemHelper::copy($src_items, $dst_hosts);

				$items_count = count($src_items);
				$title = $success
					? _n('Item copied', 'Items copied', $items_count)
					: _n('Cannot copy item', 'Cannot copy items', $items_count);
				break;

			case 'triggers':
				$src_options = ['triggerids' => $this->getInput('triggerids')]
					+ ($this->hasInput('src_hostid') ? ['hostids' => $this->getInput('src_hostid')] : []);
				$success = CTriggerHelper::copy($src_options, $dst_options);

				$triggers_count = count($src_options['triggerids']);
				$title = $success
					? _n('Trigger copied', 'Triggers copied', $triggers_count)
					: _n('Cannot copy trigger', 'Cannot copy triggers', $triggers_count);
				break;

			case 'graphs':
				$src_options = ['graphids' => $this->getInput('graphids')]
					+ ($this->hasInput('src_hostid') ? ['hostids' => $this->getInput('src_hostid')] : []);
				$success = CGraphHelper::copy($src_options, $dst_options);

				$graphs_count = count($src_options['graphids']);
				$title = $success
					? _n('Graph copied', 'Graphs copied', $graphs_count)
					: _n('Cannot copy graph', 'Cannot copy graphs', $graphs_count);
				break;
		}

		DBend($success);

		$output = $success
			? ['success' => ['title' => $title]]
			: ['error' => ['title' => $title, 'messages' => array_column(get_and_clear_messages(), 'message')]];

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	private function getTargets(): array {
		$targetids = $this->getInput('copy_targetids', []);
		$copy_type = $this->getInput('copy_type');

		switch ($copy_type) {
			case COPY_TYPE_TO_HOST:
				$dst_hosts = API::Host()->get([
					'output' => ['host', 'status'],
					'hostids' => $targetids,
					'preservekeys' => true
				]);
				break;

			case COPY_TYPE_TO_HOST_GROUP:
				$dst_hosts = API::Host()->get([
					'output' => ['host', 'status'],
					'groupids' => $targetids,
					'preservekeys' => true
				]);
				break;

			case COPY_TYPE_TO_TEMPLATE:
				$dst_hosts = API::Template()->get([
					'output' => ['host'],
					'templateids' => $targetids,
					'preservekeys' => true
				]);
				break;

			case COPY_TYPE_TO_TEMPLATE_GROUP:
				$dst_hosts = API::Template()->get([
					'output' => ['host'],
					'groupids' => $targetids,
					'preservekeys' => true
				]);
				break;
		}

		if (in_array($copy_type, [COPY_TYPE_TO_TEMPLATE, COPY_TYPE_TO_TEMPLATE_GROUP])) {
			foreach ($dst_hosts as &$dst_host) {
				$dst_host['status'] = HOST_STATUS_TEMPLATE;
			}
			unset($dst_host);
		}

		return $dst_hosts;
	}
}
