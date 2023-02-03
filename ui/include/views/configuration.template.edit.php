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
 */

require_once __DIR__.'/js/common.template.edit.js.php';

$html_page = (new CHtmlPage())
	->setTitle(_('Templates'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_TEMPLATES_EDIT));

if ($data['form'] !== 'clone' && $data['form'] !== 'full_clone') {
	$html_page->setNavigation(getHostNavigation('', $data['templateid']));
}

$tabs = new CTabView();

if ($data['form_refresh'] == 0) {
	$tabs->setSelected(0);
}

$form = (new CForm())
	->addItem((new CVar('form_refresh', $data['form_refresh'] + 1))->removeId())
	->addItem((new CVar(CCsrfTokenHelper::CSRF_TOKEN_NAME, CCsrfTokenHelper::get('templates.php')))->removeId())
	->setId('templates-form')
	->setName('templatesForm')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addVar('form', $data['form']);

if ($data['templateid'] != 0) {
	$form->addVar('templateid', $data['templateid']);
}

$template_tab = (new CFormList('hostlist'))
	->addRow(
		(new CLabel(_('Template name'), 'template_name'))->setAsteriskMark(),
		(new CTextBox('template_name', $data['template_name'], false, 128))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(
		_('Visible name'),
		(new CTextBox('visiblename', $data['visible_name'], false, 128))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow((new CLabel(_('Template groups'), 'groups__ms'))->setAsteriskMark(),
		(new CMultiSelect([
			'name' => 'groups[]',
			'object_name' => 'templateGroup',
			'add_new' => (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN),
			'data' => $data['groups_ms'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'template_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'groups_',
					'editable' => true,
					'disableids' => array_column($data['groups_ms'], 'id')
				]
			]
		]))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Description'),
		(new CTextArea('description', $data['description']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('hosts', 'description'))
	);

if ($data['vendor']) {
	$template_tab->addRow(_('Vendor and version'), implode(', ', [
		$data['vendor']['name'],
		$data['vendor']['version']
	]));
}

$tabs->addTab('tmplTab', _('Templates'), $template_tab, false);

// tags
$tabs->addTab('tags-tab', _('Tags'), new CPartial('configuration.tags.tab', [
		'source' => 'template',
		'tags' => $data['tags'],
		'readonly' => $data['readonly'],
		'tabs_id' => 'tabs',
		'tags_tab_id' => 'tags-tab'
	]), TAB_INDICATOR_TAGS
);

$tabs->addTab('macroTab', _('Macros'),
	(new CFormList('macrosFormList'))
		->addRow(null, (new CRadioButtonList('show_inherited_macros', (int) $data['show_inherited_macros']))
			->addValue(_('Template macros'), 0)
			->addValue(_('Inherited and template macros'), 1)
			->setModern(true)
		)
		->addRow(
			null,
			new CPartial($data['show_inherited_macros'] ? 'hostmacros.inherited.list.html' : 'hostmacros.list.html', [
				'macros' => $data['macros'],
				'readonly' => $data['readonly'],
				'source' => 'template'
			]),
			'macros_container'
		),
	TAB_INDICATOR_MACROS
);

// Value mapping.
$tabs->addTab('valuemap-tab', _('Value mapping'), (new CFormList('valuemap-formlist'))->addRow(null,
	new CPartial('configuration.valuemap', [
		'valuemaps' => $data['valuemaps'],
		'readonly' => $data['readonly'],
		'form' => 'template'
	])),
	TAB_INDICATOR_VALUEMAPS
);

// footer
if ($data['templateid'] != 0 && $data['form'] !== 'full_clone') {
	$tabs->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CSubmit('clone', _('Clone')),
			new CSubmit('full_clone', _('Full clone')),
			new CButtonDelete(_('Delete template?'), url_param('form').url_param('templateid').'&'.
				CCsrfTokenHelper::CSRF_TOKEN_NAME.'='.CCsrfTokenHelper::get('templates.php')
			),
			new CButtonQMessage(
				'delete_and_clear',
				_('Delete and clear'),
				_('Delete and clear template? (Warning: all linked hosts will be cleared!)'),
				url_param('form').url_param('templateid').'&'.CCsrfTokenHelper::CSRF_TOKEN_NAME.'='.
				CCsrfTokenHelper::get('templates.php')
			),
			new CButtonCancel()
		]
	));
}
else {
	$tabs->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$form->addItem($tabs);

$html_page
	->addItem($form)
	->show();
