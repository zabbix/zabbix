<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
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

	protected function checkPermissions() {
		return true;
	}

	protected function checkInput() {
		$fields = [
			'itemid' =>	'int32|required',
			'clock' =>	'int32|required',
			'ns' =>		'int32|required'
		];

		return $this->validateInput($fields);
	}

	protected function doAction() {
		$itemid = $this->getInput('itemid', '');
		$clock = $this->getInput('clock', 0);
		$ns = $this->getInput('ns', 0);

		$result = [];

		if ($itemid !== '') {
			$db_item = API::Item()->get([
				'output' => ['itemid', 'value_type'],
				'itemids' => [$itemid],
				'webitems' => true
			]);

			if ($db_item && $db_item[0]['value_type'] == ITEM_VALUE_TYPE_BINARY) {
				$history_value = API::History()->get([
					'history' => ITEM_VALUE_TYPE_BINARY,
					'output' => ['value'],
					'filter' => [
						'clock' => $clock,
						'ns' => $ns
					],
					'itemids' => [$db_item[0]['itemid']]
				]);

				if ($history_value) {
					$image = imagecreatefromstring(base64_decode($history_value[0]['value'])) ?: '';

					if ($image) {
						$result['image'] = $image;
					}
				}
			}
		}

		$this->setResponse((new CControllerResponseData($result))->disableView());
	}
}
