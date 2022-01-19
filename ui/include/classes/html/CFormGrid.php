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


class CFormGrid extends CTag {

	/**
	 * Default CSS class name for HTML root element.
	 */
	private const ZBX_STYLE_CLASS = 'form-grid';

	public const ZBX_STYLE_FORM_GRID_1_1 = 'form-grid-1-1';
	public const ZBX_STYLE_FORM_GRID_3_1 = 'form-grid-3-1';

	// True label column width for use in filter forms.
	public const ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE = 'label-width-true';

	public function __construct($items = null) {
		parent::__construct('div', true, $items);

		$this->addClass(self::ZBX_STYLE_CLASS);
	}
}
