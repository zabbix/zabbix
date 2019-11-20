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
 * Module edit action.
 */
class CControllerModuleEdit extends CController {

	/**
	 * Current module data.
	 *
	 * @var array
	 */
	private $module = [];

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			/*
				PROTOTYPE:

				'moduleid' =>		'required|array_db module.moduleid',
			*/

			// Testing dummy moduleid.
			'moduleid' =>		'required',

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

		/*
			PROTOTYPE:

			$modules = API::Module()->get([
				'output' => ['moduleid', 'relative_path', 'name', 'version', 'status'],
				'moduleids' => $this->getInput('moduleid'),
				'editable' => true
			]);

			if (!$modules) {
				return false;
			}

			$this->module = $modules[0];
		*/

		// Testing dummy modules.
		$modules = self::getModules(['name' => '', 'status' => -1], 'name', 'ASC');
		$this->module = $modules[$this->getInput('moduleid')];

		return true;
	}

	protected function doAction() {
		$moduleid = $this->getInput('moduleid');

		/*
			PROTOTYPE:

			$manifest = CModuleRegistry::getManifest($moduleid);
		*/

		// Testing dummy manifest.
		$manifest = [
			'author' => 'John Smith',
			'description' => 'Test data module description. Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
			'namespace' => 'Modules\Example',
			'url' => 'http://www.module.com',
		];

		$data = [
			'moduleid' => $moduleid,
			'relative_path' => $this->module['relative_path'],
			'name' => $this->module['name'],
			'version' => $this->module['version'],
			'status' => $this->hasInput('form_refresh')
				? $this->hasInput('status')
					? MODULE_STATUS_ENABLED
					: MODULE_STATUS_DISABLED
				: $this->module['status'],
			'author' => ($manifest !== null && array_key_exists('author', $manifest)) ? $manifest['author'] : null,
			'description' => ($manifest !== null && array_key_exists('description', $manifest))
				? $manifest['description']
				: null,
			'namespace' => ($manifest !== null) ? $manifest['namespace'] : null,
			'url' => ($manifest !== null && array_key_exists('url', $manifest)) ? $manifest['url'] : null
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Modules'));
		$this->setResponse($response);
	}

	// Dummy substitute for API::Module()->get().
	private static function getModules($filter, $sort_field, $sort_order) {
		$modules = [];

		for ($i = 1; $i <= 100; $i++) {
			$modules[$i] = [
				'moduleid' => $i,
				'relative_path' => './modules/module'.$i,
				'name' => 'Test Module - '.$i,
				'version' => '1.0.'.$i,
				'status' => $i % 2 ? MODULE_STATUS_ENABLED : MODULE_STATUS_DISABLED
			];
		}

		$modules = array_filter($modules, function($module, $key) use ($filter) {
			if ($filter['status'] != -1 && $filter['status'] != $module['status']) {
				return false;
			}
			if ($filter['name'] !== '' && stripos($module['name'], $filter['name']) === false) {
				return false;
			}

			return true;
		}, ARRAY_FILTER_USE_BOTH);

		order_result($modules, $sort_field, $sort_order);

		return $modules;
	}
}
