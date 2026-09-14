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


$readonly = $data['readonly'];
$filter = $data['filter'];

$operators = CSelect::createOptionsFromArray([
	CONDITION_OPERATOR_REGEXP => _('matches'),
	CONDITION_OPERATOR_NOT_REGEXP => _('does not match'),
	CONDITION_OPERATOR_EXISTS => _('exists'),
	CONDITION_OPERATOR_NOT_EXISTS => _('does not exist')
]);

$formgrid = (new CFormGrid());

$formgrid
	->addItem([
		(new CLabel(_('Type of calculation'), 'label-evaltype'))
			->addClass('js-item-condition'),
		(new CFormField([
			(new CDiv(
				(new CSelect('evaltype'))
					->setFocusableElementId('label-evaltype')
					->setId('evaltype')
					->setValue($filter['evaltype'])
					->addOptions(CSelect::createOptionsFromArray([
						CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
						CONDITION_EVAL_TYPE_AND => _('And'),
						CONDITION_EVAL_TYPE_OR => _('Or'),
						CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
					]))
					->addClass(ZBX_STYLE_FORM_INPUT_MARGIN)
					->addClass('js-evaltype')
					->setReadonly($readonly)
			))->addClass(ZBX_STYLE_CELL),
			(new CDiv([
				(new CSpan(''))->addClass('js-expression'),
				(new CTextBox('formula', $filter['formula'], $readonly))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAttribute('placeholder', 'A or (B and C) ...')
					->addClass('js-formula')
			]))
				->addClass(ZBX_STYLE_CELL)
				->addClass(ZBX_STYLE_CELL_EXPRESSION)
		]))->addClass('js-item-condition')
	])
	->addItem([
		(new CLabel(_('Filters'))),
		(new CFormField(
			(new CDiv([
				(new CTable())
					->setId('conditions')
					->setHeader([_('Label'), _('Macro'), '', _('Regular expression'), ''])
					->addClass('js-lld-filters')
					->setAttribute('data-field-type', 'set')
					->setAttribute('data-field-name', 'conditions')
					->setFooter(
						(new CCol(
							(new CButtonLink(_('Add')))
								->addClass('element-table-add')
								->setEnabled(!$readonly)
						))->setColSpan(3)
					),
				(new CTemplateTag('', [
					(new CRow([
						[
							new CSpan('#{formulaid}'),
							new CVar('conditions[#{rowNum}][formulaid]', '#{formulaid}')
						],
						(new CTextAreaFlexible('conditions[#{rowNum}][macro]', '#{macro}'))
							->setAttribute('placeholder', '{#MACRO}')
							->addClass('js-macro')
							->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
							->setMaxlength(DB::getFieldLength('item_condition', 'macro'))
							->setReadonly($readonly)
							->setErrorContainer('conditions-#{rowNum}-error-container'),
						(new CSelect('conditions[#{rowNum}][operator]'))
							->setValue('#{operator}')
							->addClass('js-operator')
							->setReadonly($readonly)
							->addOptions($operators),
						(new CDiv(
							(new CTextAreaFlexible('conditions[#{rowNum}][value]', '#{value}'))
								->setAttribute('placeholder', _('regular expression'))
								->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
								->setMaxlength(DB::getFieldLength('item_condition', 'value'))
								->setReadonly($readonly)
								->addClass('js-value')
								->setErrorContainer('conditions-#{rowNum}-error-container')
						))->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH),
						(new CButtonLink(_('Remove')))
							->addClass('element-table-remove')
							->setEnabled(!$readonly)
					]))->addClass('form_row'),
					(new CRow())
						->addClass('error-container-row')
						->addItem((new CCol())->setId('conditions-#{rowNum}-error-container')
							->setColSpan(3)
						)
				]))->addClass('js-lld-filters-template')
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH .'px;')
		))
	]);

$formgrid
	->show();
