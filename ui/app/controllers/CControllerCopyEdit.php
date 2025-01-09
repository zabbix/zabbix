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


class CControllerCopyEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'itemids' =>	'array_db items.itemid',
			'triggerids' =>	'array_db triggers.triggerid',
			'src_hostid' =>	'db hosts.hostid',
			'graphids' =>	'array_db graphs.graphid',
			'source' =>		'required|in '.implode(',', ['items', 'triggers', 'graphs'])
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
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
				&& !$this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)) {
			return false;
		}

		$source = $this->getInput('source');

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

	protected function doAction(): void {
		$data = [
			'source' => $this->getInput('source')
		];

		switch ($data['source']) {
			case 'items':
				$data['itemids'] = $this->getInput('itemids');
				$data['element_type'] = 'items';
				break;

			case 'triggers':
				$data['triggerids'] = $this->getInput('triggerids');
				$data['element_type'] = 'triggers';
				break;

			case 'graphs':
				$data['graphids'] = $this->getInput('graphids');
				$data['element_type'] = 'graphs';
				break;
		}

		if ($this->hasInput('src_hostid')) {
			$data['src_hostid'] = $this->getInput('src_hostid');
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
