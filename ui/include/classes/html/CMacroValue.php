<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CMacroValue extends CInput {

	/**
	 * Container class.
	 */
	public const ZBX_STYLE_MACRO_INPUT_GROUP = 'macro-input-group';
	public const ZBX_STYLE_MACRO_VALUE_TEXT = 'macro-value-text';
	public const ZBX_STYLE_MACRO_VALUE_SECRET = 'macro-value-secret';
	public const ZBX_STYLE_MACRO_VALUE_VAULT = 'macro-value-vault';

	/**
	 * Button class for undo.
	 */
	public const ZBX_STYLE_BTN_UNDO = 'btn-undo';

	/**
	 * Add element initialization javascript.
	 *
	 * @var bool
	 */
	protected $add_post_js = true;

	/**
	 * Revert button visibility.
	 *
	 * @var bool
	 */
	protected $revert_visible = true;

	/**
	 * Revert button element.
	 *
	 * @var CTag
	 */
	protected $revert_button = null;

	/**
	 * Maxlength of macro value input field.
	 *
	 * @var int
	 */
	protected $maxlength = 2048;

	/**
	 * Class constructor.
	 *
	 * @param int    $type         Macro type one of ZBX_MACRO_TYPE_SECRET or ZBX_MACRO_TYPE_TEXT value.
	 * @param string $name         Macro input name.
	 * @param string $value        Macro value, null when value will not be set.
	 * @param bool   $add_post_js  Add element initialization javascript.
	 */
	public function __construct(int $type, string $name, string $value = null, bool $add_post_js = true) {
		parent::__construct($type, $name, $value);

		$this->add_post_js = $add_post_js;
		$this->setId(uniqid('macro-value-'));
	}

	/**
	 * Get content of all Javascript code.
	 *
	 * @return string  Javascript code.
	 */
	public function getPostJS(): string {
		return 'jQuery("#'.$this->getId().'").macroValue();';
	}

	/**
	 * Allow to revert macro value.
	 */
	public function addRevertButton() {
		$this->revert_button = (new CButton(null))
			->setAttribute('title', _('Revert changes'))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass(self::ZBX_STYLE_BTN_UNDO);

		return $this;
	}

	/**
	 * Set revert macro value button visibility.
	 *
	 * @param bool $visible  Button visibility state.
	 */
	public function setRevertButtonVisibility(bool $visible) {
		$this->revert_visible = $visible;

		return $this;
	}

	/**
	 * Render object.
	 *
	 * @param boolean $destroy
	 *
	 * @return string
	 */
	public function toString($destroy = true) {
		$name = $this->getAttribute('name');
		$value_type = $this->getAttribute('type');
		$value = $this->getAttribute('value');
		$readonly = $this->getAttribute('readonly');
		$elements = [];

		if ($value_type == ZBX_MACRO_TYPE_TEXT) {
			$wrapper_class = self::ZBX_STYLE_MACRO_INPUT_GROUP.' '.self::ZBX_STYLE_MACRO_VALUE_TEXT;
			$dropdown_btn_class = ZBX_STYLE_ICON_TEXT;
			$elements[] = (new CTextAreaFlexible($name.'[value]', $value, ['add_post_js' => $this->add_post_js]))
				->setMaxlength($this->maxlength)
				->setAttribute('placeholder', _('value'))
				->setReadonly($readonly);
		}
		elseif ($value_type == ZBX_MACRO_TYPE_VAULT) {
			$wrapper_class = self::ZBX_STYLE_MACRO_INPUT_GROUP.' '.self::ZBX_STYLE_MACRO_VALUE_VAULT;
			$dropdown_btn_class = ZBX_STYLE_ICON_SECRET_TEXT;
			$elements[] = (new CTextAreaFlexible($name.'[value]', $value, ['add_post_js' => $this->add_post_js]))
				->setMaxlength($this->maxlength)
				->setAttribute('placeholder', _('path/to/secret:key'))
				->setReadonly($readonly);
		}
		else {
			$wrapper_class = self::ZBX_STYLE_MACRO_INPUT_GROUP.' '.self::ZBX_STYLE_MACRO_VALUE_SECRET;
			$dropdown_btn_class = ZBX_STYLE_ICON_INVISIBLE;
			$elements[] = (new CInputSecret($name.'[value]', $value, $this->add_post_js))
				->setAttribute('maxlength', $this->maxlength)
				->setAttribute('disabled', ($readonly !== null) ? 'disabled' : null)
				->setAttribute('placeholder', _('value'));
		}

		if ($this->revert_button !== null) {
			$elements[] = $this->revert_button->addStyle($this->revert_visible ? 'display: block' : '');
		}

		$elements[] = (new CButtonDropdown($name.'[type]',  $value_type, [
				['label' => _('Text'), 'value' => ZBX_MACRO_TYPE_TEXT, 'class' => ZBX_STYLE_ICON_TEXT],
				['label' => _('Secret text'), 'value' => ZBX_MACRO_TYPE_SECRET, 'class' => ZBX_STYLE_ICON_INVISIBLE],
				['label' => _('Vault secret'), 'value' => ZBX_MACRO_TYPE_VAULT, 'class' => ZBX_STYLE_ICON_SECRET_TEXT]
			]))
				->addClass($dropdown_btn_class)
				->setAttribute('disabled', ($readonly !== null) ? 'disabled' : null)
				->setAttribute('aria-label', _('Change type'));

		$node = (new CDiv())
			->addClass($wrapper_class)
			->addItem($elements);

		if ($this->add_post_js) {
			zbx_add_post_js($this->getPostJS());
		}

		return $node->toString(true);
	}
}
