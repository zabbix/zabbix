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


class CButtonDropdown extends CDiv {

	/**
	 * Default CSS class name for HTML root element.
	 */
	public const ZBX_STYLE_CLASS = 'btn-dropdown-container';

	/**
	 * Button style names.
	 */
	public const ZBX_STYLE_BTN_TOGGLE = 'btn-dropdown-toggle';
	public const ZBX_STYLE_BTN_VALUE = 'dropdown-value';

	/**
	 * Options array.
	 *
	 * @var array
	 */
	protected $options = [
		'disabled' => false
	];

	/**
	 * Element value.
	 *
	 * @var string
	 */
	protected $value;

	/**
	 * Element name.
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * CButtonDropdown constructor.
	 *
	 * @param string $name
	 * @param string $value
	 * @param array  $options
	 * @param string $options['title']           aria-label title
	 * @param string $options['active_class']
	 * @param bool   $options['disabled']        (optional)
	 * @param array  $options['items']
	 * @param string $options['items']['label']
	 * @param string $options['items']['value']
	 * @param string $options['items']['class']
	 */
	public function __construct(string $name, string $value, array $options) {
		$this->options = array_merge($this->options, $options);

		parent::__construct();

		$this->name = $name;

		$this->value = $value;
	}

	public function toString($destroy = true) {
		$this
			->setId(uniqid('btn-dropdown-'))
			->addClass(self::ZBX_STYLE_CLASS)
			->addItem(
				(new CButton(null))
					->setAttribute('aria-label', $this->options['title'])
					// In setMenuPopup is check for disabled attribute. Its why we set disabled before setMenuPopup.
					->setEnabled(!$this->options['disabled'])
					->addClass(implode(' ', [ZBX_STYLE_BTN_ALT, self::ZBX_STYLE_BTN_TOGGLE,
						$this->options['active_class']
					]))
					->setId(zbx_formatDomId($this->name.'[btn]'))
					->setMenuPopup([
						'type' => 'dropdown',
						'data' => [
							'items' => $this->options['items']
						]
					])
			)
			->addItem((new CInput('hidden', $this->name, $this->value))->addClass(self::ZBX_STYLE_BTN_VALUE));

		return parent::toString($destroy);
	}
}
