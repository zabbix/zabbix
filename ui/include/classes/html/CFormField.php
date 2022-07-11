<?php declare(strict_types = 0);
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


class CFormField extends CTag {

	/**
	 * Default CSS class name for HTML root element.
	 */
	private const ZBX_STYLE_CLASS = 'form-field';

	public const ZBX_STYLE_FORM_FIELD_OFFSET_1 = 'offset-1';
	public const ZBX_STYLE_FORM_FIELD_OFFSET_2 = 'offset-2';
	public const ZBX_STYLE_FORM_FIELD_OFFSET_3 = 'offset-3';

	public const ZBX_STYLE_FORM_FIELD_FLUID    = 'field-fluid';

	/**
	 * @param CTag|CTag[]|null $items
	 */
	public function __construct($items = null) {
		parent::__construct('div', true);

		$this
			->addClass(self::ZBX_STYLE_CLASS)
			->addItem($items);
	}
}
