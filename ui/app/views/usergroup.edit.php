<?php
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

$this->includeJsFile('usergroup.edit.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('User groups'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::USERS_USERGROUP_EDIT));

$csrf_token = CCsrfTokenHelper::get('usergroup');

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, $csrf_token))->removeId())
	->setId('user-group-form')
	->setName('user_group_form')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addVar('usrgrpid', $data['usrgrpid']);

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
		->setAdaptiveWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

	foreach ($data['userdirectories'] as $db_userdirectory) {
		$userdirectory->addOption((new CSelectOption($db_userdirectory['userdirectoryid'], $db_userdirectory['name'])));
	}

	$ldap_warning = (makeWarningIcon(_('LDAP authentication is disabled system-wide.')))
		->addStyle('display: none')
		->setId('ldap-warning');

	$mfa_warning = (makeWarningIcon(_('Multi-factor authentication is disabled system-wide.')))
		->addStyle('display: none')
		->setId('mfa-warning');

	$mfa_select = (new CSelect('mfaid'))
		->setValue($data['mfaid'])
		->setDisabled($data['mfa_status'] == GROUP_MFA_DISABLED)
		->addOption((new CSelectOption(0, _('Default')))
			->addClass(ZBX_STYLE_DEFAULT_OPTION)
		)
		->setFocusableElementId('mfaid')
		->setWidthAuto();

	foreach ($data['mfas'] as $db_mfa) {
		$mfa_select->addOption((new CSelectOption($db_mfa['mfaid'], $db_mfa['name'])));
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
			(new CLabel([_('Multi-factor authentication'), $mfa_warning], 'mfa_status')),
			new CFormField(
				(new CDiv([
					(new CCheckBox('mfa_status'))
						->setChecked($data['mfa_status'] == GROUP_MFA_ENABLED)
						->setUncheckedValue(GROUP_MFA_DISABLED),
					$mfa_select
				]))
					->addClass(CFormField::ZBX_STYLE_FORM_FIELD_INLINE)
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
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
		$mfa_name = $data['mfas'][$data['mfaid']]['name'];
	}
	else {
		$mfa_name = $data['mfa_status'] == GROUP_MFA_DISABLED ? _('Disabled') : _('Default');
	}

	$userdirectory_name = array_key_exists($data['userdirectoryid'], $data['userdirectories'])
		? $data['userdirectories'][$data['userdirectoryid']]['name']
		: _('Default');

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
			new CLabel(_('LDAP Server')),
			new CFormField(
				(new CSpan($userdirectory_name))
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
					->setAttribute('data-field-type', 'set')
					->setAttribute('data-field-name', 'templategroup_rights')
			))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		)
	);

$templategroup_right_row_template = (new CTemplateTag('templategroup-right-row-template'))->addItem(
	(new CRow([
		(new CMultiSelect([
			'name' => 'templategroup_rights[#{rowid}][groupids][]',
			'object_name' => 'templateGroup',
			'popup' => [
				'parameters' => [
					'srctbl' => 'template_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'templategroup_rights_#{rowid}_groupids_'
				]
			],
			'add_post_js' => false
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		(new CCol(
			(new CRadioButtonList('templategroup_rights[#{rowid}][permission]', PERM_DENY))
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
					->setAttribute('data-field-type', 'set')
					->setAttribute('data-field-name', 'hostgroup_rights')
			))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		)
	);

$host_permissions_row_template = (new CTemplateTag('hostgroup-right-row-template'))->addItem(
	(new CRow([
		(new CMultiSelect([
			'name' => 'hostgroup_rights[#{rowid}][groupids][]',
			'object_name' => 'hostGroup',
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'hostgroup_rights_#{rowid}_groupids_'
				]
			],
			'add_post_js' => false
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		(new CCol(
			(new CRadioButtonList('hostgroup_rights[#{rowid}][permission]', PERM_DENY))
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
	->addTab('tag_filter_tab', _('Problem tag filter'), $tag_filter_form_grid, TAB_INDICATOR_TAG_FILTER)
	->setSelected(0);

$cancel_button = (new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
	->setArgument('action', 'usergroup.list')
	->setArgument('page', CPagerHelper::loadPage('usergroup.list', null))
))->addClass('js-cancel');

if ($data['usrgrpid'] != 0) {
	$tabs->setFooter(makeFormFooter(
		(new CSubmit('', _('Update')))->addClass('js-submit'),
		[
			(new CSimpleButton(_('Delete')))->addClass('js-delete'),
			$cancel_button
		]
	));
}
else {
	$tabs->setFooter(makeFormFooter(
		(new CSubmit('', _('Add')))->addClass('js-submit'),
		[
			$cancel_button
		]
	));
}

$form->addItem($tabs);

$html_page
	->addItem($form)
	->show();

(new CScriptTag(
	'view.init('.json_encode([
		'rules' => $data['js_validation_rules'],
		'templategroup_rights' => $data['templategroup_rights'],
		'hostgroup_rights' => $data['hostgroup_rights'],
		'tag_filters' => $data['tag_filters'],
		'can_update_group' => $data['can_update_group'],
		'ldap_status' => array_key_exists('ldap_status', $data) ? $data['ldap_status'] : 0,
		'mfa_status' => $data['mfa_config_status']
	]).');'
))
	->setOnDocumentReady()
	->show();
