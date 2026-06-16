<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


/**
 * @var CPartial $this
 * @var array    $data
 */

echo (new CObject())
	->addItem(new CLabel(_('Type of calculation'), 'ceprule-window-condition-evaltype-select'))
	->addItem(new CFormField([
		(new CDiv(
			(new CSelect('window[filter][evaltype]'))
				->setValue($data['filter']['evaltype'])
				->setId('ceprule-window-filter-evaltype')
				->setFocusableElementId('ceprule-window-condition-evaltype-select')
				->addOption(new CSelectOption(CONDITION_EVAL_TYPE_AND_OR, _('And/Or')))
				->addOption(new CSelectOption(CONDITION_EVAL_TYPE_AND, _('And')))
				->addOption(new CSelectOption(CONDITION_EVAL_TYPE_OR, _('Or')))
				->addOption(new CSelectOption(CONDITION_EVAL_TYPE_EXPRESSION, _('Custom expression')))
				->addClass(ZBX_STYLE_FORM_INPUT_MARGIN)
		))->addClass(ZBX_STYLE_CELL),
		(new CDiv([
			(new CSpan())->setId('ceprule-window-filter-expression-preview'),
			(new CTextBox('window[filter][formula]', $data['filter']['formula']))
				->setId('ceprule-window-filter-expression')
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setAttribute('placeholder', 'A or (B and C) ...')
		]))->addClass(ZBX_STYLE_CELL)
	]))
	->addItem((new CLabel(_('Historical conditions')))->setAsteriskMark())
	->addItem((new CFormField())
		->addItem((new CDiv())
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('data-field-type', 'set')
			->setAttribute('data-field-name', 'window[filter][conditions]')
			->addItem((new CTable())
				->setColumns([
					new CTableColumn(new CColHeader(_('Label'))),
					(new CTableColumn(new CColHeader(_('Name'))))
						->setAttribute('width', ZBX_TEXTAREA_BIG_WIDTH.'px'),
					new CTableColumn(new CColHeader(_('Actions')))
				])
				->setId('ceprule-window-condition-table')
				->addItem((new CTag('tfoot', true))
					->addItem((new CCol(
						(new CButtonLink(_('Add')))->addClass('js-window-condition-add')
					))->setColSpan(4))
				)
			)
		)
	);
