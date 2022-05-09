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
		$config_defaults = [
			'name' => $this->getDefaultName(),
			'type' => $fields['clock_type'],
			'time' => null,
			'time_zone_offset' => null,
			'date' => date(ZBX_DATE),
			'time_zone' => TIMEZONE_DEFAULT_LOCAL,
			'is_enabled' => true,
			'critical_error' => null
		];

		switch ($fields['time_type']) {
			case TIME_TYPE_HOST:
				$clock_data = $this->configureHostTime($fields) + $config_defaults;
				break;

			case TIME_TYPE_SERVER:
				$clock_data = $this->configureFields($fields) + $config_defaults;
				$clock_data['name'] = _('Server');
				break;

			default:
				$clock_data = $this->configureFields($fields) + $config_defaults;
				$clock_data['name'] = _('Local');
				break;
		}

		// Pass clock configiguration to browser script.
		if ($fields['clock_type'] === WIDGET_CLOCK_TYPE_DIGITAL) {
			$clock_data['show'] = $fields['show'];
			$clock_data['bg_color'] = $fields['bg_color'];
			$clock_data['time_format'] = $fields['time_format'];
			$clock_data['seconds'] = ($fields['time_sec'] == 1);
			$clock_data['tzone_format'] = $fields['tzone_format'];
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $clock_data['name']),
			'clock_data' => $clock_data,
			'styles' => self::getFieldStyles($fields),
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
	protected function makeTimeZoneValue(string $time_zone, int $format = WIDGET_CLOCK_TIMEZONE_SHORT): string {
		if ($time_zone === TIMEZONE_DEFAULT_LOCAL) {
			return $time_zone;
		}
		elseif ($time_zone === ZBX_DEFAULT_TIMEZONE) {
			$zone = CTimezoneHelper::getSystemTimezone();
		}
		else {
			$zone = $time_zone;
		}

		if ($format === WIDGET_CLOCK_TIMEZONE_SHORT) {
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
	protected function showDate(array $fields): bool {
		return ($fields['clock_type'] === WIDGET_CLOCK_TYPE_DIGITAL
			&& in_array(WIDGET_CLOCK_SHOW_DATE, $fields['show'])
		);
	}

	/**
	 * @param array $fields
	 *
	 * @return boolean
	 */
	protected function showTime(array $fields): bool {
		return ($fields['clock_type'] === WIDGET_CLOCK_TYPE_ANALOG
			|| in_array(WIDGET_CLOCK_SHOW_TIMEZONE, $fields['show'])
		);
	}

	/**
	 * @param array $fields
	 *
	 * @return boolean
	 */
	protected function showTimeZone(array $fields): bool {
		return ($fields['clock_type'] === WIDGET_CLOCK_TYPE_DIGITAL
			&& in_array(WIDGET_CLOCK_SHOW_TIMEZONE, $fields['show'])
		);
	}

	/**
	 * @param DateTime $date
	 *
	 * @return array
	 */
	protected function makeTimeFromDateTime(DateTime $date): array {
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
	protected function makeDateTimeFromTimeZone(string $time_zone): ?DateTime {
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
	protected function configureFields(array $fields): array {
		$clock = [];

		$date = $this->makeDateTimeFromTimeZone($fields['tzone_timezone']);

		if ($this->showDate($fields) && $date !== null) {
			$clock['date'] = $date->format(ZBX_DATE);
		}

		if ($this->showTime($fields) && $date !== null) {
			$clock = array_merge($clock, $this->makeTimeFromDateTime($date));
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
	protected function configureHostTime(array $fields): array {
		$clock = ['is_enabled' => true];

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
	protected static function getFieldStyles(array $fields): array {
		$cells = [];

		if ($fields['clock_type'] === WIDGET_CLOCK_TYPE_DIGITAL) {
			$show = $fields['show'];

			if (in_array(WIDGET_CLOCK_SHOW_DATE, $show)) {
				$cells['date'] = [
					'size' => $fields['date_size'],
					'bold' => ($fields['date_bold'] == 1),
					'color' => $fields['date_color']
				];
			}

			if (in_array(WIDGET_CLOCK_SHOW_TIME, $show)) {
				$cells['time'] = [
					'size' => $fields['time_size'],
					'bold' => ($fields['time_bold'] == 1),
					'color' => $fields['time_color']
				];
			}

			if (in_array(WIDGET_CLOCK_SHOW_TIMEZONE, $show)) {
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
