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


class CFormGrid extends CTag {

	/**
	 * Default CSS class name for HTML root element.
	 */
	private const ZBX_STYLE_CLASS = 'form-grid';

	public const ZBX_STYLE_FIELDS_GROUP       = 'fields-group';
	public const ZBX_STYLE_FIELDS_GROUP_LABEL = 'fields-group-label';

	// Adds a margin if contains form actions only.
	public const ZBX_STYLE_FORM_GRID_ACTIONS  = 'form-grid-actions';

	public const ZBX_STYLE_FORM_GRID_1_1 = 'form-grid-1-1';
	public const ZBX_STYLE_FORM_GRID_3_1 = 'form-grid-3-1';

	// True label column width for use in filter forms.
	public const ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE = 'label-width-true';

	public function __construct($items = null) {
		parent::__construct('div', true, $items);

		$this->addClass(self::ZBX_STYLE_CLASS);
	}
}
