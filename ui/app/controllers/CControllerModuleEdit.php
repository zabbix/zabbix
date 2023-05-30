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


class CControllerModuleEdit extends CController {

	private array $module = [];

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'moduleid' => 'required|db module.moduleid'
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
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)) {
			return false;
		}

		$module = API::Module()->get([
			'output' => ['relative_path', 'status'],
			'moduleids' => $this->getInput('moduleid')
		]);

		if (!$module) {
			return false;
		}

		$this->module = $module[0];

		return true;
	}

	protected function doAction(): void {
		$module_manager = new CModuleManager(APP::getRootDir());

		$manifest = $module_manager->addModule($this->module['relative_path']);

		if ($manifest !== null) {
			$data = [
				'moduleid' => $this->getInput('moduleid'),
				'name' => $manifest['name'],
				'version' => $manifest['version'],
				'author' => $manifest['author'],
				'description' => $manifest['description'],
				'relative_path' => $this->module['relative_path'],
				'namespace' => $manifest['namespace'],
				'url' => $manifest['url'],
				'status' => $this->module['status'],
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			];

			$response = new CControllerResponseData($data);
		}
		else {
			$response = (new CControllerResponseData(['main_block' => json_encode([
				'error' => [
					'title' => _s('Cannot load module at: %1$s.', $this->module['relative_path']),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]
			])]))->disableView();
		}

		$this->setResponse($response);
	}
}
