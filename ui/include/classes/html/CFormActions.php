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


class CFormActions extends CTag {

	/**
	 * Default CSS class name for HTML root element.
	 */
	private const ZBX_STYLE_CLASS = 'form-actions';

	/**
	 * @param CButtonInterface|null $main_button
	 * @param CButtonInterface[]    $other_buttons
	 */
	public function __construct(CButtonInterface $main_button = null, array $other_buttons = []) {
		parent::__construct('div', true);

		foreach ($other_buttons as $other_button) {
			$other_button->addClass(ZBX_STYLE_BTN_ALT);
		}

		if ($main_button !== null) {
			array_unshift($other_buttons, $main_button);
		}

		$this
			->addClass(self::ZBX_STYLE_CLASS)
			->addItem($other_buttons);
	}
}
