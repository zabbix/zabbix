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


class CControllerModuleUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'moduleid' =>	'required|db module.moduleid',
			'status' =>		'in 1'
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
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$moduleid = $this->getInput('moduleid');

		$set_status = ($this->hasInput('status') ? MODULE_STATUS_ENABLED : MODULE_STATUS_DISABLED);

		$errors = [];

		if ($set_status == MODULE_STATUS_ENABLED) {
			$module_manager_enabled = new CModuleManager(APP::getRootDir());

			$db_modules = API::Module()->get([
				'output' => ['relative_path', 'status'],
				'sortfield' => 'relative_path',
				'preservekeys' => true
			]);

			foreach ($db_modules as $db_moduleid => $db_module) {
				$new_status = $db_moduleid == $moduleid ? $set_status : $db_module['status'];

				if ($new_status == MODULE_STATUS_ENABLED) {
					$module_manager_enabled->addModule($db_module['relative_path']);
				}
			}

			$errors = $module_manager_enabled->checkConflicts()['conflicts'];

			array_map('error', $errors);
		}

		$result = false;

		if (!$errors) {
			$update = [
				'moduleid' => $moduleid,
				'status' => $set_status
			];

			$result = API::Module()->update($update);
		}

		if ($result) {
			$output['success']['title'] = _s('Module updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _s('Cannot update module'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
