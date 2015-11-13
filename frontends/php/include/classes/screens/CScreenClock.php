<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CScreenClock extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$time_offset = null;
		$title = null;
		$time_zone = null;
		$error = null;

		switch ($this->screenitem['style']) {
			case TIME_TYPE_HOST:
				$items = API::Item()->get([
					'output' => ['itemid', 'value_type'],
					'selectHosts' => ['host'],
					'itemids' => [$this->screenitem['resourceid']]
				]);

				if ($items) {
					$item = $items[0];
					$title = $item['hosts'][0]['host'];
					unset($items, $item['hosts']);

					$last_value = Manager::History()->getLast([$item]);

					if ($last_value) {
						$last_value = $last_value[$item['itemid']][0];

//						$time = DateTime::createFromFormat('Y-m-d,H:i:s.???,P', $last_value['value']);
						$time = new DateTime($last_value['value']);

						if ($time !== false) {
							$time_zone = 'GMT '.$time->format('P');

							$diff = time() - $last_value['clock'];
							$time_offset = $time->getTimestamp() + $diff;
						}
						else {
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

				$time = new DateTime();
				$time_offset = time();
				$time_zone = 'GMT '.$time->format('P');
				break;

			default:
				$title = _('Local');
				break;
		}

		if ($this->screenitem['width'] > $this->screenitem['height']) {
			$this->screenitem['width'] = $this->screenitem['height'];
		}

		$item = (new CClock(/*$this->action*/))
			->setWidth($this->screenitem['width'])
			->setHeight($this->screenitem['height'])
			->setTimeZone($time_zone)
			->setTitle($title);
//		$item->setTimeError($error);
//		$item->setTimeOffset($timeOffset);

		return $this->getOutput($item);
	}
}
