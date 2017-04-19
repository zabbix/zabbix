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
		return true;
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
			'style' => null,
			'resourceid' => null,
			'hostid' => null,
			'width' => null,
			'height' => null,
		];

		// Get Clock widget configuration for dashboard
		// ------------------------ START OF TEST DATA -------------------
		// TODO (VM): replace test data with data from configuration

		switch (mt_rand(0,2)) {
			case 0:
				$data['style'] = TIME_TYPE_LOCAL;
				break;
			case 1:
				$data['style'] = TIME_TYPE_SERVER;
				break;
			case 2:
				$data['style'] = TIME_TYPE_HOST;
				$data['resourceid'] = 23308;
				$data['hostid'] = null;
				break;
		}

		// TODO (VM): Should be optional values, should have minimal value.
		// In case of beeing NULL, will take all available widget's space.
		// Validation: both null, or both bigger/smaller than.
		$data['width'] = null;
		$data['height'] = null;
//		$data['width'] = 150;
//		$data['height'] = 100;
		// ------------------------ END OF TEST DATA -------------------

		// Apply defualt value for data
		foreach ($default as $key => $value) {
			if (!array_key_exists($key, $data)) {
				$data[$key] = $value;
			}
		}

		switch ($data['style']) {
			case TIME_TYPE_HOST:
				$itemid = $data['resourceid'];

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
				'width' => $data['width'],
				'height' => $data['height']
			]
		]));
	}
}
