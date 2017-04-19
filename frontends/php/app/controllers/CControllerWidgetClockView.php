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

class CControllerWidgetClockView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'widgetid'		=>	'required', // TODO VM: in db.widget
			'fields'		=>	'array',
		];

		$ret = $this->validateInput($fields);
		if ($ret) {
			// TODO VM: if fields are present, check that fields have enough data
		}

		if (!$ret) {
			// TODO VM: prepare propper response for case of incorrect fields
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {

		$time = null;
		$title = null;
		$time_zone_string = null;
		$time_zone_offset = null;
		$error = null;
		$data = [];

		// Default values
		$default = [
			'time_type' => null,
			'itemid' => null,
			'hostid' => null, // TODO VM: probably will not be used at all
			'inner_width' => null,
			'inner_height' => null,
		];

		if ($this->hasInput('fields')) {
			// Use configured data, if possible
			$data = $this->getInput('fields');
		}

		// TODO VM: Should be optional values, should have minimal value.
		// In case of beeing NULL, will take all available widget's space.
		// Validation: both null, or both bigger/smaller than.
		if (!array_key_exists('inner_width', $data)
				|| !array_key_exists('inner_height', $data)
				|| $data['inner_width'] == 0
				|| $data['inner_height'] == 0
		) {
			$data['inner_width'] = $default['inner_width'];
			$data['inner_height'] = $default['inner_height'];
		}

		// Apply defualt value for data
		foreach ($default as $key => $value) {
			if (!array_key_exists($key, $data)) {
				$data[$key] = $value;
			}
		}

		switch ($data['time_type']) {
			case TIME_TYPE_HOST:
				$itemid = $data['itemid'];

				if (!empty($data['hostid'])) {
					$new_itemid = get_same_item_for_host($itemid, $data['hostid']);
					$itemid = !empty($new_itemid) ? $new_itemid : '';
				}

				$items = API::Item()->get([
					'output' => ['itemid', 'value_type'],
					'selectHosts' => ['name'],
					'itemids' => [$itemid]
				]);

				if ($items) {
					$item = $items[0];
					$title = $item['hosts'][0]['name'];
					unset($items, $item['hosts']);

					$last_value = Manager::History()->getLast([$item]);

					if ($last_value) {
						$last_value = $last_value[$item['itemid']][0];

						try {
							$now = new DateTime($last_value['value']);

							$time_zone_string = 'GMT'.$now->format('P');
							$time_zone_offset = $now->format('Z');

							$time = time() - ($last_value['clock'] - $now->getTimestamp());
						}
						catch (Exception $e) {
							$error = _('No data');
						}
					}
					else {
						$error = _('No data');
					}
				}
				else {
					$error = _('No data');
				}
				break;

			case TIME_TYPE_SERVER:
				$title = _('Server');

				$now = new DateTime();
				$time = $now->getTimestamp();
				$time_zone_string = 'GMT'.$now->format('P');
				$time_zone_offset = $now->format('Z');
				break;

			default:
				$title = _('Local');
				break;
		}

		$this->setResponse(new CControllerResponseData([
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'clock' => [
				'title' => $title,
				'time' => $time,
				'time_zone_string' => $time_zone_string,
				'time_zone_offset' => $time_zone_offset,
				'error' => $error,
				'inner_width' => $data['inner_width'],
				'inner_height' => $data['inner_height']
			]
		]));
	}
}
