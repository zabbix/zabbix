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
 * @var CView $this
 * @var array $data
 */

$this->includeJsFile('template.list.js.php');

if ($data['uncheck']) {
	uncheckTableRows('templates');
}

$html_page = (new CHtmlPage())
	->setTitle(_('Templates'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_TEMPLATES_LIST))
	->setControls((new CTag('nav', true,
		(new CList())
			->addItem(
				(new CSimpleButton(_('Create template')))
					->setAttribute('data-groupids', json_encode(array_keys($data['filter']['groups'])))
					->setId('js-create'))
			->addItem((new CSimpleButton(_('Import')))->setId('js-import'))
	))->setAttribute('aria-label', _('Content controls')));

$action_url = (new CUrl('zabbix.php'))->setArgument('action', $data['action']);

$filter_tags_table = CTagFilterFieldHelper::getTagFilterField([
	'evaltype' => $data['filter']['evaltype'],
	'tags' => $data['filter']['tags']
]);

$filter = (new CFilter())
	->setResetUrl($action_url)
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addVar('action', $data['action'], 'filter_action')
	->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				(new CLabel(_('Template groups'), 'filter_groups__ms')),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_groups[]',
						'object_name' => 'templateGroup',
						'data' => $data['filter']['groups'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'template_groups',
								'srcfld1' => 'groupid',
								'dstfrm' => CFilter::FORM_NAME,
								'dstfld1' => 'filter_groups_',
								'with_templates' => true,
								'editable' => true,
								'enrich_parent_groups' => true
							]
						]
					]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				(new CLabel(_('Linked templates'), 'filter_templates__ms')),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_templates[]',
						'object_name' => 'templates',
						'data' => $data['filter']['templates'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'templates',
								'srcfld1' => 'hostid',
								'srcfld2' => 'host',
								'dstfrm' => CFilter::FORM_NAME,
								'dstfld1' => 'filter_templates_'
							]
						]
					]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Name'), 'filter_name'),
				new CFormField((new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH))
			])
			->addItem([
				new CLabel(_('Vendor'), 'filter_vendor_name'),
				new CFormField(
					(new CTextBox('filter_vendor_name', $data['filter']['vendor_name'], false,
						DB::getFieldLength('hosts', 'vendor_name')
					))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Version'), 'filter_vendor_version'),
				new CFormField(
					(new CTextBox('filter_vendor_version', $data['filter']['vendor_version'], false,
						DB::getFieldLength('hosts', 'vendor_version')
					))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			]),
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([new CLabel(_('Tags')), new CFormField($filter_tags_table)])
	]);

$html_page->addItem($filter);

$csrf_token = CCsrfTokenHelper::get('template');

$form = (new CForm())
	->setName('templates');

$form->addItem([
	(new CDataTable())->setId('templates'),
	(new CActionButtonList('action', 'templates', [
		'template.export' => [
			'content' => new CButtonExport('export.templates',
				(new CUrl('zabbix.php'))
					->setArgument('action', $data['action'])
					->setArgument('page', $data['page'] == 1 ? null : $data['page'])
					->getUrl()
			)
		],
		'template.massupdate' => [
			'content' => (new CSimpleButton(_('Mass update')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massupdate')
				->addClass('js-no-chkbxrange')
		],
		'template.massdelete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdelete')
				->addClass('js-no-chkbxrange')
		],
		'template.massdeleteclear' => [
			'content' => (new CSimpleButton(_('Delete and clear')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdelete-clear')
				->addClass('js-no-chkbxrange')
		]
	], 'templates'))->setAddSelectedCountElement(false)
]);

$html_page
	->addItem($form)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'csrf_token' => $csrf_token,
		'default_sort_field' => $data['default_sort_field'],
		'default_sort_order' => $data['default_sort_order'],
		'filter' => $data['filter'],
		'page' => $data['page'],
		'sort_field' => $data['sort_field'],
		'sort_order' => $data['sort_order'],
		'storage_idx' => $data['storage_idx'],
		'user_configs' => $data['user_configs']
	]).');
'))
	->setOnDocumentReady()
	->show();
