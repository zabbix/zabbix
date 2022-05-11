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


/**
 * This controller is used by gtlc.js to update time selector date and time interval in user's profile.
 */
class CControllerTimeSelectorUpdate extends CController {

	public static $profiles = ['web.dashboard.filter', 'web.charts.filter', 'web.httpdetails.filter',
		'web.problem.filter', 'web.auditlog.filter', 'web.auditacts.filter', 'web.item.graph.filter',
		'web.toptriggers.filter', 'web.avail_report.filter', CControllerHost::FILTER_IDX, CControllerProblem::FILTER_IDX
	];

	/**
	 * @var CRangeTimeParser
	 */
	private $range_time_parser;

	private $data = [];

	public function init() {
		$this->range_time_parser = new CRangeTimeParser();
	}

	protected function checkInput() {
		$fields = [
			'method' => 'required|in increment,zoomout,decrement,rangechange,rangeoffset',
			'idx' => 'required|in '.implode(',', static::$profiles),
			'idx2' => 'required|id',
			'from' => 'required|string',
			'to' => 'required|string',
			'from_offset' => 'int32|ge 0',
			'to_offset' => 'int32|ge 0'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			/*
			 * This block executes if, for example, a missing profile is given. Since this is an AJAX request, it should
			 * throw a JS alert() with current message in timeSelectorEventHandler() in gtlc.js.
			 */

			$messages = CMessageHelper::getMessages();

			$this->setResponse(new CControllerResponseData([
				'main_block' => json_encode(['error' => $messages[0]['message']])
			]));

			return $ret;
		}

		$ret = $this->validateInputDateRange();

		if ($this->getInput('method') === 'rangeoffset' && (!$this->hasInput('from_offset')
				|| !$this->hasInput('to_offset'))) {
			$ret = false;
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($this->data)]));
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
		$date_type = [];
		$ts = [];
		$ts['now'] = time();

		foreach (['from', 'to'] as $field) {
			$value[$field] = $this->getInput($field);
			$this->range_time_parser->parse($value[$field]);
			$date_type[$field] = $this->range_time_parser->getTimeType();
			$ts[$field] = $this->range_time_parser
				->getDateTime($field === 'from')
				->getTimestamp();
		}

		$period = $ts['to'] - $ts['from'] + 1;
		$this->range_time_parser->parse('now-'.CSettingsHelper::get(CSettingsHelper::MAX_PERIOD));
		$max_period = 1 + $ts['now'] - $this->range_time_parser
			->getDateTime(true)
			->getTimestamp();

		switch ($method) {
			case 'decrement':
				$offset = $period;

				if ($ts['from'] - $offset < 0) {
					$offset = $ts['from'];
				}

				$ts['from'] -= $offset;
				$ts['to'] -= $offset;

				$value['from'] = $date->setTimestamp($ts['from'])->format(ZBX_FULL_DATE_TIME);
				$value['to'] = $date->setTimestamp($ts['to'])->format(ZBX_FULL_DATE_TIME);
				break;

			case 'increment':
				$offset = $period;

				if ($ts['to'] + $offset > $ts['now']) {
					$offset = $ts['now'] - $ts['to'];
				}

				$ts['from'] += $offset;
				$ts['to'] += $offset;

				$value['from'] = $date->setTimestamp($ts['from'])->format(ZBX_FULL_DATE_TIME);
				$value['to'] = $date->setTimestamp($ts['to'])->format(ZBX_FULL_DATE_TIME);
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

				if ($ts['to'] - $ts['from'] + 1 > $max_period) {
					$ts['from'] = $ts['to'] - $max_period + 1;
				}

				$value['from'] = $date->setTimestamp($ts['from'])->format(ZBX_FULL_DATE_TIME);
				$value['to'] = $date->setTimestamp($ts['to'])->format(ZBX_FULL_DATE_TIME);
				break;

			case 'rangeoffset':
				$from_offset = $this->getInput('from_offset');
				$to_offset = $this->getInput('to_offset');

				if ($from_offset > 0) {
					$ts['from'] += $from_offset;
					$value['from'] = $date->setTimestamp($ts['from'])->format(ZBX_FULL_DATE_TIME);
				}

				if ($to_offset > 0) {
					$ts['to'] -= $to_offset;
					$value['to'] = $date->setTimestamp($ts['to'])->format(ZBX_FULL_DATE_TIME);
				}
				break;

			case 'rangechange':
				// Format only absolute date according ZBX_FULL_DATE_TIME string.
				foreach (['from', 'to'] as $field) {
					if ($date_type[$field] === CRangeTimeParser::ZBX_TIME_ABSOLUTE) {
						$value[$field] = $date->setTimestamp($ts[$field])->format(ZBX_FULL_DATE_TIME);
					}
				}
				break;
		}

		updateTimeSelectorPeriod([
			'profileIdx' => $this->getInput('idx'),
			'profileIdx2' => $this->getInput('idx2'),
			'from' => $value['from'],
			'to' => $value['to']
		]);

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode([
			'label' => relativeDateToText($value['from'], $value['to']),
			'from' => $value['from'],
			'from_ts' => $ts['from'],
			'from_date' => $date->setTimestamp($ts['from'])->format(ZBX_FULL_DATE_TIME),
			'to' => $value['to'],
			'to_ts' => $ts['to'],
			'to_date' => $date->setTimestamp($ts['to'])->format(ZBX_FULL_DATE_TIME)
		] + getTimeselectorActions($value['from'], $value['to']))]));
	}

	/**
	 * Validate input 'from' and 'to' arguments. Returns true on success.
	 *
	 * @return bool
	 */
	protected function validateInputDateRange() {
		$this->data['error'] = [];
		$ts = [];
		$ts['now'] = time();

		foreach (['from', 'to'] as $field) {
			$value = $this->getInput($field);

			if ($this->range_time_parser->parse($value) !== CParser::PARSE_SUCCESS) {
				$this->data['error'][$field] = _('Invalid date.');
			}
			else {
				$ts[$field] = $this->range_time_parser
					->getDateTime($field === 'from')
					->getTimestamp();
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
		$this->range_time_parser->parse('now-'.CSettingsHelper::get(CSettingsHelper::MAX_PERIOD));
		$max_period = 1 + $ts['now'] - $this->range_time_parser
			->getDateTime(true)
			->getTimestamp();

		if ($period < ZBX_MIN_PERIOD) {
			$this->data['error']['from'] = _n('Minimum time period to display is %1$s minute.',
				'Minimum time period to display is %1$s minutes.', (int) (ZBX_MIN_PERIOD / SEC_PER_MIN)
			);
		}
		elseif ($period > $max_period) {
			$this->data['error']['from'] = _n('Maximum time period to display is %1$s day.',
				'Maximum time period to display is %1$s days.', (int) round($max_period / SEC_PER_DAY)
			);
		}

		return !$this->data['error'];
	}
}
