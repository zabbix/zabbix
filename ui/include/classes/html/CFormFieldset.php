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
