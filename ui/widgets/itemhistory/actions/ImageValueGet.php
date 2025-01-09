<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


namespace Widgets\ItemHistory\Actions;

use API,
	CController,
	CControllerResponseData;

class ImageValueGet extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function checkInput(): bool {
		$fields = [
			'itemid' =>	'int32|required',
			'clock' =>	'int32|required',
			'ns' =>		'int32|required'
		];

		return $this->validateInput($fields);
	}

	protected function doAction(): void {
		$result = [];

		$db_item = API::Item()->get([
			'output' => ['itemid', 'value_type'],
			'itemids' => $this->getInput('itemid'),
			'webitems' => true
		]);

		if ($db_item && $db_item[0]['value_type'] == ITEM_VALUE_TYPE_BINARY) {
			$history_value = API::History()->get([
				'output' => ['value'],
				'history' => ITEM_VALUE_TYPE_BINARY,
				'itemids' => $db_item[0]['itemid'],
				'filter' => [
					'clock' => $this->getInput('clock'),
					'ns' => $this->getInput('ns')
				]
			]);

			if ($history_value) {
				$result['image'] = @imagecreatefromstring(base64_decode($history_value[0]['value']));
			}
		}

		$this->setResponse(
			(new CControllerResponseData($result))->disableView()
		);
	}
}
