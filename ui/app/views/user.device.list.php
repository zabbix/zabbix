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

$this->addJsFile('qrcode.js');
$this->includeJsFile('user.device.list.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Devices'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::USERS_DEVICE))
	->setControls((new CList([
		(new CTag('nav', true,
			(new CList())->addItem(
				(new CSimpleButton(_('Add device')))
					->addClass('js-create-device')
					->setEnabled(CWebUser::checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_USER))
			)
		))->setAttribute('aria-label', _('Content controls'))
	])))
	->addItem((new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'user.device.list'))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [

			(new CFormGrid())
				->addItem([
					new CLabel(_('Users'), 'filter_userids__ms'),
					new CFormField(
						(new CMultiSelect([
							'name' => 'filter_userids[]',
							'object_name' => 'users',
							'data' => $data['filter']['ms_users'],
							'popup' => [
								'parameters' => [
									'srctbl' => 'users',
									'srcfld1' => 'userid',
									'srcfld2' => 'fullname',
									'dstfrm' => CFilter::FORM_NAME,
									'dstfld1' => 'filter_userids_'
								]
							]
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
				])
				->addItem([
					new CLabel(_('User roles'), 'filter_roleids__ms'),
					new CFormField(
						(new CMultiSelect([
							'name' => 'filter_roleids[]',
							'object_name' => 'roles',
							'data' => $data['filter']['ms_roles'],
							'popup' => [
								'parameters' => [
									'srctbl' => 'roles',
									'srcfld1' => 'roleid',
									'dstfrm' => CFilter::FORM_NAME,
									'dstfld1' => 'filter_roleids_'
								]
							]
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
				])
				->addItem([
					new CLabel(_('User groups'), 'filter_usrgrpids__ms'),
					new CFormField(
						(new CMultiSelect([
							'name' => 'filter_usrgrpids[]',
							'object_name' => 'usersGroups',
							'data' => $data['filter']['ms_usrgrps'],
							'popup' => [
								'parameters' => [
									'srctbl' => 'usrgrp',
									'srcfld1' => 'usrgrpid',
									'dstfrm' => CFilter::FORM_NAME,
									'dstfld1' => 'filter_usrgrpids_'
								]
							]
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
				])
				->addItem([
					new CLabel(_('Status'), 'filter_status'),
					new CFormField(
						(new CRadioButtonList('filter_status', (int) $data['filter']['filter_status']))
							->addValue(_('Any'), -1)
							->addValue(_('New'), ZBX_DEVICE_STATUS_NEW)
							->addValue(_('Active'), ZBX_DEVICE_STATUS_ACTIVATED)
							->addValue(_('Orphaned'), ZBX_DEVICE_STATUS_ORPHANED)
							->setModern()
					)
				])
		])
		->addVar('action', 'user.device.list')
	);

$deviceForm = (new CForm())
	->setName('devices');

$deviceTable = (new CTableInfo())
	->setHeader([
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $data['url']),
		make_sorting_header(_('Device ID'), 'uuid', $data['sort'], $data['sortorder'], $data['url']),
		make_sorting_header(_('User'), 'user_fullname', $data['sort'], $data['sortorder'], $data['url']),
		make_sorting_header(_('User role'), 'user_role', $data['sort'], $data['sortorder'], $data['url']),
		make_sorting_header(_('Linked on'), 'activated_at', $data['sort'], $data['sortorder'], $data['url']),
		make_sorting_header(_('Last active'), 'lastaccess', $data['sort'], $data['sortorder'], $data['url']),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $data['url']),
		$data['has_access'][CRoleHelper::DEVICES_ACTIONS_MANAGE_USER] ? '' : null
	])
	->setPageNavigation($data['paging']);

foreach ($data['devices'] as $device) {
	$can_manage = CWebUser::$data['userid'] == $device['userid']
		? $data['has_access'][CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN]
		: $data['has_access'][CRoleHelper::DEVICES_ACTIONS_MANAGE_USER];

	$deviceTable->addRow([
		$device['name'],
		$device['uuid'],
		$device['user_fullname'],
		$device['user_role'],
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $device['activated_at']),
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $device['lastaccess']),
		$data['device_statuses'][$device['status']],
		$data['has_access'][CRoleHelper::DEVICES_ACTIONS_MANAGE_USER] && $can_manage
			? (new CButton('', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->removeId()
				->setEnabled(true)
				->setAttribute('data-deviceid', $device['deviceid'])
				->addClass('js-device-delete')
			: null
	]);
}

$deviceForm->addItem($deviceTable);

$html_page
	->addItem($deviceForm)
	->show();

$confirm_messages = [
	'user.device.delete' => _('Remove selected device?')
];

(new CScriptTag('
	view.init('.json_encode([
		'confirm_messages' => $confirm_messages
	]).');
'))
	->setOnDocumentReady()
	->show();
