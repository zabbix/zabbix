<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		$moduleids = $this->getInput('moduleids');

		$this->modules = API::Module()->get([
			'output' => ['id', 'status'],
			'moduleids' => $moduleids,
			'preservekeys' => true
		]);

		return (count($this->modules) == count($moduleids));
	}

	protected function doAction() {
		$set_status = ($this->getAction() === 'module.update')
			? $this->hasInput('status')
				? MODULE_STATUS_ENABLED
				: MODULE_STATUS_DISABLED
			: ($this->getAction() === 'module.enable')
				? MODULE_STATUS_ENABLED
				: MODULE_STATUS_DISABLED;

		$manager = new CModuleManager(APP::getRootDir());

		$db_modules = API::Module()->get([
			'output' => ['id', 'relative_path', 'status'],
			'preservekeys' => true
		]);

		foreach ($db_modules as $moduleid => $db_module) {
			$new_status = array_key_exists($moduleid, $this->modules)
				? $set_status
				: $db_module['status'];

			if ($new_status == MODULE_STATUS_ENABLED) {
				if ($manager->loadModule($db_module['relative_path'])) {
					$manager->initModule($db_module['id'], $manager->registerModule($db_module['id']));
				}
			}
		}

		$manager_errors = $manager->getErrors();

		array_map('error', $manager_errors);

		$result = false;

		if (!$manager_errors) {
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
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))
					->setArgument('action', 'module.edit')
					->setArgument('moduleid', array_keys($this->modules)[0])
					->getUrl()
			);
			$response->setFormData($this->getInputAll());
		}
		else {
			$curl = (new CUrl('zabbix.php'))->setArgument('action', 'module.list');
			if ($result) {
				$curl->setArgument('uncheck', '1');
			}
			$response = new CControllerResponseRedirect($curl->getUrl());
		}

		if ($result) {
			$response->setMessageOk(_n('Module updated', 'Modules updated', count($this->modules)));
		}
		else {
			$response->setMessageError(_n('Cannot update module', 'Cannot update modules', count($this->modules)));
		}

		$this->setResponse($response);
	}
}
