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
 * @var CView $this
 * @var array $data
 */

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('tagfilter')))->removeId())
	->setId('tag-filter-add-form')
	->setName('tag-filter-add-form');

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form_grid = new CFormGrid();

$new_tag_filter_table = (new CTable())
	->setId('new-tag-filter-table')
	->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_FILTER_STANDARD_WIDTH.'px;')
	->setHeader([_('Tag'), _('Value'), ''])
	->addRow((new CRow())->addClass('js-tag-filter-row-placeholder'))
	->addItem(
		(new CTag('tfoot', true))
			->addItem(new CCol((new CButtonLink(_('Add')))->addClass('js-add-tag-filter-row')))
	);

$form_grid
	->addItem([
		(new CLabel(_('Host groups'), 'ms_new_tag_filter_groupids__ms'))->setAsteriskMark(),
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
						'dstfld1' => 'ms_new_tag_filter_groupids_',
						'disableids' => array_column($data['host_groups_ms'], 'id')
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
			(new CRadioButtonList('filter_type', TAG_FILTER_ALL))
				->addValue(_('All tags'), TAG_FILTER_ALL)
				->addValue(_('Tag list'), TAG_FILTER_LIST)
				->setModern(true)
		)
	])
	->addItem([
		(new CLabel(_('Tags'), 'tag_filters'))->setAsteriskMark(),
		(new CFormField(
			(new CDiv($new_tag_filter_table))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		))
		->setId('tag-list-form-field')
	]);

$tag_filter_row_template = (new CTemplateTag('tag-filter-row-template'))->addItem(
	(new CRow([
		(new CTextBox('new_tag_filter[#{rowid}][tag]', '#{tag}', false,
			DB::getFieldLength('tag_filter', 'tag')
		))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->setAttribute('placeholder', _('tag')),
		(new CTextBox('new_tag_filter[#{rowid}][value]', '#{value}', false,
			DB::getFieldLength('tag_filter', 'value')
		))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->setAttribute('placeholder', _('value')),
		(new CButtonLink(_('Remove')))->addClass('js-remove-table-row')
	]))->addClass('form_row')
);

$form_grid->addItem($tag_filter_row_template);

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			tag_filter_edit.init('.json_encode([
				'tag_filters' => $data['tag_filters'],
				'groupid' => $data['groupid'] ?: 0
			]).');
		'))->setOnDocumentReady()
	)
	->setAttribute('autofocus', 'autofocus');

$output = [
	'header' => $data['title'],
	'script_inline' =>  getPagePostJs().
		$this->readJsFile('usergroup.tagfilter.edit.js.php'),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['edit'] == 0 ? _('Add') : _('Update'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'tag_filter_edit.submit();'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
