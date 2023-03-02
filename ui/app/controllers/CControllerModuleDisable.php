<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * Module disable action from module list.
 */
class CControllerModuleDisable extends CController {

	/**
	 * List of modules to disable.
	 */
	private array $modules = [];

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'moduleids' =>		'required|array_db module.moduleid',

			// form update fields
			'status' =>			'in 1',
			'form_refresh' =>	'int32'
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
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)) {
			return false;
		}

		$moduleids = $this->getInput('moduleids');

		$this->modules = API::Module()->get([
			'output' => [],
			'moduleids' => $moduleids,
			'preservekeys' => true
		]);

		return (count($this->modules) == count($moduleids));
	}

	protected function doAction(): void {
		$set_status = MODULE_STATUS_DISABLED;

		$db_modules_update_names = [];

		$db_modules = API::Module()->get([
			'output' => ['relative_path', 'status'],
			'sortfield' => 'relative_path',
			'preservekeys' => true
		]);

		$module_manager = new CModuleManager(APP::getRootDir());

		foreach ($db_modules as $moduleid => $db_module) {
			$manifest = $module_manager->addModule($db_module['relative_path']);

			if (array_key_exists($moduleid, $this->modules) && $manifest) {
				$db_modules_update_names[] = $manifest['name'];
			}
		}

		$update = [];

		foreach (array_keys($this->modules) as $moduleid) {
			$update[] = [
				'moduleid' => $moduleid,
				'status' => $set_status
			];
		}

		$result = API::Module()->update($update);

		if ($result) {
			$output['success']['title'] = _n('Module disabled: %1$s.', 'Modules disabled: %1$s.',
				implode(', ', $db_modules_update_names), count($this->modules)
			);

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _n('Cannot disable module: %1$s.', 'Cannot disable modules: %1$s.',
					implode(', ', $db_modules_update_names), count($this->modules)
				),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$output['keepids'] = array_keys($this->modules);

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
