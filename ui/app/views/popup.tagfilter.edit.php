<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


/**
 * @var CView $this
 * @var array	 $data
 */

$form = (new CForm())
	->addItem((new CVar(CCsrfTokenHelper::CSRF_TOKEN_NAME, CCsrfTokenHelper::get('tagfilter')))->removeId())
	->setId('tag-filter-add-form')
	->setName('tag-filter-add-form')
	->addVar('action', $data['action'])
	->addVar('update', 1);

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form_grid = new CFormGrid();

$new_tag_filter_table = (new CTable())
	->setId('new-tag-filter-table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Name'), _('Value'), _('Action')])
	->addRow((new CRow())->addClass('js-tag-filter-row-placeholder'))
	->addRow([
		(new CSimpleButton(_('Add')))
			->addClass('js-add-tag-filter-row')
			->addClass(ZBX_STYLE_BTN_LINK)
	]);

$form_grid
	->addItem([
		(new CLabel(_('Host groups'), 'tag_filter__ms'))->setAsteriskMark(),
		new CFormField(
			(new CMultiSelect([
				'name' => 'ms_new_tag_filter[groupids][]',
				'object_name' => 'hostGroup',
				'data' => $data['host_groups_ms'],
				'popup' => [
					'parameters' => [
						'srctbl' => 'host_groups',
						'srcfld1' => 'groupid',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'ms_new_tag_filter_groupids_'
					]
				],
				'add_post_js' => false
			]))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		new CLabel(_('Filter'), 'filter_type'),
		new CFormField(
			(new CRadioButtonList('filter_type', 0))
				->addValue(_('All tags'), 0)
				->addValue(_('Tag list'), 1)
				->setModern(true)
		)
	])
	->addItem([
		(new CLabel(_('Tags'), 'tag_filters')),
		new CFormField(
			(new CDiv(
				$new_tag_filter_table
			))->addClass('table-forms-separator')
		)
	]);

$tag_filter_row_template = (new CTemplateTag('tag-filter-row-template'))->addItem(
	(new CRow([
		(new CCol(
			(new CTextBox('tag_filter[tag][#{rowid}]'))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAttribute('placeholder', _('tag'))
		))->setAttribute('style', 'vertical-align: top'),
		(new CCol(
			(new CTextBox('tag_filter[value][#{rowid}]'))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAttribute('placeholder', _('value'))
		))->setAttribute('style', 'vertical-align: top'),
		(new CCol(
			(new CButtonLink(_('Remove')))->addClass('js-remove-table-row')
		))->setAttribute('style', 'vertical-align: top')
	]))
		->addClass('form_row')
);

$form_grid->addItem($tag_filter_row_template);

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			tag_filter_popup.init('.json_encode([
			]).');
		'))->setOnDocumentReady()
	);

$output = [
	'header' => $data['title'],
	'script_inline' => $this->readJsFile('popup.tagfilter.edit.js.php', []),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['edit'] ? _('Update') : _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'tag_filter_popup.submit();'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
