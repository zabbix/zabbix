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
	const STATE_FLEXIBLE_HOUR_TILL = 5;
	const STATE_FLEXIBLE_MINUTE_TILL = 6;
	const STATE_SCHEDULING_MONTH_FROM = 7;
	const STATE_SCHEDULING_MONTH_TILL = 8;
	const STATE_SCHEDULING_MONTH_STEP = 9;
	const STATE_SCHEDULING_WEEK_FROM = 10;
	const STATE_SCHEDULING_WEEK_TILL = 11;
	const STATE_SCHEDULING_WEEK_STEP = 12;
	const STATE_SCHEDULING_HOUR_FROM = 13;
	const STATE_SCHEDULING_HOUR_TILL = 14;
	const STATE_SCHEDULING_HOUR_STEP = 15;
	const STATE_SCHEDULING_MINUTE_FROM = 16;
	const STATE_SCHEDULING_MINUTE_TILL = 17;
	const STATE_SCHEDULING_MINUTE_STEP = 18;
	const STATE_SCHEDULING_SECOND_FROM = 19;
	const STATE_SCHEDULING_SECOND_TILL = 20;
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
		 * Empty string means value is omitted (not entered yet). Some values are not allowed to be empty or too large.
		 * For example week days can only be one character. Months, hours, minutes are either one or two characters.
		 * If more are entered, parser will raise and error.
		 */
		$month_day_from = '';
		$month_day_till = '';
		$month_day_step = '';
		$week_day_from = '';
		$week_day_till = '';
		$week_day_step = '';
		$hours_from = '';
		$hours_till = '';
		$hour_step = '';
		$minutes_from = '';
		$minutes_till = '';
		$minute_step = '';
		$seconds_from = '';
		$seconds_till = '';
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
										$this->intervals[$i]['md'][$months] = [
											'from' => '',
											'till' => '',
											'step' => ''
										];

										$state = self::STATE_SCHEDULING_MONTH_FROM;

										$this->pos++;
									}
									else {
										$this->intervals[$i]['interval'] = 'm';
										$this->intervals[$i]['m'][$minutes] = [
											'from' => '',
											'till' => '',
											'step' => ''
										];

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
									$this->intervals[$i]['wd'][$weeks] = ['from' => '', 'till' => '', 'step' => ''];

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
								$this->intervals[$i]['h'][$hours] = ['from' => '', 'till' => '', 'step' => ''];

								$state = self::STATE_SCHEDULING_HOUR_FROM;
								break;

							case 's':
								$this->intervals[$i]['interval'] = 's';
								$this->intervals[$i]['s'][$seconds] = ['from' => '', 'till' => '', 'step' => ''];

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
							if (strlen($week_day_from) != 1 || strlen($week_day_till) > 1
									|| $this->source[$this->pos - 2] !== '/') {
								$this->setError();
								return;
							}
							break;

						case ',':
							if (strlen($week_day_from) != 1
									|| (strlen($week_day_from) == 1 && strlen($week_day_till) > 1)) {
								$this->setError();
								return;
							}

							if (strlen($week_day_till)) {
								$this->intervals[$i]['interval'] .= $week_day_from.'-'.$week_day_till.',';
								$this->intervals[$i]['period'] .= $week_day_from.'-'.$week_day_till.',';
							}
							else {
								$this->intervals[$i]['interval'] .= $week_day_from.',';
								$this->intervals[$i]['period'] .= $week_day_from.',';
							}

							$state = self::STATE_FLEXIBLE_HOUR_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if (strlen($week_day_from)) {
									$week_day_till = $this->source[$this->pos];

									if (strlen($week_day_till) != 1 || $this->source[$this->pos - 1] !== '-') {
										$this->setError();
										return;
									}
								}
								else {
									$week_day_from = $this->source[$this->pos];
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
							if (strlen($hours_from) != 1 && strlen($hours_from) != 2) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hours_from.':';
							$this->intervals[$i]['period'] .= $hours_from.':';

							$state = self::STATE_FLEXIBLE_MINUTE_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$hours_from .= $this->source[$this->pos];

								if (strlen($hours_from) != 1 && strlen($hours_from) != 2) {
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
							if (strlen($minutes_from) != 2) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minutes_from.'-';
							$this->intervals[$i]['period'] .= $minutes_from.'-';

							$state = self::STATE_FLEXIBLE_HOUR_TILL;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$minutes_from .= $this->source[$this->pos];

								if (strlen($minutes_from) != 1 && strlen($minutes_from) != 2) {
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

				case self::STATE_FLEXIBLE_HOUR_TILL:
					switch ($this->source[$this->pos]) {
						case ':':
							if (strlen($hours_till) != 1 && strlen($hours_till) != 2) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hours_till.':';
							$this->intervals[$i]['period'] .= $hours_till.':';

							$state = self::STATE_FLEXIBLE_MINUTE_TILL;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$hours_till .= $this->source[$this->pos];

								if (strlen($hours_till) != 1 && strlen($hours_till) != 2) {
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

				case self::STATE_FLEXIBLE_MINUTE_TILL:
					switch ($this->source[$this->pos]) {
						case ';':
							if (strlen($minutes_till) != 2) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minutes_till;
							$this->intervals[$i]['period'] .= $minutes_till;

							$state = self::STATE_NEW;

							$week_day_from = '';
							$week_day_till = '';
							$hours_from = '';
							$hours_till = '';
							$minutes_from = '';
							$minutes_till = '';
							$i++;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$minutes_till .= $this->source[$this->pos];

								if (strlen($minutes_till) != 1 && strlen($minutes_till) != 2) {
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
							if (!$this->validateSchedulingMonthFrom($month_day_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_from;
							$this->intervals[$i]['md'][$months]['from'] .= $month_day_from;

							$state = self::STATE_NEW;

							$i++;

							$month_day_from = '';
							$months = 0;
							break;

						case '/':
							// Step can be entered of first month day is omitted.
							if (strlen($month_day_from) == 0) {
								$this->intervals[$i]['interval'] .= '/';

								$state = self::STATE_SCHEDULING_MONTH_STEP;
							}
							else {
								$this->setError();
								return;
							}
							break;

						case '-':
							if (!$this->validateSchedulingMonthFrom($month_day_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_from.'-';
							$this->intervals[$i]['md'][$months]['from'] .= $month_day_from;

							$state = self::STATE_SCHEDULING_MONTH_TILL;
							break;

						case ',':
							if (!$this->validateSchedulingMonthFrom($month_day_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_from.',';
							$this->intervals[$i]['md'][$months]['from'] .= $month_day_from;

							$month_day_from = '';
							$months++;
							$this->intervals[$i]['md'][$months] = ['from' => '', 'till' => '', 'step' => ''];
							break;

						case 'w':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthFrom($month_day_from)) {
								$this->setError();
								return;
							}

							if ($this->source[$this->pos + 1] === 'd') {
								$this->intervals[$i]['interval'] .= $month_day_from.'wd';
								$this->intervals[$i]['md'][$months]['from'] .= $month_day_from;
								$this->intervals[$i]['wd'][$weeks] = ['from' => '', 'till' => '', 'step' => ''];

								$state = self::STATE_SCHEDULING_WEEK_FROM;

								$this->pos++;
							}
							else {
								$this->setError();
								return;
							}
							break;

						case 'h':
							if (!$this->validateSchedulingMonthFrom($month_day_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_from.'h';
							$this->intervals[$i]['md'][$months]['from'] .= $month_day_from;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_HOUR_FROM;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthFrom($month_day_from)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_from.'m';
							$this->intervals[$i]['md'][$months]['from'] .= $month_day_from;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingMonthFrom($month_day_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_from.'s';
							$this->intervals[$i]['md'][$months]['from'] .= $month_day_from;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$month_day_from .= $this->source[$this->pos];

								if (strlen($month_day_from) != 1 && strlen($month_day_from) != 2) {
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

				case self::STATE_SCHEDULING_MONTH_TILL:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingMonthTo($month_day_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_till;
							$this->intervals[$i]['md'][$months]['till'] .= $month_day_till;

							$state = self::STATE_NEW;

							$i++;

							$month_day_from = '';
							$month_day_till = '';
							$months = 0;
							break;

						case ',':
							if (!$this->validateSchedulingMonthTo($month_day_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_till.',';
							$this->intervals[$i]['md'][$months]['till'] .= $month_day_till;

							$state = self::STATE_SCHEDULING_MONTH_FROM;

							$month_day_from = '';
							$month_day_till = '';
							$months++;
							$this->intervals[$i]['md'][$months] = ['from' => '', 'till' => '', 'step' => ''];
							break;

						case '/':
							// Step can be entered of first week day is omitted.
							if (!$this->validateSchedulingMonthTo($month_day_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_till.'/';
							$this->intervals[$i]['md'][$months]['till'] .= $month_day_till;

							$state = self::STATE_SCHEDULING_MONTH_STEP;
							break;

						case 'w':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthTo($month_day_till)) {
								$this->setError();
								return;
							}

							if ($this->source[$this->pos + 1] === 'd') {
								$this->intervals[$i]['interval'] .= $month_day_till.'wd';
								$this->intervals[$i]['md'][$months]['till'] .= $month_day_till;
								$this->intervals[$i]['wd'][$weeks] = ['from' => '', 'till' => '', 'step' => ''];

								$state = self::STATE_SCHEDULING_WEEK_FROM;

								$this->pos++;
							}
							else {
								$this->setError();
								return;
							}
							break;

						case 'h':
							if (!$this->validateSchedulingMonthTo($month_day_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_till.'h';
							$this->intervals[$i]['md'][$months]['till'] .= $month_day_till;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_HOUR_FROM;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthTo($month_day_till)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_till.'m';
							$this->intervals[$i]['md'][$months]['till'] .= $month_day_till;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingMonthTo($month_day_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_till.'s';
							$this->intervals[$i]['md'][$months]['till'] .= $month_day_till;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$month_day_till .= $this->source[$this->pos];

								if (strlen($month_day_till) != 1 && strlen($month_day_till) != 2) {
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
							if (!$this->validateSchedulingMonthStep($month_day_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_step;
							$this->intervals[$i]['md'][$months]['step'] .= $month_day_step;

							$state = self::STATE_NEW;

							$i++;

							$month_day_from = '';
							$month_day_till = '';
							$month_day_step = '';
							$months = 0;
							break;

						case ',':
							if (!$this->validateSchedulingMonthStep($month_day_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_step.',';
							$this->intervals[$i]['md'][$months]['step'] .= $month_day_step;

							$state = self::STATE_SCHEDULING_MONTH_FROM;

							$month_day_from = '';
							$month_day_till = '';
							$month_day_step = '';
							$months++;
							$this->intervals[$i]['md'][$months] = ['from' => '', 'till' => '', 'step' => ''];
							break;

						case 'w':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthStep($month_day_step)) {
								$this->setError();
								return;
							}

							if ($this->source[$this->pos + 1] === 'd') {
								$this->intervals[$i]['interval'] .= $month_day_step.'wd';
								$this->intervals[$i]['md'][$months]['step'] .= $month_day_step;
								$this->intervals[$i]['wd'][$weeks] = ['from' => '', 'till' => '', 'step' => ''];

								$state = self::STATE_SCHEDULING_WEEK_FROM;

								$this->pos++;
							}
							break;

						case 'h':
							if (!$this->validateSchedulingMonthStep($month_day_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_step.'h';
							$this->intervals[$i]['md'][$months]['step'] .= $month_day_step;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_HOUR_FROM;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthStep($month_day_step)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_step.'m';
							$this->intervals[$i]['md'][$months]['step'] .= $month_day_step;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingMonthStep($month_day_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_day_step.'s';
							$this->intervals[$i]['md'][$months]['step'] .= $month_day_step;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$month_day_step .= $this->source[$this->pos];

								if (strlen($month_day_step) != 1 && strlen($month_day_step) != 2) {
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
							if (!$this->validateSchedulingWeekFrom($week_day_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_from;
							$this->intervals[$i]['wd'][$weeks]['from'] .= $week_day_from;

							$state = self::STATE_NEW;

							$i++;

							$month_day_from = '';
							$month_day_till = '';
							$month_day_step = '';
							$months = 0;

							$week_day_from = '';
							$weeks = 0;
							break;

						case '/':
							if (strlen($week_day_from) == 0) {
								$this->intervals[$i]['interval'] .= '/';

								$state = self::STATE_SCHEDULING_WEEK_STEP;
							}
							else {
								$this->setError();
								return;
							}
							break;

						case '-':
							if (!$this->validateSchedulingWeekFrom($week_day_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_from.'-';
							$this->intervals[$i]['wd'][$weeks]['from'] .= $week_day_from;

							$state = self::STATE_SCHEDULING_WEEK_TILL;
							break;

						case ',':
							if (!$this->validateSchedulingWeekFrom($week_day_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_from.',';
							$this->intervals[$i]['wd'][$weeks]['from'] .= $week_day_from;

							$week_day_from = '';
							$weeks++;
							$this->intervals[$i]['wd'][$weeks] = ['from' => '', 'till' => '', 'step' => ''];
							break;

						case 'h':
							if (!$this->validateSchedulingWeekFrom($week_day_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_from.'h';
							$this->intervals[$i]['wd'][$weeks]['from'] .= $week_day_from;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_HOUR_FROM;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingWeekFrom($week_day_from)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_from.'m';
							$this->intervals[$i]['wd'][$weeks]['from'] .= $week_day_from;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingWeekFrom($week_day_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_from.'s';
							$this->intervals[$i]['wd'][$weeks]['from'] .= $week_day_from;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$week_day_from .= $this->source[$this->pos];

								if (strlen($week_day_from) != 1) {
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

				case self::STATE_SCHEDULING_WEEK_TILL:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingWeekTo($week_day_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_till;
							$this->intervals[$i]['wd'][$weeks]['till'] .= $week_day_till;

							$state = self::STATE_NEW;

							$i++;

							$month_day_from = '';
							$month_day_till = '';
							$month_day_step = '';
							$months = 0;

							$week_day_from = '';
							$week_day_till = '';
							$weeks = 0;
							break;

						case ',':
							if (!$this->validateSchedulingWeekTo($week_day_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_till.',';
							$this->intervals[$i]['wd'][$weeks]['till'] .= $week_day_till;

							$state = self::STATE_SCHEDULING_WEEK_FROM;

							$week_day_from = '';
							$week_day_till = '';
							$weeks++;
							$this->intervals[$i]['wd'][$weeks] = ['from' => '', 'till' => '', 'step' => ''];
							break;

						case '/':
							if (!$this->validateSchedulingWeekTo($week_day_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_till.'/';
							$this->intervals[$i]['wd'][$weeks]['till'] .= $week_day_till;

							$state = self::STATE_SCHEDULING_WEEK_STEP;
							break;

						case 'h':
							if (!$this->validateSchedulingWeekTo($week_day_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_till.'h';
							$this->intervals[$i]['wd'][$weeks]['till'] .= $week_day_till;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_HOUR_FROM;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingWeekTo($week_day_till)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_till.'m';
							$this->intervals[$i]['wd'][$weeks]['till'] .= $week_day_till;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingWeekTo($week_day_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_till.'s';
							$this->intervals[$i]['wd'][$weeks]['till'] .= $week_day_till;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$week_day_till .= $this->source[$this->pos];

								if (strlen($week_day_till) != 1) {
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
							if (!$this->validateSchedulingWeekStep($week_day_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_step;
							$this->intervals[$i]['wd'][$weeks]['step'] .= $week_day_step;

							$state = self::STATE_NEW;

							$i++;

							$month_day_from = '';
							$month_day_till = '';
							$month_day_step = '';
							$months = 0;

							$week_day_from = '';
							$week_day_till = '';
							$week_day_step = '';
							$weeks = 0;
							break;

						case ',':
							if (!$this->validateSchedulingWeekStep($week_day_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_step.',';
							$this->intervals[$i]['wd'][$weeks]['step'] .= $week_day_step;

							$state = self::STATE_SCHEDULING_WEEK_FROM;

							$week_day_from = '';
							$week_day_till = '';
							$week_day_step = '';
							$weeks++;
							$this->intervals[$i]['wd'][$weeks] = ['from' => '', 'till' => '', 'step' => ''];
							break;

						case 'h':
							if (!$this->validateSchedulingWeekStep($week_day_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_step.'h';
							$this->intervals[$i]['wd'][$weeks]['step'] .= $week_day_step;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_HOUR_FROM;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingWeekStep($week_day_step)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_step.'m';
							$this->intervals[$i]['wd'][$weeks]['step'] .= $week_day_step;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingWeekStep($week_day_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_day_step.'s';
							$this->intervals[$i]['wd'][$weeks]['step'] .= $week_day_step;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$week_day_step .= $this->source[$this->pos];

								if (strlen($week_day_step) != 1) {
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
							if (!$this->validateSchedulingTimeFrom($hours_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hours_from;
							$this->intervals[$i]['h'][$hours]['from'] .= $hours_from;

							$state = self::STATE_NEW;

							$i++;

							$month_day_from = '';
							$month_day_till = '';
							$month_day_step = '';
							$months = 0;

							$week_day_from = '';
							$week_day_till = '';
							$week_day_step = '';
							$weeks = 0;

							$hours_from = '';
							$hours = 0;
							break;

						case '/':
							// Step can be entered of first hour is omitted.
							if (strlen($hours_from) == 0) {
								$this->intervals[$i]['interval'] .= '/';

								$state = self::STATE_SCHEDULING_HOUR_STEP;
							}
							else {
								$this->setError();
								return;
							}
							break;

						case '-':
							if (!$this->validateSchedulingTimeFrom($hours_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hours_from.'-';
							$this->intervals[$i]['h'][$hours]['from'] .= $hours_from;

							$state = self::STATE_SCHEDULING_HOUR_TILL;
							break;

						case ',':
							if (!$this->validateSchedulingTimeFrom($hours_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hours_from.',';
							$this->intervals[$i]['h'][$hours]['from'] .= $hours_from;

							$hours_from = '';
							$hours++;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'till' => '', 'step' => ''];
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingTimeFrom($hours_from, $state)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hours_from.'m';
							$this->intervals[$i]['h'][$hours]['from'] .= $hours_from;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingTimeFrom($hours_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hours_from.'s';
							$this->intervals[$i]['h'][$hours]['from'] .= $hours_from;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$hours_from .= $this->source[$this->pos];

								if (strlen($hours_from) != 1 && strlen($hours_from) != 2) {
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

				case self::STATE_SCHEDULING_HOUR_TILL:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingTimeTo($hours_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hours_till;
							$this->intervals[$i]['h'][$hours]['till'] .= $hours_till;

							$state = self::STATE_NEW;

							$i++;

							$month_day_from = '';
							$month_day_till = '';
							$month_day_step = '';
							$months = 0;

							$week_day_from = '';
							$week_day_till = '';
							$week_day_step = '';
							$weeks = 0;

							$hours_from = '';
							$hours_till = '';
							$hours = 0;
							break;

						case ',':
							if (!$this->validateSchedulingTimeTo($hours_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hours_till.',';
							$this->intervals[$i]['h'][$hours]['till'] .= $hours_till;

							$state = self::STATE_SCHEDULING_HOUR_FROM;

							$hours_from = '';
							$hours_till = '';
							$hours++;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'till' => '', 'step' => ''];
							break;

						case '/':
							if (!$this->validateSchedulingTimeTo($hours_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hours_till.'/';
							$this->intervals[$i]['h'][$hours]['till'] .= $hours_till;

							$state = self::STATE_SCHEDULING_HOUR_STEP;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingTimeTo($hours_till)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hours_till.'m';
							$this->intervals[$i]['h'][$hours]['till'] .= $hours_till;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingTimeTo($hours_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hours_till.'s';
							$this->intervals[$i]['h'][$hours]['till'] .= $hours_till;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$hours_till .= $this->source[$this->pos];

								if (strlen($hours_till) != 1 && strlen($hours_till) != 2) {
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

							$month_day_from = '';
							$month_day_till = '';
							$month_day_step = '';
							$months = 0;

							$week_day_from = '';
							$week_day_till = '';
							$week_day_step = '';
							$weeks = 0;

							$hours_from = '';
							$hours_till = '';
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

							$hours_from = '';
							$hours_till = '';
							$hour_step = '';
							$hours++;
							$this->intervals[$i]['h'][$hours] = ['from' => '', 'till' => '', 'step' => ''];
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
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingTimeStep($hour_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_step.'s';
							$this->intervals[$i]['h'][$hours]['step'] .= $hour_step;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'till' => '', 'step' => ''];

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
							if (!$this->validateSchedulingTimeFrom($minutes_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minutes_from;
							$this->intervals[$i]['h'][$minutes]['from'] .= $minutes_from;

							$state = self::STATE_NEW;

							$i++;

							$month_day_from = '';
							$month_day_till = '';
							$month_day_step = '';
							$months = 0;

							$week_day_from = '';
							$week_day_till = '';
							$week_day_step = '';
							$weeks = 0;

							$hours_from = '';
							$hours_till = '';
							$hour_step = '';
							$hours = 0;

							$minutes_from = '';
							$minutes = 0;
							break;

						case '/':
							if (strlen($minutes_from) == 0) {
								$this->intervals[$i]['interval'] .= '/';

								$state = self::STATE_SCHEDULING_MINUTE_STEP;
							}
							else {
								$this->setError();
								return;
							}
							break;

						case '-':
							if (!$this->validateSchedulingTimeFrom($minutes_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minutes_from.'-';
							$this->intervals[$i]['m'][$minutes]['from'] .= $minutes_from;

							$state = self::STATE_SCHEDULING_MINUTE_TILL;
							break;

						case ',':
							if (!$this->validateSchedulingTimeFrom($minutes_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minutes_from.',';
							$this->intervals[$i]['m'][$minutes]['from'] .= $minutes_from;

							$minutes_from = '';
							$minutes++;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'till' => '', 'step' => ''];
							break;

						case 's':
							if (!$this->validateSchedulingTimeFrom($minutes_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minutes_from.'s';
							$this->intervals[$i]['m'][$minutes]['from'] .= $minutes_from;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$minutes_from .= $this->source[$this->pos];

								if (strlen($minutes_from) != 1 && strlen($minutes_from) != 2) {
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

				case self::STATE_SCHEDULING_MINUTE_TILL:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingTimeTo($minutes_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minutes_till;
							$this->intervals[$i]['m'][$minutes]['till'] .= $minutes_till;

							$state = self::STATE_NEW;

							$i++;

							$month_day_from = '';
							$month_day_till = '';
							$month_day_step = '';
							$months = 0;

							$week_day_from = '';
							$week_day_till = '';
							$week_day_step = '';
							$weeks = 0;

							$hours_from = '';
							$hours_till = '';
							$hour_step = '';
							$hours = 0;

							$minutes_from = '';
							$minutes_till = '';
							$minutes = 0;
							break;

						case ',':
							if (!$this->validateSchedulingTimeTo($minutes_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minutes_till.',';
							$this->intervals[$i]['m'][$minutes]['till'] .= $minutes_till;

							$state = self::STATE_SCHEDULING_MINUTE_FROM;

							$minutes_from = '';
							$minutes_till = '';
							$minutes++;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'till' => '', 'step' => ''];
							break;

						case '/':
							if (!$this->validateSchedulingTimeTo($minutes_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minutes_till.'/';
							$this->intervals[$i]['m'][$minutes]['till'] .= $minutes_till;

							$state = self::STATE_SCHEDULING_MINUTE_STEP;
							break;

						case 's':
							if (!$this->validateSchedulingTimeTo($minutes_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minutes_till.'s';
							$this->intervals[$i]['m'][$minutes]['till'] .= $minutes_till;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'till' => '', 'step' => ''];

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$minutes_till .= $this->source[$this->pos];

								if (strlen($minutes_till) != 1 && strlen($minutes_till) != 2) {
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

							$month_day_from = '';
							$month_day_till = '';
							$month_day_step = '';
							$months = 0;

							$week_day_from = '';
							$week_day_till = '';
							$week_day_step = '';
							$weeks = 0;

							$hours_from = '';
							$hours_till = '';
							$hour_step = '';
							$hours = 0;

							$minutes_from = '';
							$minutes_till = '';
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

							$minutes_from = '';
							$minutes_till = '';
							$minute_step = '';
							$minutes++;
							$this->intervals[$i]['m'][$minutes] = ['from' => '', 'till' => '', 'step' => ''];
							break;

						case 's':
							if (!$this->validateSchedulingTimeStep($minute_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_step.'s';
							$this->intervals[$i]['m'][$minutes]['step'] .= $minute_step;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'till' => '', 'step' => ''];

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
							if (!$this->validateSchedulingTimeFrom($seconds_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $seconds_from;
							$this->intervals[$i]['s'][$seconds]['from'] .= $seconds_from;

							$state = self::STATE_NEW;

							$i++;

							$month_day_from = '';
							$month_day_till = '';
							$month_day_step = '';
							$months = 0;

							$week_day_from = '';
							$week_day_till = '';
							$week_day_step = '';
							$weeks = 0;

							$hours_from = '';
							$hours_till = '';
							$hour_step = '';
							$hours = 0;

							$minutes_from = '';
							$minutes_till = '';
							$minute_step = '';
							$minutes = 0;

							$seconds_from = '';
							$seconds = 0;
							break;

						case '/':
							if (strlen($seconds_from) == 0) {
								$this->intervals[$i]['interval'] .= '/';

								$state = self::STATE_SCHEDULING_SECOND_STEP;
							}
							else {
								$this->setError();
								return;
							}
							break;

						case '-':
							if (!$this->validateSchedulingTimeFrom($seconds_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $seconds_from.'-';
							$this->intervals[$i]['s'][$seconds]['from'] .= $seconds_from;

							$state = self::STATE_SCHEDULING_SECOND_TILL;
							break;

						case ',':
							if (!$this->validateSchedulingTimeFrom($seconds_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $seconds_from.',';
							$this->intervals[$i]['s'][$seconds]['from'] .= $seconds_from;

							$seconds_from = '';
							$seconds++;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'till' => '', 'step' => ''];
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$seconds_from .= $this->source[$this->pos];

								if (strlen($seconds_from) != 1 && strlen($seconds_from) != 2) {
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

				case self::STATE_SCHEDULING_SECOND_TILL:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingTimeTo($seconds_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $seconds_till;
							$this->intervals[$i]['s'][$seconds]['till'] .= $seconds_till;

							$state = self::STATE_NEW;

							$i++;

							$month_day_from = '';
							$month_day_till = '';
							$month_day_step = '';
							$months = 0;

							$week_day_from = '';
							$week_day_till = '';
							$week_day_step = '';
							$weeks = 0;

							$hours_from = '';
							$hours_till = '';
							$hour_step = '';
							$hours = 0;

							$minutes_from = '';
							$minutes_till = '';
							$minute_step = '';
							$minutes = 0;

							$seconds_from = '';
							$seconds_till = '';
							$seconds = 0;
							break;

						case ',':
							if (!$this->validateSchedulingTimeTo($seconds_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $seconds_till.',';
							$this->intervals[$i]['s'][$seconds]['till'] .= $seconds_till;

							$state = self::STATE_SCHEDULING_SECOND_FROM;

							$seconds_from = '';
							$seconds_till = '';
							$seconds++;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'till' => '', 'step' => ''];
							break;

						case '/':
							if (!$this->validateSchedulingTimeTo($seconds_till)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $seconds_till.'/';
							$this->intervals[$i]['s'][$seconds]['till'] .= $seconds_till;

							$state = self::STATE_SCHEDULING_SECOND_STEP;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								$seconds_till .= $this->source[$this->pos];

								if (strlen($seconds_till) != 1 && strlen($seconds_till) != 2) {
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

							$month_day_from = '';
							$month_day_till = '';
							$month_day_step = '';
							$months = 0;

							$week_day_from = '';
							$week_day_till = '';
							$week_day_step = '';
							$weeks = 0;

							$hours_from = '';
							$hours_till = '';
							$hour_step = '';
							$hours = 0;

							$minutes_from = '';
							$minutes_till = '';
							$minute_step = '';
							$minutes = 0;

							$seconds_from = '';
							$seconds_till = '';
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

							$seconds_from = '';
							$seconds_till = '';
							$second_step = '';
							$seconds++;
							$this->intervals[$i]['s'][$seconds] = ['from' => '', 'till' => '', 'step' => ''];
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

		// String can end at any state. Validate the last entered characters depending on the last state once more.
		switch ($state) {
			case self::STATE_FLEXIBLE_MINUTE_TILL:
				if (strlen($minutes_till) != 2) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $minutes_till;
				$this->intervals[$i]['period'] .= $minutes_till;
				break;

			case self::STATE_SCHEDULING_MONTH_FROM:
				if (!$this->validateSchedulingMonthFrom($month_day_from)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $month_day_from;
				$this->intervals[$i]['md'][$months]['from'] .= $month_day_from;
				break;

			case self::STATE_SCHEDULING_MONTH_TILL:
				if (!$this->validateSchedulingMonthTo($month_day_till)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $month_day_till;
				$this->intervals[$i]['md'][$months]['till'] .= $month_day_till;
				break;

			case self::STATE_SCHEDULING_MONTH_STEP:
				if (!$this->validateSchedulingMonthStep($month_day_step)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $month_day_step;
				$this->intervals[$i]['md'][$months]['step'] .= $month_day_step;
				break;

			case self::STATE_SCHEDULING_WEEK_FROM:
				if (!$this->validateSchedulingWeekFrom($week_day_from)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $week_day_from;
				$this->intervals[$i]['wd'][$weeks]['from'] .= $week_day_from;
				break;

			case self::STATE_SCHEDULING_WEEK_TILL:
				if (!$this->validateSchedulingWeekTo($week_day_till)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $week_day_till;
				$this->intervals[$i]['wd'][$weeks]['till'] .= $week_day_till;
				break;

			case self::STATE_SCHEDULING_WEEK_STEP:
				if (!$this->validateSchedulingWeekStep($week_day_step)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $week_day_step;
				$this->intervals[$i]['wd'][$weeks]['step'] .= $week_day_step;
				break;

			case self::STATE_SCHEDULING_HOUR_FROM:
				if (!$this->validateSchedulingTimeFrom($hours_from, $state)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $hours_from;
				$this->intervals[$i]['h'][$hours]['from'] .= $hours_from;
				break;

			case self::STATE_SCHEDULING_HOUR_TILL:
				if (!$this->validateSchedulingTimeTo($hours_till)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $hours_till;
				$this->intervals[$i]['h'][$hours]['till'] .= $hours_till;
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
				if (!$this->validateSchedulingTimeFrom($minutes_from, $state)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $minutes_from;
				$this->intervals[$i]['m'][$minutes]['from'] .= $minutes_from;
				break;

			case self::STATE_SCHEDULING_MINUTE_TILL:
				if (!$this->validateSchedulingTimeTo($minutes_till)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $minutes_till;
				$this->intervals[$i]['m'][$minutes]['till'] .= $minutes_till;
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
				if (!$this->validateSchedulingTimeFrom($seconds_from, $state)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $seconds_from;
				$this->intervals[$i]['s'][$seconds]['from'] .= $seconds_from;
				break;

			case self::STATE_SCHEDULING_SECOND_TILL:
				if (!$this->validateSchedulingTimeTo($seconds_till)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $seconds_till;
				$this->intervals[$i]['s'][$seconds]['till'] .= $seconds_till;
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
	 * Validate the "month day till" parameter. Month day should be either one or two digits. If month day is a singe
	 * digit, it must have previous character "-". Otherwise it checks behind two previous characters for "-".
	 *
	 * Example: md1-5;md07-09;md01-31
	 *
	 * @param string $till
	 *
	 * @return bool
	 */
	private function validateSchedulingMonthTo($till) {
		if ((strlen($till) != 1 && strlen($till) != 2)
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
	 * Validate the "week day till" parameter. Week day must be single digit and previous character must be "-".
	 *
	 * Example: wd1-5;md7-9
	 *
	 * @param string $till
	 *
	 * @return bool
	 */
	private function validateSchedulingWeekTo($till) {
		if (strlen($till) != 1 || $this->source[$this->pos - 2] !== '-') {
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
	 * Validate the three time parameters "hours from", "minutes from" and "seconds from" with single function depending
	 * on "state". Hours, minutes and seconds have either one or two digits. If it is a single digit, depending if
	 * "state" valid previous character ("h" for hours, "m" for minutes, "s" for seconds or "," for all states).
	 * Otherwise it checks behind two previous characters for "h", "m", "s" or ",".
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
	 * Validate the three time parameters "hours till", "minutes till" and "seconds till" with single function depending
	 * on "state". Hours, minutes and seconds must be either one or two digits. If it is single digit previous character
	 * must be "-". Otherwise it checks behind two previous characters for "-".
	 *
	 * Example: h1-9;h12-17;h09-23,10;h00-23;m1-9;m30-59;m09-59,10-30,25-26;s1-5;s30-59;s09-59,10-30,50
	 *
	 * @param string $till
	 *
	 * @return bool
	 */
	private function validateSchedulingTimeTo($till) {
		if ((!isset($this->source[$this->pos - 2]) && !isset($this->source[$this->pos - 3]))
				|| (strlen($till) != 1 && strlen($till) != 2)
				|| ($this->source[$this->pos - 2] !== '-' && $this->source[$this->pos - 3] !== '-')) {
			return false;
		}

		return true;
	}

	/**
	 * Validate the three time parameters "hour step", "minute step" and "second step" with single function depending on
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
