<?php declare(strict_types = 1);
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


class CControllerPopupServiceStatusRuleEdit extends CController {

	protected function checkInput(): bool {
		$fields = [
			'form_refresh' => 	'int32',
			'edit' => 			'in 1',
			'row_index' =>		'required|int32',
			'new_status' =>		'in '.implode(',', array_keys(CServiceHelper::getProblemStatusNames())),
			'type' =>			'in '.implode(',', array_keys(CServiceHelper::getStatusRuleTypeOptions())),
			'limit_value' =>	'int32',
			'limit_status' =>	'in '.implode(',', array_keys(CServiceHelper::getStatusNames()))
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_SERVICES_SERVICES);
	}

	protected function doAction(): void {
		$form = [
			'new_status' => $this->getInput('new_status', TRIGGER_SEVERITY_NOT_CLASSIFIED),
			'type' => $this->getInput('type', ZBX_SERVICE_STATUS_RULE_TYPE_N_GE),
			'limit_value' => $this->getInput('limit_value', 1),
			'limit_status' => $this->getInput('limit_status', ZBX_SEVERITY_OK)
		];

		$data = [
			'is_edit' => $this->hasInput('edit'),
			'row_index' => $this->getInput('row_index'),
			'form' => $form,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
