<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

$data['form_name'] = 'host-form';
$popup_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'host.edit');

if ($data['hostid'] == 0) {
	if (array_key_exists('groupids', $data) && $data['groupids']) {
		$popup_url->setArgument('groupids', $data['groupids']);
	}
	elseif ($data['clone_hostid'] !== null) {
		$popup_url->setArgument('hostid', $data['clone_hostid']);

		if ($data['full_clone'] === 1) {
			$popup_url->setArgument('full_clone', 1);
		}
		else {
			$popup_url->setArgument('clone', 1);
		}
	}

	$buttons = [
		[
			'title' => _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'host_edit_popup.submit();'
		]
	];
}
else {
	$popup_url->setArgument('hostid', $data['hostid']);

	$buttons = [
		[
			'title' => _('Update'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'host_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => 'btn-alt',
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'host_edit_popup.clone();'
		],
		[
			'title' => _('Full clone'),
			'class' => 'btn-alt',
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'host_edit_popup.fullClone();'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete selected host?'),
			'class' => 'btn-alt',
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'host_edit_popup.delete('.json_encode($data['hostid']).');'
		]
	];
}

$output = [
	'header' => ($data['hostid'] == 0) ? _('New host') : _('Host'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::CONFIGURATION_HOST_EDIT),
	'body' => (new CPartial('configuration.host.edit.html', $data))->getOutput(),
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.host.edit.js.php').
		'host_edit_popup.init('.json_encode([
			'popup_url' => $popup_url->getUrl(),
			'form_name' => $data['form_name'],
			'host_interfaces' => $data['host']['interfaces'],
			'host_is_discovered' => ($data['host']['flags'] == ZBX_FLAG_DISCOVERY_CREATED),
			'warning' => $data['warning']
		]).');',
	'buttons' => $buttons
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
