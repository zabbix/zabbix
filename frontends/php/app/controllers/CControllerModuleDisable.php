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
 * Module disable action.
 */
class CControllerModuleDisable extends CController {

	protected function checkInput() {
		$fields = [
			/*
				PROTOTYPE:

				'moduleids' =>		'required|array_db module.moduleid'
			*/

			// Testing dummy moduleids.
			'moduleids' =>	'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		/*
			PROTOTYPE:

			if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
				return false;
			}

			$moduleids = API::Module()->get([
				'moduleids' => $this->getInput('moduleids'),
				'countOutput' => true,
				'editable' => true
			]);

			return ($moduleids == count($this->getInput('moduleids')));
		*/

		// Testing dummy return.
		return true;
	}

	protected function doAction() {
		$modules = [];

		foreach ($this->getInput('moduleids') as $moduleid) {
			$modules[] = [
				'moduleid' => $moduleid,
				'status' => MODULE_STATUS_DISABLED
			];
		}
		/*
			PROTOTYPE:

			$result = API::Module()->update($modules);
		*/

		// Testing dummy result.
		$result = true;

		$updated = count($modules);

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))
				->setArgument('action', 'module.list')
				->setArgument('uncheck', 1)
				->getUrl()
		);

		if ($result) {
			$response->setMessageOk(_n('Module disabled', 'Modules disabled', $updated));
		}
		else {
			$response->setMessageError(_n('Cannot disable module', 'Cannot disable modules', $updated));
		}

		$this->setResponse($response);
	}
}
