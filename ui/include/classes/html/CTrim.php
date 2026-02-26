<?php declare(strict_types = 0);
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


class CTrim extends CSpan {

	public function __construct(string $string, int $limit) {
		$is_trimmed = mb_strlen($string) > $limit;

		if ($is_trimmed) {
			$string = mb_substr($string, 0, $limit);
		}

		parent::__construct($string);

		if ($is_trimmed) {
			$this->addClass(ZBX_STYLE_TRIMMED_CONTENT);
		}
	}
}
