<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CControllerServiceList extends CController {

	protected function init(): void {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			// Empty validation rules only to init CMessageHelper.
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICES);
	}

	protected function doAction(): void {
		$db_services = API::Service()->get([
			'output' => ['name', 'serviceid', 'algorithm', 'sortorder'],
			'selectParent' => ['serviceid'],
			'selectDependencies' => ['servicedownid', 'soft', 'linkid'],
			'selectTrigger' => ['description'],
			'preservekeys' => true
		]);

		sortServices($db_services);

		$tree_data = [];
		createServiceConfigurationTree($db_services, $tree_data);

		$tree = new CServiceTree('service_conf_tree', $tree_data, [
			'caption' => _('Service'),
			'action' => _('Action'),
			'algorithm' => _('Status calculation'),
			'description' => _('Trigger')
		]);

		if (!$tree_data) {
			CMessageHelper::setErrorTitle(_('Cannot format tree.'));
		}

		$data = ['tree' => $tree];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
