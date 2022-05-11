<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CControllerWidgetClockView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_CLOCK);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json',
			'dynamic_hostid' => 'db hosts.hostid'
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();

		$time = null;
		$name = $this->getDefaultName();
		$time_zone_offset = null;
		$is_enabled = true;
		$critical_error = null;

		switch ($fields['time_type']) {
			case TIME_TYPE_HOST:
				if ($this->getContext() === CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD) {
					if ($this->hasInput('dynamic_hostid')) {
						$template_items = API::Item()->get([
							'output' => ['key_'],
							'itemids' => $fields['itemid'],
							'webitems' => true
						]);

						if ($template_items) {
							$items = API::Item()->get([
								'output' => ['itemid', 'value_type'],
								'selectHosts' => ['name'],
								'hostids' => [$this->getInput('dynamic_hostid')],
								'filter' => [
									'key_' => $template_items[0]['key_']
								],
								'webitems' => true
							]);
						}
						else {
							$items = [];
						}
					}
					// Editing template dashboard?
					else {
						$is_enabled = false;
					}
				}
				else {
					$items = API::Item()->get([
						'output' => ['itemid', 'value_type'],
						'selectHosts' => ['name'],
						'itemids' => $fields['itemid'],
						'webitems' => true
					]);
				}

				if ($is_enabled) {
					if ($items) {
						$item = $items[0];
						$name = $item['hosts'][0]['name'];

						$last_value = Manager::History()->getLastValues([$item]);

						if ($last_value) {
							$last_value = $last_value[$item['itemid']][0];

							try {
								$now = new DateTime($last_value['value']);

								$time_zone_offset = (int) $now->format('Z');

								$time = time() - ($last_value['clock'] - $now->getTimestamp());
							}
							catch (Exception $e) {
								$is_enabled = false;
							}
						}
						else {
							$is_enabled = false;
						}
					}
					else {
						$critical_error = _('No permissions to referred object or it does not exist!');
					}
				}
				break;

			case TIME_TYPE_SERVER:
				$name = _('Server');

				$now = new DateTime();
				$time = $now->getTimestamp();
				$time_zone_offset = (int) $now->format('Z');
				break;

			default:
				$name = _('Local');
				break;
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $name),
			'clock' => [
				'time' => $time,
				'time_zone_offset' => $time_zone_offset,
				'is_enabled' => $is_enabled,
				'critical_error' => $critical_error
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
