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


class CControllerScriptDelete extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'scriptids' =>	'required|array_db scripts.scriptid'
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
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_SCRIPTS)) {
			return false;
		}

		return true;
	}

	protected function doAction(): void {
		$scriptids = $this->getInput('scriptids');
		$output = [];
		$deleted = count($scriptids);

		$result = (bool) API::Script()->delete($scriptids);

		if ($result) {
			$output['success']['title'] = _n('Script deleted', 'Scripts deleted', $deleted);

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _n('Cannot delete script', 'Cannot delete scripts', $deleted),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];

			$scripts = API::Script()->get([
				'output' => [],
				'scriptids' => $scriptids,
				'editable' => true,
				'preservekeys' => true
			]);

			$output['keepids'] = array_keys($scripts);
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
