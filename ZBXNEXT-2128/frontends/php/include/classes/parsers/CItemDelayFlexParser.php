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
		 * "-1" means value is ommited (not entered yet). Some values are not allowed to be 0, so set default "-1".
		 * These temporary values are reset after each successful validation. For example when a "month day from" is
		 * entered and a "," is encountered, the value in $month_from gets validated and then reset to "-1", so the next
		 * "month day from" value can be validated again.
		 */
		$month_from = -1;
		$month_to = -1;
		$month_step = -1;
		$week_from = -1;
		$week_to = -1;
		$week_step = -1;
		$hour_from = -1;
		$hour_to = -1;
		$hour_step = -1;
		$minute_from = -1;
		$minute_to = -1;
		$minute_step = -1;
		$second_from = -1;
		$second_to = -1;
		$second_step = -1;

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
						$flexible_interval = $this->source[$this->pos];

						$this->intervals[$i] = [
							'interval' => $flexible_interval,
							'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE
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

										$state = self::STATE_SCHEDULING_MONTH_FROM;

										$this->pos++;
									}
									else {
										$this->intervals[$i]['interval'] = 'm';

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

								$state = self::STATE_SCHEDULING_HOUR_FROM;
								break;

							case 's':
								$this->intervals[$i]['interval'] = 's';

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
					/*
					 * We can enter 00000000000... seconds. The next check calculation is done after we have parsed all
					 * chars and collected data. But make sure, we don't get stuck in this loop. The maximum length is
					 * 255 characters. The last 12 characters require to be a valid period. For example "/7,7:00-9:00".
					 * The period can also consist of 16 characters. For example "/1-7,00:00-23:00".
					 */
					if ($this->pos + 12 > 255) {
						$this->setError();
						return;
					}

					$this->intervals[$i]['interval'] .= $this->source[$this->pos];

					if ($this->source[$this->pos] === '/') {
						$state = self::STATE_FLEXIBLE_PERIOD;
					}
					elseif (is_numeric($this->source[$this->pos])) {
						$flexible_interval = (int) $flexible_interval.$this->source[$this->pos];

						if ($flexible_interval > SEC_PER_DAY) {
							$this->setError();
							return;
						}
					}
					else {
						$this->setError();
						return;
					}
					break;

				case self::STATE_FLEXIBLE_PERIOD:
					switch ($this->source[$this->pos]) {
						case '-':
							if ($week_from < 1 || $week_from > 7 || $this->source[$this->pos - 2] !== '/') {
								$this->setError();
								return;
							}
							break;

						case ',':
							if ($week_from < 1 || $week_from > 7) {
								$this->setError();
								return;
							}

							if ($week_to > -1) {
								if ($week_to == 0 || $week_to > 7 || $week_from > $week_to
										|| $this->source[$this->pos - 2] !== '-') {
									$this->setError();
									return;
								}

								$this->intervals[$i]['interval'] .= $week_from.'-'.$week_to.',';
							}
							else {
								// Second weekday is ommited.
								if ($this->source[$this->pos - 2] !== '/') {
									$this->setError();
									return;
								}

								$this->intervals[$i]['interval'] .= $week_from.',';
							}

							$state = self::STATE_FLEXIBLE_HOUR_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($week_from > -1) {
									if ($week_to > -1 || $this->source[$this->pos - 1] !== '-') {
										$this->setError();
										return;
									}

									$week_to = $this->source[$this->pos];

									if ($week_from > $week_to) {
										$this->setError();
										return;
									}
								}
								else {
									$week_from = $this->source[$this->pos];

									if ($week_from < 1 || $week_from > 7) {
										$this->setError();
										return;
									}
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
							if ($hour_from == -1
									|| ($this->source[$this->pos - 2] !== ','
										&& $this->source[$this->pos - 3] !== ',')) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_from.':';

							$state = self::STATE_FLEXIBLE_MINUTE_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($hour_from > -1) {
									$hour_from = (int) $hour_from.$this->source[$this->pos];

									if ($hour_from > 23) {
										$this->setError();
										return;
									}
								}
								else {
									$hour_from = $this->source[$this->pos];
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
							if ($minute_from == -1 || $this->source[$this->pos - 3] !== ':') {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_from.'-';

							$state = self::STATE_FLEXIBLE_HOUR_TO;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($minute_from > -1) {
									$minute_from = (int) $minute_from.$this->source[$this->pos];

									if ($minute_from > 59) {
										$this->setError();
										return;
									}
								}
								else {
									$minute_from = $this->source[$this->pos];
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
							if ($hour_to == -1
									|| ($this->source[$this->pos - 2] !== '-'
										&& $this->source[$this->pos - 3] !== '-')) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_to.':';

							$state = self::STATE_FLEXIBLE_MINUTE_TO;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($hour_to > -1) {
									$hour_to = (int) $hour_to.$this->source[$this->pos];

									if ($hour_to > 24) {
										$this->setError();
										return;
									}
								}
								else {
									$hour_to = $this->source[$this->pos];
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
							if ($this->source[$this->pos - 3] !== ':' || $minute_to == -1 || $hour_from > $hour_to
									|| ($hour_from == $hour_to && $minute_from >= $minute_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_to;

							$state = self::STATE_NEW;

							$week_from = -1;
							$week_to = -1;
							$hour_from = -1;
							$hour_to = -1;
							$minute_from = -1;
							$minute_to = -1;
							$i++;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($minute_to > -1) {
									// This is the second digit and the second minute.
									$minute_to = (int) $minute_to.$this->source[$this->pos];

									if ($minute_to > 59) {
										$this->setError();
										return;
									}
								}
								else {
									$minute_to = $this->source[$this->pos];
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

							$state = self::STATE_NEW;

							$month_from = -1;
							$i++;
							break;

						case '/':
							if ($month_from == -1) {
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

							$state = self::STATE_SCHEDULING_MONTH_TO;
							break;

						case ',':
							if (!$this->validateSchedulingMonthFrom($month_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_from.',';

							$month_from = -1;
							break;

						case 'w':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthFrom($month_from)) {
								$this->setError();
								return;
							}

							if ($this->source[$this->pos + 1] === 'd') {
								$this->intervals[$i]['interval'] .= $month_from.'wd';

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

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingMonthFrom($month_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_from.'s';

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($month_from > -1) {
									$month_from = (int) $month_from.$this->source[$this->pos];

									if ($month_from == 0 || $month_from > 31) {
										$this->setError();
										return;
									}
								}
								else {
									$month_from = $this->source[$this->pos];
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
							if (!$this->validateSchedulingMonthTo($month_from, $month_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_to;

							$state = self::STATE_NEW;

							$month_from = -1;
							$month_to = -1;
							$i++;
							break;

						case ',':
							if (!$this->validateSchedulingMonthTo($month_from, $month_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_to.',';

							$state = self::STATE_SCHEDULING_MONTH_FROM;

							$month_from = -1;
							$month_to = -1;
							break;

						case '/':
							if (!$this->validateSchedulingMonthTo($month_from, $month_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_to.'/';

							$state = self::STATE_SCHEDULING_MONTH_STEP;
							break;

						case 'w':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthTo($month_from, $month_to)) {
								$this->setError();
								return;
							}

							if ($this->source[$this->pos + 1] === 'd') {
								$this->intervals[$i]['interval'] .= $month_to.'wd';

								$state = self::STATE_SCHEDULING_WEEK_FROM;

								$this->pos++;
							}
							else {
								$this->setError();
								return;
							}
							break;

						case 'h':
							if (!$this->validateSchedulingMonthTo($month_from, $month_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_to.'h';

							$state = self::STATE_SCHEDULING_HOUR_FROM;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthTo($month_from, $month_to)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_to.'m';

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingMonthTo($month_from, $month_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_to.'s';

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($month_to > -1) {
									$month_to = (int) $month_to.$this->source[$this->pos];

									if ($month_from > $month_to) {
										$this->setError();
										return;
									}
								}
								else {
									$month_to = $this->source[$this->pos];
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
							if (!$this->validateSchedulingMonthStep($month_from, $month_to, $month_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_step;

							$state = self::STATE_NEW;

							$month_from = -1;
							$month_to = -1;
							$month_step = -1;
							$i++;
							break;

						case ',':
							if (!$this->validateSchedulingMonthStep($month_from, $month_to, $month_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_step.',';

							$state = self::STATE_SCHEDULING_MONTH_FROM;

							$month_from = -1;
							$month_to = -1;
							$month_step = -1;
							break;

						case 'w':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthStep($month_from, $month_to, $month_step)) {
								$this->setError();
								return;
							}

							if ($this->source[$this->pos + 1] === 'd') {
								$this->intervals[$i]['interval'] .= $month_step.'wd';

								$state = self::STATE_SCHEDULING_WEEK_FROM;

								$this->pos++;
							}
							break;

						case 'h':
							if (!$this->validateSchedulingMonthStep($month_from, $month_to, $month_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_step.'h';

							$state = self::STATE_SCHEDULING_HOUR_FROM;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingMonthStep($month_from, $month_to, $month_step)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_step.'m';

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingMonthStep($month_from, $month_to, $month_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $month_step.'s';

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($month_step > -1) {
									$month_step = (int) $month_step.$this->source[$this->pos];

									if ($month_step == 0 || $month_step > 30
											|| ($month_from != -1 && $month_step > ($month_to - $month_from))
											|| ($month_from != -1 && $month_from == $month_to && $month_step != 1)) {
										$this->setError();
										return;
									}
								}
								else {
									$month_step = $this->source[$this->pos];
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

							$state = self::STATE_NEW;

							$month_from = -1;
							$month_to = -1;
							$month_step = -1;
							$week_from = -1;
							$i++;
							break;

						case '/':
							if ($week_from == -1) {
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

							$state = self::STATE_SCHEDULING_WEEK_TO;
							break;

						case ',':
							if (!$this->validateSchedulingWeekFrom($week_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_from.',';

							$week_from = -1;
							break;

						case 'h':
							if (!$this->validateSchedulingWeekFrom($week_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_from.'h';

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

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingWeekFrom($week_from)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_from.'s';

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (!is_numeric($this->source[$this->pos]) || $week_from > -1) {
								$this->setError();
								return;
							}

							$week_from = $this->source[$this->pos];
					}
					break;

				case self::STATE_SCHEDULING_WEEK_TO:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingWeekTo($week_from, $week_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_to;

							$state = self::STATE_NEW;

							$month_from = -1;
							$month_to = -1;
							$week_from = -1;
							$week_to = -1;
							$i++;
							break;

						case ',':
							if (!$this->validateSchedulingWeekTo($week_from, $week_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_to.',';

							$state = self::STATE_SCHEDULING_WEEK_FROM;

							$week_from = -1;
							$week_to = -1;
							break;

						case '/':
							if (!$this->validateSchedulingWeekTo($week_from, $week_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_to.'/';

							$state = self::STATE_SCHEDULING_WEEK_STEP;
							break;

						case 'h':
							if (!$this->validateSchedulingWeekTo($week_from, $week_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_to.'h';

							$state = self::STATE_SCHEDULING_HOUR_FROM;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingWeekTo($week_from, $week_to)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_to.'m';

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingWeekTo($week_from, $week_to)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_to.'s';

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (!is_numeric($this->source[$this->pos]) || $week_to > -1) {
								$this->setError();
								return;
							}

							$week_to = $this->source[$this->pos];
					}
					break;

				case self::STATE_SCHEDULING_WEEK_STEP:
					switch ($this->source[$this->pos]) {
						case ';':
							if (!$this->validateSchedulingWeekStep($week_from, $week_to, $week_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_step;

							$state = self::STATE_NEW;

							$week_from = -1;
							$week_to = -1;
							$week_step = -1;
							$i++;
							break;

						case ',':
							if (!$this->validateSchedulingWeekStep($week_from, $week_to, $week_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_step.',';

							$state = self::STATE_SCHEDULING_WEEK_FROM;

							$week_from = -1;
							$week_to = -1;
							$week_step = -1;
							break;

						case 'h':
							if (!$this->validateSchedulingWeekStep($week_from, $week_to, $week_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_step.'h';

							$state = self::STATE_SCHEDULING_HOUR_FROM;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingWeekStep($week_from, $week_to, $week_step)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_step.'m';

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingWeekStep($week_from, $week_to, $week_step)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $week_step.'s';

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (!is_numeric($this->source[$this->pos]) || $week_step > -1) {
								$this->setError();
								return;
							}

							$week_step = $this->source[$this->pos];
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

							$state = self::STATE_NEW;

							$month_from = -1;
							$month_to = -1;
							$month_step = -1;
							$week_from = -1;
							$week_to = -1;
							$week_day_step = -1;
							$hour_from = -1;
							$i++;
							break;

						case '/':
							if ($hour_from == -1) {
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

							$state = self::STATE_SCHEDULING_HOUR_TO;
							break;

						case ',':
							if (!$this->validateSchedulingTimeFrom($hour_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_from.',';

							$hour_from = -1;
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

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingTimeFrom($hour_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_from.'s';

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($hour_from > -1) {
									$hour_from = (int) $hour_from.$this->source[$this->pos];

									if ($hour_from > 23) {
										$this->setError();
										return;
									}
								}
								else {
									$hour_from = $this->source[$this->pos];
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
							if (!$this->validateSchedulingTimeTo($hour_from, $hour_to, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_to;

							$state = self::STATE_NEW;

							$month_from = -1;
							$month_to = -1;
							$month_step = -1;
							$week_from = -1;
							$week_to = -1;
							$week_step = -1;
							$hour_from = -1;
							$hour_to = -1;
							$i++;
							break;

						case ',':
							if (!$this->validateSchedulingTimeTo($hour_from, $hour_to, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_to.',';

							$state = self::STATE_SCHEDULING_HOUR_FROM;

							$hour_from = -1;
							$hour_to = -1;
							break;

						case '/':
							if (!$this->validateSchedulingTimeTo($hour_from, $hour_to, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_to.'/';

							$state = self::STATE_SCHEDULING_HOUR_STEP;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingTimeTo($hour_from, $hour_to, $state)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_to.'m';

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingTimeTo($hour_from, $hour_to, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_to.'s';

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($hour_to > -1) {
									$hour_to = (int) $hour_to.$this->source[$this->pos];

									if ($hour_from > $hour_to || $hour_to > 23) {
										$this->setError();
										return;
									}
								}
								else {
									$hour_to = $this->source[$this->pos];
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
							if (!$this->validateSchedulingTimeStep($hour_from, $hour_to, $hour_step, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_step;

							$state = self::STATE_NEW;

							$month_from = -1;
							$month_to = -1;
							$month_step = -1;
							$week_from = -1;
							$week_to = -1;
							$week_step = -1;
							$hour_from = -1;
							$hour_to = -1;
							$hour_step = -1;
							$i++;
							break;

						case ',':
							if (!$this->validateSchedulingTimeStep($hour_from, $hour_to, $hour_step, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_step.',';

							$state = self::STATE_SCHEDULING_HOUR_FROM;

							$month_from = -1;
							$month_to = -1;
							$month_step = -1;
							$week_from = -1;
							$week_to = -1;
							$week_step = -1;
							$hour_from = -1;
							$hour_to = -1;
							$hour_step = -1;
							break;

						case 'm':
							if (!isset($this->source[$this->pos + 1])
									|| !$this->validateSchedulingTimeStep($hour_from, $hour_to, $hour_step, $state)
									|| ($this->source[$this->pos + 1] !== '/'
										&& !is_numeric($this->source[$this->pos + 1]))) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_step.'m';

							$state = self::STATE_SCHEDULING_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateSchedulingTimeStep($hour_from, $hour_to, $hour_step, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $hour_step.'s';

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($hour_step > -1) {
									$hour_step = (int) $hour_step.$this->source[$this->pos];

									if ($hour_step == 0 || $hour_step > 23
											|| ($hour_from != -1
												&& $hour_step > ($hour_to - $hour_from))
											|| ($hour_from != -1 && $hour_from == $hour_to
												&& $hour_step != 1)) {
										$this->setError();
										return;
									}
								}
								else {
									$hour_step = $this->source[$this->pos];
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

							$state = self::STATE_NEW;

							$month_from = -1;
							$month_to = -1;
							$month_step = -1;
							$week_from = -1;
							$week_to = -1;
							$week_day_step = -1;
							$hour_from = -1;
							$hour_to = -1;
							$hour_step = -1;
							$minute_from = -1;
							$i++;
							break;

						case '/':
							if ($minute_from == -1) {
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

							$state = self::STATE_SCHEDULING_MINUTE_TO;
							break;

						case ',':
							if (!$this->validateSchedulingTimeFrom($minute_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_from.',';

							$minute_from = -1;
							break;

						case 's':
							if (!$this->validateSchedulingTimeFrom($minute_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_from.'s';

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($minute_from > -1) {
									$minute_from = (int) $minute_from.$this->source[$this->pos];

									if ($minute_from > 59) {
										$this->setError();
										return;
									}
								}
								else {
									$minute_from = $this->source[$this->pos];
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
							if (!$this->validateSchedulingTimeTo($minute_from, $minute_to, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_to;

							$state = self::STATE_NEW;

							$month_from = -1;
							$month_to = -1;
							$month_step = -1;
							$week_from = -1;
							$week_to = -1;
							$week_step = -1;
							$hour_from = -1;
							$hour_to = -1;
							$hour_step = -1;
							$minute_from = -1;
							$minute_to = -1;
							$i++;
							break;

						case ',':
							if (!$this->validateSchedulingTimeTo($minute_from, $minute_to, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_to.',';

							$state = self::STATE_SCHEDULING_MINUTE_FROM;

							$minute_from = -1;
							$minute_to = -1;
							break;

						case '/':
							if (!$this->validateSchedulingTimeTo($minute_from, $minute_to, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_to.'/';

							$state = self::STATE_SCHEDULING_MINUTE_STEP;
							break;

						case 's':
							if (!$this->validateSchedulingTimeTo($minute_from, $minute_to, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_to.'s';

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($minute_to > -1) {
									$minute_to = (int) $minute_to.$this->source[$this->pos];

									if ($minute_from > $minute_to || $minute_to > 59) {
										$this->setError();
										return;
									}
								}
								else {
									$minute_to = $this->source[$this->pos];
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
							if (!$this->validateSchedulingTimeStep($minute_from, $minute_to, $minute_step, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_step;

							$state = self::STATE_NEW;

							$month_from = -1;
							$month_to = -1;
							$month_step = -1;
							$week_from = -1;
							$week_to = -1;
							$week_step = -1;
							$hour_from = -1;
							$hour_to = -1;
							$hour_step = -1;
							$minute_from = -1;
							$minute_to = -1;
							$minute_step = -1;
							$i++;
							break;

						case ',':
							if (!$this->validateSchedulingTimeStep($minute_from, $minute_to, $minute_step, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_step.',';

							$state = self::STATE_SCHEDULING_MINUTE_FROM;

							$month_from = -1;
							$month_to = -1;
							$month_step = -1;
							$week_from = -1;
							$week_to = -1;
							$week_step = -1;
							$hour_from = -1;
							$hour_to = -1;
							$hour_step = -1;
							$minute_from = -1;
							$minute_to = -1;
							$minute_step = -1;
							break;

						case 's':
							if (!$this->validateSchedulingTimeStep($minute_from, $minute_to, $minute_step, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $minute_step.'s';

							$state = self::STATE_SCHEDULING_SECOND_FROM;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($minute_step > -1) {
									$minute_step = (int) $minute_step.$this->source[$this->pos];

									if ($minute_step == 0 || $minute_step > 59
											|| ($minute_from != -1 && $minute_step > ($minute_to - $minute_from))
											|| ($minute_from != -1 && $minute_from == $minute_to
												&& $minute_step != 1)) {
										$this->setError();
										return;
									}
								}
								else {
									$minute_step = $this->source[$this->pos];
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

							$state = self::STATE_NEW;

							$month_from = -1;
							$month_to = -1;
							$month_step = -1;
							$week_from = -1;
							$week_to = -1;
							$week_day_step = -1;
							$hour_from = -1;
							$hour_to = -1;
							$hour_step = -1;
							$minute_from = -1;
							$minute_to = -1;
							$minute_step = -1;
							$second_from = -1;
							$i++;
							break;

						case '/':
							if ($second_from == -1) {
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

							$state = self::STATE_SCHEDULING_SECOND_TO;
							break;

						case ',':
							if (!$this->validateSchedulingTimeFrom($second_from, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $second_from.',';

							$second_from = -1;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($second_from > -1) {
									$second_from = (int) $second_from.$this->source[$this->pos];

									if ($second_from > 59) {
										$this->setError();
										return;
									}
								}
								else {
									$second_from = $this->source[$this->pos];
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
							if (!$this->validateSchedulingTimeTo($second_from, $second_to, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $second_to;

							$state = self::STATE_NEW;

							$month_from = -1;
							$month_to = -1;
							$month_step = -1;
							$week_from = -1;
							$week_to = -1;
							$week_step = -1;
							$hour_from = -1;
							$hour_to = -1;
							$hour_step = -1;
							$minute_from = -1;
							$minute_to = -1;
							$minute_step = -1;
							$second_from = -1;
							$second_to = -1;
							$i++;
							break;

						case ',':
							if (!$this->validateSchedulingTimeTo($second_from, $second_to, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $second_to.',';

							$state = self::STATE_SCHEDULING_SECOND_FROM;

							$second_from = -1;
							$second_to = -1;
							break;

						case '/':
							if (!$this->validateSchedulingTimeTo($second_from, $second_to, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $second_to.'/';

							$state = self::STATE_SCHEDULING_SECOND_STEP;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($second_to > -1) {
									$second_to = (int) $second_to.$this->source[$this->pos];

									if ($second_from > $second_to || $second_to > 59) {
										$this->setError();
										return;
									}
								}
								else {
									$second_to = $this->source[$this->pos];
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
							if (!$this->validateSchedulingTimeStep($second_from, $second_to, $second_step, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $second_step;

							$state = self::STATE_NEW;

							$month_from = -1;
							$month_to = -1;
							$month_step = -1;
							$week_from = -1;
							$week_to = -1;
							$week_step = -1;
							$hour_from = -1;
							$hour_to = -1;
							$hour_step = -1;
							$minute_from = -1;
							$minute_to = -1;
							$minute_step = -1;
							$second_from = -1;
							$second_to = -1;
							$second_step = -1;
							$i++;
							break;

						case ',':
							if (!$this->validateSchedulingTimeStep($second_from, $second_to, $second_step, $state)) {
								$this->setError();
								return;
							}

							$this->intervals[$i]['interval'] .= $second_step.',';

							$state = self::STATE_SCHEDULING_SECOND_FROM;

							$month_from = -1;
							$month_to = -1;
							$month_step = -1;
							$week_from = -1;
							$week_to = -1;
							$week_step = -1;
							$hour_from = -1;
							$hour_to = -1;
							$hour_step = -1;
							$minute_from = -1;
							$minute_to = -1;
							$minute_step = -1;
							$second_from = -1;
							$second_to = -1;
							$second_step = -1;
							break;

						default:
							if (is_numeric($this->source[$this->pos])) {
								if ($second_step > -1) {
									$second_step = (int) $second_step.$this->source[$this->pos];

									if ($second_step == 0 || $second_step > 59
											|| ($second_from != -1 && $second_step > ($second_to - $second_from))
											|| ($second_from != -1 && $second_from == $second_to
												&& $second_step != 1)) {
										$this->setError();
										return;
									}
								}
								else {
									$second_step = $this->source[$this->pos];
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
				if ($this->source[$this->pos - 3] !== ':' || $minute_to == -1 || $hour_from > $hour_to
						|| ($hour_from == $hour_to && $minute_from >= $minute_to)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $minute_to;
				break;

			case self::STATE_SCHEDULING_MONTH_FROM:
				if (!$this->validateSchedulingMonthFrom($month_from)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $month_from;
				break;

			case self::STATE_SCHEDULING_MONTH_TO:
				if (!$this->validateSchedulingMonthTo($month_from, $month_to)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $month_to;
				break;

			case self::STATE_SCHEDULING_MONTH_STEP:
				if (!$this->validateSchedulingMonthStep($month_from, $month_to, $month_step)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $month_step;
				break;

			case self::STATE_SCHEDULING_WEEK_FROM:
				if (!$this->validateSchedulingWeekFrom($week_from)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $week_from;
				break;

			case self::STATE_SCHEDULING_WEEK_TO:
				if (!$this->validateSchedulingWeekTo($week_from, $week_to)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $week_to;
				break;

			case self::STATE_SCHEDULING_WEEK_STEP:
				if (!$this->validateSchedulingWeekStep($week_from, $week_to, $week_step)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $week_step;
				break;

			case self::STATE_SCHEDULING_HOUR_FROM:
				if (!$this->validateSchedulingTimeFrom($hour_from, $state)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $hour_from;
				break;

			case self::STATE_SCHEDULING_HOUR_TO:
				if (!$this->validateSchedulingTimeTo($hour_from, $hour_to, $state)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $hour_to;
				break;

			case self::STATE_SCHEDULING_HOUR_STEP:
				if (!$this->validateSchedulingTimeStep($hour_from, $hour_to, $hour_step, $state)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $hour_step;
				break;

			case self::STATE_SCHEDULING_MINUTE_FROM:
				if (!$this->validateSchedulingTimeFrom($minute_from, $state)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $minute_from;
				break;

			case self::STATE_SCHEDULING_MINUTE_TO:
				if (!$this->validateSchedulingTimeTo($minute_from, $minute_to, $state)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $minute_to;
				break;

			case self::STATE_SCHEDULING_MINUTE_STEP:
				if (!$this->validateSchedulingTimeStep($minute_from, $minute_to, $minute_step, $state)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $minute_step;
				break;

			case self::STATE_SCHEDULING_SECOND_FROM:
				if (!$this->validateSchedulingTimeFrom($second_from, $state)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $second_from;
				break;

			case self::STATE_SCHEDULING_SECOND_TO:
				if (!$this->validateSchedulingTimeTo($second_from, $second_to, $state)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $second_to;
				break;

			case self::STATE_SCHEDULING_SECOND_STEP:
				if (!$this->validateSchedulingTimeStep($second_from, $second_to, $second_step, $state)) {
					$this->setError();
					return;
				}

				$this->intervals[$i]['interval'] .= $second_step;
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
	 * Validate the "month day from" parameter. Month day cannot be 0 or greater that 31 days. If month day is a singe
	 * digit, it must have previous character "d" (from "md" syntax) or ",". Otherwise it checks behind two previous
	 * characters for "d" or ",".
	 *
	 * Example: md1;md07;md31;md1,3,6
	 *
	 * @param string $from
	 *
	 * @return bool
	 */
	private function validateSchedulingMonthFrom($from) {
		if ($from < 1 || $from > 31 || ($this->source[$this->pos - 2] !== 'd' && $this->source[$this->pos - 3] !== 'd'
				&& $this->source[$this->pos - 2] !== ',' && $this->source[$this->pos - 3] !== ',')) {
			return false;
		}

		return true;
	}

	/**
	 * Validate the "month day to" parameter. Month day cannot be less than "month day from". If month day is a singe
	 * digit, it must have previous character "-". Otherwise it checks behind two previous characters for "-".
	 *
	 * Example: md1-5;md07-09;md01-31
	 *
	 * @param string $from
	 * @param string $to
	 *
	 * @return bool
	 */
	private function validateSchedulingMonthTo($from, $to) {
		if ($from > $to || ($this->source[$this->pos - 2] !== '-' && $this->source[$this->pos - 3] !== '-')) {
			return false;
		}

		return true;
	}

	/**
	 * Validate the "month day step" parameter. Month day step should be at least "1", cannot exceed maximum amout of
	 * days which is 30 (31-1) or it cannot be greater than difference between month days "month day to"-"month day from".
	 * If step is single digit, it must have previous character "/". Otherwise it checks behind two previous characters
	 * for "/".
	 *
	 * Example: md1-5/4;md07-09/02;md/30
	 *
	 * @param string $from
	 * @param string $to
	 * @param string $step
	 *
	 * @return bool
	 */
	private function validateSchedulingMonthStep($from, $to, $step) {
		if ($step == 0 || $step > 30 || ($from != -1 && $step > ($to - $from))
				|| ($from != -1 && $from == $to && $step != 1)
				|| ($this->source[$this->pos - 2] !== '/' && $this->source[$this->pos - 3] !== '/')) {
			return false;
		}

		return true;
	}

	/**
	 * Validate the "week day from" parameter. Week day cannot be 0 or greater that 7 days. It must have previous
	 * character "d" (from "wd" syntax) or ",".
	 *
	 * Example: wd1;wd7;wd1,3,7
	 *
	 * @param string $from
	 *
	 * @return bool
	 */
	private function validateSchedulingWeekFrom($from) {
		if ($from < 1 || $from > 7
				|| ($this->source[$this->pos - 2] !== 'd' && $this->source[$this->pos - 2] !== ',')) {
			return false;
		}

		return true;
	}

	/**
	 * Validate the "week day to" parameter. Week day cannot be less than "week day from". It must have previous
	 * character "-".
	 *
	 * Example: wd1-5;md7-9
	 *
	 * @param string $from
	 * @param string $to
	 *
	 * @return bool
	 */
	private function validateSchedulingWeekTo($from, $to) {
		if ($from > $to || $this->source[$this->pos - 2] !== '-') {
			return false;
		}

		return true;
	}

	/**
	 * Validate the "week day step" parameter. Week day step should be at least "1", cannot exceed maximum amout of
	 * days which is 6 (7-1) or it cannot be greater than difference between week days "week day to"-"week day from".
	 * It must have previous character "/".
	 *
	 * Example: wd1-5/4;wd/6
	 *
	 * @param string $from
	 * @param string $to
	 * @param string $step
	 *
	 * @return bool
	 */
	private function validateSchedulingWeekStep($from, $to, $step) {
		if ($step == 0 || $step > 6 || $this->source[$this->pos - 2] !== '/' || ($from != -1 && $step > ($to - $from))
				|| ($from != -1 && $from == $to && $step != 1)) {
			return false;
		}

		return true;
	}

	/**
	 * Validate the three time parameters "hour from", "minute from" and "second from" with single function depeding on
	 * "state". For hours, the maximum hours can be entered 23, for minutes and seconds - 59. If any parameter has two
	 * digits, it must have previous character "h" for hours, "m" for minutes, "s" for seconds or ",". Otherwise it
	 * checks behind two previous characters for "h", "m", "s" or ",". Hours, minutes and seconds can be 0 or 00.
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
				$max = 23;
				break;

			case self::STATE_SCHEDULING_MINUTE_FROM:
				$char = 'm';
				$max = 59;
				break;

			case self::STATE_SCHEDULING_SECOND_FROM:
				$char = 's';
				$max = 59;
				break;

			default:
				return false;
		}

		if ((!isset($this->source[$this->pos - 2]) && !isset($this->source[$this->pos - 3]))
				|| $from < 0 || $from > $max
				|| ($this->source[$this->pos - 2] !== $char && $this->source[$this->pos - 3] !== $char
						&& $this->source[$this->pos - 2] !== ',' && $this->source[$this->pos - 3] !== ',')) {
			return false;
		}

		return true;
	}

	/**
	 * Validate the three time parameters "hour to", "minute to" and "second to" with single function depeding on
	 * "state". For hours, the maximum hours can be entered 23, for minutes and seconds - 59. "* from" values cannot be
	 * greater that "* to" values. If any parameter has two digits, it must have previous character "-". Otherwise it
	 * checks behind two previous characters for "-".
	 *
	 * Example: h1-9;h12-17;h09-23,10;h00-23;m1-9;m30-59;m09-59,10-30,25-26;s1-5;s30-59;s09-59,10-30,50
	 *
	 * @param string $from
	 * @param string $to
	 * @param int $state
	 *
	 * @return bool
	 */
	private function validateSchedulingTimeTo($from, $to, $state) {
		switch ($state) {
			case self::STATE_SCHEDULING_HOUR_TO:
				$max = 23;
				break;

			case self::STATE_SCHEDULING_MINUTE_TO:
			case self::STATE_SCHEDULING_SECOND_TO:
				$max = 59;
				break;

			default:
				return false;
		}

		if ((!isset($this->source[$this->pos - 2]) && !isset($this->source[$this->pos - 3]))
				|| $from > $to || $to > $max
				|| ($this->source[$this->pos - 2] !== '-' && $this->source[$this->pos - 3] !== '-')) {
			return false;
		}

		return true;
	}

	/**
	 * Validate the three time parameters "hour step", "minute step" and "second step" with single function depeding on
	 * "state". For hours, the maximum step can be entered 23, for minutes and seconds - 59. Step should be at least "1"
	 * or cannot be greater than "* to"-"* from" If any parameter has two digits, it must have previous character "/". Otherwise it
	 * checks behind two previous characters for "/".
	 *
	 * Example: h/1;h/09;h12-17/5;h/23;m/1;m/09;m30-59/29;m/59;s/1;s/09;s30-59/29;s/59
	 *
	 * @param string $from
	 * @param string $to
	 * @param int $state
	 *
	 * @return bool
	 */
	private function validateSchedulingTimeStep($from, $to, $step, $state) {
		switch ($state) {
			case self::STATE_SCHEDULING_HOUR_STEP:
				$max = 23;
				break;

			case self::STATE_SCHEDULING_MINUTE_STEP:
			case self::STATE_SCHEDULING_SECOND_STEP:
				$max = 59;
				break;

			default:
				return false;
		}

		if ((!isset($this->source[$this->pos - 2]) && !isset($this->source[$this->pos - 3]))
				|| $step == 0 || $step > $max
				|| ($from != -1 && $step > ($to - $from)) || ($from != -1 && $from == $to && $step != 1)
				|| ($this->source[$this->pos - 2] !== '/' && isset($this->source[$this->pos - 3])
					&& $this->source[$this->pos - 3] !== '/')
				|| ($this->source[$this->pos - 2] !== '/' && !isset($this->source[$this->pos - 3]))) {
			return false;
		}

		return true;
	}
}
