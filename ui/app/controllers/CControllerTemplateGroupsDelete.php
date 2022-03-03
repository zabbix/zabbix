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


class CControllerTemplateGroupsDelete extends CController {

	protected function checkInput(): bool {
		$fields = [
			'groupids' => 'required|array_db tplgrp.groupid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOST_GROUPS); // TODO change to templategroups
	}

	protected function doAction(): void {
		$groupids = $this->getInput('groupids');

		$result = API::TemplateGroup()->delete($groupids);

		$deleted = count($groupids);

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'templategroups.list')
			->setArgument('page', CPagerHelper::loadPage('templategroups.list', null))
		);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_n('Template group deleted', 'Template groups deleted', $deleted));
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot delete template group', 'Cannot delete template group', $deleted));
		}
		$this->setResponse($response);
	}
}

