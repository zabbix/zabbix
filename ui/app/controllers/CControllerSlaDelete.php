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

	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'slaids' =>	'required|array_db sla.slaid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData([
					'main_block' => json_encode(['messages' => array_column(get_and_clear_messages(), 'message')])
				])
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

		return true;
	}

	/**
	 * @throws APIException
	 */
	protected function doAction(): void {
		$output = [];
		$slaids = $this->getInput('slaids');
		$result = API::SLA()->delete($slaids);

		if ($result) {
			$output['title'] = _n('SLA deleted', 'SLAs deleted', count($result['slaids']));
		}
		else {
			$output['errors'] = makeMessageBox(ZBX_STYLE_MSG_BAD, filter_messages(), CMessageHelper::getTitle())
				->toString();
			$output['keepids'] = $slaids;
		}

		if ($messages = get_and_clear_messages()) {
			$output['messages'] = array_column($messages, 'message');
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
	}
}
