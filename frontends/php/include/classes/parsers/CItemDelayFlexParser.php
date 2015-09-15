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


/**
 * A parser for flexible and scheduling intervals.
 */
class CItemDelayFlexParser {

	// Possible parsing states.
	const STATE_NEW = 0;
	const STATE_FLEXIBLE_INTERVAL = 1;
	const STATE_FLEXIBLE_PERIOD = 2;
	const STATE_FLEXIBLE_HOUR_FROM = 3;
	const STATE_FLEXIBLE_MINUTE_FROM = 4;
	const STATE_FLEXIBLE_HOUR_TO = 5;
	const STATE_FLEXIBLE_MINUTE_TO = 6;
	const STATE_SCHEDULING_MONTH_FROM = 7;
	const STATE_SCHEDULING_MONTH_TO = 8;
	const STATE_SCHEDULING_MONTH_STEP = 9;
	const STATE_SCHEDULING_WEEK_FROM = 10;
	const STATE_SCHEDULING_WEEK_TO = 11;
	const STATE_SCHEDULING_WEEK_STEP = 12;
	const STATE_SCHEDULING_HOUR_FROM = 13;
	const STATE_SCHEDULING_HOUR_TO = 14;
	const STATE_SCHEDULING_HOUR_STEP = 15;
	const STATE_SCHEDULING_MINUTE_FROM = 16;
	const STATE_SCHEDULING_MINUTE_TO = 17;
	const STATE_SCHEDULING_MINUTE_STEP = 18;
	const STATE_SCHEDULING_SECOND_FROM = 19;
	const STATE_SCHEDULING_SECOND_TO = 20;
	const STATE_SCHEDULING_SECOND_STEP = 21;

	/**
	 * Source string.
	 *
	 * @var string
	 */
	private $source;

	/**
	 * Current position on a parsed element.
	 *
	 * @var integer
	 */
	private $pos = 0;

	/**
	 * Set to true if the interval is valid.
	 *
	 * @var bool
	 */
	private $is_valid = false;

	/**
	 * Error message if the interval is invalid.
	 *
	 * @var string
	 */
	private $error = '';

	/**
	 * Stores all intervals found in string.
	 *
	 * @var array
	 */
	private $intervals = [];

	public function __construct($source) {
		$this->parse($source);
	}

	/**
	 * Parse the given source string. The string can contain multiple intervals of two types (flexible and scheduled)
	 * separated by a semicolon.
	 *
	 * @param string $source	Source string that needs to be parsed.
	 */

