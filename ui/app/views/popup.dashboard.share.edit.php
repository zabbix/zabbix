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

$form = (new CForm('post'))
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('dashboard')))->removeId())
	->setId('dashboard-share-form')
	->setName('dashboard_share_form')
	->addItem(getMessages());

$table_user_groups = (new CTable())
	->setHeader([_('User groups'), _('Permissions'), _('Action')])
	->addRow(
		(new CRow(
			(new CCol(
				(new CButtonLink(_('Add')))->onClick(
					'return PopUp("popup.generic", '.json_encode([
						'srctbl' => 'usrgrp',
						'srcfld1' => 'usrgrpid',
						'srcfld2' => 'name',
						'dstfrm' => $form->getName(),
						'multiselect' => '1'
					]).', {dialogue_class: "modal-popup-generic"});'
				)
			))->setColSpan(3)
		))->setId('user-group-list-footer')
	)
	->addStyle('width: 100%;');

$table_users = (new CTable())
	->setHeader([_('Users'), _('Permissions'), _('Action')])
	->addRow(
		(new CRow(
			(new CCol(
				(new CButtonLink(_('Add')))->onClick(
					'return PopUp("popup.generic", '.json_encode([
						'srctbl' => 'users',
						'srcfld1' => 'userid',
						'srcfld2' => 'fullname',
						'dstfrm' => $form->getName(),
						'multiselect' => '1'
					]).', {dialogue_class: "modal-popup-generic"});'
				)
			))->setColSpan(3)
		))->setId('user-list-footer')
	)
	->addStyle('width: 100%;');

$user_group_row_template = (new CRow([
	new CCol([
		(new CTextBox('userGroups[#{usrgrpid}][usrgrpid]', '#{usrgrpid}'))->setAttribute('type', 'hidden'),
		'#{name}'
	]),
	new CCol(
		(new CRadioButtonList('userGroups[#{usrgrpid}][permission]', PERM_READ))
			->addValue(_('Read-only'), PERM_READ, 'user-group-#{usrgrpid}-permission-'.PERM_READ)
			->addValue(_('Read-write'), PERM_READ_WRITE, 'user-group-#{usrgrpid}-permission-'.PERM_READ_WRITE)
			->setModern(true)
	),
	(new CCol(
		(new CButton('remove', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->onClick('dashboard_share_edit_popup.removeUserGroupShares("#{usrgrpid}");')
			->removeId()
	))->addClass(ZBX_STYLE_NOWRAP)
]))
	->setId('user-group-shares-#{usrgrpid}')
	->toString();

$user_row_template = (new CRow([
	new CCol([
		(new CTextBox('users[#{id}][userid]', '#{id}'))->setAttribute('type', 'hidden'),
		'#{name}'
	]),
	new CCol(
		(new CRadioButtonList('users[#{id}][permission]', PERM_READ))
			->addValue(_('Read-only'), PERM_READ, 'user_#{id}_permission_'.PERM_READ)
			->addValue(_('Read-write'), PERM_READ_WRITE, 'user_#{id}_permission_'.PERM_READ_WRITE)
			->setModern(true)
	),
	(new CCol(
		(new CButton('remove', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->onClick('dashboard_share_edit_popup.removeUserShares("#{id}");')
			->removeId()
	))->addClass(ZBX_STYLE_NOWRAP)
]))
	->setId('user-shares-#{id}')
	->toString();

$form
	->addItem(getMessages())
	->addItem(new CInput('hidden', 'dashboardid', $data['dashboard']['dashboardid']))
	// indicator to help delete all users
	->addItem(new CInput('hidden', 'users['.CControllerDashboardShareUpdate::EMPTY_USER.']', '1'))
	// indicator to help delete all user groups
	->addItem(new CInput('hidden', 'userGroups['.CControllerDashboardShareUpdate::EMPTY_GROUP.']', '1'))
	->addItem((new CFormList('sharing_form'))
		->addRow(_('Type'),
			(new CRadioButtonList('private', PRIVATE_SHARING))
				->addValue(_('Private'), PRIVATE_SHARING)
				->addValue(_('Public'), PUBLIC_SHARING)
				->setModern(true)
		)
		->addRow(_('List of user group shares'),
			(new CDiv($table_user_groups))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		)
		->addRow(_('List of user shares'),
			(new CDiv($table_users))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		)
	)
	->addItem(
		(new CScriptTag('
			dashboard_share_edit_popup.init('.json_encode([
				'dashboard' => $data['dashboard'],
				'user_group_row_template' => $user_group_row_template,
				'user_row_template' => $user_row_template
			]).');
		'))->setOnDocumentReady()
	);

$output = [
	'header' => _('Dashboard sharing'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DASHBOARDS_SHARE_EDIT),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Update'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'dashboard_share_edit_popup.submit();'
		]
	],
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.dashboard.share.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
