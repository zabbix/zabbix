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

		$default_values = [
			'dynamic' => WIDGET_SIMPLE_ITEM,
			'style' => 0
		];

		foreach ($default_values as $field_key => $value) {
			if (!array_key_exists($field_key, $fields)) {
				$fields[$field_key] = $value;
			}
		}

		$dynamic_hostid = $this->getInput('dynamic_hostid', 0);
		$show_lines = $fields['show_lines'];
		$dynamic = $fields['dynamic'];
		$style = $fields['style'];
		$table_rows = [];

		// Select dynamically selected host.
		if ($dynamic && $dynamic_hostid) {
			$new_itemid = get_same_item_for_host($fields['itemid'], $dynamic_hostid);
			$fields['itemid'] = $new_itemid ?: 0;
		}

		// Resolve item name.
		if ($fields['itemid']) {
			$items = CMacrosResolverHelper::resolveItemNames([get_item_by_itemid($fields['itemid'])]);
		}
		else {
			$items = [];
		}

		if (!$items) {
			$error = _('No item selected.');
		}
		// Select item history data.
		else if (($item = reset($items)) !== false) {
			$histories = API::History()->get([
				'history' => $item['value_type'],
				'itemids' => $item['itemid'],
				'output' => API_OUTPUT_EXTEND,
				'sortorder' => ZBX_SORT_DOWN,
				'sortfield' => ['itemid', 'clock'],
				'limit' => $show_lines
			]);

			foreach ($histories as $history) {
				switch ($item['value_type']) {
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

				if ($item['valuemapid'] > 0) {
					$value = applyValueMap($value, $item['valuemapid']);
				}

				if ($style == 0) {
					$value = new CPre($value);
				}

				$table_rows[] = [zbx_date2str(DATE_TIME_FORMAT_SECONDS, $history['clock']), $value];
			}
		}

		if (!$error && !$table_rows) {
			$error = _('No permissions to selected item or it does not exist.');
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', CWidgetConfig::getKnownWidgetTypes()[WIDGET_PLAIN_TEXT]),
			'table_rows' => $table_rows,
			'error' => $error,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
