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

class BinaryValueGet extends CController {

	private const BASE64_STRING_LENGTH = 1024;

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkPermissions() {
		return true;
	}

	protected function checkInput() {
		$fields = [
			'itemid' =>		'int32|required',
			'clock' =>		'int32|required',
			'ns' =>			'int32|required',
			'preview' =>	'in 1'
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
		$preview = $this->getInput('preview', 0);

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

				if ($preview == 1) {
					if ($history_value) {
						$image = @imagecreatefromstring(base64_decode($history_value[0]['value']));

						if ($image) {
							ob_start();

							imagepng(imageThumb($image, 0, 112));

							$result['thumbnail'] = base64_encode(ob_get_clean());
						}
						else {
							$result['value'] = substr($history_value[0]['value'], 0, self::BASE64_STRING_LENGTH);
							$result['has_more'] = strlen($history_value[0]['value']) >= self::BASE64_STRING_LENGTH;
						}
					}
				}
				else {
					$result['value'] = $history_value[0]['value'];
				}
			}
		}

		$this->setResponse(
			(new CControllerResponseData(['main_block' => json_encode($result, JSON_THROW_ON_ERROR)]))->disableView()
		);
	}
}
