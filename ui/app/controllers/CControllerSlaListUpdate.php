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


class CControllerSlaListUpdate extends CController {

	protected $slas;

	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'slaids' =>	'required|array_db sla.slaid',
			'status' => 'required|in '.implode(',', [
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

		$this->slas = API::SLA()->get([
			'output' => ['slaid', 'name'],
			'slaids' => $this->getInput('slaids'),
			'editable' => true,
			'preservekeys' => true
		]);

		return (bool) $this->slas;
	}

	/**
	 * @throws APIException
	 */
	protected function doAction(): void {
		$output = [];
		$update = [];

		$this->getInputs($update, ['slaids', 'status']);

		foreach ($update['slaids'] as $key => $slaid) {
			if (!array_key_exists($slaid, $this->slas)) {
				unset($update['slaids'][$key]);
				continue;
			}

			$update['slaids'][$key] = array_merge($this->slas[$slaid], [
				'status' => $update['status'],
			]);
		}

		$result = API::SLA()->update($update['slaids']);

		if ($result) {
			$output['title'] = _n('SLA updated', 'SLAs updated', count($result['slaids']));
		}
		else {
			$output['errors'] = makeMessageBox(ZBX_STYLE_MSG_BAD, filter_messages(), CMessageHelper::getTitle())
				->toString();
			$output['keepids'] = $this->getInput('slaids');
		}

		if ($messages = get_and_clear_messages()) {
			$output['messages'] = array_column($messages, 'message');
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
	}
}
