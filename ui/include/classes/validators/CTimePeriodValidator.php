<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class CTimePeriodValidator {

	const ERROR_TYPE_EMPTY = 0;
	const ERROR_TYPE_INVALID_DATE_TIME = 1;
	const ERROR_TYPE_INVALID_DATE = 2;
	const ERROR_TYPE_INVALID_RANGE = 3;
	const ERROR_TYPE_INVALID_MIN_PERIOD = 4;
	const ERROR_TYPE_INVALID_MAX_PERIOD = 5;

	/**
	 * Validate time period.
	 *
	 * @param array  $time_period
	 *        string $time_period['from']     Absolute or relative start date time.
	 *        int    $time_period['from_ts']  Calculated. Timestamp of the start date time.
	 *        string $time_period['to']       Absolute or relative end date time.
	 *        int    $time_period['to_ts']    Calculated. Timestamp of the ending date time.
	 *
	 * @param array        $options                       Supported options.
	 *        DateTimeZone $options['timezone']           Date time zone to use for timestamp calculation.
	 *        bool         $options['require_date_only']  Whether custom time is accepted.
	 *        bool         $options['require_not_empty']  Whether both From and To fields must not be empty.
	 *        int          $options['min_period']         Minimal allowed time period.
	 *        int          $options['max_period']         Maximal allowed time period.
	 *        string       $options['from_label']         "From" field label to use for error reporting.
	 *        string       $options['to_label']           "To" field label to use for error reporting.
	 *
	 * @return array  Errors.
	 */
	public static function validate(array &$time_period, array $options = []): array {
		$fields_typed_errors = self::validateRaw($time_period, $options);

		if (!$fields_typed_errors) {
			return [];
		}

		$options += [
			'from_label' => _('From'),
			'to_label' => _('To')
		];

		$labels = [
			'from' => $options['from_label'],
			'to' => $options['to_label']
		];

		$errors = [];

		foreach ($fields_typed_errors as $field => $typed_error) {
			switch ($typed_error) {
				case self::ERROR_TYPE_EMPTY:
					$errors[] = _s('Invalid parameter "%1$s": %2$s.', $labels[$field], _('cannot be empty'));
					break;

				case self::ERROR_TYPE_INVALID_DATE_TIME:
					$errors[] = _s('Invalid parameter "%1$s": %2$s.', $labels[$field], _('a time is expected'));
					break;

				case self::ERROR_TYPE_INVALID_DATE:
					$errors[] = _s('Invalid parameter "%1$s": %2$s.', $labels[$field], _('a date is expected'));
					break;

				case self::ERROR_TYPE_INVALID_RANGE:
					$errors[] = _s('Invalid parameter "%1$s": %2$s.', $labels['to'],
						_s('value must be greater than "%1$s"', $labels['from'])
					);
					break;

				case self::ERROR_TYPE_INVALID_MIN_PERIOD:
					$errors[] = _n('Minimum time period to display is %1$s minute.',
						'Minimum time period to display is %1$s minutes.', (int) ($options['min_period'] / SEC_PER_MIN)
					);
					break;

				case self::ERROR_TYPE_INVALID_MAX_PERIOD:
					$errors[] = _n('Maximum time period to display is %1$s day.',
						'Maximum time period to display is %1$s days.',
						(int) round($options['max_period'] / SEC_PER_DAY)
					);
					break;
			}
		}

		return $errors;
	}

	/**
	 * Raw validate time period.
	 *
	 * This method allows to access error codes and build custom error messages for each field.
	 *
	 * @see validate
	 *
	 * @return array  Error codes for each field
	 */
	public static function validateRaw(array &$time_period, array $options = []): array {
		$options += [
			'timezone' => null,
			'require_date_only' => false,
			'require_not_empty' => false,
			'min_period' => null,
			'max_period' => null,
		];

		$fields_typed_errors = [];

		foreach (['from' => 'from_ts', 'to' => 'to_ts'] as $field => $field_ts) {
			if ($time_period[$field] === '') {
				if ($options['require_not_empty']) {
					$fields_typed_errors[$field] = self::ERROR_TYPE_EMPTY;
				}
				else {
					$time_period[$field_ts] = 0;
				}

				continue;
			}

			$is_valid = self::validateField($time_period[$field], $time_period[$field_ts], $field === 'from',
				$options['require_date_only'], $options['timezone']
			);

			if (!$is_valid) {
				$fields_typed_errors[$field] = $options['require_date_only']
					? self::ERROR_TYPE_INVALID_DATE
					: self::ERROR_TYPE_INVALID_DATE_TIME;
			}
		}

		if (!$fields_typed_errors) {
			$fields_typed_errors = self::validatePeriod($time_period, $options['min_period'], $options['max_period']);
		}

		return $fields_typed_errors;
	}

	private static function validateField(string $value, ?int &$value_ts, bool $is_start, bool $require_date_only,
			?DateTimeZone $timezone): bool {
		$value_ts = 0;

		$absolute_time_parser = new CAbsoluteTimeParser();
		$relative_time_parser = new CRelativeTimeParser();

		if ($absolute_time_parser->parse($value) === CParser::PARSE_SUCCESS) {
			$datetime = $absolute_time_parser->getDateTime($is_start, $timezone);

			if ($require_date_only && $datetime->format('H:i:s') !== ($is_start ? '00:00:00' : '23:59:59')) {
				return false;
			}

			$value_ts = $datetime->getTimestamp();
		}
		elseif ($relative_time_parser->parse($value) === CParser::PARSE_SUCCESS) {
			$datetime = $relative_time_parser->getDateTime($is_start, $timezone);

			if ($require_date_only) {
				foreach ($relative_time_parser->getTokens() as $token) {
					if ($token['suffix'] === 'h' || $token['suffix'] === 'm' || $token['suffix'] === 's') {
						return false;
					}
				}
			}

			$value_ts = $datetime->getTimestamp();
		}
		else {
			return false;
		}

		if ($value_ts < 0 || $value_ts > ZBX_MAX_DATE) {
			$value_ts = 0;

			return false;
		}

		return true;
	}

	private static function validatePeriod(array $time_period, ?int $min_period, ?int $max_period): array {
		if ($time_period['from'] === '' || $time_period['to'] === '') {
			return [];
		}

		if ($time_period['from_ts'] >= $time_period['to_ts']) {
			return ['from' => self::ERROR_TYPE_INVALID_RANGE];
		}

		$period = $time_period['to_ts'] - $time_period['from_ts'] + 1;

		if ($min_period !== null && $period < $min_period) {
			return ['from' => self::ERROR_TYPE_INVALID_MIN_PERIOD];
		}

		if ($max_period !== null && $period > $max_period + 1) {
			return ['from' => self::ERROR_TYPE_INVALID_MAX_PERIOD];
		}

		return [];
	}
}
