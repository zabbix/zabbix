<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CPeriodTimeRangeValidator extends CValidator {

	private CPeriodTimeParser $period_time_parser;

	public function __construct(array $options = [])
	{
		parent::__construct($options);
		$this->period_time_parser = new CPeriodTimeParser();
	}

	public function validate($value) {
		$result = [];
		$value = trim($value);

		foreach (explode(',', $value) as $schedule_period) {
			if ($this->period_time_parser->parse($schedule_period) == $this->period_time_parser::PARSE_FAIL) {
				$this->setError(_('comma separated list of time periods is expected'));

				return false;
			}
			$matches = $this->period_time_parser->getMatches();

			$from_m = $matches['from_m'];
			$to_m = $matches['to_m'];

			$day_period_from = $this->period_time_parser->getDayPeriodFrom();
			$day_period_to = $this->period_time_parser->getDayPeriodTo();

			if ($from_m > 59 || $to_m > 59 || $day_period_to > SEC_PER_DAY) {
				$this->setError(_('comma separated list of time periods is expected'));

				return false;
			}

			if ($day_period_from >= $day_period_to) {
				$this->setError(_('start time must be less than end time'));

				return false;
			}

			// Validate period uniqueness.
			$result[] = [
				'period_from' => SEC_PER_DAY + $day_period_from,
				'period_to' => SEC_PER_DAY + $day_period_to
			];

			if (count($result) !== count(array_unique(array_map('json_encode', $result)))) {
				$this->setError(_('periods must be unique'));

				return false;
			}
		}

		return true;
	}
}
