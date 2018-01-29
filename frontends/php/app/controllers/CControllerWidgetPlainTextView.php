<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


/**
 * Class for Dashboard Plain-text widget view.
 */
class CControllerWidgetPlainTextView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_PLAIN_TEXT);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json',
			'dynamic_hostid' => 'db hosts.hostid'
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();
		$error = null;

		$itemids = $fields['itemids'];
		$name_location = $fields['name_location'];
		$show_lines = $fields['show_lines'];
		$style = $fields['style'];
		$dynamic = $fields['dynamic'];

		$same_host = null;
		$items = [];
		$history_data = [];

		if (count($itemids)) {
			$items = API::Item()->get([
				'output' => ['itemid', 'hostid', 'name', 'key_', 'value_type', 'valuemapid'],
				'selectHosts' => ['name'],
				'itemids' => $itemids,
				'webitems' => true
			]);

			$dynamic_hostid = $this->getInput('dynamic_hostid', 0);
			if ($items && $dynamic && $dynamic_hostid) {
				$items = API::Item()->get([
					'output' => ['itemid', 'hostid', 'name', 'key_', 'value_type', 'valuemapid'],
					'selectHosts' => ['name'],
					'filter' => [
						'hostid' => $dynamic_hostid,
						'key_' => array_column($items, 'key_')
					],
					'webitems' => true
				]);
			}
		}

		if (!$items) {
			$error = _('No permissions to referred object or it does not exist!');
		}
		else {
			$items = zbx_toHash($items, 'itemid');
			$items = CMacrosResolverHelper::resolveItemNames($items);

			// Grouping items ids by value type and detect same host.
			$itemsids_by_type = [];
			foreach ($items as $item) {
				if (! array_key_exists($item['value_type'], $itemsids_by_type)) {
					$itemsids_by_type[$item['value_type']] = [];
				}
				$itemsids_by_type[$item['value_type']][] = $item['itemid'];

				if ($same_host !== false) {
					if ($same_host == null) {
						$same_host = $item['hosts'][0]['name'];
					}
					elseif ($same_host !== null && $same_host != $item['hosts'][0]['name']) {
						$same_host = false;
					}
				}
			}

			foreach ($itemsids_by_type as $value_type => $ids) {
				$histories = API::History()->get([
					'output' => API_OUTPUT_EXTEND,
					'history' => $value_type,
					'itemids' => $ids,
					'sortorder' => ZBX_SORT_DOWN,
					'sortfield' => 'clock',
					'limit' => $show_lines
				]);

				if ($histories) {
					foreach ($histories as $history) {

						switch ($value_type) {
							case ITEM_VALUE_TYPE_FLOAT:
								sscanf($history['value'], '%f', $value);
								break;
							case ITEM_VALUE_TYPE_TEXT:
							case ITEM_VALUE_TYPE_STR:
							case ITEM_VALUE_TYPE_LOG:
								$value = $style ? new CJsScript($history['value']) : $history['value'];
								break;
							default:
								$value = $history['value'];
								break;
						}

						$item = $items[$history['itemid']];

						if ($item['valuemapid'] != 0) {
							$value = applyValueMap($value, $item['valuemapid']);
						}

						if ($style == 0) {
							$value = new CPre($value);
						}

						$history_data[] = [
							'itemid' => $history['itemid'],
							'host_name' => $item['hosts'][0]['name'],
							'item_name' => $item['name_expanded'],
							'value' => $value,
							'clock' => $history['clock'],
							'ns' => $history['ns']
						];
					}
				}
			}

			CArrayHelper::sort($history_data, [
				['field' => 'clock', 'order' => ZBX_SORT_DOWN],
				['field' => 'ns', 'order' => ZBX_SORT_DOWN]
			]);
		}

		$dynamic_widget_name = $this->getDefaultHeader();

		$items_count = count($items);
		if ($items_count == 1) {
			$item = reset($items);
			$dynamic_widget_name = $same_host.NAME_DELIMITER.$item['name_expanded'];
		}
		elseif ($same_host && $items_count > 1) {
			$dynamic_widget_name = $same_host.NAME_DELIMITER._n('%1$s item', '%1$s items', $items_count);
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $dynamic_widget_name),
			'items' => $items,
			'history_data' => $history_data,
			'name_location' => $name_location,
			'same_host' => $same_host,
			'show_lines' => $show_lines,
			'error' => $error,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
