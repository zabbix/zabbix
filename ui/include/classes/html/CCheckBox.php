<?php
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


class CCheckBox extends CInput {

	/**
	 * Constant for putting label text before the checkbox.
	 */
	const LABEL_POSITION_LEFT = 0;

	/**
	 * Constant for putting label text after the checkbox.
	 */
	const LABEL_POSITION_RIGHT = 1;

	/**
	 * Checkbox label.
	 *
	 * @var string
	 */
	private $label = '';

	/**
	 * Checkbox name.
	 *
	 * @var string
	 */
	private $name = '';

	/**
	 * Checkbox value.
	 *
	 * @var string
	 */
	private $value = '';

	/**
	 * Checked or unchecked state of checkbox.
	 *
	 * @var bool
	 */
	private $checked = false;

	/**
	 * Checkbox label position (LABEL_POSITION_LEFT or LABEL_POSITION_RIGHT).
	 *
	 * @var int
	 */
	private $label_position = self::LABEL_POSITION_RIGHT;

	public function __construct($name = 'checkbox', $value = '1') {
		$this->name = $name;
		$this->value = $value;

		parent::__construct('checkbox', $this->name, $this->value);

		$this->setChecked(false);
		$this->addClass(ZBX_STYLE_CHECKBOX_RADIO);
	}

	/**
	 * Check or uncheck the checkbox.
	 *
	 * @param bool $checked
	 *
	 * @return CCheckBox
	 */
	public function setChecked($checked) {
		$this->checked = $checked;

		if ($this->checked) {
			$this->attributes['checked'] = 'checked';
		}
		else {
			$this->removeAttribute('checked');
		}

		return $this;
	}

	/**
	 * Set the label for the checkbox.
	 *
	 * @param string $label
	 *
	 * @return CCheckBox
	 */
	public function setLabel($label) {
		$this->label = $label;

		return $this;
	}

	/**
	 * Get the label for the checkbox.
	 *
	 * @return string
	 */
	public function getLabel() {
		return $this->label;
	}

	/**
	 * Set the label position for the checkbox.
	 *
	 * If $label_position is LABEL_POSITION_LEFT, then label text goes before the span that draws the checkbox:
	 *    <input ...><label ...>$label<span></span></label>
	 *
	 * If $label_position is LABEL_POSITION_RIGHT, then label text goes after the span that draws the checkbox:
	 *    <input ...><label ...><span></span>$label</label>
	 *
	 * @param int $label_position One of LABEL_POSITION_LEFT or LABEL_POSITION_RIGHT.
	 *
	 * @return CCheckBox
	 */
	public function setLabelPosition($label_position) {
		$this->label_position = $label_position;

		return $this;
	}

	/**
	 * Allow to set value for not checked checkbox.
	 *
	 * @param string $value  Value for unchecked state.
	 *
	 * @return CCheckBox
	 */
	public function setUncheckedValue($value) {
		$this->setAttribute('unchecked-value', $value);

		return $this;
	}

	public function toString($destroy = true) {
		$elements = ($this->label_position === self::LABEL_POSITION_LEFT)
			? [$this->label, new CSpan()]
			: [new CSpan(), $this->label];

		$label = (new CLabel($elements, $this->getId()))
			->addClass($this->label_position === self::LABEL_POSITION_LEFT ? 'label-pos-left' : null)
			->setTitle($this->label);

		return parent::toString($destroy).$label->toString();
	}
}
