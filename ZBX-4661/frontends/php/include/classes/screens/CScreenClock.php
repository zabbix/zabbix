<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
		$error = null;
		$timeOffset = null;
		$timeZone = null;

		switch ($this->screenitem['style']) {
			case TIME_TYPE_HOST:
				$items = API::Item()->get(array(
					'itemids' => $this->screenitem['resourceid'],
					'selectHosts' => API_OUTPUT_EXTEND,
					'output' => API_OUTPUT_EXTEND
				));
				$item = reset($items);
				$host = reset($item['hosts']);

				$timeType = $host['host'];
				preg_match('/([+-]{1})([\d]{1,2}):([\d]{1,2})/', $item['lastvalue'], $arr);

				if (!empty($arr)) {
					$timeZone = $arr[2] * SEC_PER_HOUR + $arr[3] * SEC_PER_MIN;
					if ($arr[1] == '-') {
						$timeZone = 0 - $timeZone;
					}
				}

				if ($lastvalue = strtotime($item['lastvalue'])) {
					$diff = (time() - $item['lastclock']);
					$timeOffset = $lastvalue + $diff;
				}
				else {
					$error = _('NO DATA');
				}
				break;
			case TIME_TYPE_SERVER:
				$error = null;
				$timeType = _('SERVER');
				$timeOffset = time();
				$timeZone = date('Z');
				break;
			default:
				$error = null;
				$timeType = _('LOCAL');
				$timeOffset = null;
				$timeZone = null;
				break;
		}

		if ($this->screenitem['width'] > $this->screenitem['height']) {
			$this->screenitem['width'] = $this->screenitem['height'];
		}

		$item = new CFlashClock($this->screenitem['width'], $this->screenitem['height'], $this->action);
		$item->setTimeError($error);
		$item->setTimeType($timeType);
		$item->setTimeZone($timeZone);
		$item->setTimeOffset($timeOffset);

		$flashclockOverDiv = new CDiv(null, 'flashclock');
		$flashclockOverDiv->setAttribute('style', 'width: '.$this->screenitem['width'].'px; height: '.$this->screenitem['height'].'px;');

		return $this->getOutput(array($item, $flashclockOverDiv));
	}
}
