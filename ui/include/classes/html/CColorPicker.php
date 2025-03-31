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


class CColorPicker extends CTag {

	public function __construct() {
		parent::__construct('z-color-picker', true);
	}

	/**
	 * @param string $value
	 *
	 * @return self
	 */
	public function setValue(string $value): self {
		$this->setAttribute('value', $value);

		return $this;
	}

	/**
	 * @param string $input_id
	 *
	 * @return self
	 */
	public function setInputId(string $input_id): self {
		$this->setAttribute('input-id', $input_id);

		return $this;
	}

	/**
	 * @param bool $has_default
	 *
	 * @return self
	 */
	public function setHasDefault(bool $has_default = true): self {
		if ($has_default) {
			$this->setAttribute('has-default', '');
		}
		else {
			$this->removeAttribute('has-default');
		}

		return $this;
	}

	/**
	 * @param bool $has_palette
	 *
	 * @return self
	 */
	public function setHasPalette(bool $has_palette = true): self {
		if ($has_palette) {
			$this->setAttribute('has-palette', '');
		}
		else {
			$this->removeAttribute('has-palette');
		}

		return $this;
	}

	/**
	 * @param bool $disabled
	 *
	 * @return self
	 */
	public function setDisabled(bool $disabled = true): self {
		if ($disabled) {
			$this->setAttribute('disabled', '');
		}
		else {
			$this->removeAttribute('disabled');
		}

		return $this;
	}
}
