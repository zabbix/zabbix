<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CRelativeTimeValidator extends CValidator {

	protected ?array $allowed_suffixes = null;
	protected ?array $allowed_types = null;
	protected bool $max_now = false;
	protected ?int $max_tokens = null;
	protected bool $usermacros = false;

	/**
	 * Checks if the given string is:
	 * - valid relative time with CRelativeTimeParser,
	 * - only uses provided suffixes
	 * - matches provided type
	 * - in limit of allowed time units
	 * - can limit to only allow values in the past
	 *
	 * @param string $value
	 */
	public function validate($value): bool {
		$relative_time_parser = new CRelativeTimeParser(['usermacros' => $this->usermacros]);

		if ($relative_time_parser->parse($value) != CParser::PARSE_SUCCESS) {
			$this->setError(_('a relative time is expected'));

			return false;
		}

		$tokens = $relative_time_parser->getTokens();

		if ($this->max_tokens !== null && count($tokens) > $this->max_tokens) {
			$this->setError(
				_n('only one time unit is allowed', 'only %1$s time units are allowed', $this->max_tokens)
			);

			return false;
		}

		foreach ($tokens as $token) {
			if ($this->allowed_types !== null && !in_array($token['type'], $this->allowed_types)) {
				$this->setError(_('a relative time is expected'));

				return false;
			}

			if ($this->allowed_suffixes !== null && !in_array($token['suffix'], $this->allowed_suffixes)) {
				$this->setError(_('unsupported time suffix'));

				return false;
			}
		}

		if ($this->max_now) {
			$current_time = time();
			$timestamp = $relative_time_parser->getDateTime(true)->getTimestamp();

			if ($timestamp > $current_time) {
				$this->setError(_('should be less than or equal to current time'));

				return false;
			}
		}

		return true;
	}
}
