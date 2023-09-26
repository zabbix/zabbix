<?php
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


class CLabel extends CTag {

	private bool $has_asterisk = false;

	public function __construct($label, $id = null) {
		parent::__construct('label', true, $label);

		$this->setFor($id);
	}

	public function setFor($id): self {
		if ($id !== null) {
			$this->setAttribute('for', zbx_formatDomId($id));
		}

		return $this;
	}

	/**
	 * Allow to add visual 'asterisk' mark to label.
	 *
	 * @param bool $has_asterisk  Define is label marked with asterisk or not.
	 *
	 * @return CLabel
	 */
	public function setAsteriskMark(bool $has_asterisk = true): self {
		$this->has_asterisk = $has_asterisk;

		return $this;
	}

	public function toString($destroy = true) {
		if ($this->has_asterisk) {
			$this->addClass(ZBX_STYLE_FIELD_LABEL_ASTERISK);
		}

		return parent::toString($destroy);
	}
}
