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


class CControllerModuleEnable extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'moduleids' => 'required|array_db module.moduleid'
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
		$moduleids = $this->getInput('moduleids');

		$db_modules = API::Module()->get([
			'output' => ['relative_path', 'status'],
			'sortfield' => 'relative_path',
			'preservekeys' => true
		]);

		$module_manager = new CModuleManager(APP::getRootDir());

		foreach ($db_modules as $db_moduleid => $db_module) {
			$new_status = in_array($db_moduleid, $moduleids) ? MODULE_STATUS_ENABLED : $db_module['status'];

			if ($new_status == MODULE_STATUS_ENABLED) {
				$module_manager->addModule($db_module['relative_path']);
			}
		}

		$errors = $module_manager->checkConflicts()['conflicts'];

		array_map('error', $errors);

		$result = false;

		if (!$errors) {
			$update = [];

			foreach ($moduleids as $moduleid) {
				$update[] = [
					'moduleid' => $moduleid,
					'status' => MODULE_STATUS_ENABLED
				];
			}

			$result = API::Module()->update($update);
		}

		if ($result) {
			$output['success']['title'] = _n('Module enabled', 'Modules enabled', count($moduleids));

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _n('Cannot enable module', 'Cannot enable modules', count($moduleids)),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];

			$modules = API::Module()->get([
				'output' => [],
				'moduleids' => $moduleids,
				'preservekeys' => true
			]);

			$output['keepids'] = array_keys($modules);
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
