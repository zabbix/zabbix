<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

$data['form_name'] = 'host-form';
$data['popup_form'] = true;
$popup_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'host.edit');

if ($data['hostid'] == 0) {
	$popup_url->setArgument('groupids', $data['groupids']); // TODO VM: check
	$buttons = [
		[
			'id' => 'host-add',
			'title' => _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'host_edit.submit(document.getElementById("'.$data['form_name'].'"));'
		]
	];
}
else {
	$popup_url->setArgument('hostid', $data['hostid']);
	$buttons = [
		[
			'id' => 'host-update',
			'title' => _('Update'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'host_edit_popup.submit();'
		],
		[
			'id' => 'host-clone',
			'title' => _('Clone'),
			'class' => 'btn-alt',
			'keepOpen' => true,
			'isSubmit' => false
		],
		[
			'id' => 'host-full_clone',
			'title' => _('Full clone'),
			'class' => 'btn-alt',
			'keepOpen' => true,
			'isSubmit' => false
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete selected host?'),
			'class' => 'btn-alt',
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'host_edit_popup.deleteHost(event, "'.$data['hostid'].'");'
		]
	];
}

if ($data['warning']) {
	$data['warning'] = makeMessageBox(ZBX_STYLE_MSG_WARNING, [['message' => $data['warning']]]);
}

$output = [
	'header' => ($data['hostid'] == 0) ? _('New host') : _('Host'),
	'body' => (new CPartial('configuration.host.edit.html', $data))->getOutput(),
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.host.edit.js.php').
		'host_edit_popup.init('.json_encode([
			'popup_url' => $popup_url->getUrl()
		]).');',
	'buttons' => $buttons,
	'cancel_action' => 'host_edit_popup.closePopup();'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