	private function parse($source) {
		$this->source = $source;

		// Interval counter.
		$i = 0;

		/*
		 * Empty string means value is ommited (not entered yet). Some values are not allowed to be empty or too large.
		 * For example week days can only be one character. Months, hours, minutes are either one or two characters.
		 * If more are entered, parser will raise and error.
		 */
		$month_from = '';
		$month_to = '';
		$month_step = '';
		$week_from = '';
		$week_to = '';
		$week_step = '';
		$hour_from = '';
		$hour_to = '';
		$hour_step = '';
		$minute_from = '';
		$minute_to = '';
		$minute_step = '';
		$second_from = '';
		$second_to = '';
		$second_step = '';

		// Each month, week, hour, minute and second can have multiple <from's> and <to's> separated by a comma.
		$months = 0;
		$weeks = 0;
		$hours = 0;
		$minutes = 0;
		$seconds = 0;

		$state = self::STATE_NEW;

		while (isset($this->source[$this->pos])) {
			if (!array_key_exists($i, $this->intervals)) {
				$this->intervals[$i] = [];
			}

			switch ($state) {
				case self::STATE_NEW:
					/*
					 * Depending on first character in each interval, if it's a number, parse it as flexible interval.
					 * Otherwise parse it as scheduling interval.
					 */
					if (is_numeric($this->source[$this->pos])) {
						$this->intervals[$i] = [
							'interval' => $this->source[$this->pos],
							'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE,
							'delay' => $this->source[$this->pos],
							'period' => ''
						];

						$state = self::STATE_FLEXIBLE_INTERVAL;
					}
					else {
						$this->intervals[$i]['type'] = ITEM_DELAY_FLEX_TYPE_SCHEDULING;

						/*
						 * Determine the time unit:
						 *   md - month days
						 *   wd - week days
						 *   h - hours
						 *   m - minutes
						 *   s - seconds
						 */
						switch ($this->source[$this->pos]) {
							case 'm':
								// At this point it can be minutes or month days, so check the next character.
								if (isset($this->source[$this->pos + 1])) {
									if ($this->source[$this->pos + 1] === 'd') {
										$this->intervals[$i]['interval'] = 'md';
										$this->intervals[$i]['md'][$months] = ['from' => '', 'to' => '', 'step' => ''];

										$state = self::STATE_SCHEDULING_MONTH_FROM;

										$this->pos++;
									}
									else {
										$this->intervals[$i]['interval'] = 'm';
										$this->intervals[$i]['m'][$minutes] = ['from' => '', 'to' => '', 'step' => ''];

										$state = self::STATE_SCHEDULING_MINUTE_FROM;
									}
								}
								else {
									$this->setError();
									return;
								}
								break;

							case 'w':
								if (isset($this->source[$this->pos + 1]) && $this->source[$this->pos + 1] === 'd') {
									$this->intervals[$i]['interval'] = 'wd';
									$this->intervals[$i]['wd'][$weeks] = ['from' => '', 'to' => '', 'step' => ''];

									$state = self::STATE_SCHEDULING_WEEK_FROM;

									$this->pos++;
								}
								else {
									$this->setError();
									return;
								}
								break;

							case 'h':
								$this->intervals[$i]['interval'] = 'h';
								$this->intervals[$i]['h'][$hours] = ['from' => '', 'to' => '', 'step' => ''];

								$state = self::STATE_SCHEDULING_HOUR_FROM;
								break;

							case 's':
								$this->intervals[$i]['interval'] = 's';
								$this->intervals[$i]['s'][$seconds] = ['from' => '', 'to' => '', 'step' => ''];

								$state = self::STATE_SCHEDULING_SECOND_FROM;
								break;

							default:
								// Invalid first character.
								$this->setError();
								return;
						}
					}
					break;

				case self::STATE_FLEXIBLE_INTERVAL:
					if (is_numeric($this->source[$this->pos])) {
						$this->intervals[$i]['interval'] .= $this->source[$this->pos];
						$this->intervals[$i]['delay'] .= $this->source[$this->pos];
					}
					elseif ($this->source[$this->pos] === '/') {
						$this->intervals[$i]['interval'] .= '/';
						$state = self::STATE_FLEXIBLE_PERIOD;
					}
					else {
						$this->setError();
						return;
					}
					break;

				case self::STATE_FLEXIBLE_PERIOD:
					switch ($this->source[$this->pos]) {
						case '-':
							if (strlen($week_from) != 1 || strlen($week_to) > 1
									|| $this->source[$this->pos - 2] !== '/') {
								$this->setError();
								return;
							}
							break;

						case ',':
							if (strlen($week_from) != 1 || (strlen($week_from) == 1) && strlen($week_to) > 1) {
								$this->setError();
								return;
							}

							if (strlen($week_to)) {
								$this->intervals[$i]['interval'] .= $week_from.'-'.$week_to.',';
								$this->intervals[$i]['period'] .= $week_from.'-'.$week_to.',';
							}
							else {
								$this->intervals[$i]['interval'] .= $week_from.',';
								$this->intervals[$i]['period'] .= $week_from.',';
							}

							$state = self::STATE_FLEXIBLE_HOUR_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if (strlen($week_from)) {
									$week_to = $this->source[$this->pos];

									if (strlen($week_to) != 1 || $this->source[$this->pos - 1] !== '-') {
										$this->setError();
										return;
									}
								}
								else {
									$week_from = $this->source[$this->pos];
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_FLEXIBLE_HOUR_FROM:
					switch ($this->source[$this->pos]) {
						case ':':
							if (strlen($hour_from) != 1 && strlen($hour_from) != 2) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_from.':';
							$this->intervals[$i]['period'] .= $hour_from.':';

							$state = self::STATE_FLEXIBLE_MINUTE_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$hour_from .= $this->source[$this->pos];

								if (strlen($hour_from) != 1 && strlen($hour_from) != 2) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_FLEXIBLE_MINUTE_FROM:
					switch ($this->source[$this->pos]) {
						case '-':
							if (strlen($minute_from) != 2) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_from.'-';
							$this->intervals[$i]['period'] .= $minute_from.'-';

							$state = self::STATE_FLEXIBLE_HOUR_TO;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$minute_from .= $this->source[$this->pos];

								if (strlen($minute_from) != 1 && strlen($minute_from) != 2) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_FLEXIBLE_HOUR_TO:
					switch ($this->source[$this->pos]) {
						case ':':
							if (strlen($hour_to) != 1 && strlen($hour_to) != 2) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_to.':';
							$this->intervals[$i]['period'] .= $hour_to.':';

							$state = self::STATE_FLEXIBLE_MINUTE_TO;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$hour_to .= $this->source[$this->pos];

								if (strlen($hour_to) != 1 && strlen($hour_to) != 2) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_FLEXIBLE_MINUTE_TO:
					switch ($this->source[$this->pos]) {
						case ';':
							if (strlen($minute_to) != 2) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_to;
							$this->intervals[$i]['period'] .= $minute_to;

							$state = self::STATE_NEW;

							$week_from = '';
							$week_to = '';
							$hour_from = '';
							$hour_to = '';
							$minute_from = '';
							$minute_to = '';
							$i++;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$minute_to .= $this->source[$this->pos];

								if (strlen($minute_to) != 1 && strlen($minute_to) != 2) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_SCHEDULING_MONTH_FROM:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingMonthFrom($month_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_from;
							$this->intervals[$i]['md'][$months]['from'] .= $month_from;

							$state = self::STATE_NEW;

							$i++;

							$month_from = '';
							$months = 0;
							break;

						case '/':
							// Step can be entered of first month day is ommited.
							if (strlen($month_from) == 0) {
								$this->intervals[$i]['interval'] .= '/';

								$state = self::STATE_SCHEDULING_MONTH_STEP;
							}
							else {
								$this->setError();
								return;
							}
							break;

						case '-':
							if (!$this->validateSchedulingMonthFrom($month_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_from.'-';
							$this->intervals[$i]['md'][$months]['from'] .= $month_from;

							$state = self::STATE_SCHEDULING_MONTH_TO;
							break;

						case ',':
							if (!$this->validateSchedulingMonthFrom($month_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_from.',';
							$this->intervals[$i]['md'][$months]['from'] .= $month_from;

							$month_from = '';
							$months++;
							$this->intervals[$i]['md'][$months] = ['from' => '', 'to' => '', 'step' => ''];
							break;

						case 'w':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthFrom($month_from)) {
								$this->setError();
								return;
							}

							if ($this->source[$this->pos + 1] === 'd') {
								$this->intervals[$i]['interval'] .= $month_from.'wd';
								$this->intervals[$i]['md'][$months]['from'] .= $month_from;
								$this->intervals[$i]['wd'][$weeks] = ['from' => '', 'to' => '', 'step' => ''];

								$state = self::STATE_SCHEDULING_WEEK_FROM;

								$this->pos++;
							}
							else {
								$this->setError();
								return;
							}
							break;

						case 'h':
							if (!$this->validateSchedulingMonthFrom($month_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_from.'h';
							$this->intervals[$i]['md'][$months]['from'] .= $month_from;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_HOUR_FROM;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthFrom($month_from)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_from.'m';
							$this->intervals[$i]['md'][$months]['from'] .= $month_from;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingMonthFrom($month_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_from.'s';
							$this->intervals[$i]['md'][$months]['from'] .= $month_from;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$month_from .= $this->source[$this->pos];

								if (strlen($month_from) != 1 && strlen($month_from) != 2) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_SCHEDULING_MONTH_TO:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingMonthTo($month_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_to;
							$this->intervals[$i]['md'][$months]['to'] .= $month_to;

							$state = self::STATE_NEW;

							$i++;

							$month_from = '';
							$month_to = '';
							$months = 0;
							break;

						case ',':
							if (!$this->validateSchedulingMonthTo($month_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_to.',';
							$this->intervals[$i]['md'][$months]['to'] .= $month_to;

							$state = self::STATE_SCHEDULING_MONTH_FROM;

							$month_from = '';
							$month_to = '';
							$months++;
							$this->intervals[$i]['md'][$months] = ['from' => '', 'to' => '', 'step' => ''];
							break;

						case '/':
							// Step can be entered of first week day is ommited.
							if (!$this->validateSchedulingMonthTo($month_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_to.'/';
							$this->intervals[$i]['md'][$months]['to'] .= $month_to;

							$state = self::STATE_SCHEDULING_MONTH_STEP;
							break;

						case 'w':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthTo($month_to)) {
								$this->setError();
								return;
							}

							if ($this->source[$this->pos + 1] === 'd') {
								$this->intervals[$i]['interval'] .= $month_to.'wd';
								$this->intervals[$i]['md'][$months]['to'] .= $month_to;
								$this->intervals[$i]['wd'][$weeks] = ['from' => '', 'to' => '', 'step' => ''];

								$state = self::STATE_SCHEDULING_WEEK_FROM;

								$this->pos++;
							}
							else {
								$this->setError();
								return;
							}
							break;

						case 'h':
							if (!$this->validateSchedulingMonthTo($month_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_to.'h';
							$this->intervals[$i]['md'][$months]['to'] .= $month_to;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_HOUR_FROM;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthTo($month_to)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_to.'m';
							$this->intervals[$i]['md'][$months]['to'] .= $month_to;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingMonthTo($month_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_to.'s';
							$this->intervals[$i]['md'][$months]['to'] .= $month_to;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$month_to .= $this->source[$this->pos];

								if (strlen($month_to) != 1 && strlen($month_to) != 2) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_SCHEDULING_MONTH_STEP:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingMonthStep($month_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_step;
							$this->intervals[$i]['md'][$months]['step'] .= $month_step;

							$state = self::STATE_NEW;

							$i++;

							$month_from = '';
							$month_to = '';
							$month_step = '';
							$months = 0;
							break;

						case ',':
							if (!$this->validateSchedulingMonthStep($month_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_step.',';
							$this->intervals[$i]['md'][$months]['step'] .= $month_step;

							$state = self::STATE_SCHEDULING_MONTH_FROM;

							$month_from = '';
							$month_to = '';
							$month_step = '';
							$months++;
							$this->intervals[$i]['md'][$months] = ['from' => '', 'to' => '', 'step' => ''];
							break;

						case 'w':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthStep($month_step)) {
								$this->setError();
								return;
							}

							if ($this->source[$this->pos + 1] === 'd') {
								$this->intervals[$i]['interval'] .= $month_step.'wd';
								$this->intervals[$i]['md'][$months]['step'] .= $month_step;
								$this->intervals[$i]['wd'][$weeks] = ['from' => '', 'to' => '', 'step' => ''];

								$state = self::STATE_SCHEDULING_WEEK_FROM;

								$this->pos++;
							}
							break;

						case 'h':
							if (!$this->validateSchedulingMonthStep($month_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_step.'h';
							$this->intervals[$i]['md'][$months]['step'] .= $month_step;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_HOUR_FROM;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthStep($month_step)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_step.'m';
							$this->intervals[$i]['md'][$months]['step'] .= $month_step;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingMonthStep($month_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_step.'s';
							$this->intervals[$i]['md'][$months]['step'] .= $month_step;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$month_step .= $this->source[$this->pos];

								if (strlen($month_step) != 1 && strlen($month_step) != 2) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_SCHEDULING_WEEK_FROM:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingWeekFrom($week_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_from;
							$this->intervals[$i]['wd'][$weeks]['from'] .= $week_from;

							$state = self::STATE_NEW;

							$i++;

							$month_from = '';
							$month_to = '';
							$month_step = '';
							$months = 0;

							$week_from = '';
							$weeks = 0;
							break;

						case '/':
							if (strlen($week_from) == 0) {
								$this->intervals[$i]['interval'] .= '/';

								$state = self::STATE_SCHEDULING_WEEK_STEP;
							}
							else {
								$this->setError();
								return;
							}
							break;

						case '-':
							if (!$this->validateSchedulingWeekFrom($week_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_from.'-';
							$this->intervals[$i]['wd'][$weeks]['from'] .= $week_from;

							$state = self::STATE_SCHEDULING_WEEK_TO;
							break;

						case ',':
							if (!$this->validateSchedulingWeekFrom($week_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_from.',';
							$this->intervals[$i]['wd'][$weeks]['from'] .= $week_from;

							$week_from = '';
							$weeks++;
							$this->intervals[$i]['wd'][$weeks] = ['from' => '', 'to' => '', 'step' => ''];
							break;

						case 'h':
							if (!$this->validateSchedulingWeekFrom($week_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_from.'h';
							$this->intervals[$i]['wd'][$weeks]['from'] .= $week_from;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_HOUR_FROM;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1]) || !$this->validateSchedulingWeekFrom($week_from)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_from.'m';
							$this->intervals[$i]['wd'][$weeks]['from'] .= $week_from;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingWeekFrom($week_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_from.'s';
							$this->intervals[$i]['wd'][$weeks]['from'] .= $week_from;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$week_from .= $this->source[$this->pos];

								if (strlen($week_from) != 1) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_SCHEDULING_WEEK_TO:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingWeekTo($week_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_to;
							$this->intervals[$i]['wd'][$weeks]['to'] .= $week_to;

							$state = self::STATE_NEW;

							$i++;

							$month_from = '';
							$month_to = '';
							$month_step = '';
							$months = 0;

							$week_from = '';
							$week_to = '';
							$weeks = 0;
							break;

						case ',':
							if (!$this->validateSchedulingWeekTo($week_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_to.',';
							$this->intervals[$i]['wd'][$weeks]['to'] .= $week_to;

							$state = self::STATE_SCHEDULING_WEEK_FROM;

							$week_from = '';
							$week_to = '';
							$weeks++;
							$this->intervals[$i]['wd'][$weeks] = ['from' => '', 'to' => '', 'step' => ''];
							break;

						case '/':
							if (!$this->validateSchedulingWeekTo($week_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_to.'/';
							$this->intervals[$i]['wd'][$weeks]['to'] .= $week_to;

							$state = self::STATE_SCHEDULING_WEEK_STEP;
							break;

						case 'h':
							if (!$this->validateSchedulingWeekTo($week_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_to.'h';
							$this->intervals[$i]['wd'][$weeks]['to'] .= $week_to;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_HOUR_FROM;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingWeekTo($week_to)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_to.'m';
							$this->intervals[$i]['wd'][$weeks]['to'] .= $week_to;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingWeekTo($week_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_to.'s';
							$this->intervals[$i]['wd'][$weeks]['to'] .= $week_to;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$week_to .= $this->source[$this->pos];

								if (strlen($week_to) != 1) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_SCHEDULING_WEEK_STEP:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingWeekStep($week_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_step;
							$this->intervals[$i]['wd'][$weeks]['step'] .= $week_step;

							$state = self::STATE_NEW;

							$i++;

							$month_from = '';
							$month_to = '';
							$month_step = '';
							$months = 0;

							$week_from = '';
							$week_to = '';
							$week_step = '';
							$weeks = 0;
							break;

						case ',':
							if (!$this->validateSchedulingWeekStep($week_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_step.',';
							$this->intervals[$i]['wd'][$weeks]['step'] .= $week_step;

							$state = self::STATE_SCHEDULING_WEEK_FROM;

							$week_from = '';
							$week_to = '';
							$week_step = '';
							$weeks++;
							$this->intervals[$i]['wd'][$weeks] = ['from' => '', 'to' => '', 'step' => ''];
							break;

						case 'h':
							if (!$this->validateSchedulingWeekStep($week_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_step.'h';
							$this->intervals[$i]['wd'][$weeks]['step'] .= $week_step;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_HOUR_FROM;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingWeekStep($week_step)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_step.'m';
							$this->intervals[$i]['wd'][$weeks]['step'] .= $week_step;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingWeekStep($week_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_step.'s';
							$this->intervals[$i]['wd'][$weeks]['step'] .= $week_step;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$week_step .= $this->source[$this->pos];

								if (strlen($week_step) != 1) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_SCHEDULING_HOUR_FROM:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingTimeFrom($hour_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_from;
							$this->intervals[$i]['h'][$hours]['from'] .= $hour_from;

							$state = self::STATE_NEW;

							$i++;

							$month_from = '';
							$month_to = '';
							$month_step = '';
							$months = 0;

							$week_from = '';
							$week_to = '';
							$week_step = '';
							$weeks = 0;

							$hour_from = '';
							$hours = 0;
							break;

						case '/':
							// Step can be entered of first hour is ommited.
							if (strlen($hour_from) == 0) {
								$this->intervals[$i]['interval'] .= '/';

								$state = self::STATE_SCHEDULING_HOUR_STEP;
							}
							else {
								$this->setError();
								return;
							}
							break;

						case '-':
							if (!$this->validateSchedulingTimeFrom($hour_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_from.'-';
							$this->intervals[$i]['h'][$hours]['from'] .= $hour_from;

							$state = self::STATE_SCHEDULING_HOUR_TO;
							break;

						case ',':
							if (!$this->validateSchedulingTimeFrom($hour_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_from.',';
							$this->intervals[$i]['h'][$hours]['from'] .= $hour_from;

							$hour_from = '';
							$hours++;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'to' => '', 'step' => ''];
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingTimeFrom($hour_from, $state)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_from.'m';
							$this->intervals[$i]['h'][$hours]['from'] .= $hour_from;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingTimeFrom($hour_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_from.'s';
							$this->intervals[$i]['h'][$hours]['from'] .= $hour_from;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$hour_from .= $this->source[$this->pos];

								if (strlen($hour_from) != 1 && strlen($hour_from) != 2) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_SCHEDULING_HOUR_TO:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingTimeTo($hour_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_to;
							$this->intervals[$i]['h'][$hours]['to'] .= $hour_to;

							$state = self::STATE_NEW;

							$i++;

							$month_from = '';
							$month_to = '';
							$month_step = '';
							$months = 0;

							$week_from = '';
							$week_to = '';
							$week_step = '';
							$weeks = 0;

							$hour_from = '';
							$hour_to = '';
							$hours = 0;
							break;

						case ',':
							if (!$this->validateSchedulingTimeTo($hour_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_to.',';
							$this->intervals[$i]['h'][$hours]['to'] .= $hour_to;

							$state = self::STATE_SCHEDULING_HOUR_FROM;

							$hour_from = '';
							$hour_to = '';
							$hours++;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'to' => '', 'step' => ''];
							break;

						case '/':
							if (!$this->validateSchedulingTimeTo($hour_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_to.'/';
							$this->intervals[$i]['h'][$hours]['to'] .= $hour_to;

							$state = self::STATE_SCHEDULING_HOUR_STEP;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingTimeTo($hour_to)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_to.'m';
							$this->intervals[$i]['h'][$hours]['to'] .= $hour_to;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingTimeTo($hour_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_to.'s';
							$this->intervals[$i]['h'][$hours]['to'] .= $hour_to;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$hour_to .= $this->source[$this->pos];

								if (strlen($hour_to) != 1 && strlen($hour_to) != 2) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_SCHEDULING_HOUR_STEP:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingTimeStep($hour_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_step;
							$this->intervals[$i]['h'][$hours]['step'] .= $hour_step;

							$state = self::STATE_NEW;

							$i++;

							$month_from = '';
							$month_to = '';
							$month_step = '';
							$months = 0;

							$week_from = '';
							$week_to = '';
							$week_step = '';
							$weeks = 0;

							$hour_from = '';
							$hour_to = '';
							$hour_step = '';
							$hours = 0;
							break;

						case ',':
							if (!$this->validateSchedulingTimeStep($hour_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_step.',';
							$this->intervals[$i]['h'][$hours]['step'] .= $hour_step;

							$state = self::STATE_SCHEDULING_HOUR_FROM;

							$hour_from = '';
							$hour_to = '';
							$hour_step = '';
							$hours++;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'to' => '', 'step' => ''];
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingTimeStep($hour_step)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_step.'m';
							$this->intervals[$i]['h'][$hours]['step'] .= $hour_step;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingTimeStep($hour_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_step.'s';
							$this->intervals[$i]['h'][$hours]['step'] .= $hour_step;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$hour_step .= $this->source[$this->pos];

								if (strlen($hour_step) != 1 && strlen($hour_step) != 2) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_SCHEDULING_MINUTE_FROM:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingTimeFrom($minute_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_from;
							$this->intervals[$i]['h'][$minutes]['from'] .= $minute_from;

							$state = self::STATE_NEW;

							$i++;

							$month_from = '';
							$month_to = '';
							$month_step = '';
							$months = 0;

							$week_from = '';
							$week_to = '';
							$week_step = '';
							$weeks = 0;

							$hour_from = '';
							$hour_to = '';
							$hour_step = '';
							$hours = 0;

							$minute_from = '';
							$minutes = 0;
							break;

						case '/':
							if (strlen($minute_from) == 0) {
								$this->intervals[$i]['interval'] .= '/';

								$state = self::STATE_SCHEDULING_MINUTE_STEP;
							}
							else {
								$this->setError();
								return;
							}
							break;

						case '-':
							if (!$this->validateSchedulingTimeFrom($minute_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_from.'-';
							$this->intervals[$i]['m'][$minutes]['from'] .= $minute_from;

							$state = self::STATE_SCHEDULING_MINUTE_TO;
							break;

						case ',':
							if (!$this->validateSchedulingTimeFrom($minute_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_from.',';
							$this->intervals[$i]['m'][$minutes]['from'] .= $minute_from;

							$minute_from = '';
							$minutes++;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'to' => '', 'step' => ''];
							break;

						case 's':
							if (!$this->validateSchedulingTimeFrom($minute_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_from.'s';
							$this->intervals[$i]['m'][$minutes]['from'] .= $minute_from;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$minute_from .= $this->source[$this->pos];

								if (strlen($minute_from) != 1 && strlen($minute_from) != 2) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_SCHEDULING_MINUTE_TO:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingTimeTo($minute_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_to;
							$this->intervals[$i]['m'][$minutes]['to'] .= $minute_to;

							$state = self::STATE_NEW;

							$i++;

							$month_from = '';
							$month_to = '';
							$month_step = '';
							$months = 0;

							$week_from = '';
							$week_to = '';
							$week_step = '';
							$weeks = 0;

							$hour_from = '';
							$hour_to = '';
							$hour_step = '';
							$hours = 0;

							$minute_from = '';
							$minute_to = '';
							$minutes = 0;
							break;

						case ',':
							if (!$this->validateSchedulingTimeTo($minute_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_to.',';
							$this->intervals[$i]['m'][$minutes]['to'] .= $minute_to;

							$state = self::STATE_SCHEDULING_MINUTE_FROM;

							$minute_from = '';
							$minute_to = '';
							$minutes++;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'to' => '', 'step' => ''];
							break;

						case '/':
							if (!$this->validateSchedulingTimeTo($minute_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_to.'/';
							$this->intervals[$i]['m'][$minutes]['to'] .= $minute_to;

							$state = self::STATE_SCHEDULING_MINUTE_STEP;
							break;

						case 's':
							if (!$this->validateSchedulingTimeTo($minute_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_to.'s';
							$this->intervals[$i]['m'][$minutes]['to'] .= $minute_to;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$minute_to .= $this->source[$this->pos];

								if (strlen($minute_to) != 1 && strlen($minute_to) != 2) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_SCHEDULING_MINUTE_STEP:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingTimeStep($minute_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_step;
							$this->intervals[$i]['m'][$minutes]['step'] .= $minute_step;

							$state = self::STATE_NEW;

							$i++;

							$month_from = '';
							$month_to = '';
							$month_step = '';
							$months = 0;

							$week_from = '';
							$week_to = '';
							$week_step = '';
							$weeks = 0;

							$hour_from = '';
							$hour_to = '';
							$hour_step = '';
							$hours = 0;

							$minute_from = '';
							$minute_to = '';
							$minute_step = '';
							$minutes = 0;
							break;

						case ',':
							if (!$this->validateSchedulingTimeStep($minute_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_step.',';
							$this->intervals[$i]['m'][$minutes]['step'] .= $minute_step;

							$state = self::STATE_SCHEDULING_MINUTE_FROM;

							$minute_from = '';
							$minute_to = '';
							$minute_step = '';
							$minutes++;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'to' => '', 'step' => ''];
							break;

						case 's':
							if (!$this->validateSchedulingTimeStep($minute_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_step.'s';
							$this->intervals[$i]['m'][$minutes]['step'] .= $minute_step;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'to' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$minute_step .= $this->source[$this->pos];

								if (strlen($minute_step) != 1 && strlen($minute_step) != 2) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_SCHEDULING_SECOND_FROM:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingTimeFrom($second_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $second_from;
							$this->intervals[$i]['s'][$seconds]['from'] .= $second_from;

							$state = self::STATE_NEW;

							$i++;

							$month_from = '';
							$month_to = '';
							$month_step = '';
							$months = 0;

							$week_from = '';
							$week_to = '';
							$week_step = '';
							$weeks = 0;

							$hour_from = '';
							$hour_to = '';
							$hour_step = '';
							$hours = 0;

							$minute_from = '';
							$minute_to = '';
							$minute_step = '';
							$minutes = 0;

							$second_from = '';
							$seconds = 0;
							break;

						case '/':
							if (strlen($second_from) == 0) {
								$this->intervals[$i]['interval'] .= '/';

								$state = self::STATE_SCHEDULING_SECOND_STEP;
							}
							else {
								$this->setError();
								return;
							}
							break;

						case '-':
							if (!$this->validateSchedulingTimeFrom($second_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $second_from.'-';
							$this->intervals[$i]['s'][$seconds]['from'] .= $second_from;

							$state = self::STATE_SCHEDULING_SECOND_TO;
							break;

						case ',':
							if (!$this->validateSchedulingTimeFrom($second_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $second_from.',';
							$this->intervals[$i]['s'][$seconds]['from'] .= $second_from;

							$second_from = '';
							$seconds++;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'to' => '', 'step' => ''];
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$second_from .= $this->source[$this->pos];

								if (strlen($second_from) != 1 && strlen($second_from) != 2) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_SCHEDULING_SECOND_TO:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingTimeTo($second_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $second_to;
							$this->intervals[$i]['s'][$seconds]['to'] .= $second_to;

							$state = self::STATE_NEW;

							$i++;

							$month_from = '';
							$month_to = '';
							$month_step = '';
							$months = 0;

							$week_from = '';
							$week_to = '';
							$week_step = '';
							$weeks = 0;

							$hour_from = '';
							$hour_to = '';
							$hour_step = '';
							$hours = 0;

							$minute_from = '';
							$minute_to = '';
							$minute_step = '';
							$minutes = 0;

							$second_from = '';
							$second_to = '';
							$seconds = 0;
							break;

						case ',':
							if (!$this->validateSchedulingTimeTo($second_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $second_to.',';
							$this->intervals[$i]['s'][$seconds]['to'] .= $second_to;

							$state = self::STATE_SCHEDULING_SECOND_FROM;

							$second_from = '';
							$second_to = '';
							$seconds++;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'to' => '', 'step' => ''];
							break;

						case '/':
							if (!$this->validateSchedulingTimeTo($second_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $second_to.'/';
							$this->intervals[$i]['s'][$seconds]['to'] .= $second_to;

							$state = self::STATE_SCHEDULING_SECOND_STEP;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$second_to .= $this->source[$this->pos];

								if (strlen($second_to) != 1 && strlen($second_to) != 2) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;

				case self::STATE_SCHEDULING_SECOND_STEP:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingTimeStep($second_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $second_step;
							$this->intervals[$i]['s'][$seconds]['step'] .= $second_step;

							$state = self::STATE_NEW;

							$i++;

							$month_from = '';
							$month_to = '';
							$month_step = '';
							$months = 0;

							$week_from = '';
							$week_to = '';
							$week_step = '';
							$weeks = 0;

							$hour_from = '';
							$hour_to = '';
							$hour_step = '';
							$hours = 0;

							$minute_from = '';
							$minute_to = '';
							$minute_step = '';
							$minutes = 0;

							$second_from = '';
							$second_to = '';
							$second_step = '';
							$seconds = 0;
							break;

						case ',':
							if (!$this->validateSchedulingTimeStep($second_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $second_step.',';
							$this->intervals[$i]['s'][$seconds]['step'] .= $second_step;

							$state = self::STATE_SCHEDULING_SECOND_FROM;

							$second_from = '';
							$second_to = '';
							$second_step = '';
							$seconds++;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'to' => '', 'step' => ''];
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$second_step .= $this->source[$this->pos];

								if (strlen($second_step) != 1 && strlen($second_step) != 2) {
									$this->setError();
									return;
								}
							}
							else {
								$this->setError();
								return;
							}
					}
					break;
			}

			$this->pos++;
		}

		// String can end at any state. Validate the last entered characters depeding on the last state once more.
		switch ($state) {
			case self::STATE_FLEXIBLE_MINUTE_TO:
				if (strlen($minute_to) != 2) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $minute_to;
				$this->intervals[$i]['period'] .= $minute_to;
				break;

			case self::STATE_SCHEDULING_MONTH_FROM:
				if (!$this->validateSchedulingMonthFrom($month_from)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $month_from;
				$this->intervals[$i]['md'][$months]['from'] .= $month_from;
				break;

			case self::STATE_SCHEDULING_MONTH_TO:
				if (!$this->validateSchedulingMonthTo($month_to)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $month_to;
				$this->intervals[$i]['md'][$months]['to'] .= $month_to;
				break;

			case self::STATE_SCHEDULING_MONTH_STEP:
				if (!$this->validateSchedulingMonthStep($month_step)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $month_step;
				$this->intervals[$i]['md'][$months]['step'] .= $month_step;
				break;

			case self::STATE_SCHEDULING_WEEK_FROM:
				if (!$this->validateSchedulingWeekFrom($week_from)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $week_from;
				$this->intervals[$i]['wd'][$weeks]['from'] .= $week_from;
				break;

			case self::STATE_SCHEDULING_WEEK_TO:
				if (!$this->validateSchedulingWeekTo($week_to)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $week_to;
				$this->intervals[$i]['wd'][$weeks]['to'] .= $week_to;
				break;

			case self::STATE_SCHEDULING_WEEK_STEP:
				if (!$this->validateSchedulingWeekStep($week_step)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $week_step;
				$this->intervals[$i]['wd'][$weeks]['step'] .= $week_step;
				break;

			case self::STATE_SCHEDULING_HOUR_FROM:
				if (!$this->validateSchedulingTimeFrom($hour_from, $state)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $hour_from;
				$this->intervals[$i]['h'][$hours]['from'] .= $hour_from;
				break;

			case self::STATE_SCHEDULING_HOUR_TO:
				if (!$this->validateSchedulingTimeTo($hour_to)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $hour_to;
				$this->intervals[$i]['h'][$hours]['to'] .= $hour_to;
				break;

			case self::STATE_SCHEDULING_HOUR_STEP:
				if (!$this->validateSchedulingTimeStep($hour_step)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $hour_step;
				$this->intervals[$i]['h'][$hours]['step'] .= $hour_step;
				break;

			case self::STATE_SCHEDULING_MINUTE_FROM:
				if (!$this->validateSchedulingTimeFrom($minute_from, $state)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $minute_from;
				$this->intervals[$i]['m'][$minutes]['from'] .= $minute_from;
				break;

			case self::STATE_SCHEDULING_MINUTE_TO:
				if (!$this->validateSchedulingTimeTo($minute_to)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $minute_to;
				$this->intervals[$i]['m'][$minutes]['to'] .= $minute_to;
				break;

			case self::STATE_SCHEDULING_MINUTE_STEP:
				if (!$this->validateSchedulingTimeStep($minute_step)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $minute_step;
				$this->intervals[$i]['m'][$minutes]['step'] .= $minute_step;
				break;

			case self::STATE_SCHEDULING_SECOND_FROM:
				if (!$this->validateSchedulingTimeFrom($second_from, $state)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $second_from;
				$this->intervals[$i]['s'][$seconds]['from'] .= $second_from;
				break;

			case self::STATE_SCHEDULING_SECOND_TO:
				if (!$this->validateSchedulingTimeTo($second_to)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $second_to;
				$this->intervals[$i]['s'][$seconds]['to'] .= $second_to;
				break;

			case self::STATE_SCHEDULING_SECOND_STEP:
				if (!$this->validateSchedulingTimeStep($second_step)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $second_step;
				$this->intervals[$i]['s'][$seconds]['step'] .= $second_step;
				break;

			default:
				$this->setError();
				return;
		}

		$this->is_valid = true;
	}

	/**
	 * Get an array valid intervals. Invalid intervals are stripped from the array.
	 *
	 * @return array
	 */
	public function getIntervals() {
		return $this->intervals;
	}

	/**
	 * Get an array flexible intervals.
	 *
	 * @return array
	 */
	public function getFlexibleIntervals() {
		$intervals = [];

		foreach ($this->intervals as $interval) {
			if ($interval['type'] == ITEM_DELAY_FLEX_TYPE_FLEXIBLE) {
				$intervals[] = $interval['interval'];
			}
		}

		return $intervals;
	}

	/**
	 * Get an array scheduling intervals.
	 *
	 * @return array
	 */
	public function getSchedulingIntervals() {
		$intervals = [];

		foreach ($this->intervals as $interval) {
			if ($interval['type'] == ITEM_DELAY_FLEX_TYPE_SCHEDULING) {
				$intervals[] = $interval['interval'];
			}
		}

		return $intervals;
	}

	/**
	 * Mark the interval string as invalid and set an error message.
	 */
	private function setError() {
		$this->is_valid = false;

		// Remove the last invalid interval.
		array_pop($this->intervals);

		if (!isset($this->source[$this->pos])) {
			$this->error = _('unexpected end of interval');

			return;
		}

		for ($i = $this->pos, $chunk = '', $maxChunkSize = 50; isset($this->source[$i]); $i++) {
			if (0x80 != (0xc0 & ord($this->source[$i])) && $maxChunkSize-- == 0) {
				break;
			}
			$chunk .= $this->source[$i];
		}

		if (isset($this->source[$i])) {
			$chunk .= ' ...';
		}

		$this->error = _s('incorrect syntax near "%1$s"', $chunk);
	}

	/**
	 * Get the error message if interval string is invalid.
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * Check if interval string is valid.
	 */
	public function isValid() {
		return $this->is_valid;
	}

	/**
	 * Validate the "month day from" parameter. Month day should be either one or two digits.
	 *
	 * Example: md1;md07;md31;md1,3,6
	 *
	 * @param string $from
	 *
	 * @return bool
	 */
	private function validateSchedulingMonthFrom($from) {
		if (strlen($from) != 1 && strlen($from) != 2) {
			return false;
		}

		return true;
	}

	/**
	 * Validate the "month day to" parameter. Month day should be either one or two digits. If month day is a singe
	 * digit, it must have previous character "-". Otherwise it checks behind two previous characters for "-".
	 *
	 * Example: md1-5;md07-09;md01-31
	 *
	 * @param string $to
	 *
	 * @return bool
	 */
	private function validateSchedulingMonthTo($to) {
		if ((strlen($to) != 1 && strlen($to) != 2)
				|| ($this->source[$this->pos - 2] !== '-' && $this->source[$this->pos - 3] !== '-')) {
			return false;
		}

		return true;
	}

	/**
	 * Validate the "month day step" parameter. Month day step should be either one or two digits. If step is single
	 * digit, it must have previous character "/". Otherwise it checks behind two previous characters for "/".
	 *
	 * Example: md1-5/4;md07-09/02;md/30
	 *
	 * @param string $step
	 *
	 * @return bool
	 */
	private function validateSchedulingMonthStep($step) {
		if ((strlen($step) != 1 && strlen($step) != 2)
				|| ($this->source[$this->pos - 2] !== '/' && $this->source[$this->pos - 3] !== '/')) {
			return false;
		}

		return true;
	}

	/**
	 * Validate the "week day from" parameter. Week day must be single digit and previous character must be "d" or ",".
	 *
	 * Example: wd1;wd7;wd1,3,7
	 *
	 * @param string $from
	 *
	 * @return bool
	 */
	private function validateSchedulingWeekFrom($from) {
		if (strlen($from) != 1 || ($this->source[$this->pos - 2] !== 'd' && $this->source[$this->pos - 2] !== ',')) {
			return false;
		}

		return true;
	}

	/**
	 * Validate the "week day to" parameter. Week day must be single digit and previous character must be "-".
	 *
	 * Example: wd1-5;md7-9
	 *
	 * @param string $to
	 *
	 * @return bool
	 */
	private function validateSchedulingWeekTo($to) {
		if (strlen($to) != 1 || $this->source[$this->pos - 2] !== '-') {
			return false;
		}

		return true;
	}

	/**
	 * Validate the "week day step" parameter. Week day step should be single digit and previous character must be "/".
	 *
	 * Example: wd1-5/4;wd/6
	 *
	 * @param string $step
	 *
	 * @return bool
	 */
	private function validateSchedulingWeekStep($step) {
		if (strlen($step) != 1 || $this->source[$this->pos - 2] !== '/') {
			return false;
		}

		return true;
	}

	/**
	 * Validate the three time parameters "hour from", "minute from" and "second from" with single function depeding on
	 * "state". Hours, minutes and seconds have either one or two digits. If it is a single digit, depeding if "state"
	 * valide previous character ("h" for hours, "m" for minutes, "s" for seconds or "," for all states). Otherwise it
	 * checks behind two previous characters for "h", "m", "s" or ",".
	 *
	 * Example: h1;h09,10;m1;m09,10,25;s1;s09,10,50
	 *
	 * @param string $from
	 * @param int $state
	 *
	 * @return bool
	 */
	private function validateSchedulingTimeFrom($from, $state) {
		switch ($state) {
			case self::STATE_SCHEDULING_HOUR_FROM:
				$char = 'h';
				break;

			case self::STATE_SCHEDULING_MINUTE_FROM:
				$char = 'm';
				break;

			case self::STATE_SCHEDULING_SECOND_FROM:
				$char = 's';
				break;

			default:
				return false;
		}

		if ((!isset($this->source[$this->pos - 2]) && !isset($this->source[$this->pos - 3]))
				|| (strlen($from) != 1 && strlen($from) != 2)
				|| ($this->source[$this->pos - 2] !== $char && $this->source[$this->pos - 3] !== $char
						&& $this->source[$this->pos - 2] !== ',' && $this->source[$this->pos - 3] !== ',')) {
			return false;
		}

		return true;
	}

	/**
	 * Validate the three time parameters "hour to", "minute to" and "second to" with single function depeding on
	 * "state". Hours, minutes and seconds must be either one or two digits. If it is single digit previous character
	 * must be "-". Otherwise it checks behind two previous characters for "-".
	 *
	 * Example: h1-9;h12-17;h09-23,10;h00-23;m1-9;m30-59;m09-59,10-30,25-26;s1-5;s30-59;s09-59,10-30,50
	 *
	 * @param string $to
	 *
	 * @return bool
	 */
	private function validateSchedulingTimeTo($to) {
		if ((!isset($this->source[$this->pos - 2]) && !isset($this->source[$this->pos - 3]))
				|| (strlen($to) != 1 && strlen($to) != 2)
				|| ($this->source[$this->pos - 2] !== '-' && $this->source[$this->pos - 3] !== '-')) {
			return false;
		}

		return true;
	}

	/**
	 * Validate the three time parameters "hour step", "minute step" and "second step" with single function depeding on
	 * "state". Hours, minutes and seconds must be either one or two digits. If it is singe digit previous character
	 * must be "/". Otherwise it checks behind two previous characters for "/".
	 *
	 * Example: h/1;h/09;h12-17/5;h/23;m/1;m/09;m30-59/29;m/59;s/1;s/09;s30-59/29;s/59
	 *
	 * @param string $step
	 *
	 * @return bool
	 */
	private function validateSchedulingTimeStep($step) {
		if ((!isset($this->source[$this->pos - 2]) && !isset($this->source[$this->pos - 3]))
				|| (strlen($step) != 1 && strlen($step) != 2)
				|| ($this->source[$this->pos - 2] !== '/' && isset($this->source[$this->pos - 3])
					&& $this->source[$this->pos - 3] !== '/')
				|| ($this->source[$this->pos - 2] !== '/' && !isset($this->source[$this->pos - 3]))) {
			return false;
		}

		return true;
	}
}
