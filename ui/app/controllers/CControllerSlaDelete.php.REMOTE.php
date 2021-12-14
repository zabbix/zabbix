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


class CControllerSlaDelete extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'slaids' => 'required|array_id'
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

	/**
	 * @throws APIException
	 */
	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_SERVICES_SLA)
				|| !$this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA)) {
			return false;
		}

		$sla_count = API::Sla()->get([
			'countOutput' => true,
			'slaids' => $this->getInput('slaids')
		]);

		return $sla_count == count($this->getInput('slaids'));
	}

	/**
	 * @throws APIException
	 */
	protected function doAction(): void {
		$slaids = $this->getInput('slaids');

		$result = API::Sla()->delete($slaids);

		$deleted = count($slaids);

		if ($result) {
			$output = ['title' => _n('SLA deleted', 'SLAs deleted', $deleted)];

			if ($messages = CMessageHelper::getMessages()) {
				$output['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output = [
				'errors' => makeMessageBox(ZBX_STYLE_MSG_BAD, filter_messages(),
					_n('Cannot delete SLA', 'Cannot delete SLAs', $deleted)
				)->toString()
			];
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
	}
}
