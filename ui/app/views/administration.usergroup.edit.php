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
 * @var array $data
 */

$this->includeJsFile('administration.usergroup.edit.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('User groups'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::USERS_USERGROUP_EDIT));

$csrf_token = CCsrfTokenHelper::get('usergroup');

$form = (new CForm())
	->addItem((new CVar('form_refresh', $data['form_refresh'] + 1))->removeId())
	->addItem((new CVar(CCsrfTokenHelper::CSRF_TOKEN_NAME, $csrf_token))->removeId())
	->setId('user-group-form')
	->setName('user_group_form')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID);

if ($data['usrgrpid'] != 0) {
	$form->addVar('usrgrpid', $data['usrgrpid']);
}

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Group name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['name']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
				->setAttribute('maxlength', DB::getFieldLength('usrgrp', 'name'))
		)
	])
	->addItem([
		new CLabel(_('Users'), 'userids__ms'),
		new CFormField(
			(new CMultiSelect([
				'name' => 'userids[]',
				'object_name' => 'users',
				'data' => $data['users_ms'],
				'popup' => [
					'parameters' => [
						'srctbl' => 'users',
						'srcfld1' => 'userid',
						'srcfld2' => 'fullname',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'userids_'
					]
				]
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	]);

if ($data['can_update_group']) {
	$select_gui_access = (new CSelect('gui_access'))
		->setValue($data['gui_access'])
		->setFocusableElementId('gui-access')
		->addOptions(CSelect::createOptionsFromArray([
			GROUP_GUI_ACCESS_SYSTEM => user_auth_type2str(GROUP_GUI_ACCESS_SYSTEM),
			GROUP_GUI_ACCESS_INTERNAL => user_auth_type2str(GROUP_GUI_ACCESS_INTERNAL),
			GROUP_GUI_ACCESS_LDAP => user_auth_type2str(GROUP_GUI_ACCESS_LDAP),
			GROUP_GUI_ACCESS_DISABLED => user_auth_type2str(GROUP_GUI_ACCESS_DISABLED)
		]));

	$userdirectory = (new CSelect('userdirectoryid'))
		->setValue($data['userdirectoryid'])
		->setFocusableElementId('userdirectoryid')
		->addOption((new CSelectOption(0, _('Default')))->addClass(ZBX_STYLE_DEFAULT_OPTION))
		->addOptions(CSelect::createOptionsFromArray($data['userdirectories']))
		->setAdaptiveWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

	$form_grid
		->addItem([
			(new CLabel(_('Frontend access'), $select_gui_access->getFocusableElementId())),
			new CFormField($select_gui_access)
		])
		->addItem([
			(new CLabel(_('LDAP Server'), $userdirectory->getFocusableElementId())),
			new CFormField($userdirectory)
		])
		->addItem([
			new CLabel(_('Enabled')),
			new CFormField(
				(new CCheckBox('users_status', GROUP_STATUS_ENABLED))
					->setUncheckedValue(GROUP_STATUS_DISABLED)
					->setChecked($data['users_status'] == GROUP_STATUS_ENABLED)
			)
		]);
}
else {
	$form_grid
		->addItem([
			new CLabel(_('Frontend access')),
			new CFormField(
				(new CSpan(user_auth_type2str($data['gui_access'])))
					->addClass('text-field')
					->addClass('green')
			)
		])
		->addItem([
			new CLabel(_('Enabled')),
			new CFormField(
				(new CSpan(_('Enabled')))
					->addClass('text-field')
					->addClass('green')
			)
		]);
}

$form_grid
	->addItem([
		new CLabel(_('Debug mode')),
		new CFormField(
			(new CCheckBox('debug_mode'))
				->setUncheckedValue(GROUP_DEBUG_MODE_DISABLED)
				->setChecked($data['debug_mode'] == GROUP_DEBUG_MODE_ENABLED)
		)
	]);

$template_permissions_form_grid = (new CFormGrid())->addItem([new CLabel(_('Permissions'))]);

$new_templategroup_right_table = (new CTable())
	->setId('new-templategroup-right-table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Template groups'), _('Permissions')])
	->addRow((new CRow())->addClass('templategroup-placeholder-row'))
	->addRow([
		(new CSimpleButton(_('Add')))
			->addClass('add-new-template-row')
			->addClass(ZBX_STYLE_BTN_LINK)
	]);

$template_permissions_form_grid
	->addItem(
		new CFormField(
			(new CDiv($new_templategroup_right_table))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		)
	);

$templates_multiselect = (new CMultiSelect([
	'name' => 'ms_new_templategroup_right[groupids][#{rowid}][]',
	'object_name' => 'templateGroup',
	'popup' => [
		'parameters' => [
			'srctbl' => 'template_groups',
			'srcfld1' => 'groupid',
			'dstfrm' => $form->getName(),
			'dstfld1' => 'ms_new_templategroup_right_groupids_#{rowid}_'
		]
	],
	'add_post_js' => false
]))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$template_permissions_row_template = (new CTemplateTag('template-permissions-row-template'))->addItem(
	(new CRow([
		$templates_multiselect,
		(new CCol(
			(new CRadioButtonList('new_templategroup_right[permission][#{rowid}]', PERM_DENY))
				->addValue(_('Read-write'), PERM_READ_WRITE)
				->addValue(_('Read'), PERM_READ)
				->addValue(_('Deny'), PERM_DENY)
				->setModern(true)
		))->setAttribute('style', 'vertical-align: top'),
		(new CCol(
			(new CButton('template_permission_rights_remove', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->setAttribute('style', 'vertical-align: top')
	]))
		->addClass('form_row')
);

$template_permissions_form_grid->addItem($template_permissions_row_template);

$host_permissions_form_grid = (new CFormGrid())->addItem([new CLabel(_('Permissions'))]);

$new_group_right_table = (new CTable())
	->setId('new-group-right-table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Host groups'), _('Permissions')])
	->addRow((new CRow())->addClass('group-placeholder-row'))
	->addRow([
		(new CSimpleButton(_('Add')))
			->addClass('add-new-host-row')
			->addClass(ZBX_STYLE_BTN_LINK)
	]);

$host_permissions_form_grid
	->addItem(
		new CFormField(
			(new CDiv($new_group_right_table))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		)
	);

$hosts_multiselect = (new CMultiSelect([
			'name' => 'ms_new_group_right[groupids][#{rowid}][]',
			'object_name' => 'hostGroup',
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'ms_new_group_right_groupids_#{rowid}_'
				]
			],
			'add_post_js' => false
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$host_permissions_row_template = (new CTemplateTag('host-permissions-row-template'))->addItem(
	(new CRow([
		$hosts_multiselect,
		(new CCol(
			(new CRadioButtonList('new_group_right[permission][#{rowid}]', PERM_DENY))
				->addValue(_('Read-write'), PERM_READ_WRITE)
				->addValue(_('Read'), PERM_READ)
				->addValue(_('Deny'), PERM_DENY)
				->setModern(true)
		))->setAttribute('style', 'vertical-align: top'),
		(new CCol(
			(new CButton('host_permission_rights_remove', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->setAttribute('style', 'vertical-align: top')
	]))
		->addClass('form_row')
);

$host_permissions_form_grid->addItem($host_permissions_row_template);

$tag_filter_form_grid = (new CFormGrid())->addItem([new CLabel(_('Permissions'))]);

$new_tag_filter_table = (new CTable())
	->setId('new-tag-filter-table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Host groups'), _('Tags')])
	->addRow((new CRow())->addClass('tag-filter-placeholder-row'))
	->addRow([
		(new CSimpleButton(_('Add')))
			->addClass('add-new-tag-filter-row')
			->addClass(ZBX_STYLE_BTN_LINK)
	]);

$tag_filter_form_grid
	->addItem(
		new CFormField(
			(new CDiv($new_tag_filter_table))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		)
	);

$tag_filter_multiselect = (new CMultiSelect([
			'name' => 'ms_new_tag_filter[groupids][#{rowid}][]',
			'object_name' => 'hostGroup',
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'ms_new_tag_filter_groupids_#{rowid}_'
				]
			],
			'add_post_js' => false
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$tag_filter_row_template = (new CTemplateTag('tab-filter-row-template'))->addItem(
	(new CRow([
		$tag_filter_multiselect,
		(new CCol(
			(new CTextBox('new_tag_filter[tag][#{rowid}]'))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAttribute('placeholder', _('tag'))
		))->setAttribute('style', 'vertical-align: top'),
		(new CCol(
			(new CTextBox('new_tag_filter[value][#{rowid}]'))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAttribute('placeholder', _('value'))
		))->setAttribute('style', 'vertical-align: top'),
		(new CCol(
			(new CButton('tag_filter_remove', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->setAttribute('style', 'vertical-align: top')
	]))
		->addClass('form_row')
);

$tag_filter_form_grid->addItem($tag_filter_row_template);

$tabs = (new CTabView())
	->addTab('user_group_tab', _('User group'), $form_grid)
	->addTab('template_permissions_tab', _('Template permissions'), $template_permissions_form_grid, TAB_INDICATOR_TEMPLATE_PERMISSIONS)
	->addTab('permissions_tab', _('Host permissions'), $host_permissions_form_grid, TAB_INDICATOR_HOST_PERMISSIONS)
	->addTab('tag_filter_tab', _('Problem tag filter'), $tag_filter_form_grid, TAB_INDICATOR_TAG_FILTER);
if ($data['form_refresh'] == 0) {
	$tabs->setSelected(0);
}

$cancel_button = (new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
	->setArgument('action', 'usergroup.list')
	->setArgument('page', CPagerHelper::loadPage('usergroup.list', null))
))->setId('cancel');

if ($data['usrgrpid'] != 0) {
	$tabs->setFooter(makeFormFooter(
		(new CSubmitButton(_('Update'), 'action', 'usergroup.update'))->setId('update'),
		[
			(new CRedirectButton(_('Delete'),
				(new CUrl('zabbix.php'))->setArgument('action', 'usergroup.delete')
					->setArgument('usrgrpids', [$data['usrgrpid']])
					->setArgument(CCsrfTokenHelper::CSRF_TOKEN_NAME, $csrf_token),
				_('Delete selected group?')
			))->setId('delete'),
			$cancel_button
		]
	));
}
else {
	$tabs->setFooter(makeFormFooter(
		(new CSubmitButton(_('Add'), 'action', 'usergroup.create'))->setId('add'),
		[
			$cancel_button
		]
	));
}

$form->addItem($tabs);

$form->addItem(
	(new CScriptTag('view.init('.json_encode([
		'templategroup_rights' => $data['templategroup_rights'],
		'hostgroup_rights' => $data['group_rights'],
		'tag_filters' => $data['tag_filters']
	]).');'))->setOnDocumentReady()
);

$html_page
	->addItem($form)
	->show();
