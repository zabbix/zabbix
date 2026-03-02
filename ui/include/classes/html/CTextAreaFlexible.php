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


class CTextAreaFlexible extends CTag {
	/**
	 * Default maxlength of textarea.
	 *
	 * @var int
	 */
	protected int $default_maxlength = 255;

	/**
	 * CTextAreaFlexible constructor.
	 *
	 * @param string $name
	 * @param string $value		(optional)
	 */

	public function __construct(string $name, $value = '') {
		parent::__construct('z-textarea-flexible', true);

		$this
			->setId(zbx_formatDomId($name))
			->setName($name)
			->setAttribute('value', $value)
			->setAttribute('data-field-type', 'z-textarea-flexible')
			->setAttribute('singleline', 'singleline')
			->setAttribute('maxlength', $this->default_maxlength);
	}

	public function setSingleline(bool $is_singleline = true): self {
		if ($is_singleline) {
			$this->setAttribute('singleline', 'singleline');
		}
		else {
			$this->removeAttribute('singleline');
		}

		return $this;
	}

	public function setAutofocus(bool $is_autofocus = true): self {
		if ($is_autofocus) {
			$this->setAttribute('autofocus', 'autofocus');
		}
		else {
			$this->removeAttribute('autofocus');
		}

		return $this;
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

	/**
	 * Specify ID of error container.
	 *
	 * @param string|null $container_id    ID of form element where to display field errors.
	 *
	 * @return self
	 */
	public function setErrorContainer(?string $container_id): self {
		$this->setAttribute('data-error-container', $container_id);

		return $this;
	}

	/**
	 * Specify the field label used for error message.
	 *
	 * @param string|null $label    Field label used in error message.
	 *
	 * @return self
	 */
	public function setErrorLabel(?string $label): self {
		$this->setAttribute('data-error-label', $label);

		return $this;
	}
}
