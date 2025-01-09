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


class CControllerTriggerDisable extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'triggerids' =>	'required|array_db triggers.triggerid'
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
		return $this->getUserType() == USER_TYPE_ZABBIX_ADMIN
			|| $this->getUserType() == USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		$triggerids = $this->getInput('triggerids');
		$triggers_count = count($triggerids);
		$triggers = [];

		foreach ($triggerids as $triggerid) {
			$triggers[] = ['triggerid' => $triggerid, 'status' => TRIGGER_STATUS_DISABLED];
		}

		$result = API::Trigger()->update($triggers);

		$output = [];

		if ($result) {
			$output['success']['title'] = _n('Trigger disabled', 'Triggers disabled', $triggers_count);

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _n('Cannot disable trigger', 'Cannot disable triggers', $triggers_count),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];

			$triggers = API::Trigger()->get([
				'output' => [],
				'triggerids' => $triggerids,
				'preservekeys' => true
			]);

			$output['keepids'] = array_keys($triggers);
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
