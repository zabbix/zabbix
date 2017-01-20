<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
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

		DBstart();

		$result = API::Script()->delete($scriptids);

		if ($result) {
			foreach ($scriptids as $scriptid) {
				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCRIPT, _('Script').' ['.$scriptid.']');
			}
		}

		$result = DBend($result);

		$deleted = count($scriptids);

		$response = new CControllerResponseRedirect('zabbix.php?action=script.list&uncheck=1');

		if ($result) {
			$response->setMessageOk(_n('Script deleted', 'Scripts deleted', $deleted));
		}
		else {
			$response->setMessageError(_n('Cannot delete script', 'Cannot delete scripts', $deleted));
		}

		$this->setResponse($response);
	}
}
