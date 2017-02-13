<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
 * A parser for scheduling intervals.
 */
class CSchedulingIntervalParser extends CParser {

	// Possible parsing states.
	const STATE_NEW = 0;
	const STATE_MONTH_FROM = 1;
	const STATE_MONTH_TILL = 2;
	const STATE_MONTH_STEP = 3;
	const STATE_WEEK_FROM = 4;
	const STATE_WEEK_TILL = 5;
	const STATE_WEEK_STEP = 6;
	const STATE_HOUR_FROM = 7;
	const STATE_HOUR_TILL = 8;
	const STATE_HOUR_STEP = 9;
	const STATE_MINUTE_FROM = 10;
	const STATE_MINUTE_TILL = 11;
	const STATE_MINUTE_STEP = 12;
	const STATE_SECOND_FROM = 13;
	const STATE_SECOND_TILL = 14;
	const STATE_SECOND_STEP = 15;

	/*
	 * Current parsing state.
	 *
	 * @var int
	 */
	private $state;

	/**
	 * Parse the given scheduled interval.
	 *
	 * @param string $source	Source string that needs to be parsed.
	 * @param int    $pos		Position offset.
	 */

	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$p = $pos;
		if (!isset($source[$p])) {
			return self::PARSE_FAIL;
		}

		$md_from = '';
		$md_till = '';
		$md_step = '';
		$wd_from = '';
		$wd_till = '';
		$wd_step = '';
		$h_from = '';
		$h_till = '';
		$h_step = '';
		$m_from = '';
		$m_till = '';
		$m_step = '';
		$s_from = '';
		$s_till = '';
		$s_step = '';

		$this->state = self::STATE_NEW;

