<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CJsScript,
	CPre,
	Manager;

use Zabbix\Core\CWidget;

class WidgetView extends CControllerDashboardWidgetView {

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'dynamic_hostid' => 'db hosts.hostid'
		]);
	}

	protected function doAction(): void {
		$error = null;

		$dynamic_widget_name = $this->widget->getDefaultName();
		$same_host = true;
		$items = [];
		$histories = [];

		// Editing template dashboard?
		if ($this->hasInput('templateid') && !$this->hasInput('dynamic_hostid')) {
			$error = _('No data.');
		}
		else {
			$is_template_dashboard = $this->hasInput('templateid');
			$is_dynamic_item = ($is_template_dashboard || $this->fields_values['dynamic'] == CWidget::DYNAMIC_ITEM);

			if ($this->fields_values['itemids']) {
				$items = API::Item()->get([
					'output' => ['itemid', 'name', 'key_', 'value_type', 'units', 'valuemapid'],
					'selectHosts' => ['name'],
					'selectValueMap' => ['mappings'],
					'itemids' => $this->fields_values['itemids'],
					'webitems' => true,
					'preservekeys' => true
				]);

				$dynamic_hostid = $this->getInput('dynamic_hostid', 0);

				if ($items && $is_dynamic_item && $dynamic_hostid) {
					$items = API::Item()->get([
						'output' => ['itemid', 'name', 'value_type', 'units', 'valuemapid'],
						'selectHosts' => ['name'],
						'selectValueMap' => ['mappings'],
						'filter' => [
							'hostid' => $dynamic_hostid,
							'key_' => array_keys(array_column($items, null, 'key_'))
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
				$histories = Manager::History()->getLastValues($items, $this->fields_values['show_lines']);

				if ($histories) {
					$histories = array_merge(...$histories);

					foreach ($histories as &$history) {
						$history['value'] = formatHistoryValue($history['value'], $items[$history['itemid']], false);
						$history['value'] = $this->fields_values['show_as_html']
							? new CJsScript($history['value'])
							: new CPre($history['value']);
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
					$dynamic_widget_name = $is_template_dashboard
						? $item['name']
						: $host_name.NAME_DELIMITER.$item['name'];
				}
				elseif ($same_host && $items_count > 1) {
					$dynamic_widget_name = $is_template_dashboard
						? _n('%1$s item', '%1$s items', $items_count)
						: $host_name.NAME_DELIMITER._n('%1$s item', '%1$s items', $items_count);
				}
			}
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $dynamic_widget_name),
			'items' => $items,
			'histories' => $histories,
			'style' => $this->fields_values['style'],
			'same_host' => $same_host,
			'show_lines' => $this->fields_values['show_lines'],
			'error' => $error,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
