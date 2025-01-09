<?php
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
 * @var CView $this
 */

$this->includeJsFile('administration.macros.edit.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Macros'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_MACROS_EDIT));

$table = (new CTable())
	->setId('tbl_macros')
	->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
	->addClass('global-macro-table')
	->setColumns([
		(new CTableColumn(_('Macro')))->addClass('table-col-macro'),
		(new CTableColumn(_('Value')))->addClass('table-col-value'),
		(new CTableColumn(_('Description')))->addClass('table-col-description'),
		(new CTableColumn())->addClass('table-col-action')
	]);

foreach ($data['macros'] as $i => $macro) {
	$macro_input = (new CTextAreaFlexible('macros['.$i.'][macro]', $macro['macro']))
		->addClass('macro')
		->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
		->setAttribute('placeholder', '{$MACRO}')
		->disableSpellcheck();

	if ($i == 0) {
		$macro_input->setAttribute('autofocus', 'autofocus');
	}

	$macro_value = new CMacroValue($macro['type'], 'macros['.$i.']');

	if ($macro['type'] == ZBX_MACRO_TYPE_SECRET) {
		$macro_value->addRevertButton();
		$macro_value->setRevertButtonVisibility(array_key_exists('value', $macro)
			&& array_key_exists('globalmacroid', $macro)
		);
	}

	if (array_key_exists('value', $macro)) {
		$macro_value->setAttribute('value', $macro['value']);
	}

	$description_input = (new CTextAreaFlexible('macros['.$i.'][description]', $macro['description']))
		->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
		->setMaxlength(DB::getFieldLength('globalmacro', 'description'))
		->setAttribute('placeholder', _('description'));

	$button_cell = [
		(new CButton('macros['.$i.'][remove]', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
	];
	if (array_key_exists('globalmacroid', $macro)) {
		$button_cell[] = new CVar('macros['.$i.'][globalmacroid]', $macro['globalmacroid']);
	}

	$table->addRow([
		(new CCol($macro_input))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($macro_value))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($description_input))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($button_cell))->addClass(ZBX_STYLE_NOWRAP)
	], 'form_row');
}

$table->setFooter(new CCol(
	(new CButton('macro_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
));

$macros_form_list = (new CFormList('macrosFormList'))->addRow($table);

$tab_view = (new CTabView())->addTab('macros', _('Macros'), $macros_form_list);

$save_button = (new CSubmit('update', _('Update')))->setAttribute('data-removed-count', 0);

$tab_view->setFooter(makeFormFooter($save_button));

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('macros')))->removeId())
	->setName('macrosForm')
	->disablePasswordAutofill()
	->setAction((new CUrl('zabbix.php'))->setArgument('action', 'macros.update')->getUrl())
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addItem($tab_view);

$html_page
	->addItem($form)
	->show();
