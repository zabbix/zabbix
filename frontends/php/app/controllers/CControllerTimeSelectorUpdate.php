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

	/**
	 * @var CRangeTimeParser
	 */
	private $range_time_parser;

	private $data = [];

	public function __construct() {
		$this->range_time_parser = new CRangeTimeParser();
	}

	protected function checkInput() {
		$profiles = ['web.dashbrd.filter', 'web.screens.filter', 'web.graphs.filter', 'web.httpdetails.filter',
			'web.problem.filter', 'web.auditlogs.filter', 'web.slides.filter', 'web.auditacts.filter',
			'web.item.graph.filter'
		];

		$fields = [
			'method' => 'required|in increment,zoomout,decrement,rangechange,rangeoffset',
			'idx' => 'required|in '.implode(',', $profiles),
			'idx2' => 'required|id',
			'from' => 'required|string',
			'to' => 'required|string',
			'from_offset' => 'int32|ge 0',
			'to_offset' => 'int32|ge 0'
		];

		$ret = $this->validateInput($fields) && $this->validateInputDateRange();

		if ($this->getInput('method') === 'rangeoffset' && (!$this->hasInput('from_offset')
				|| !$this->hasInput('to_offset'))) {
			$ret = false;
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($this->data)]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$method = $this->getInput('method');
		$date = new DateTime();
		$value = [];
		$ts = [];
		$ts['now'] = time();

		foreach (['from', 'to'] as $field) {
			$value[$field] = $this->getInput($field);
			$this->range_time_parser->parse($value[$field]);
			$ts[$field] = $this->range_time_parser->getDateTime($field === 'from')->getTimestamp();
		}

		$period = $ts['to'] - $ts['from'] + 1;

		switch ($method) {
			case 'decrement':
				$offset = $period;

				if ($ts['from'] - $offset < 0) {
					$offset = $ts['from'];
				}

				$ts['from'] -= $offset;
				$ts['to'] -= $offset;

				$value['from'] = $date->setTimestamp($ts['from'])->format(ZBX_DATE_TIME);
				$value['to'] = $date->setTimestamp($ts['to'])->format(ZBX_DATE_TIME);
				break;

			case 'increment':
				$offset = $period;

				if ($ts['to'] + $offset > $ts['now']) {
					$offset = $ts['now'] - $ts['to'];
				}

				$ts['from'] += $offset;
				$ts['to'] += $offset;

				$value['from'] = $date->setTimestamp($ts['from'])->format(ZBX_DATE_TIME);
				$value['to'] = $date->setTimestamp($ts['to'])->format(ZBX_DATE_TIME);
				break;

			case 'zoomout':
				$right_offset = (int) ($period / 2);
				if ($ts['to'] + $right_offset > $ts['now']) {
					$right_offset = $ts['now'] - $ts['to'];
				}
				$left_offset = $period - $right_offset;
				if ($ts['from'] - $left_offset < 0) {
					$left_offset = $ts['from'];
				}

				$ts['from'] -= $left_offset;
				$ts['to'] += $right_offset;

				if ($ts['to'] - $ts['from'] + 1 > ZBX_MAX_PERIOD) {
					$ts['from'] = $ts['to'] - ZBX_MAX_PERIOD + 1;
				}

				$value['from'] = $date->setTimestamp($ts['from'])->format(ZBX_DATE_TIME);
				$value['to'] = $date->setTimestamp($ts['to'])->format(ZBX_DATE_TIME);
				break;

			case 'rangeoffset':
				$from_offset = $this->getInput('from_offset');
				$to_offset = $this->getInput('to_offset');

				if ($from_offset > 0) {
					$ts['from'] += $from_offset;
					$value['from'] = $date->setTimestamp($ts['from'])->format(ZBX_DATE_TIME);
				}

				if ($to_offset > 0) {
					$ts['to'] -= $to_offset;
					$value['to'] = $date->setTimestamp($ts['to'])->format(ZBX_DATE_TIME);
				}
				break;
		}

		updateTimeSelectorPeriod([
			'profileIdx' => $this->getInput('idx'),
			'profileIdx2' => $this->getInput('idx2'),
			'from' => $value['from'],
			'to' => $value['to']
		]);

		$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson([
			'label' => relativeDateToText($value['from'], $value['to']),
			'from' => $value['from'],
			'from_ts' => $ts['from'],
			'from_date' => $date->setTimestamp($ts['from'])->format(ZBX_DATE_TIME),
			'to' => $value['to'],
			'to_ts' => $ts['to'],
			'to_date' => $date->setTimestamp($ts['to'])->format(ZBX_DATE_TIME),
			'can_zoomout' => ($ts['to'] - $ts['from'] + 1 < ZBX_MAX_PERIOD),
			'can_decrement' => ($ts['from'] > 0),
			'can_increment' => ($ts['to'] < $ts['now'])
		])]));
	}

	/**
	 * Validate input 'from' and 'to' arguments. Returns true on success.
	 *
	 * @return bool
	 */
	protected function validateInputDateRange() {
		$this->data['error'] = [];
		$ts = [];

		foreach (['from', 'to'] as $field) {
			$value = $this->getInput($field);

			if ($this->range_time_parser->parse($value) !== CParser::PARSE_SUCCESS) {
				$this->data['error'][$field] = _('Invalid date.');
			}
			else {
				$ts[$field] = $this->range_time_parser->getDateTime($field === 'from')->getTimestamp();
			}
		}

		if ($this->data['error']) {
			return false;
		}

		if ($this->getInput('method') === 'rangeoffset') {
			$ts['from'] += $this->getInput('from_offset');
			$ts['to'] -= $this->getInput('to_offset');
		}

		$period = $ts['to'] - $ts['from'] + 1;

		if ($period < ZBX_MIN_PERIOD) {
			$this->data['error']['from'] = _n('Minimum time period to display is %1$s minute.',
				'Minimum time period to display is %1$s minutes.', (int) ZBX_MIN_PERIOD / SEC_PER_MIN
			);
		}
		elseif ($period > ZBX_MAX_PERIOD) {
			$this->data['error']['from'] = _n('Maximum time period to display is %1$s day.',
				'Maximum time period to display is %1$s days.', (int) ZBX_MAX_PERIOD / SEC_PER_DAY
			);
		}

		return !$this->data['error'];
	}
}
