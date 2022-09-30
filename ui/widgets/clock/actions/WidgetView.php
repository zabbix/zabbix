<?php declare(strict_types = 0);
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

	public function __construct() {
		parent::__construct();

		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'required|array',
			'dynamic_hostid' => 'db hosts.hostid'
		]);
	}

	protected function doAction(): void {
		$values = $this->getForm()->getFieldsValues();

		$config_defaults = [
			'name' => $this->widget->getDefaultName(),
			'type' => $values['clock_type'],
			'time' => null,
			'time_zone_offset' => null,
			'date' => date(ZBX_DATE),
			'time_zone' => TIMEZONE_DEFAULT_LOCAL,
			'is_enabled' => true,
			'critical_error' => null
		];

		switch ($values['time_type']) {
			case TIME_TYPE_HOST:
				$clock_data = $this->configureHostTime($values) + $config_defaults;
				break;

			case TIME_TYPE_SERVER:
				$clock_data = $this->configureFields($values) + $config_defaults;
				$clock_data['name'] = _('Server');
				break;

			default:
				$clock_data = $this->configureFields($values) + $config_defaults;
				$clock_data['name'] = _('Local');
				break;
		}

		// Pass clock configuration to browser script.
		if ($values['clock_type'] === Widget::TYPE_DIGITAL) {
			$clock_data['show'] = $values['show'];
			$clock_data['bg_color'] = $values['bg_color'];
			$clock_data['time_format'] = $values['time_format'];
			$clock_data['seconds'] = ($values['time_sec'] == 1);
			$clock_data['tzone_format'] = $values['tzone_format'];
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $clock_data['name']),
			'clock_data' => $clock_data,
			'styles' => self::getFieldStyles($values),
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

	/**
	 * @param array $fields
	 *
	 * @return boolean
	 */
	private function showDate(array $fields): bool {
		return ($fields['clock_type'] === Widget::TYPE_DIGITAL && in_array(Widget::SHOW_DATE, $fields['show']));
	}

	/**
	 * @param array $fields
	 *
	 * @return boolean
	 */
	private function showTime(array $fields): bool {
		return ($fields['clock_type'] === Widget::TYPE_ANALOG || in_array(Widget::SHOW_TIME, $fields['show']));
	}

	/**
	 * @param array $fields
	 *
	 * @return boolean
	 */
	private function showTimeZone(array $fields): bool {
		return ($fields['clock_type'] === Widget::TYPE_DIGITAL && in_array(Widget::SHOW_TIMEZONE, $fields['show']));
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

	/**
	 * Create required clock field values both for analog and digital clock.
	 *
	 * @param array $fields  Saved clock configuration.
	 *
	 * @return array  Return prepared clock configuration.
	 */
	private function configureFields(array $fields): array {
		$clock = [];

		$date = $this->makeDateTimeFromTimeZone($fields['tzone_timezone']);

		if ($date !== null) {
			if ($this->showDate($fields)) {
				$clock['date'] = $date->format(ZBX_DATE);
			}

			if ($this->showTime($fields)) {
				$clock = array_merge($clock, $this->makeTimeFromDateTime($date));
			}
		}

		if ($this->showTimeZone($fields)) {
			$clock['time_zone'] = $this->makeTimeZoneValue($fields['tzone_timezone'], $fields['tzone_format']);
		}

		return $clock;
	}

	/**
	 * @param array $fields  Saved clock configuration.
	 *
	 * @return array
	 */
	private function configureHostTime(array $fields): array {
		$items = [];
		$clock = ['is_enabled' => true];

		if ($this->hasInput('templateid')) {
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
				'itemids' => $fields['itemid'],
				'webitems' => true
			]);
		}

		if (!$clock['is_enabled']) {
			return $clock;
		}

		if ($items) {
			$item = $items[0];
			$clock['name'] = $item['hosts'][0]['name'];

			$last_value = Manager::History()->getLastValues([$item]);

			if ($last_value) {
				$last_value = $last_value[$item['itemid']][0];

				try {
					$now = new DateTime($last_value['value']);

					if ($this->showDate($fields)) {
						$clock['date'] = $now->format(ZBX_DATE);
					}

					$clock['time_zone_offset'] = (int) $now->format('Z');

					$clock['time'] = time() - ($last_value['clock'] - $now->getTimestamp());

					if ($this->showTimeZone($fields)) {
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
	 * Groups enabled field styles by field name (Date, Time, Time zone).
	 *
	 * @param array $fields  Saved clock configuration.
	 *
	 * @return array
	 */
	private static function getFieldStyles(array $fields): array {
		$cells = [];

		if ($fields['clock_type'] === Widget::TYPE_DIGITAL) {
			$show = $fields['show'];

			if (in_array(Widget::SHOW_DATE, $show)) {
				$cells['date'] = [
					'size' => $fields['date_size'],
					'bold' => ($fields['date_bold'] == 1),
					'color' => $fields['date_color']
				];
			}

			if (in_array(Widget::SHOW_TIME, $show)) {
				$cells['time'] = [
					'size' => $fields['time_size'],
					'bold' => ($fields['time_bold'] == 1),
					'color' => $fields['time_color']
				];
			}

			if (in_array(Widget::SHOW_TIMEZONE, $show)) {
				$cells['timezone'] = [
					'size' => $fields['tzone_size'],
					'bold' => ($fields['tzone_bold'] == 1),
					'color' => $fields['tzone_color']
				];
			}
		}

		return $cells;
	}
}
