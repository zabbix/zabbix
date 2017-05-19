<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerWidgetNavigationtreeView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'widgetid'		=>	'required',
			'fields'		=>	'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$error = null;
		$data = [];

		// Default values
		$default = [
			'widget_name' => ''
		];

		if ($this->hasInput('fields')) {
			// Use configured data, if possible
			$data = $this->getInput('fields');
		}

		// Apply defualt value for data
		foreach ($default as $key => $value) {
			if (!array_key_exists($key, $data)) {
				$data[$key] = $value;
			}
		}

		// Parse tree items and prepare an array from them.
		$items = [];
		foreach ($data as $field_key => $field_value) {
			preg_match('/^map\.?(?<item_key>id|parent|name)\.(?<itemid>\d+)$/', $field_key, $field_details);
			if (array_key_exists('item_key', $field_details) && array_key_exists('itemid', $field_details)) {
				$item_key = $field_details['item_key'];
				$itemid = $field_details['itemid'];

				if (!array_key_exists($itemid, $items)) {
					$items[$itemid] = [
						'parent' => 0,
						'name' => '',
						'mapid' => 0,
						'id' => $itemid
					];
				}

				$items[$itemid][($item_key == 'id') ? 'mapid' : $item_key] = $field_value;
				unset($data[$field_key]);
			}
		}

		$this->setResponse(new CControllerResponseData([
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'widgetid' => getRequest('widgetid'),
			'field_items' => $items,
			'fields' => $data,
			'error' => $error
		]));
	}
}
