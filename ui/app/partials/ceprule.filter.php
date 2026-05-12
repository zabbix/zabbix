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


/**
 * @var CPartial $this
 * @var array    $data
 */

$filter = $data['filter'];
$id = $data['id'];

$id_modal = "$id-condition-modal";
$id_modal_template = "$id_modal-template";
$id_evaltype_select = "$id-evaltype-select";
$id_row_template = "$id-row-template";
$id_table = "$id-table";
?>

<?= new CLabel(_('Type of calculation'), $id_evaltype_select) ?>
<?= new CFormField([
	(new CDiv(
		(new CSelect('filter[evaltype]'))
		/* (new CSelect('filters[evaltype]')) */
			->setValue($filter['evaltype'])
			->setId('cep-filter-evaltype')
			->setFocusableElementId($id_evaltype_select)
			->addOption(new CSelectOption(CONDITION_EVAL_TYPE_AND_OR, _('And/Or')))
			->addOption(new CSelectOption(CONDITION_EVAL_TYPE_AND, _('And')))
			->addOption(new CSelectOption(CONDITION_EVAL_TYPE_OR, _('Or')))
			->addOption(new CSelectOption(CONDITION_EVAL_TYPE_EXPRESSION, _('Custom expression')))
			->addClass(ZBX_STYLE_FORM_INPUT_MARGIN)
	))->addClass(ZBX_STYLE_CELL),
	(new CDiv([
		(new CSpan())->setId('cep-filter-expression-preview'),
		(new CTextBox('filter[formula]', $filter['formula']))
				->setId('cep-filter-expression')
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setAttribute('placeholder', 'A or (B and C) ...')
	]))->addClass(ZBX_STYLE_CELL)
]) ?>

<?= new CLabel(_('Conditions')) ?>
<?= (new CFormField(
	(new CTable())
		->setAttribute('data-field-type', 'set')
		->setAttribute('data-field-name', 'filter')
		->setId($id_table)
		->setHeader([_('Label'), _('Name'), _('Action')])
		->addItem(
			(new CTag('tfoot', true))
				->addItem(
					(new CCol(
						(new CButtonLink(_('Add')))->addClass('js-condition-add')
					))->setColSpan(4)
				)
		)
))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR) ?>
