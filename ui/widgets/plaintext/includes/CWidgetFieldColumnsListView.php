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

use API,
	CButton,
	CCol,
	CColHeader,
	CDiv,
	CList,
	CTable,
	CTag,
	CVar;

use CWidgetFieldView;


class CWidgetFieldColumnsListView extends CWidgetFieldView
{

	public function __construct(CWidgetFieldColumnsList $field) {
		$this->field = $field;
	}

	public function getView(): CTag {
		$columns = $this->field->getValueWithItemNames();

		$header = [
			'',
			(new CColHeader(_('Name')))->addStyle('width: 39%'),
			(new CColHeader(_('Data')))->addStyle('width: 59%'),
			_('Action')
		];

		$row_actions = [
			(new CButton('edit', _('Edit')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->removeId(),
			(new CButton('remove', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->removeId()
		];

		$view = (new CTable())
			->setId('list_'.$this->field->getName())
			->setHeader($header);

		foreach ($columns as $column_index => $column) {
			$item_name = $column['item_name'];
			unset($column['item_name']);

			$column_data = [new CVar('sort_order['.$this->field->getName().'][]', $column_index)];

			foreach ($column as $key => $value) {
				$column_data[] = new CVar($this->field->getName().'['.$column_index.']['.$key.']', $value);
			}

			$view->addRow([
				(new CCol((new CDiv)->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
				(new CDiv($column['name']))
					->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
					->addClass('text'),
				(new CDiv($item_name))
					->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
					->addClass('text'),
				(new CList(array_merge($row_actions, [$column_data])))->addClass(ZBX_STYLE_HOR_LIST)
			]);


		}

		$view->addRow(
			(new CCol(
				(new CButton('add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->setEnabled(!$this->isDisabled())
			))->setColSpan(count($header))
		);

		return $view;
	}
}
