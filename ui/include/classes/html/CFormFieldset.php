<?php declare(strict_types = 0);
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


class CFormFieldset extends CTag {

	protected ?string $caption;

	public function __construct(string $caption = null, $body = null) {
		parent::__construct('fieldset', true, $body);

		$this->caption = $caption;
	}

	protected function makeLegend(): string {
		return $this->caption !== null
			? (new CTag('legend', true, new CSpan($this->caption)))->toString()
			: '';
	}

	protected function bodyToString(): string {
		return $this->makeLegend().parent::bodyToString();
	}
}
