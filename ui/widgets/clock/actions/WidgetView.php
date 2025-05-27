<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


namespace Widgets\Clock\Actions;

use API,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CTimezoneHelper,
	DateTime,
	DateTimeZone,
	Exception,
	Manager;

use Widgets\Clock\Widget;

class WidgetView extends CControllerDashboardWidgetView {

	protected function doAction(): void {
		$config_defaults = [
			'name' => $this->widget->getDefaultName(),
			'type' => $this->fields_values['clock_type'],
			'time' => null,
			'time_zone_offset' => null,
			'date' => date(ZBX_DATE),
			'time_zone' => TIMEZONE_DEFAULT_LOCAL,
			'is_enabled' => true,
			'critical_error' => null
		];

		switch ($this->fields_values['time_type']) {
			case TIME_TYPE_HOST:
				$clock_data = $this->configureHostTime() + $config_defaults;
				break;

			case TIME_TYPE_SERVER:
				$clock_data = $this->configureFields() + $config_defaults;
				$clock_data['name'] = _('Server');
				break;

			default:
				$clock_data = $this->configureFields() + $config_defaults;
				$clock_data['name'] = _('Local');
				break;
		}

		// Pass clock configuration to browser script.
		if ($this->fields_values['clock_type'] === Widget::TYPE_DIGITAL) {
			$clock_data['show'] = $this->fields_values['show'];
			$clock_data['bg_color'] = $this->fields_values['bg_color'];
			$clock_data['time_format'] = $this->fields_values['time_format'];
			$clock_data['seconds'] = ($this->fields_values['time_sec'] == 1);
			$clock_data['tzone_format'] = $this->fields_values['tzone_format'];
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $clock_data['name']),
			'clock_data' => $clock_data,
			'styles' => $this->getFieldStyles(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	/**
	 * Makes time zone field value.
	 *
	 * @param string $time_zone  Saved time zone name.
	 * @param int    $format     Use time zone name (from database) if short or time zone title (from list) if long.
	 *
	 * @return string  Return time zone name from list or 'local' if time zone must be set via browser.
	 */
	private function makeTimeZoneValue(string $time_zone, int $format = Widget::TIMEZONE_SHORT): string {
		if ($time_zone === TIMEZONE_DEFAULT_LOCAL) {
			return $time_zone;
		}

		$zone = $time_zone === ZBX_DEFAULT_TIMEZONE ? CTimezoneHelper::getSystemTimezone() : $time_zone;

		if ($format === Widget::TIMEZONE_SHORT) {
			if (($pos = strrpos($zone, '/')) !== false) {
				$zone = substr($zone, $pos + 1);
			}
		}
		else {
			$zone = CTimezoneHelper::getTitle($zone);
		}

		return str_replace('_', ' ', $zone);
	}

	private function showDate(): bool {
		return $this->fields_values['clock_type'] === Widget::TYPE_DIGITAL
			&& in_array(Widget::SHOW_DATE, $this->fields_values['show']);
	}

	private function showTime(): bool {
		return $this->fields_values['clock_type'] === Widget::TYPE_ANALOG
			|| in_array(Widget::SHOW_TIME, $this->fields_values['show']);
	}

	private function showTimeZone(): bool {
		return $this->fields_values['clock_type'] === Widget::TYPE_DIGITAL
			&& in_array(Widget::SHOW_TIMEZONE, $this->fields_values['show']);
	}

	/**
	 * @param DateTime $date
	 *
	 * @return array
	 */
	private function makeTimeFromDateTime(DateTime $date): array {
		$time = [];

		$time['time'] = $date->getTimestamp();
		$time['time_zone_offset'] = (int) $date->format('Z');

		return $time;
	}

	/**
	 * Makes DateTime object from passed time_zone string.
	 *
	 * @param string $time_zone  Time zone string.
	 *
	 * @return DateTime|null  Returns created DateTime object or null if time zone is set by browser.
	 */
	private function makeDateTimeFromTimeZone(string $time_zone): ?DateTime {
		if ($time_zone === TIMEZONE_DEFAULT_LOCAL) {
			return null;
		}

		$now = new DateTime();

		if ($time_zone !== ZBX_DEFAULT_TIMEZONE) {
			$now->setTimezone(new DateTimeZone($time_zone));
		}

		return $now;
	}

	private function configureHostTime(): array {
		$items = [];
		$clock = ['is_enabled' => true];

		if ($this->isTemplateDashboard()) {
			if ($this->fields_values['override_hostid']) {
				$template_items = API::Item()->get([
					'output' => ['key_'],
					'itemids' => $this->fields_values['itemid'],
					'webitems' => true
				]);

				if ($template_items) {
					$items = API::Item()->get([
						'output' => ['itemid', 'value_type'],
						'selectHosts' => ['name'],
						'hostids' => $this->fields_values['override_hostid'],
						'filter' => [
							'key_' => $template_items[0]['key_']
						],
						'webitems' => true
					]);
				}
			}
			// Editing template dashboard?
			else {
				$clock['is_enabled'] = false;
			}
		}
		else {
			$items = API::Item()->get([
				'output' => ['itemid', 'value_type'],
				'selectHosts' => ['name'],
				'itemids' => $this->fields_values['itemid'],
				'webitems' => true
			]);
		}

		if (!$clock['is_enabled']) {
			return $clock;
		}

		if ($items) {
			$item = $items[0];
			$clock['name'] = $item['hosts'][0]['name'];

			$last_value = $item['value_type'] == ITEM_VALUE_TYPE_BINARY
				? []
				: Manager::History()->getLastValues([$item]);

			if ($last_value) {
				$last_value = $last_value[$item['itemid']][0];

				try {
					$now = new DateTime($last_value['value']);

					if ($this->showDate()) {
						$clock['date'] = $now->format(ZBX_DATE);
					}

					$clock['time_zone_offset'] = (int) $now->format('Z');

					$clock['time'] = time() - ($last_value['clock'] - $now->getTimestamp());

					if ($this->showTimeZone()) {
						$clock['time_zone'] = 'UTC'.$now->format('P');
					}
				}
				catch (Exception $e) {
					$clock['is_enabled'] = false;
				}
			}
			else {
				$clock['is_enabled'] = false;
			}
		}
		else {
			$clock['critical_error'] = _('No permissions to referred object or it does not exist!');
		}

		return $clock;
	}

	/**
	 * Create required clock field values both for analog and digital clock.
	 */
	private function configureFields(): array {
		$clock = [];

		$date = $this->makeDateTimeFromTimeZone($this->fields_values['tzone_timezone']);

		if ($date !== null) {
			if ($this->showDate()) {
				$clock['date'] = $date->format(ZBX_DATE);
			}

			if ($this->showTime()) {
				$clock = array_merge($clock, $this->makeTimeFromDateTime($date));
			}
		}

		if ($this->showTimeZone()) {
			$clock['time_zone'] = $this->makeTimeZoneValue($this->fields_values['tzone_timezone'],
				$this->fields_values['tzone_format']
			);
		}

		return $clock;
	}

	/**
	 * Groups enabled field styles by field name (Date, Time, Time zone).
	 */
	private function getFieldStyles(): array {
		$cells = [];

		if ($this->fields_values['clock_type'] === Widget::TYPE_DIGITAL) {
			$show = $this->fields_values['show'];

			if (in_array(Widget::SHOW_DATE, $show)) {
				$cells[Widget::SHOW_DATE] = [
					'bold' => ($this->fields_values['date_bold'] == 1),
					'color' => $this->fields_values['date_color']
				];
			}

			if (in_array(Widget::SHOW_TIME, $show)) {
				$cells[Widget::SHOW_TIME] = [
					'bold' => ($this->fields_values['time_bold'] == 1),
					'color' => $this->fields_values['time_color']
				];
			}

			if (in_array(Widget::SHOW_TIMEZONE, $show)) {
				$cells[Widget::SHOW_TIMEZONE] = [
					'bold' => ($this->fields_values['tzone_bold'] == 1),
					'color' => $this->fields_values['tzone_color']
				];
			}
		}

		return $cells;
	}
}
