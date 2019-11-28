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
 * Module directory scan action.
 */
class CControllerModuleScan extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		return true;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$db_modules_create = [];
		$db_modules_create_names = [];
		$db_modules_delete = [];
		$db_modules_delete_names = [];

		$db_modules = API::ModuleDetails()->get([
			'output' => ['id'],
			'preservekeys' => true
		]);

		$db_moduleids = [];
		$db_moduleids_loaded = [];

		foreach ($db_modules as $moduleid => $db_module) {
			$db_moduleids[$db_module['id']] = $moduleid;
		}

		$manager = new CModuleManager(APP::getRootDir());

		foreach ($manager->scanModulesDirectory() as $module) {
			if ($loaded_module = $manager->loadModule($module['relative_path'])) {
				if (array_key_exists($module['id'], $db_moduleids)) {
					$db_moduleids_loaded[$module['id']] = $db_moduleids[$module['id']];
				}
				else {
					$db_modules_create[] = [
						'config' => $manager->registerModule($module['id'])
					] + $module;
					$db_modules_create_names[] = array_key_exists('name', $loaded_module['manifest'])
						? $loaded_module['manifest']['name']
						: $module['id'];
				}
			}
		}

		foreach (array_diff_key($db_moduleids, $db_moduleids_loaded) as $id => $moduleid) {
			$db_modules_delete[] = $moduleid;
			$db_modules_delete_names[] = $id;
		}

		if ($db_modules_create) {
			$result = API::ModuleDetails()->create($db_modules_create);

			if ($result) {
				info(_s('Modules added: %s', implode(', ', $db_modules_create_names)));
			}
			else {
				error(_s('Cannot add modules: %s', implode(', ', $db_modules_create_names)));
			}
		}

		if ($db_modules_delete) {
			$result = API::ModuleDetails()->delete($db_modules_delete);

			if ($result) {
				info(_s('Modules deleted: %s', implode(', ', $db_modules_delete_names)));
			}
			else {
				error(_s('Cannot delete modules: %s', implode(', ', $db_modules_delete_names)));
			}
		}

		array_map('error', $manager->getErrors());

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))->setArgument('action', 'module.list')->getUrl()
		);

		$message = ($db_modules_create || $db_modules_delete)
			? _('Modules updated')
			: _('No new modules discovered');

		if (hasErrorMesssages()) {
			$response->setMessageError($message);
		}
		else {
			$response->setMessageOk($message);
		}

		$this->setResponse($response);
	}
}
