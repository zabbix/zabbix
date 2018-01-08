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

		// item field
		$field_item = (new CWidgetFieldSelectResource('itemid', _('Item'), WIDGET_FIELD_SELECT_RES_ITEM))
			->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK);

		if (array_key_exists('itemid', $this->data)) {
			$field_item->setValue($this->data['itemid']);
		}

		$this->fields[] = $field_item;

		// Number of records to display.
		$field_lines = (new CWidgetFieldNumericBox('show_lines', _('Show lines'), ZBX_MIN_WIDGET_LINES,
			ZBX_MAX_WIDGET_LINES
		))
			->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			->setDefault(25);

		if (array_key_exists('show_lines', $this->data)) {
			$field_lines->setValue($this->data['show_lines']);
		}

		$this->fields[] = $field_lines;

		// Show text as HTML.
		$field_text_as_html = (new CWidgetFieldCheckBox('style', _('Show text as HTML')))->setDefault(0);

		if (array_key_exists('style', $this->data)) {
			$field_text_as_html->setValue($this->data['style']);
		}

		$this->fields[] = $field_text_as_html;

		// dynamic item
		$dynamic_item = (new CWidgetFieldCheckBox('dynamic', _('Dynamic item')))->setDefault(0);

		if (array_key_exists('dynamic', $this->data)) {
			$dynamic_item->setValue($this->data['dynamic']);
		}

		$this->fields[] = $dynamic_item;
	}
}
