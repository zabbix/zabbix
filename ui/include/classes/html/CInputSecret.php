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


class CInputSecret extends CInput {

	/**
	 * Default CSS class name for HTML root element.
	 */
	public const ZBX_STYLE_CLASS = 'input-secret';

	/**
	 * Style for change value button.
	 */
	public const ZBX_STYLE_BTN_CHANGE = 'btn-change';

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
	 * @param string $value        Input element value attribute.
	 * @param bool   $add_post_js  Add initialization javascript, default true.
	 */
	public function __construct(string $name, ?string $value = null, $add_post_js = true) {
		parent::__construct('text', $name, $value);

		$this->add_post_js = $add_post_js;
		$this->setAttribute('name', $name);
		$this->setId(uniqid('input-secret-'));

		if ($value !== null) {
			$this->setAttribute('value', $value);
		}
	}

	/**
	 * Get content of all Javascript code.
	 *
	 * @return string  Javascript code.
	 */
	public function getPostJS(): string {
		return 'jQuery("#'.$this->getId().'").inputSecret();';
	}

	public function toString($destroy = true) {
		$node = (new CDiv())
			->setId($this->getId())
			->addClass(self::ZBX_STYLE_CLASS);
		$name = $this->getAttribute('name');
		$value = $this->getAttribute('value');
		$maxlength = ($this->getAttribute('maxlength') === null) ? 255 : $this->getAttribute('maxlength');

		if ($value === null) {
			$node->addItem([
				(new CPassBox($name, ZBX_SECRET_MASK, $maxlength))->setAttribute('disabled', 'disabled'),
				(new CSimpleButton(_('Set new value')))
					->setId(zbx_formatDomId($name.'[btn]'))
					->addClass(self::ZBX_STYLE_BTN_CHANGE)
					->setAttribute('disabled', $this->getAttribute('disabled'))
			]);
		}
		else {
			$pass_box = new CPassBox($name, $value, $maxlength);

			if ($this->getAttribute('placeholder')) {
				$pass_box->setAttribute('placeholder', $this->getAttribute('placeholder'));
			}

			$node->addItem($pass_box);
		}

		if ($this->add_post_js) {
			zbx_add_post_js($this->getPostJS());
		}

		return $node->toString(true);
	}
}
