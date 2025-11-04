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


class CControllerHostPrototypeDisable extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'context' =>	'required|in '.implode(',', ['host', 'template']),
			'hostids' =>	'required|array_db hosts.hostid',
			'discover' =>	'db hosts.discover|in '.ZBX_PROTOTYPE_NO_DISCOVER,
			'status' =>		'db hosts.status|in '.HOST_STATUS_NOT_MONITORED
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getInput('context') === 'host'
			? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			: $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function doAction(): void {
		$host_prototypeids = $this->getInput('hostids');
		$upd_count = count($host_prototypeids);
		$host_prototypes = [];

		if ($this->hasInput('status')) {
			foreach ($host_prototypeids as $host_prototypeid) {
				$host_prototypes[] = ['hostid' => $host_prototypeid, 'status' => HOST_STATUS_NOT_MONITORED];
			}
		}
		else {
			foreach ($host_prototypeids as $host_prototypeid) {
				$host_prototypes[] = ['hostid' => $host_prototypeid, 'discover' => ZBX_PROTOTYPE_NO_DISCOVER];
			}
		}

		$result = API::HostPrototype()->update($host_prototypes);

		$output = [];

		if ($result) {
			$output['success']['title'] = _n('Host prototype updated', 'Host prototypes updated', $upd_count);

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _n('Cannot update host prototype', 'Cannot update host prototypes', $upd_count),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];

			$host_prototypes = API::HostPrototype()->get([
				'output' => [],
				'hostids' => $host_prototypeids,
				'preservekeys' => true
			]);

			$output['keepids'] = array_keys($host_prototypes);
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
