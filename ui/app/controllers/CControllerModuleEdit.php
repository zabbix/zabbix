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
 * Module edit action.
 */
class CControllerModuleEdit extends CController {

	/**
	 * Current module data.
	 */
	private array $module = [];

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'moduleid' =>		'required|db module.moduleid',

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

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)) {
			return false;
		}

		$modules = API::Module()->get([
			'output' => ['id', 'relative_path', 'status'],
			'moduleids' => [$this->getInput('moduleid')]
		]);

		if (!$modules) {
			return false;
		}

		$this->module = $modules[0];

		return true;
	}

	protected function doAction(): void {
		$module_manager = new CModuleManager(APP::getRootDir());

		$manifest = $module_manager->addModule($this->module['relative_path']);

		if ($manifest !== null) {
			$data = [
				'form_refresh' => $this->getInput('form_refresh', 0),
				'moduleid' => $this->getInput('moduleid'),
				'name' => $manifest['name'],
				'version' => $manifest['version'],
				'author' => array_key_exists('author', $manifest) ? $manifest['author'] : null,
				'description' => array_key_exists('description', $manifest) ? $manifest['description'] : null,
				'relative_path' => $this->module['relative_path'],
				'namespace' => $manifest['namespace'],
				'url' => array_key_exists('url', $manifest) ? $manifest['url'] : null,
				'status' => $this->hasInput('form_refresh')
					? ($this->hasInput('status') ? MODULE_STATUS_ENABLED : MODULE_STATUS_DISABLED)
					: $this->module['status']
			];

			$response = new CControllerResponseData($data);
			$response->setTitle(_('Modules'));
		}
		else {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'module.list')
				->setArgument('page', CPagerHelper::loadPage('module.list', null))
			);
			CMessageHelper::setErrorTitle(_s('Cannot load module at: %1$s.', $this->module['relative_path']));
		}

		$this->setResponse($response);
	}
}
