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


class CControllerTemplateGroupsUpdate extends CController {

	protected function checkInput(): bool {
		$fields = [
			'groupid' => 			'fatal|required|db tplgrp.groupid',
			'name' => 				'db tplgrp.name',
			'subgroups' => 			'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=templategroups.edit');
					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot update template group'));
					$this->setResponse($response);
					break;
				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATE_GROUPS)) {
			return false;
		}

		return (bool) API::TemplateGroup()->get([
			'output' => [],
			'groupids' => $this->getInput('groupid'),
			'editable' => true
		]);
	}

	protected function doAction(): void {
		$groupid = $this->getInput('groupid');
		$name = $this->getInput('name');

		DBstart();
		$result = API::TemplateGroup()->update([
			'groupid' => $groupid,
			'name' => $name
		]);
		$result = DBend($result);

		if ($result) {
			// Apply permissions to all subgroups.
			if ($this->getInput('subgroups', 0) == 1 && CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
				inheritPermissions($groupid, $name);
			}
		}

		if ($result) {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'templategroups.list')
				->setArgument('page', CPagerHelper::loadPage('templategroups.list', null))
			);
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('Template gropu updated'));
		}
		else {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'templategroups.edit')
				->setArgument('groupid', $groupid)
			);
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot update template group'));
		}
		$this->setResponse($response);
	}
}

