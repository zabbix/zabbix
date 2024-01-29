<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
 * Module update action.
 */
class CControllerModuleUpdate extends CController {

	/**
	 * List of modules to update.
	 *
	 * @var array
	 */
	private $modules = [];

	protected function checkInput() {
		$fields = [
			'moduleids' =>		'required|array_db module.moduleid',

			// form update fields
			'status' =>			'in 1',
			'form_refresh' =>	'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
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

	protected function doAction() {
		$set_status = ($this->getAction() === 'module.update')
			? ($this->hasInput('status') ? MODULE_STATUS_ENABLED : MODULE_STATUS_DISABLED)
			: ($this->getAction() === 'module.enable' ? MODULE_STATUS_ENABLED : MODULE_STATUS_DISABLED);

		$db_modules = API::Module()->get([
			'output' => ['relative_path', 'status'],
			'sortfield' => 'relative_path',
			'preservekeys' => true
		]);

		$module_manager_enabled = new CModuleManager(APP::ModuleManager()->getModulesDir());

		foreach ($db_modules as $moduleid => $db_module) {
			$new_status = array_key_exists($moduleid, $this->modules) ? $set_status : $db_module['status'];

			if ($new_status == MODULE_STATUS_ENABLED) {
				$manifest = $module_manager_enabled->addModule($db_module['relative_path']);
			}
		}

		$errors = $module_manager_enabled->checkConflicts()['conflicts'];

		array_map('error', $errors);

		$result = false;

		if (!$errors) {
			$update = [];

			foreach (array_keys($this->modules) as $moduleid) {
				$update[] = [
					'moduleid' => $moduleid,
					'status' => $set_status
				];
			}

			$result = API::Module()->update($update);
		}

		if (!$result && $this->getAction() === 'module.update') {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'module.edit')
				->setArgument('moduleid', array_keys($this->modules)[0])
			);
			$response->setFormData($this->getInputAll());
		}
		else {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'module.list')
				->setArgument('page', CPagerHelper::loadPage('module.list', null))
			);

			if ($result) {
				$response->setFormData(['uncheck' => '1']);
			}
		}

		if ($result) {
			if ($this->getAction() === 'module.update') {
				CMessageHelper::setSuccessTitle(_s('Module updated'));
			}
			elseif ($set_status == MODULE_STATUS_ENABLED) {
				CMessageHelper::setSuccessTitle(_n('Module enabled', 'Modules enabled', count($this->modules)));
			}
			else {
				CMessageHelper::setSuccessTitle(_n('Module disabled', 'Modules disabled', count($this->modules)));
			}
		}
		else {
			if ($this->getAction() === 'module.update') {
				CMessageHelper::setErrorTitle(_s('Cannot update module'));
			}
			elseif ($set_status == MODULE_STATUS_ENABLED) {
				CMessageHelper::setErrorTitle(
					_n('Cannot enable module', 'Cannot enable modules', count($this->modules))
				);
			}
			else {
				CMessageHelper::setErrorTitle(
					_n('Cannot disable module', 'Cannot disable modules', count($this->modules))
				);
			}
		}

		$this->setResponse($response);
	}
}
