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


class CTextArea extends CTag {

	/**
	 * Init textarea.
	 *
	 * @param string   $name
	 * @param string   $value
	 * @param array    $options
	 *        int      $options['rows']
	 *        int      $options['maxlength']
	 *        boolean  $options['readonly']
	 */
	public function __construct(string $name = 'textarea', $value = '', array $options = []) {
		parent::__construct('textarea', true);

		$this
			->setId(zbx_formatDomId($name))
			->setName($name)
			->setValue($value)
			->setRows(array_key_exists('rows', $options) ? $options['rows'] : ZBX_TEXTAREA_STANDARD_ROWS);

		if (array_key_exists('readonly', $options)) {
			$this->setReadonly($options['readonly']);
		}

		if (array_key_exists('maxlength', $options)) {
			$this->setMaxlength($options['maxlength']);
		}
	}

	public function setReadonly(bool $is_readonly = true): self {
		if ($is_readonly) {
			$this->setAttribute('readonly', 'readonly');
		}
		else {
			$this->removeAttribute('readonly');
		}

		return $this;
	}

	public function setValue($value = ''): self {
		$this->addItem($value);

		return $this;
	}

	public function setRows(int $rows): self {
		$this->setAttribute('rows', $rows);

		return $this;
	}

	public function setMaxlength(int $maxlength): self {
		$this->setAttribute('maxlength', $maxlength);

		return $this;
	}

	public function disableSpellcheck(): self {
		$this->setAttribute('spellcheck', 'false');

		return $this;
	}

	public function setWidth(int $width): self {
		$this->addStyle('width: '.$width.'px;');

		return $this;
	}

	public function setAdaptiveWidth(int $width): self {
		$this->addStyle('max-width: '.$width.'px;');
		$this->addStyle('width: 100%;');

		return $this;
	}

	public function setEnabled(bool $is_enabled = true): self {
		if ($is_enabled) {
			$this->removeAttribute('disabled');
		}
		else {
			$this->setAttribute('disabled', 'disabled');
		}

		return $this;
	}
}
