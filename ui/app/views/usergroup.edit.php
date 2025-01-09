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
 * @var array $data
 */

$this->includeJsFile('usergroup.edit.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('User groups'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::USERS_USERGROUP_EDIT));

$csrf_token = CCsrfTokenHelper::get('usergroup');

$form = (new CForm())
	->addItem((new CVar('form_refresh', $data['form_refresh'] + 1))->removeId())
	->addItem((new CVar(CSRF_TOKEN_NAME, $csrf_token))->removeId())
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
						'dstfld1' => 'userids_',
						'disableids' => array_column($data['users_ms'], 'id'),
						'exclude_provisioned' => 1
					]
				]
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	]);

// If MFA is enabled, default option for new user groups should be 0 - 'Default' , otherwise -1 - 'Disabled' .
if ($data['usrgrpid']) {
	if ($data['group_mfa_status'] == GROUP_MFA_ENABLED) {
		$mfa_index = $data['mfaid'] ?: 0;
	}
	else {
		$mfa_index = -1;
	}
}
else {
	$mfa_index = $data['mfa_config_status'] == MFA_ENABLED ? 0 : -1;
}

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

	$ldap_warning = (makeWarningIcon(_('LDAP authentication is disabled system-wide.')))->setId('ldap-warning');

	$mfa_warning = (makeWarningIcon(_('Multi-factor authentication is disabled system-wide.')))->setId('mfa-warning');
	$mfa = (new CSelect('mfaid'))
		->setValue($mfa_index)
		->setFocusableElementId('mfaid')
		->addOptions(CSelect::createOptionsFromArray($data['mfas']))
		->setAdaptiveWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

	if ($data['mfa_config_status'] == MFA_ENABLED && !$data['usrgrpid']) {
		$mfa
			->addOption((new CSelectOption(-1, _('Disabled'))))
			->addOption((new CSelectOption(0, _('Default')))->addClass(ZBX_STYLE_DEFAULT_OPTION));
	}
	else {
		$mfa
			->addOption((new CSelectOption(-1, _('Disabled')))->addClass(ZBX_STYLE_DEFAULT_OPTION))
			->addOption((new CSelectOption(0, _('Default'))));
	}

	$form_grid
		->addItem([
			(new CLabel(_('Frontend access'), $select_gui_access->getFocusableElementId())),
			new CFormField($select_gui_access)
		])
		->addItem([
			(new CLabel([_('LDAP Server'), $ldap_warning], $userdirectory->getFocusableElementId())),
			new CFormField($userdirectory)
		])
		->addItem([
			(new CLabel([_('Multi-factor authentication'), $mfa_warning], $mfa->getFocusableElementId())),
			new CFormField($mfa)
		])
		->addItem([
			new CLabel(_('Enabled'), 'users_status'),
			new CFormField(
				(new CCheckBox('users_status', GROUP_STATUS_ENABLED))
					->setUncheckedValue(GROUP_STATUS_DISABLED)
					->setChecked($data['users_status'] == GROUP_STATUS_ENABLED)
			)
		]);
}
else {
	if (array_key_exists($data['mfaid'], $data['mfas'])) {
		$mfa_name = $data['mfas'][$data['mfaid']];
	}
	else {
		$mfa_name = $mfa_index == -1 ? 'Disabled' : 'Default';
	}

	$form_grid
		->addItem([
			new CLabel(_('Frontend access')),
			new CFormField(
				(new CSpan(user_auth_type2str($data['gui_access'])))
					->addClass('text-field')
					->addClass(ZBX_STYLE_GREEN)
			)
		])
		->addItem([
			new CLabel(_('Multi-factor authentication')),
			new CFormField(
				(new CSpan($mfa_name))
					->addClass('text-field')
					->addClass(ZBX_STYLE_GREEN)
			)
		])
		->addItem([
			new CLabel(_('Enabled')),
			new CFormField(
				(new CSpan(_('Enabled')))
					->addClass('text-field')
					->addClass(ZBX_STYLE_GREEN)
			)
		]);
}

$form_grid
	->addItem([
		new CLabel(_('Debug mode'), 'debug_mode'),
		new CFormField(
			(new CCheckBox('debug_mode'))
				->setUncheckedValue(GROUP_DEBUG_MODE_DISABLED)
				->setChecked($data['debug_mode'] == GROUP_DEBUG_MODE_ENABLED)
		)
	]);

