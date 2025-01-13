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


class CMacroValue extends CDiv {

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

	protected int $type;
	protected string $name;
	protected ?string $value;
	protected bool $readonly = false;
	protected ?string $placeholder = null;

	/**
	 * Add element initialization javascript.
	 *
	 * @var bool
	 */
	protected $add_post_js = true;

	/**
	 * ID for HTML element, where the validation error will be displayed.
	 *
	 * @var string|null
	 */
	protected ?string $error_container_id = null;

	/**
	 * ID for HTML element, where the validation error will be displayed.
	 *
	 * @var string|null
	 */
	protected ?string $error_label = null;

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
	 * @param int         $type         Macro type one of ZBX_MACRO_TYPE_SECRET or ZBX_MACRO_TYPE_TEXT value.
	 * @param string      $name         Macro input name.
	 * @param string|null $value        Macro value, null when value will not be set.
	 * @param bool        $add_post_js  Add element initialization javascript.
	 */
	public function __construct(int $type, string $name, ?string $value = null, bool $add_post_js = true) {
		parent::__construct();
		$this->type = $type;
		$this->name = $name;
		$this->value = $value;

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
	 * Set ID for HTML element, where the validation error will be displayed.
	 *
	 * @param string|null $container_id
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
	 * @param bool $value
	 *
	 * @return self
	 */
	public function setReadonly(bool $value = true): self {
		$this->readonly = $value;

		return $this;
	}

	/**
	 * @param bool $value
	 *
	 * @return self
	 */
	public function setPlaceholder(?string $value = null): self {
		$this->placeholder = $value;

		return $this;
	}

	/**
	 * Allow to revert macro value.
	 */
	public function addRevertButton() {
		$this->revert_button = (new CSimpleButton())
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass(ZBX_ICON_ARROW_BACK)
			->addClass(self::ZBX_STYLE_BTN_UNDO)
			->setAttribute('title', _('Revert changes'));

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
		$elements = [];

		if ($this->type == ZBX_MACRO_TYPE_TEXT) {
			$wrapper_class = self::ZBX_STYLE_MACRO_INPUT_GROUP.' '.self::ZBX_STYLE_MACRO_VALUE_TEXT;
			$dropdown_btn_class = ZBX_ICON_TEXT;

			$elements[] = (new CTextAreaFlexible($this->name.'[value]', $this->value, [
				'add_post_js' => $this->add_post_js
			]))
				->setErrorContainer($this->error_container_id)
				->setErrorLabel($this->error_label)
				->setMaxlength($this->maxlength)
				->setAttribute('placeholder', _('value'))
				->disableSpellcheck()
				->setReadonly($this->readonly)
				->setAttribute('data-skip-from-submit', $this->getAttribute('data-skip-from-submit'));
		}
		elseif ($this->type == ZBX_MACRO_TYPE_VAULT) {
			$wrapper_class = self::ZBX_STYLE_MACRO_INPUT_GROUP.' '.self::ZBX_STYLE_MACRO_VALUE_VAULT;
			$dropdown_btn_class = ZBX_ICON_LOCK;

			$elements[] = (new CTextAreaFlexible($this->name.'[value]', $this->value, [
				'add_post_js' => $this->add_post_js
			]))
				->setErrorContainer($this->error_container_id)
				->setErrorLabel($this->error_label)
				->setMaxlength($this->maxlength)
				->setAttribute('placeholder', _('value'))
				->disableSpellcheck()
				->setReadonly($this->readonly)
				->setAttribute('data-skip-from-submit', $this->getAttribute('data-skip-from-submit'));
		}
		else {
			$wrapper_class = self::ZBX_STYLE_MACRO_INPUT_GROUP.' '.self::ZBX_STYLE_MACRO_VALUE_SECRET;
			$dropdown_btn_class = ZBX_ICON_EYE_OFF;

			$elements[] = (new CInputSecret($this->name.'[value]', $this->value, $this->add_post_js))
				->setErrorContainer($this->error_container_id)
				->setErrorLabel($this->error_label)
				->setMaxlength($this->maxlength)
				->setDisabled($this->readonly)
				->setPlaceholder(_('value'))
				->setAttribute('data-skip-from-submit', $this->getAttribute('data-skip-from-submit'));
		}

		if ($this->revert_button !== null) {
			$elements[] = $this->revert_button->addStyle($this->revert_visible ? 'display: block' : '');
		}

		$elements[] = (new CButtonDropdown($this->name.'[type]',  $this->type, [
			['label' => _('Text'), 'value' => ZBX_MACRO_TYPE_TEXT, 'class' => ZBX_ICON_TEXT],
			['label' => _('Secret text'), 'value' => ZBX_MACRO_TYPE_SECRET, 'class' => ZBX_ICON_EYE_OFF],
			['label' => _('Vault secret'), 'value' => ZBX_MACRO_TYPE_VAULT, 'class' => ZBX_ICON_LOCK]
		]))
			->addClass($dropdown_btn_class)
			->setAttribute('disabled', $this->readonly ? 'disabled' : null)
			->setAttribute('aria-label', _('Change type'))
			->setAttribute('data-skip-from-submit', $this->getAttribute('data-skip-from-submit'));

		$this->removeAttribute('data-skip-from-submit');

		$this
			->addClass($wrapper_class)
			->addItem($elements);

		if ($this->add_post_js) {
			zbx_add_post_js($this->getPostJS());
		}

		return parent::toString($destroy);
	}
}
