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
$this->includeJsFile('userprofile.device.list.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Devices'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::USERS_USERPROFILE_DEVICE))
	->setControls((new CList([
		(new CTag('nav', true,
			(new CList())->addItem(
				(new CSimpleButton(_('Add device')))
					->addClass('js-create-device')
					->setEnabled(CWebUser::checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN))
			)
		))->setAttribute('aria-label', _('Content controls'))
	])));

$deviceForm = (new CForm())
	->setName('devices');

$deviceTable = (new CTableInfo())
	->setHeader([
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $data['url']),
		make_sorting_header(_('Device ID'), 'uuid', $data['sort'], $data['sortorder'], $data['url']),
		make_sorting_header(_('Linked on'), 'activated_at', $data['sort'], $data['sortorder'], $data['url']),
		make_sorting_header(_('Last active'), 'lastaccess', $data['sort'], $data['sortorder'], $data['url']),
		$data['has_access'][CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN] ? '' : null
	])
	->setPageNavigation($data['paging']);

foreach ($data['devices'] as $device) {
	$deviceTable->addRow([
		$device['name'],
		$device['uuid'],
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $device['activated_at']),
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $device['lastaccess']),
		$data['has_access'][CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN]
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
