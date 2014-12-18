<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

class CControllerScriptMassDelete extends CController {

	protected function checkInput() {
		$fields = array(
			'scriptids' =>		'fatal|array_db:scripts.scriptid|required'
		);

		$result = $this->validateInput($fields);

		if (!$result) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $result;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			access_deny();
		}

		$scripts = API::Script()->get(array(
			'scriptids' => $this->getInput('scriptids'),
			'countOutput' => true
		));
		if ($scripts != count($this->getInput('scriptids'))) {
			access_deny();
		}
	}

	protected function doAction() {
		DBstart();

		$result = API::Script()->delete($this->getInput('scriptids'));

		if ($result) {
			foreach ($this->getInput('scriptids') as $scriptid) {
				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCRIPT, _('Script').' ['.$scriptid.']');
			}
		}

		$result = DBend($result);

		$response = new CControllerResponseRedirect('scripts.php?action=script.list&uncheck=1');

		if ($result) {
			$response->setMessageOk(_('Script deleted'));
		}
		else {
			$response->setMessageError(_('Cannot delete script'));
		}

		$this->setResponse($response);
	}
}