		while (isset($source[$p])) {
			switch ($this->state) {
				case self::STATE_NEW:
					/*
					 * Determine the time unit:
					 *   md - month days
					 *   wd - week days
					 *   h - hours
					 *   m - minutes
					 *   s - seconds
					 */
					switch ($source[$p]) {
						case 'm':
							// At this point it can be minutes or month days, so check the next character.
							if (isset($source[$p + 1])) {
								if ($source[$p + 1] === 'd') {
									$this->state = self::STATE_MONTH_FROM;
									$p++;
								}
								else {
									$this->state = self::STATE_MINUTE_FROM;
								}
							}
							else {
								return self::PARSE_FAIL;
							}
							break;

						case 'w':
							if (isset($source[$p + 1]) && $source[$p + 1] === 'd') {
								$this->state = self::STATE_WEEK_FROM;
								$p++;
							}
							else {
								return self::PARSE_FAIL;
							}
							break;

						case 'h':
							$this->state = self::STATE_HOUR_FROM;
							break;

						case 's':
							$this->state = self::STATE_SECOND_FROM;
							break;

						default:
							return self::PARSE_FAIL;
					}
					break;

				case self::STATE_MONTH_FROM:
					switch ($source[$p]) {
						case '/':
							if (strlen($md_from) == 0) {
								$this->state = self::STATE_MONTH_STEP;
							}
							else {
								return self::PARSE_FAIL;
							}
							break;

						case '-':
							if (!$this->validateTimeValueFrom($md_from)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MONTH_TILL;
							break;

						case ',':
							if (!$this->validateTimeValueFrom($md_from)) {
								return self::PARSE_FAIL;
							}

							$md_from = '';
							break;

						case 'w':
							// Check next character, because it may be changing to week days, so we need combo "wd".
							if (!$this->validateTimeValueFrom($md_from) || !isset($source[$p + 1])) {
								return self::PARSE_FAIL;
							}

							if ($source[$p + 1] === 'd') {
								$this->state = self::STATE_WEEK_FROM;
								$p++;
							}
							else {
								return self::PARSE_FAIL;
							}
							break;

						case 'h':
							if (!$this->validateTimeValueFrom($md_from)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_HOUR_FROM;
							break;

						case 'm':
							/*
							 * Need to check the next char in case it is minutes. Minutes can contain a numeric value
							 * and step with starting "/". Another "md" is not allowed.
							 */
							if (!$this->validateTimeValueFrom($md_from) || !isset($source[$p + 1])
									|| ($source[$p + 1] !== '/' && !is_numeric($source[$p + 1]))) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateTimeValueFrom($md_from)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_SECOND_FROM;
							break;

						default:
							if (is_numeric($source[$p])) {
								$md_from .= $source[$p];

								if (strlen($md_from) > 2) {
									if ($this->validateTimeValueFrom(substr($md_from, 0, -1))) {
										$this->length = $p - $pos;
										$this->match = substr($source, $pos, $this->length);

										return self::PARSE_SUCCESS_CONT;
									}
									else {
										return self::PARSE_FAIL;
									}
								}
							}
							else {
								if (strlen($md_from) <= 2 && !$this->validateTimeValueFrom($md_from)) {
									return self::PARSE_FAIL;
								}
								else {
									$this->length = $p - $pos;
									$this->match = substr($source, $pos, $this->length);

									return self::PARSE_SUCCESS_CONT;
								}
							}
					}
					break;

				case self::STATE_MONTH_TILL:
					switch ($source[$p]) {
						case ',':
							if (!$this->validateTimeValueTill($md_from, $md_till)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MONTH_FROM;

							$md_from = '';
							$md_till = '';
							break;

						case '/':
							if (!$this->validateTimeValueTill($md_from, $md_till)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MONTH_STEP;
							break;

						case 'w':
							if (!$this->validateTimeValueTill($md_from, $md_till) || !isset($source[$p + 1])) {
								return self::PARSE_FAIL;
							}

							if ($source[$p + 1] === 'd') {
								$this->state = self::STATE_WEEK_FROM;
								$p++;
							}
							else {
								return self::PARSE_FAIL;
							}
							break;

						case 'h':
							if (!$this->validateTimeValueTill($md_from, $md_till)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_HOUR_FROM;
							break;

						case 'm':
							if (!$this->validateTimeValueTill($md_from, $md_till) || !isset($source[$p + 1])
									|| ($source[$p + 1] !== '/' && !is_numeric($source[$p + 1]))) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateTimeValueTill($md_from, $md_till)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_SECOND_FROM;
							break;

						default:
							if (is_numeric($source[$p])) {
								$md_till .= $source[$p];

								if (strlen($md_till) > 2) {
									if ($this->validateTimeValueTill($md_from, substr($md_till, 0, -1))) {
										$this->length = $p - $pos;
										$this->match = substr($source, $pos, $this->length);

										return self::PARSE_SUCCESS_CONT;
									}
									else {
										return self::PARSE_FAIL;
									}
								}
							}
							else {
								if (strlen($md_till) <= 2 && !$this->validateTimeValueTill($md_from, $md_till)) {
									return self::PARSE_FAIL;
								}
								else {
									$this->length = $p - $pos;
									$this->match = substr($source, $pos, $this->length);

									return self::PARSE_SUCCESS_CONT;
								}
							}
					}
					break;

				case self::STATE_MONTH_STEP:
					switch ($source[$p]) {
						case ',':
							if (!$this->validateTimeValueStep($md_from, $md_till, $md_step)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MONTH_FROM;

							$md_from = '';
							$md_till = '';
							$md_step = '';
							break;

						case 'w':
							if (!$this->validateTimeValueStep($md_from, $md_till, $md_step)
									|| !isset($source[$p + 1])) {
								return self::PARSE_FAIL;
							}

							if ($source[$p + 1] === 'd') {
								$this->state = self::STATE_WEEK_FROM;
								$p++;
							}
							break;

						case 'h':
							if (!$this->validateTimeValueStep($md_from, $md_till, $md_step)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_HOUR_FROM;
							break;

						case 'm':
							if (!$this->validateTimeValueStep($md_from, $md_till, $md_step) || !isset($source[$p + 1])
									|| ($source[$p + 1] !== '/' && !is_numeric($source[$p + 1]))) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateTimeValueStep($md_from, $md_till, $md_step)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_SECOND_FROM;
							break;

						default:
							if (is_numeric($source[$p])) {
								$md_step .= $source[$p];

								if (strlen($md_step) > 2) {
									if ($this->validateTimeValueStep($md_from, $md_till, substr($md_step, 0, -1))) {
										$this->length = $p - $pos;
										$this->match = substr($source, $pos, $this->length);

										return self::PARSE_SUCCESS_CONT;
									}
									else {
										return self::PARSE_FAIL;
									}
								}
							}
							else {
								if (strlen($md_step) <= 2
										&& !$this->validateTimeValueStep($md_from, $md_till, $md_step)) {
									return self::PARSE_FAIL;
								}
								else {
									$this->length = $p - $pos;
									$this->match = substr($source, $pos, $this->length);

									return self::PARSE_SUCCESS_CONT;
								}
							}
					}
					break;

				case self::STATE_WEEK_FROM:
					switch ($source[$p]) {
						case '/':
							if (strlen($wd_from) == 0) {
								$this->state = self::STATE_WEEK_STEP;
							}
							else {
								return self::PARSE_FAIL;
							}
							break;

						case '-':
							if (!$this->validateTimeValueFrom($wd_from)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_WEEK_TILL;
							break;

						case ',':
							if (!$this->validateTimeValueFrom($wd_from)) {
								return self::PARSE_FAIL;
							}

							$wd_from = '';
							break;

						case 'h':
							if (!$this->validateTimeValueFrom($wd_from)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_HOUR_FROM;
							break;

						case 'm':
							if (!$this->validateTimeValueFrom($wd_from) || !isset($source[$p + 1])
									|| ($source[$p + 1] !== '/' && !is_numeric($source[$p + 1]))) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateTimeValueFrom($wd_from)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_SECOND_FROM;
							break;

						default:
							if (is_numeric($source[$p])) {
								$wd_from .= $source[$p];

								if (strlen($wd_from) > 1) {
									if ($this->validateTimeValueFrom(substr($wd_from, 0, -1))) {
										$this->length = $p - $pos;
										$this->match = substr($source, $pos, $this->length);

										return self::PARSE_SUCCESS_CONT;
									}
									else {
										return self::PARSE_FAIL;
									}
								}
							}
							else {
								if (strlen($wd_from) <= 2 && !$this->validateTimeValueFrom($wd_from)) {
									return self::PARSE_FAIL;
								}

								$this->length = $p - $pos;
								$this->match = substr($source, $pos, $this->length);

								return self::PARSE_SUCCESS_CONT;
							}
					}
					break;

				case self::STATE_WEEK_TILL:
					switch ($source[$p]) {
						case ',':
							if (!$this->validateTimeValueTill($wd_from, $wd_till)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_WEEK_FROM;

							$wd_from = '';
							$wd_till = '';
							break;

						case '/':
							if (!$this->validateTimeValueTill($wd_from, $wd_till)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_WEEK_STEP;
							break;

						case 'h':
							if (!$this->validateTimeValueTill($wd_from, $wd_till)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_HOUR_FROM;
							break;

						case 'm':
							if (!$this->validateTimeValueTill($wd_from, $wd_till) || !isset($source[$p + 1])
									|| ($source[$p + 1] !== '/' && !is_numeric($source[$p + 1]))) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateTimeValueTill($wd_from, $wd_till)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_SECOND_FROM;
							break;

						default:
							if (is_numeric($source[$p])) {
								$wd_till .= $source[$p];

								if (strlen($wd_till) > 1) {
									if ($this->validateTimeValueTill($wd_from, substr($wd_till, 0, -1))) {
										$this->length = $p - $pos;
										$this->match = substr($source, $pos, $this->length);

										return self::PARSE_SUCCESS_CONT;
									}
									else {
										return self::PARSE_FAIL;
									}
								}
							}
							else {
								if (strlen($wd_till) <= 1 && !$this->validateTimeValueTill($wd_from, $wd_till)) {
									return self::PARSE_FAIL;
								}
								else {
									$this->length = $p - $pos;
									$this->match = substr($source, $pos, $this->length);

									return self::PARSE_SUCCESS_CONT;
								}
							}
					}
					break;

				case self::STATE_WEEK_STEP:
					switch ($source[$p]) {
						case ',':
							if (!$this->validateTimeValueStep($wd_from, $wd_till, $wd_step)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_WEEK_FROM;

							$wd_from = '';
							$wd_till = '';
							$wd_step = '';
							break;

						case 'h':
							if (!$this->validateTimeValueStep($wd_from, $wd_till, $wd_step)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_HOUR_FROM;
							break;

						case 'm':
							if (!$this->validateTimeValueStep($wd_from, $wd_till, $wd_step) || !isset($source[$p + 1])
									|| ($source[$p + 1] !== '/' && !is_numeric($source[$p + 1]))) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateTimeValueStep($wd_from, $wd_till, $wd_step)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_SECOND_FROM;
							break;

						default:
							if (is_numeric($source[$p])) {
								$wd_step .= $source[$p];

								if (strlen($wd_step) > 1) {
									if ($this->validateTimeValueStep($wd_from, $wd_till, substr($wd_step, 0, -1))) {
										$this->length = $p - $pos;
										$this->match = substr($source, $pos, $this->length);

										return self::PARSE_SUCCESS_CONT;
									}
									else {
										return self::PARSE_FAIL;
									}
								}
							}
							else {
								if (strlen($wd_step) <= 1
										&& !$this->validateTimeValueStep($wd_from, $wd_till, $wd_step)) {
									return self::PARSE_FAIL;
								}
								else {
									$this->length = $p - $pos;
									$this->match = substr($source, $pos, $this->length);

									return self::PARSE_SUCCESS_CONT;
								}
							}
					}
					break;

				case self::STATE_HOUR_FROM:
					switch ($source[$p]) {
						case '/':
							// Step can be entered of first hour is ommited.
							if (strlen($h_from) == 0) {
								$this->state = self::STATE_HOUR_STEP;
							}
							else {
								return self::PARSE_FAIL;
							}
							break;

						case '-':
							if (!$this->validateTimeValueFrom($h_from)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_HOUR_TILL;
							break;

						case ',':
							if (!$this->validateTimeValueFrom($h_from)) {
								return self::PARSE_FAIL;
							}

							$h_from = '';
							break;

						case 'm':
							if (!$this->validateTimeValueFrom($h_from) || !isset($source[$p + 1])
									|| ($source[$p + 1] !== '/' && !is_numeric($source[$p + 1]))) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateTimeValueFrom($h_from)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_SECOND_FROM;
							break;

						default:
							if (is_numeric($source[$p])) {
								$h_from .= $source[$p];

								if (strlen($h_from) > 2) {
									if ($this->validateTimeValueFrom(substr($h_from, 0, -1))) {
										$this->length = $p - $pos;
										$this->match = substr($source, $pos, $this->length);

										return self::PARSE_SUCCESS_CONT;
									}
									else {
										return self::PARSE_FAIL;
									}
								}
							}
							else {
								if (strlen($h_from) <= 2 && !$this->validateTimeValueFrom($h_from)) {
									return self::PARSE_FAIL;
								}
								else {
									$this->length = $p - $pos;
									$this->match = substr($source, $pos, $this->length);

									return self::PARSE_SUCCESS_CONT;
								}
							}
					}
					break;

				case self::STATE_HOUR_TILL:
					switch ($source[$p]) {
						case ',':
							if (!$this->validateTimeValueTill($h_from, $h_till)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_HOUR_FROM;

							$h_from = '';
							$h_till = '';
							break;

						case '/':
							if (!$this->validateTimeValueTill($h_from, $h_till)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_HOUR_STEP;
							break;

						case 'm':
							if (!$this->validateTimeValueTill($h_from, $h_till) || !isset($source[$p + 1])
									|| ($source[$p + 1] !== '/' && !is_numeric($source[$p + 1]))) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateTimeValueTill($h_from, $h_till)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_SECOND_FROM;
							break;

						default:
							if (is_numeric($source[$p])) {
								$h_till .= $source[$p];

								if (strlen($h_till) > 2) {
									if ($this->validateTimeValueTill($h_from, substr($h_till, 0, -1))) {
										$this->length = $p - $pos;
										$this->match = substr($source, $pos, $this->length);

										return self::PARSE_SUCCESS_CONT;
									}
									else {
										return self::PARSE_FAIL;
									}
								}
							}
							else {
								if (strlen($h_till) <= 2 && !$this->validateTimeValueTill($h_from, $h_till)) {
									return self::PARSE_FAIL;
								}
								else {
									$this->length = $p - $pos;
									$this->match = substr($source, $pos, $this->length);

									return self::PARSE_SUCCESS_CONT;
								}
							}
					}
					break;

				case self::STATE_HOUR_STEP:
					switch ($source[$p]) {
						case ',':
							if (!$this->validateTimeValueStep($h_from, $h_till, $h_step)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_HOUR_FROM;

							$h_from = '';
							$h_till = '';
							$h_step = '';
							break;

						case 'm':
							if (!$this->validateTimeValueStep($h_from, $h_till, $h_step) || !isset($source[$p + 1])
									|| ($source[$p + 1] !== '/' && !is_numeric($source[$p + 1]))) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MINUTE_FROM;
							break;

						case 's':
							if (!$this->validateTimeValueStep($h_from, $h_till, $h_step)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_SECOND_FROM;
							break;

						default:
							if (is_numeric($source[$p])) {
								$h_step .= $source[$p];

								if (strlen($h_step) > 2) {
									if ($this->validateTimeValueStep($h_from, $h_till, substr($h_step, 0, -1))) {
										$this->length = $p - $pos;
										$this->match = substr($source, $pos, $this->length);

										return self::PARSE_SUCCESS_CONT;
									}
									else {
										return self::PARSE_FAIL;
									}
								}
							}
							else {
								if (strlen($h_step) <= 2
										&& !$this->validateTimeValueStep($h_from, $h_till, $h_step)) {
									return self::PARSE_FAIL;
								}
								else {
									$this->length = $p - $pos;
									$this->match = substr($source, $pos, $this->length);

									return self::PARSE_SUCCESS_CONT;
								}
							}
					}
					break;

				case self::STATE_MINUTE_FROM:
					switch ($source[$p]) {
						case '/':
							if (strlen($m_from) == 0) {
								$this->state = self::STATE_MINUTE_STEP;
							}
							else {
								return self::PARSE_FAIL;
							}
							break;

						case '-':
							if (!$this->validateTimeValueFrom($m_from)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MINUTE_TILL;
							break;

						case ',':
							if (!$this->validateTimeValueFrom($m_from)) {
								return self::PARSE_FAIL;
							}

							$m_from = '';
							break;

						case 's':
							if (!$this->validateTimeValueFrom($m_from)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_SECOND_FROM;
							break;

						default:
							if (is_numeric($source[$p])) {
								$m_from .= $source[$p];

								if (strlen($m_from) > 2) {
									if ($this->validateTimeValueFrom(substr($m_from, 0, -1))) {
										$this->length = $p - $pos;
										$this->match = substr($source, $pos, $this->length);

										return self::PARSE_SUCCESS_CONT;
									}
									else {
										return self::PARSE_FAIL;
									}
								}
							}
							else {
								if (strlen($m_from) <= 2 && !$this->validateTimeValueFrom($m_from)) {
									return self::PARSE_FAIL;
								}
								else {
									$this->length = $p - $pos;
									$this->match = substr($source, $pos, $this->length);

									return self::PARSE_SUCCESS_CONT;
								}
							}
					}
					break;

				case self::STATE_MINUTE_TILL:
					switch ($source[$p]) {
						case ',':
							if (!$this->validateTimeValueTill($m_from, $m_till)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MINUTE_FROM;

							$m_from = '';
							$m_till = '';
							break;

						case '/':
							if (!$this->validateTimeValueTill($m_from, $m_till)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MINUTE_STEP;
							break;

						case 's':
							if (!$this->validateTimeValueTill($m_from, $m_till)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_SECOND_FROM;
							break;

						default:
							if (is_numeric($source[$p])) {
								$m_till .= $source[$p];

								if (strlen($m_till) > 2) {
									if ($this->validateTimeValueTill($m_from, substr($m_till, 0, -1))) {
										$this->length = $p - $pos;
										$this->match = substr($source, $pos, $this->length);

										return self::PARSE_SUCCESS_CONT;
									}
									else {
										return self::PARSE_FAIL;
									}
								}
							}
							else {
								if (strlen($m_till) <= 2 && !$this->validateTimeValueTill($m_from, $m_till)) {
									return self::PARSE_FAIL;
								}
								else {
									$this->length = $p - $pos;
									$this->match = substr($source, $pos, $this->length);

									return self::PARSE_SUCCESS_CONT;
								}
							}
					}
					break;

				case self::STATE_MINUTE_STEP:
					switch ($source[$p]) {
						case ',':
							if (!$this->validateTimeValueStep($m_from, $m_till, $m_step)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_MINUTE_FROM;

							$m_from = '';
							$m_till = '';
							$m_step = '';
							break;

						case 's':
							if (!$this->validateTimeValueStep($m_from, $m_till, $m_step)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_SECOND_FROM;
							break;

						default:
							if (is_numeric($source[$p])) {
								$m_step .= $source[$p];

								if (strlen($m_step) > 2) {
									if ($this->validateTimeValueStep($m_from, $m_till, substr($m_step, 0, -1))) {
										$this->length = $p - $pos;
										$this->match = substr($source, $pos, $this->length);

										return self::PARSE_SUCCESS_CONT;
									}
									else {
										return self::PARSE_FAIL;
									}
								}
							}
							else {
								if (strlen($m_step) <= 2
										&& !$this->validateTimeValueStep($m_from, $m_till, $m_step)) {
									return self::PARSE_FAIL;
								}
								else {
									$this->length = $p - $pos;
									$this->match = substr($source, $pos, $this->length);

									return self::PARSE_SUCCESS_CONT;
								}
							}
					}
					break;

				case self::STATE_SECOND_FROM:
					switch ($source[$p]) {
						case '/':
							if (strlen($s_from) == 0) {
								$this->state = self::STATE_SECOND_STEP;
							}
							else {
								return self::PARSE_FAIL;
							}
							break;

						case '-':
							if (!$this->validateTimeValueFrom($s_from)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_SECOND_TILL;
							break;

						case ',':
							if (!$this->validateTimeValueFrom($s_from)) {
								return self::PARSE_FAIL;
							}

							$s_from = '';
							break;

						default:
							if (is_numeric($source[$p])) {
								$s_from .= $source[$p];

								if (strlen($s_from) > 2) {
									if ($this->validateTimeValueFrom(substr($s_from, 0, -1))) {
										$this->length = $p - $pos;
										$this->match = substr($source, $pos, $this->length);

										return self::PARSE_SUCCESS_CONT;
									}
									else {
										return self::PARSE_FAIL;
									}
								}
							}
							else {
								if (strlen($s_from) <= 2 && !$this->validateTimeValueFrom($s_from)) {
									return self::PARSE_FAIL;
								}
								else {
									$this->length = $p - $pos;
									$this->match = substr($source, $pos, $this->length);

									return self::PARSE_SUCCESS_CONT;
								}
							}
					}
					break;

				case self::STATE_SECOND_TILL:
					switch ($source[$p]) {
						case ',':
							if (!$this->validateTimeValueTill($s_from, $s_till)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_SECOND_FROM;

							$s_from = '';
							$s_till = '';
							break;

						case '/':
							if (!$this->validateTimeValueTill($s_from, $s_till)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_SECOND_STEP;
							break;

						default:
							if (is_numeric($source[$p])) {
								$s_till .= $source[$p];

								if (strlen($s_till) > 2) {
									if ($this->validateTimeValueTill($s_from, substr($s_till, 0, -1))) {
										$this->length = $p - $pos;
										$this->match = substr($source, $pos, $this->length);

										return self::PARSE_SUCCESS_CONT;
									}
									else {
										return self::PARSE_FAIL;
									}
								}
							}
							else {
								if (strlen($s_till) <= 2 && !$this->validateTimeValueTill($s_from, $s_till)) {
									return self::PARSE_FAIL;
								}
								else {
									$this->length = $p - $pos;
									$this->match = substr($source, $pos, $this->length);

									return self::PARSE_SUCCESS_CONT;
								}
							}
					}
					break;

				case self::STATE_SECOND_STEP:
					switch ($source[$p]) {
						case ',':
							if (!$this->validateTimeValueStep($s_from, $s_till, $s_step)) {
								return self::PARSE_FAIL;
							}

							$this->state = self::STATE_SECOND_FROM;

							$s_from = '';
							$s_till = '';
							$s_step = '';
							break;

						default:
							if (is_numeric($source[$p])) {
								$s_step .= $source[$p];

								if (strlen($s_step) > 2) {
									if ($this->validateTimeValueStep($s_from, $s_till, substr($s_step, 0, -1))) {
										$this->length = $p - $pos;
										$this->match = substr($source, $pos, $this->length);

										return self::PARSE_SUCCESS_CONT;
									}
									else {
										return self::PARSE_FAIL;
									}
								}
							}
							else {
								if (strlen($s_step) <= 2
										&& !$this->validateTimeValueStep($s_from, $s_till, $s_step)) {
									return self::PARSE_FAIL;
								}
								else {
									$this->length = $p - $pos;
									$this->match = substr($source, $pos, $this->length);

									return self::PARSE_SUCCESS_CONT;
								}
							}
					}
					break;
			}

			$p++;
		}

		// Source string can end at any state. Validate the last entered character(s) depeding on the last state.
		switch ($this->state) {
			case self::STATE_MONTH_FROM:
				if (!$this->validateTimeValueFrom($md_from)) {
					return self::PARSE_FAIL;
				}
				break;

			case self::STATE_MONTH_TILL:
				if (!$this->validateTimeValueTill($md_from, $md_till)) {
					return self::PARSE_FAIL;
				}
				break;

			case self::STATE_MONTH_STEP:
				if (!$this->validateTimeValueStep($md_from, $md_till, $md_step)) {
					return self::PARSE_FAIL;
				}
				break;

			case self::STATE_WEEK_FROM:
				if (!$this->validateTimeValueFrom($wd_from)) {
					return self::PARSE_FAIL;
				}
				break;

			case self::STATE_WEEK_TILL:
				if (!$this->validateTimeValueTill($wd_from, $wd_till)) {
					return self::PARSE_FAIL;
				}
				break;

			case self::STATE_WEEK_STEP:
				if (!$this->validateTimeValueStep($wd_from, $wd_till, $wd_step)) {
					return self::PARSE_FAIL;
				}
				break;

			case self::STATE_HOUR_FROM:
				if (!$this->validateTimeValueFrom($h_from)) {
					return self::PARSE_FAIL;
				}
				break;

			case self::STATE_HOUR_TILL:
				if (!$this->validateTimeValueTill($h_from, $h_till)) {
					return self::PARSE_FAIL;
				}
				break;

			case self::STATE_HOUR_STEP:
				if (!$this->validateTimeValueStep($h_from, $h_till, $h_step)) {
					return self::PARSE_FAIL;
				}
				break;

			case self::STATE_MINUTE_FROM:
				if (!$this->validateTimeValueFrom($m_from)) {
					return self::PARSE_FAIL;
				}
				break;

			case self::STATE_MINUTE_TILL:
				if (!$this->validateTimeValueTill($m_from, $m_till)) {
					return self::PARSE_FAIL;
				}
				break;

			case self::STATE_MINUTE_STEP:
				if (!$this->validateTimeValueStep($m_from, $m_till, $m_step)) {
					return self::PARSE_FAIL;
				}
				break;

			case self::STATE_SECOND_FROM:
				if (!$this->validateTimeValueFrom($s_from)) {
					return self::PARSE_FAIL;
				}
				break;

			case self::STATE_SECOND_TILL:
				if (!$this->validateTimeValueTill($s_from, $s_till)) {
					return self::PARSE_FAIL;
				}
				break;

			case self::STATE_SECOND_STEP:
				if (!$this->validateTimeValueStep($s_from, $s_till, $s_step)) {
					return self::PARSE_FAIL;
				}
				break;

			default:
				return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}

	/**
	 * Return minimum value depending on state.
	 *
	 * @return mixed
	 */
	private function getMinByState() {
		switch ($this->state) {
			case self::STATE_MONTH_FROM:
			case self::STATE_MONTH_TILL:
			case self::STATE_MONTH_STEP:
			case self::STATE_WEEK_FROM:
			case self::STATE_WEEK_TILL:
			case self::STATE_WEEK_STEP:
			case self::STATE_HOUR_STEP:
			case self::STATE_MINUTE_STEP:
			case self::STATE_SECOND_STEP:
				return 1;

			case self::STATE_HOUR_FROM:
			case self::STATE_HOUR_TILL:
			case self::STATE_MINUTE_FROM:
			case self::STATE_MINUTE_TILL:
			case self::STATE_SECOND_FROM:
			case self::STATE_SECOND_TILL:
				return 0;
				break;

			default:
				return false;
		}
	}

	/**
	 * Return maximum value depending on state.
	 *
	 * @return mixed
	 */
	private function getMaxByState() {
		switch ($this->state) {
			case self::STATE_MONTH_FROM:
			case self::STATE_MONTH_TILL:
				return 31;

			case self::STATE_MONTH_STEP:
				return 30;

			case self::STATE_WEEK_FROM:
			case self::STATE_WEEK_TILL:
				return 7;

			case self::STATE_WEEK_STEP:
				return 6;

			case self::STATE_HOUR_FROM:
			case self::STATE_HOUR_TILL:
			case self::STATE_HOUR_STEP:
				return 23;
				break;

			case self::STATE_MINUTE_FROM:
			case self::STATE_MINUTE_TILL:
			case self::STATE_MINUTE_STEP:
			case self::STATE_SECOND_FROM:
			case self::STATE_SECOND_TILL:
			case self::STATE_SECOND_STEP:
				return 59;
				break;

			default:
				return false;
		}
	}

	/**
	 * Validate time value "from" parameter depeding on current state.
	 *
	 * @param string $from		Time value "from".
	 * @param string $till		Time value "till".
	 *
	 * @return bool
	 */
	private function validateTimeValueFrom($from) {
		if (!strlen($from) || $from < self::getMinByState() || $from > self::getMaxByState()) {
			return false;
		}

		return true;
	}

	/**
	 * Validate time value "till" parameter depeding on current state.
	 *
	 * @param string $from		Time value "from".
	 * @param string $till		Time value "till".
	 *
	 * @return bool
	 */
	private function validateTimeValueTill($from, $till) {
		if (!strlen($till) || $till < self::getMinByState() || $till > self::getMaxByState() || $from > $till) {
			return false;
		}

		return true;
	}

	/**
	 * Validate time value "step" parameter depeding on current state.
	 *
	 * @param string $from		Time value "from".
	 * @param string $till		Time value "till".
	 * @param string $step		Time value "step".
	 *
	 * @return bool
	 */
	private function validateTimeValueStep($from, $till, $step) {
		if (!strlen($step) || $step < self::getMinByState() || $step > self::getMaxByState()
				|| (strlen($till) && $step > ($till - $from))
				|| (strlen($till) && $from == $till && $step != 1)) {
			return false;
		}

		return true;
	}
}
