<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CPlainTextWidgetForm extends CWidgetForm {

	public function __construct($data) {
		parent::__construct($data, WIDGET_PLAIN_TEXT);

		// Items selector.
		$field_items = (new CWidgetFieldItem('itemids', _('Items')))
			->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			->setMultiple(true);

		if (array_key_exists('itemids', $this->data)) {
			$field_items->setValue($this->data['itemids']);
		}

		$this->fields[] = $field_items;

		// Location of the item names.
		$field_style = (new CWidgetFieldRadioButtonList('style', _('Items location'), [
			STYLE_LEFT => _('Left'),
			STYLE_TOP => _('Top')
		]))
			->setDefault(STYLE_LEFT)
			->setModern(true);

		if (array_key_exists('style', $this->data)) {
			$field_style->setValue($this->data['style']);
		}
		$this->fields[] = $field_style;

		// Number of records to display.
		$field_lines = (new CWidgetFieldNumericBox('show_lines', _('Show lines'), ZBX_MIN_WIDGET_LINES,
			ZBX_MAX_WIDGET_LINES
		))
			->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			->setDefault(ZBX_DEFAULT_WIDGET_LINES);

		if (array_key_exists('show_lines', $this->data)) {
			$field_lines->setValue($this->data['show_lines']);
		}

		$this->fields[] = $field_lines;

		// Show text as HTML.
		$field_show_as_html = (new CWidgetFieldCheckBox('show_as_html', _('Show text as HTML')))->setDefault(0);

		if (array_key_exists('show_as_html', $this->data)) {
			$field_show_as_html->setValue($this->data['show_as_html']);
		}

		$this->fields[] = $field_show_as_html;

		// Use dynamic items.
		$dynamic_item = (new CWidgetFieldCheckBox('dynamic', _('Dynamic items')))->setDefault(WIDGET_SIMPLE_ITEM);

		if (array_key_exists('dynamic', $this->data)) {
			$dynamic_item->setValue($this->data['dynamic']);
		}

		$this->fields[] = $dynamic_item;
	}
}
