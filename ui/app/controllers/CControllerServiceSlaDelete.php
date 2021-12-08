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


class CControllerServiceSlaDelete extends CController {

	protected $ids = [];

	protected function checkInput(): bool {
		$fields = [
			'ids' =>	'required|array_db sla.slaid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	/**
	 * @throws APIException
	 */
	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_SERVICES_SLA)) {
			return false;
		}

		$this->ids = $this->getInput('ids');

		$service_count = API::SLA()->get([
			'countOutput' => true,
			'slaids' => $this->ids
		]);

		return ($service_count == count($this->ids));
	}

	/**
	 * @throws APIException
	 */
	protected function doAction(): void {
		$response = [];
		$target_count = count($this->ids);
		$result = API::SLA()->delete($this->ids);

		if ($result) {
			CMessageHelper::setSuccessTitle(_n('SLA deleted', 'SLAs deleted', $target_count));
		}
		else {
			$left_undeleted = API::SLA()->get([
				'output' => [],
				'hostids' => $this->ids,
				'editable' => true
			]);

			$response['keepids'] = array_column($left_undeleted, 'slaid');
			$target_count -= count($left_undeleted);

			CMessageHelper::setErrorTitle(_n('Cannot delete SLA', 'Cannot delete SLAs', $target_count));
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($response)]))->disableView());
	}
}
