<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


namespace Widgets\PlainText\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldIntegerBox,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldRadioButtonList
};

/**
 * Plain text widget form.
 */
class WidgetForm extends CWidgetForm {

	public const LAYOUT_HORIZONTAL = 0;
	public const LAYOUT_VERTICAL = 1;

	public const NEW_VALUES_TOP = 0;
	public const NEW_VALUES_BOTTOM = 1;

	public const COLUMN_HEADER_OFF = 0;
	public const COLUMN_HEADER_HORIZONTAL = 1;
	public const COLUMN_HEADER_VERTICAL = 2;

	public function addFields(): self {
		return $this
			->addField(
				(new CWidgetFieldRadioButtonList('layout', _('Layout'), [
					self::LAYOUT_HORIZONTAL => _('Horizontal'),
					self::LAYOUT_VERTICAL => _('Vertical')
				]))->setDefault(self::LAYOUT_HORIZONTAL)
			)
			->addField(
				(new CWidgetFieldColumnsList('columns', _('Columns')))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldIntegerBox('show_lines', _('Show lines'), ZBX_MIN_WIDGET_LINES,
					ZBX_MAX_WIDGET_LINES
				))
					->setDefault(ZBX_DEFAULT_WIDGET_LINES)
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('sortorder', _('New values'), [
					self::NEW_VALUES_TOP => _('Top'),
					self::NEW_VALUES_BOTTOM => _('Bottom')
				]))->setDefault(self::NEW_VALUES_TOP)
			)
			->addField(
				new CWidgetFieldCheckBox('show_timestamp', _('Show timestamp'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('show_column_header', _('Show column header'), [
					self::COLUMN_HEADER_OFF => _('Off'),
					self::COLUMN_HEADER_HORIZONTAL => _('Horizontal'),
					self::COLUMN_HEADER_VERTICAL => _('Vertical')
				]))->setDefault(self::COLUMN_HEADER_VERTICAL)
			)
			->addField(
				new CWidgetFieldMultiSelectOverrideHost()
			);
	}
}