$template_permissions_form_grid = (new CFormGrid())
	->addItem(new CLabel(_('Permissions')))
	->addItem(
		new CFormField(
			(new CDiv(
				(new CTable())
					->setId('templategroup-right-table')
					->setAttribute('style', 'width: 100%;')
					->setHeader([_('Template groups'), _('Permissions'), ''])
					->addRow((new CRow())->addClass('js-templategroup-right-row-placeholder'))
					->addItem(
						(new CTag('tfoot', true))
							->addItem(
								new CCol((new CButtonLink(_('Add')))->addClass('js-add-templategroup-right-row')
								)
							)
					)
			))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		)
	);

$templategroup_right_row_template = (new CTemplateTag('templategroup-right-row-template'))->addItem(
	(new CRow([
		(new CMultiSelect([
			'name' => 'ms_templategroup_right[groupids][#{rowid}][]',
			'object_name' => 'templateGroup',
			'popup' => [
				'parameters' => [
					'srctbl' => 'template_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'ms_templategroup_right_groupids_#{rowid}_'
				]
			],
			'add_post_js' => false
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		(new CCol(
			(new CRadioButtonList('templategroup_right[permission][#{rowid}]', PERM_DENY))
				->addValue(_('Read-write'), PERM_READ_WRITE)
				->addValue(_('Read'), PERM_READ)
				->addValue(_('Deny'), PERM_DENY)
				->setModern(true)
		))->setAttribute('style', 'vertical-align: top'),
		(new CCol(
			(new CButtonLink(_('Remove')))->addClass('js-remove-table-row')
		))->setAttribute('style', 'vertical-align: top')
	]))
		->addClass('form_row')
);

$template_permissions_form_grid->addItem($templategroup_right_row_template);

$host_permissions_form_grid = (new CFormGrid())
	->addItem(new CLabel(_('Permissions')))
	->addItem(
		new CFormField(
			(new CDiv(
				(new CTable())
					->setId('hostgroup-right-table')
					->setAttribute('style', 'width: 100%;')
					->setHeader([_('Host groups'), _('Permissions'), ''])
					->addRow((new CRow())->addClass('js-hostgroup-right-row-placeholder'))
					->addItem(
						(new CTag('tfoot', true))
							->addItem(new CCol((new CButtonLink(_('Add')))->addClass('js-add-hostgroup-right-row')))
					)
			))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		)
	);

$host_permissions_row_template = (new CTemplateTag('hostgroup-right-row-template'))->addItem(
	(new CRow([
		(new CMultiSelect([
			'name' => 'ms_hostgroup_right[groupids][#{rowid}][]',
			'object_name' => 'hostGroup',
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'ms_hostgroup_right_groupids_#{rowid}_'
				]
			],
			'add_post_js' => false
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		(new CCol(
			(new CRadioButtonList('hostgroup_right[permission][#{rowid}]', PERM_DENY))
				->addValue(_('Read-write'), PERM_READ_WRITE)
				->addValue(_('Read'), PERM_READ)
				->addValue(_('Deny'), PERM_DENY)
				->setModern(true)
		))->setAttribute('style', 'vertical-align: top'),
		(new CCol(
			(new CButtonLink(_('Remove')))->addClass('js-remove-table-row')
		))->setAttribute('style', 'vertical-align: top')
	]))
		->addClass('form_row')
);

$host_permissions_form_grid->addItem($host_permissions_row_template);

$tag_filter_form_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Permissions')),
		new CFormField(
			(new CDiv(
				new CPartial('usergroup.tagfilters', [
					'tag_filters' => $data['tag_filters'],
					'tag_filters_badges' => $data['tag_filters_badges']
				])
			))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
				->setId('js-tag-filter-form-field')
		)
	]);

$tabs = (new CTabView())
	->addTab('user_group_tab', _('User group'), $form_grid)
	->addTab('template_permissions_tab', _('Template permissions'), $template_permissions_form_grid,
		TAB_INDICATOR_TEMPLATE_PERMISSIONS
	)
	->addTab('host_permissions_tab', _('Host permissions'), $host_permissions_form_grid,
		TAB_INDICATOR_HOST_PERMISSIONS
	)
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
				(new CUrl('zabbix.php'))
					->setArgument('action', 'usergroup.delete')
					->setArgument('usrgrpids', [$data['usrgrpid']])
					->setArgument(CSRF_TOKEN_NAME, $csrf_token),
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

$form
	->addItem($tabs)
	->addItem(
		(new CScriptTag('view.init('.json_encode([
			'templategroup_rights' => $data['templategroup_rights'],
			'hostgroup_rights' => $data['hostgroup_rights'],
			'tag_filters' => $data['tag_filters'],
			'can_update_group' => $data['can_update_group'],
			'ldap_status' => array_key_exists('ldap_status', $data) ? $data['ldap_status'] : 0,
			'mfa_status' => $data['mfa_config_status']
		]).');'))->setOnDocumentReady()
	);

$html_page
	->addItem($form)
	->show();
