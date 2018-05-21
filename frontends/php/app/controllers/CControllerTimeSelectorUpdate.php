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
 * This controller is used by gtlc.js to update time selector date and time interval in user's profile.
 */
class CControllerTimeSelectorUpdate extends CController {

	private $data = [];
	private $relative_parser;
	private $absolute_parser;

	protected function init() {
		$this->relative_parser = new CRelativeTimeParser();
		$this->absolute_parser = new CAbsoluteTimeParser();
	}

	protected function checkInput() {
		$fields = [
			'method' => 'required|string|in increment,zoomout,decrement,rangechange',
			'idx' => 'required|string',
			'idx2' => 'required|id',
			'from' => 'required|string',
			'to' => 'required|string'
		];

		$ret = $this->validateInput($fields) && $this->validateProfile() && $this->validateInputDate();

		if (!$ret) {
			$this->data += [
				'error' => [],
				'can_zoomout' => false,
				'can_decrement' => false,
				'can_increment' => false
			];
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($this->data)]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$method = $this->getInput('method');
		$from = $this->getInput('from');
		$to = $this->getInput('to');
		$now_ts = time();
		$min_ts = $now_ts - ZBX_MAX_PERIOD;
		$from_ts = parseRelativeDate($from, true)->getTimestamp();
		$to_ts = parseRelativeDate($to, false)->getTimestamp();
		$interval = $to_ts - $from_ts + 1;
		$data = [];
		$datetime = new DateTime();

		switch ($method) {
			case 'decrement':
				$interval *= -1;

			case 'increment':
				$from_ts += $interval;
				$to_ts += $interval;
				$from = $datetime->setTimestamp($from_ts)->format(ZBX_DATE_TIME);
				$to = $datetime->setTimestamp($to_ts)->format(ZBX_DATE_TIME);
				break;

			case 'zoomout':
				$from_ts -= floor($interval / 2);
				$to_ts += floor($interval / 2);

				if ($to_ts > $now_ts) {
					$from_ts -= $to_ts - $now_ts;
				}

				if ($from_ts < $min_ts) {
					$to_ts += $min_ts - $from_ts;
					$from_ts = $min_ts;
				}

				if ($to_ts > $now_ts) {
					$to_ts = $now_ts;
				}

				$from = $datetime->setTimestamp($from_ts)->format(ZBX_DATE_TIME);
				$to = $datetime->setTimestamp($to_ts)->format(ZBX_DATE_TIME);
				break;
		}

		calculateTime([
			'profileIdx' => $this->getInput('idx'),
			'profileIdx2' => $this->getInput('idx2'),
			'updateProfile' => true,
			'from' => $from,
			'to' => $to
		]);

		$data += [
			'label' => relativeDateToText($from, $to),
			'from' => $from,
			'from_ts' => $from_ts,
			'from_date' => $datetime->setTimestamp($from_ts)->format(ZBX_DATE_TIME),
			'to' => $to,
			'to_ts' => $to_ts,
			'to_date' => $datetime->setTimestamp($to_ts)->format(ZBX_DATE_TIME),
			'can_zoomout' => $from_ts > $min_ts || $to_ts < $now_ts,
			'can_decrement' => $from_ts - $range >= $min_ts,
			'can_increment' => $to_ts + $range <= $now_ts
		];

		$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($data)]));
	}

	/**
	 * Validate input 'from' and 'to' arguments. Returns true on success.
	 *
	 * @return bool
	 */
	protected function validateInputDate() {
		$error = [];

		foreach (['from', 'to'] as $field) {
			$value = $this->getInput($field);

			if ($this->relative_parser->parse($value) !== CParser::PARSE_SUCCESS
					&& $this->absolute_parser->parse($value) !== CParser::PARSE_SUCCESS) {
				$error[$field] = _s('Invalid date "%s".', $value);
			}
		}

		$from = parseRelativeDate($this->getInput('from'), true);
		$to = parseRelativeDate($this->getInput('to'), false);
		$interval = ($from !== null && $to !== null) ? $to->getTimestamp() - $from->getTimestamp() : null;

		if ($interval !== null && $interval > ZBX_MAX_PERIOD) {
			$error['from'] = _n('Maximum time period to display is %1$s day.',
				'Maximum time period to display is %1$s days.',
				(int) ZBX_MAX_PERIOD / SEC_PER_DAY
			);
		}
		elseif ($interval !== null && $interval < ZBX_MIN_PERIOD) {
			$error['from'] = _n('Minimum time period to display is %1$s minute.',
				'Minimum time period to display is %1$s minutes.',
				(int) ZBX_MIN_PERIOD / SEC_PER_MIN
			);
		}

		if ($error) {
			$this->data['error'] = $error;
		}

		return ($error == []);
	}

	/**
	 * Validates profile 'idx' string value. Return true on success.
	 *
	 * @return bool
	 */
	function validateProfile() {
		$profiles = ['web.dashbrd.filter', 'web.screens.filter', 'web.graphs.filter', 'web.httpdetails.filter',
			'web.problem.filter', 'web.item.graph', 'web.auditlogs.filter', 'web.slides.filter', 'web.auditacts.filter',
			'web.item.graph.filter'
		];

		return in_array($this->getInput('idx'), $profiles);
	}
}
