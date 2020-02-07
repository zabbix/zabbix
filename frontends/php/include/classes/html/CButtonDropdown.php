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
	const ZBX_STYLE_CLASS = 'dropdown';

	public function __construct(string $name, string $value = '', array $options = []) {
		parent::__construct('div', true);

		$this
			->setId(uniqid('dropdown-'))
			->addClass(self::ZBX_STYLE_CLASS)
			->addItem(
				(new CButton(null))
					->setAttribute('title', $options['title'])
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass(ZBX_STYLE_BTN_DROPDOWN_TOGGLE)
					->addClass($options['active_class'])
					->setMenuPopup([
						'type' => 'dropdown',
						'data' => [
							'items' => $options['items']
						]
					])
			)->addItem(
				(new CInput('hidden', $name, $value))->addClass('dropdown-value')
			);

		zbx_add_post_js($this->getPostJS());
	}

	/**
	 * Get content of all Javascript code.
	 *
	 * @return string  Javascript code.
	 */
	public function getPostJS(): string {
		return 'jQuery("#'.$this->getId().'").buttonDropdown();';
	}
}
