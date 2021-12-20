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


class CControllerWidgetSlaReportView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_SLA_REPORT);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json'
		]);
	}

	/**
	 * @throws APIException
	 */
	protected function doAction(): void {
		$fields = $this->getForm()->getFieldsData();

		$data = [
			'name' => $this->getInput('name', $this->getDefaultName()),
			'has_access' => [
				CRoleHelper::ACTIONS_MANAGE_SLA => $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA)
			],
			'has_serviceid' => (bool) $fields['serviceid'],
			'has_permissions_error' => false,
			'rows_per_page' => CWebUser::$data['rows_per_page'],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$db_slas = API::Sla()->get([
			'output' => ['slaid', 'name', 'period', 'slo', 'timezone'],
			'slaids' => $fields['slaid']
		]);

		if ($db_slas) {
			$data['sla'] = $db_slas[0];

			$data['services'] = API::Service()->get([
				'output' => ['name'],
				'serviceids' => $fields['serviceid'] ?: null,
				'slaids' => $data['sla']['slaid'],
				'sortfield' => 'name',
				'sortorder' => ZBX_SORT_UP,
				'limit' => CWebUser::$data['rows_per_page'] + 1,
				'preservekeys' => true
			]);

			if ($fields['serviceid'] && !$data['services']) {
				$data['has_permissions_error'] = true;
			}
			else {
				$data['sli'] = API::Sla()->getSli([
					'slaid' => $data['sla']['slaid'],
					'serviceids' => array_keys($data['services']),
					'periods' => $fields['show_periods'] !== '' ? $fields['show_periods'] : null,
					'period_from' => $fields['date_from'] !== '' ? $fields['date_from'] : null,
					'period_to' => $fields['date_to'] !== '' ? $fields['date_to'] : null
				]);
			}
		}
		else {
			$data['has_permissions_error'] = true;
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
