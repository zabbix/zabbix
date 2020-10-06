<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CButtonDropdown extends CButton {

	/**
	 * Default CSS class name for HTML root element.
	 */
	public const ZBX_STYLE_CLASS = 'btn-dropdown-container';

	/**
	 * Button style names.
	 */
	public const ZBX_STYLE_BTN_VALUE = 'dropdown-value';

	/**
	 * Dropdown items array.
	 *
	 * @var array
	 */
	public $dropdown_items = [];

	/**
	 * Create CButtonDropdown instance.
	 *
	 * @param string $name                Element name.
	 * @param string $value               Element selected value.
	 * @param array  $items               Dropdown items.
	 * @param string $items[]['label']    Dropdown item label.
	 * @param string $items[]['value']    Dropdown item value.
	 * @param string $items[]['class']    Dropdown css class to be used for CButtonDropdown when item is selected.
	 */
	public function __construct(string $name, $value = null, array $items = []) {
		parent::__construct($name, '');

		$this->setId(uniqid('btn-dropdown-'));
		$this->addClass(ZBX_STYLE_BTN_ALT);
		$this->addClass(ZBX_STYLE_BTN_TOGGLE);
		$this->dropdown_items = $items;

		if ($value !== null) {
			$this->setAttribute('value', $value);
		}
	}

	public function toString($destroy = true) {
		$name = $this->getAttribute('name');
		$node = (new CDiv())
			->setId($this->getId())
			->addClass(self::ZBX_STYLE_CLASS)
			->addItem((new CButton(null))
				->setAttribute('aria-label', $this->getAttribute('title'))
				->setAttribute('disabled', $this->getAttribute('disabled'))
				->addClass($this->getAttribute('class'))
				->setId(zbx_formatDomId($name.'[btn]'))
				->setMenuPopup([
					'type' => 'dropdown',
					'data' => [
						'items' => $this->dropdown_items,
						'toggle_class' => ZBX_STYLE_BTN_TOGGLE
					]
				])
			)
			->addItem((new CInput('hidden', $name, $this->getAttribute('value')))
				->addClass(self::ZBX_STYLE_BTN_VALUE)
		);

		return $node->toString(true);
	}
}
