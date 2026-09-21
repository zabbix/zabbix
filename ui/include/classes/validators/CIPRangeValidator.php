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


class CIPRangeValidator extends CValidator {

	/**
	 * Support for IPv6 addresses.
	 */
	protected bool $v6 = false;

	/**
	 * Support for DNS names.
	 */
	protected bool $dns = false;

	/**
	 * Maximum value for IPv4 CIDR subnet mask notations.
	 */
	protected int $max_ipv4_cidr = 30;

	/**
	 * Maximum allowed IP count inside the range.
	 *
	 * Null means no maximum check.
	 */
	protected ?int $max = null;

	public function validate($value): bool {
		$range_parser = new CIPRangeParser(['v6' => $this->v6, 'dns' => $this->dns,
			'max_ipv4_cidr' => $this->max_ipv4_cidr
		]);

		if ($range_parser->parse($value) != CParser::PARSE_SUCCESS) {
			$this->setError($range_parser->getError());

			return false;
		}

		if ($this->max !== null && bccomp($range_parser->getMaxIPCount(), (string)$this->max) > 0) {
			$this->setError(_s('IP range "%1$s" exceeds "%2$s" address limit',
				$range_parser->getMaxIPRange(),
				$this->max
			));

			return false;
		}

		return true;
	}
}
