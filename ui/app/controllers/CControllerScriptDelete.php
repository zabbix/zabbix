<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CControllerScriptDelete extends CController {

	protected function checkInput() {
		$fields = [
			'scriptids' =>	'required|array_db scripts.scriptid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_SCRIPTS)) {
			return false;
		}

		$scripts = API::Script()->get([
			'countOutput' => true,
			'scriptids' => $this->getInput('scriptids'),
			'editable' => true
		]);

		return ($scripts == count($this->getInput('scriptids')));
	}

	protected function doAction() {
		$scriptids = $this->getInput('scriptids');

		$result = (bool) API::Script()->delete($scriptids);

		$deleted = count($scriptids);

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'script.list')
			->setArgument('page', CPagerHelper::loadPage('script.list', null))
		);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_n('Script deleted', 'Scripts deleted', $deleted));
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot delete script', 'Cannot delete scripts', $deleted));
		}

		$this->setResponse($response);
	}
}
