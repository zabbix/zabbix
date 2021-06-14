<?php declare(strict_types = 1);
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


class CControllerPopupServiceCreate extends CController {

	protected function checkInput(): bool {
		$fields = [
			'name' =>				'required|db services.name|not_empty',
			'parent_serviceids' =>	'array_db services.serviceid',
			'algorithm' =>			'db services.algorithm|in '.implode(',', [SERVICE_ALGORITHM_NONE, SERVICE_ALGORITHM_MAX, SERVICE_ALGORITHM_MIN]),
			'triggerid' =>			'db services.triggerid',
			'sortorder' =>			'required|db services.sortorder|ge 0|le 999',
			'showsla' =>			'db services.showsla|in '.SERVICE_SHOW_SLA_OFF.','.SERVICE_SHOW_SLA_ON,
			'goodsla' =>			'db services.goodsla',
			'times' =>				'array',
			'tags' =>				'array',
			'child_serviceids' =>	'array_db services.serviceid',
			'form_refresh' =>		'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_SERVICES)
			&& $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SERVICES);
	}

	protected function doAction(): void {
		$service = [];

		$this->getInputs($service, ['name', 'algorithm', 'sortorder', 'showsla', 'goodsla']);

		$result = API::Service()->create($service);

		if ($result) {
			$output['title'] = _('Service created');
			$messages = CMessageHelper::getMessages();

			if ($messages) {
				$output['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['errors'] = makeMessageBox(false, filter_messages(), CMessageHelper::getTitle())->toString();
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
	}
}
