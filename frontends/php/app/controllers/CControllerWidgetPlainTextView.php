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

		$dynamic_widget_name = $this->getDefaultHeader();
		$same_host = true;
		$items = [];
		$histories = [];

		if ($fields['itemids']) {
			$items = API::Item()->get([
				'output' => ['itemid', 'hostid', 'name', 'key_', 'value_type', 'valuemapid'],
				'selectHosts' => ['name'],
				'itemids' => $fields['itemids'],
				'webitems' => true,
				'preservekeys' => true
			]);

			$dynamic_hostid = $this->getInput('dynamic_hostid', 0);

			$keys = [];
			foreach ($items as $item) {
				$keys[$item['key_']] = true;
			}

			if ($items && $fields['dynamic'] && $dynamic_hostid) {
				$items = API::Item()->get([
					'output' => ['itemid', 'hostid', 'name', 'key_', 'value_type', 'valuemapid'],
					'selectHosts' => ['name'],
					'filter' => [
						'hostid' => $dynamic_hostid,
						'key_' => array_keys($keys)
					],
					'webitems' => true,
					'preservekeys' => true
				]);
			}
		}

		if (!$items) {
			$error = _('No permissions to referred object or it does not exist!');
		}
		else {
			$items = CMacrosResolverHelper::resolveItemNames($items);
			$histories = Manager::History()->getLastValues($items, $fields['show_lines']);

			if ($histories) {
				$histories = call_user_func_array('array_merge', $histories);

				foreach ($histories as &$history) {

					switch ($items) {
						case ITEM_VALUE_TYPE_FLOAT:
							sscanf($history['value'], '%f', $history['value']);
							break;
						case ITEM_VALUE_TYPE_TEXT:
						case ITEM_VALUE_TYPE_STR:
						case ITEM_VALUE_TYPE_LOG:
							if ($fields['style']) {
								$history['value'] = new CJsScript($history['value']);
							}
							break;
					}

					if ($items[$history['itemid']]['valuemapid'] != 0) {
						$history['value'] = applyValueMap($history['value'], $items[$history['itemid']]['valuemapid']);
					}

					if ($fields['style'] == 0) {
						$history['value'] = new CPre($history['value']);
					}
				}
				unset($history);
			}

			CArrayHelper::sort($histories, [
				['field' => 'clock', 'order' => ZBX_SORT_DOWN],
				['field' => 'ns', 'order' => ZBX_SORT_DOWN]
			]);

			$host_name = '';
			foreach ($items as $item) {
				if ($host_name === '') {
					$host_name = $item['hosts'][0]['name'];
				}
				elseif ($host_name !== $item['hosts'][0]['name']) {
					$same_host = false;
				}
			}

			$items_count = count($items);
			if ($items_count == 1) {
				$item = reset($items);
				$dynamic_widget_name = $host_name.NAME_DELIMITER.$item['name_expanded'];
			}
			elseif ($same_host && $items_count > 1) {
				$dynamic_widget_name = $host_name.NAME_DELIMITER._n('%1$s item', '%1$s items', $items_count);
			}
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $dynamic_widget_name),
			'items' => $items,
			'histories' => $histories,
			'name_location' => $fields['name_location'],
			'same_host' => $same_host,
			'show_lines' => $fields['show_lines'],
			'error' => $error,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
