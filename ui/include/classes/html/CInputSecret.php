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


class CInputSecret extends CDiv {

	/**
	 * Default CSS class name for HTML root element.
	 */
	public const ZBX_STYLE_CLASS = 'input-secret';

	/**
	 * Style for change value button.
	 */
	public const ZBX_STYLE_BTN_CHANGE = 'btn-change';

	protected string $name;
	protected ?string $value;
	protected bool $disabled = false;
	protected ?string $placeholder = null;
	protected ?int $maxlength = null;
	protected ?string $error_container_id = null;
	protected ?string $error_label = null;

	/**
	 * Add initialization javascript code.
	 *
	 * @var bool
	 */
	protected $add_post_js;

	/**
	 * CInputSecret constructor.
	 *
	 * @param string $name         Input element name attribute.
	 * @param string|null $value   Input element value attribute.
	 * @param bool   $add_post_js  Add initialization javascript, default true.
	 */
	public function __construct(string $name, ?string $value = null, $add_post_js = true) {
		parent::__construct();
		$this->setId(uniqid('input-secret-'));
		$this->addClass(self::ZBX_STYLE_CLASS);

		$this->add_post_js = $add_post_js;
		$this->name = $name;
		$this->value = $value;
	}

	/**
	 * @param bool $value
	 *
	 * @return self
	 */
	public function setDisabled(bool $value = true): self {
		$this->disabled = $value;

		return $this;
	}

	/**
	 * @param string|null $value
	 *
	 * @return self
	 */
	public function setPlaceholder(?string $value = null): self {
		$this->placeholder = $value;

		return $this;
	}

	/**
	 * @param int|null $value
	 *
	 * @return self
	 */
	public function setMaxlength(?string $value = null): self {
		$this->maxlength = $value;

		return $this;
	}

	/**
	 * @param string|null $value
	 *
	 * @return self
	 */
	public function setErrorContainer(?string $container_id): self {
		$this->error_container_id = $container_id;

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
		$this->error_label = $label;

		return $this;
	}

	/**
	 * Get content of all Javascript code.
	 *
	 * @return string  Javascript code.
	 */
	public function getPostJS(): string {
		return 'jQuery("#'.$this->getId().'").inputSecret();';
	}

	public function toString($destroy = true): string {
		$maxlength = $this->maxlength ?? 255;

		$skip_from_submit = $this->getAttribute('data-skip-from-submit');
		$this->removeAttribute('data-skip-from-submit');

		if ($this->value === null) {
			$this->addItem([
				(new CPassBox($this->name, ZBX_SECRET_MASK, $maxlength))
					->setErrorContainer($this->error_container_id)
					->setAttribute('disabled', 'disabled')
					->setErrorLabel($this->error_label)
					->setAttribute('data-skip-from-submit', $skip_from_submit),
				(new CSimpleButton(_('Set new value')))
					->setId(zbx_formatDomId($this->name.'[btn]'))
					->addClass(self::ZBX_STYLE_BTN_CHANGE)
					->setAttribute('disabled', $this->disabled ? 'disabled' : null)
			]);
		}
		else {
			$this->addItem(
				(new CPassBox($this->name, $this->value, $maxlength))
					->setErrorContainer($this->error_container_id)
					->setAttribute('placeholder', $this->placeholder)
					->setAttribute('data-skip-from-submit', $skip_from_submit)
			);
		}

		if ($this->add_post_js) {
			zbx_add_post_js($this->getPostJS());
		}

		return parent::toString($destroy);
	}
}
