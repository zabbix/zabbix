<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


namespace Widgets\PlainText\Actions;

use API,
	CController,
	CControllerResponseData;

class DataBinary extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}


	protected function checkPermissions() {
		return true;
	}

	protected function checkInput() {
		$fields = [
			'itemid' =>				'int32|required',
			'clock' =>				'int32|required',
			'ns' =>					'int32|required'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)]))->disableView()
			);
		}

		return $ret;
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
					$image = imagecreatefromstring(base64_decode($history_value[0]['value']));

					if ($image) {
						ob_start();

						imagepng(imageThumb($image, 0, 112));

						$result['thumbnail'] = base64_encode(ob_get_clean());
					}
					else {
						$result['text'] = $history_value[0]['value'];
					}
				}
			}
		}

		$this->setResponse(
			(new CControllerResponseData(['main_block' => json_encode($result, JSON_THROW_ON_ERROR)]))->disableView()
		);
	}
}
