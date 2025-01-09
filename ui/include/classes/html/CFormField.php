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
