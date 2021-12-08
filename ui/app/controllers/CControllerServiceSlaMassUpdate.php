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


class CControllerServiceSlaMassUpdate extends CController {

	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'ids' =>						'required|db sla.slaid',
			'status' => 					'in '.implode(',', [
				CSlaHelper::SLA_STATUS_DISABLED,
				CSlaHelper::SLA_STATUS_ENABLED
			]),
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
		if (!$this->checkAccess(CRoleHelper::UI_SERVICES_SLA)) {
			return false;
		}

		$records = API::SLA()->get([
			'output' => [],
			'slaids' => $this->getInput('ids'),
			'editable' => true
		]);

		return count($records) > 0;
	}

	/**
	 * @throws APIException
	 */
	protected function doAction(): void {
		$update = [];

		$this->getInputs($update, ['status']);
		$update = array_filter($update);

		if (!$update) {
			$this->setResponse(new CControllerResponseFatal());

			return;
		}

		$update['slaids'] = $this->getInput('ids');

		$result = API::SLA()->update($update);

		if ($result) {
			$output = ['title' => _('SLA updated')];

			if ($messages = CMessageHelper::getMessages()) {
				$output['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output = [
				'errors' => makeMessageBox(ZBX_STYLE_MSG_BAD, filter_messages(), CMessageHelper::getTitle())
					->toString()
			];
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
	}
}
