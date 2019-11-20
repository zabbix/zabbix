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

	protected function checkInput() {
		$fields = [
			/*
				PROTOTYPE:

				'moduleid' =>		'required|db module.moduleid',
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

			return (bool) API::Module()->get([
				'moduleids' => $this->getInput('moduleid'),
				'countOutput' => true,
				'editable' => true
			]);
		*/

		// Testing dummy return.
		return true;
	}

	protected function doAction() {
		$moduleid = $this->getInput('moduleid');

		/*
			PROTOTYPE:

			$module = [
				'moduleid' => $moduleid,
				'status' => $this->hasInput('status')
					? MODULE_STATUS_ENABLED
					: MODULE_STATUS_DISABLED
			];

			$result = API::Module()->update($module);
		*/

		// Testing dummy result.
		$result = true;

		if ($result) {
			$response = new CControllerResponseRedirect(
				(new Curl('zabbix.php'))
					->setArgument('action', 'module.list')
					->setArgument('uncheck', '1')
					->getUrl()
			);
			$response->setMessageOk(_('Module updated'));
		}
		else {
			$response = new CControllerResponseRedirect(
				(new Curl('zabbix.php'))
					->setArgument('action', 'module.edit')
					->setArgument('moduleid', $moduleid)
					->getUrl()
			);
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot update module'));
		}

		$this->setResponse($response);
	}
}
