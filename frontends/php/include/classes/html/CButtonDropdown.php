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


class CButtonDropdown extends CTag {

	/**
	 * Default CSS class name for HTML root element.
	 */
	public const ZBX_STYLE_CLASS = 'btn-dropdown-container';

	protected $options = [
		'disabled' => false
	];

	/**
	 * CButtonDropdown constructor.
	 *
	 * @param string $name
	 * @param string $value
	 * @param array  $options
	 * @param string $options['title']
	 * @param string $options['active_class']
	 * @param bool   $options['disabled']     (optional)
	 * @param array  $options['items']
	 * @param string $options['items']['label']
	 * @param string $options['items']['value']
	 * @param string $options['items']['class']
	 */
	public function __construct(string $name, string $value, array $options) {
		$this->options = array_merge($this->options, $options);

		parent::__construct('div', true);

		$this
			->setId(uniqid('btn-dropdown-'))
			->addClass(self::ZBX_STYLE_CLASS)
			->addItem(
				(new CButton(null))
					->setAttribute('title', $this->options['title'])
					->addClass(implode(' ', [ZBX_STYLE_BTN_ALT, ZBX_STYLE_BTN_DROPDOWN_TOGGLE, $this->options['active_class']]))
					->setMenuPopup([
						'type' => 'dropdown',
						'data' => [
							'items' => $this->options['items']
						]
					])
					->setEnabled(!$this->options['disabled'])
			)
			->addItem((new CInput('hidden', $name, $value))->addClass(ZBX_STYLE_BTN_DROPDOWN_VALUE));
	}
}
