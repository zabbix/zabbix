<?php declare(strict_types = 0);
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


class CControllerTemplateGroupEdit extends CController{

	protected function init(): void {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'groupid' =>			'db hstgrp.groupid',
			'name' =>				'string',
			'subgroups' =>			'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATE_GROUPS)) {
			return false;
		}

		if ($this->getInput('groupid', 0)) {
			return (bool) API::TemplateGroup()->get([
				'output' => [],
				'groupids' => $this->getInput('groupid'),
				'editable' => true
			]);
		}

		return true;
	}

	protected function doAction(): void {
		$data = [
			'sid' => $this->getUserSID(),
			'groupid' => null,
			'name' => '',
			'subgroups' => 0
		];

		if ($this->getInput('groupid', 0)) {
			$data['groupid'] = $this->getInput('groupid');

			$groups = API::TemplateGroup()->get([
				'output' => ['name'],
				'selectTemplates' => ['templateid'],
				'groupids' => $data['groupid']
			]);
			$group = $groups[0];
			$data['name'] = $group['name'];
		}

		// For clone action.
		if ($this->hasInput('name')) {
			$data['name'] = $this->getInput('name');
		}

		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of template group'));
		$this->setResponse($response);
	}
}
