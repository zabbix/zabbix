<?php declare(strict_types=0);
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


namespace Widgets\TopHosts\Includes;

use CButton,
	CCol,
	CColHeader,
	CDiv,
	CList,
	CTable,
	CTag,
	CVar,
	CWidgetFieldView;

class CWidgetFieldColumnsListView extends CWidgetFieldView {

	public function __construct(CWidgetFieldColumnsList $field) {
		$this->field = $field;
	}

	public function getView(): CTag {
		$columns = $this->field->getValue();

		$header = [
			'',
			(new CColHeader(_('Name')))->addStyle('width: 39%'),
			(new CColHeader(_('Data')))->addStyle('width: 59%'),
			_('Actions')
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
			$column_data = [new CVar('sortorder['.$this->field->getName().'][]', $column_index)];

			foreach ($column as $key => $value) {
				$column_data[] = new CVar($this->field->getName().'['.$column_index.']['.$key.']', $value);
			}

			if ($column['data'] == CWidgetFieldColumnsList::DATA_HOST_NAME) {
				$label = new CTag('em', true, _('Host name'));
			}
			elseif ($column['data'] == CWidgetFieldColumnsList::DATA_TEXT) {
				$label = new CTag('em', true, $column['text']);
			}
			elseif (array_key_exists('item', $column)) {
				$label = $column['item'];
			}
			else {
				$label = '';
			}

			$view->addRow([
				(new CCol((new CDiv)->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
				(new CDiv($column['name']))->addClass('text'),
				(new CDiv($label))->addClass('text'),
				[
					(new CList($row_actions))->addClass(ZBX_STYLE_HOR_LIST),
					$column_data
				]
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
