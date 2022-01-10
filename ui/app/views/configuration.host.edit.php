<?php declare(strict_types = 1);
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

$this->includeJsFile('configuration.host.edit.js.php');

$cancel_button = (new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))->setArgument('action', 'host.list')))
	->addClass('js-cancel');

$data += [
	'form_name' => 'host-form',
	'buttons' => ($data['hostid'] == 0)
		? [
			(new CSubmit('add', _('Add')))
				->removeAttribute('id'),
			$cancel_button
		]
		: [
			(new CSubmit('update', _('Update')))
				->removeAttribute('id'),
			(new CSimpleButton(_('Clone')))
				->onClick('view.clone();')
				->removeAttribute('id'),
			(new CSimpleButton(_('Full clone')))
				->onClick('view.fullClone();')
				->removeAttribute('id'),
			(new CSimpleButton(_('Delete')))
				->setAttribute('confirm', _('Delete selected host?'))
				->onClick('view.delete('.json_encode($data['hostid']).', this);')
				->removeAttribute('id'),
			$cancel_button
		]
];

if ($data['warning']) {
	CMessageHelper::addWarning($data['warning']);
	show_messages();

	$data['warning'] = null;
}

(new CWidget())
	->setTitle(($data['hostid'] == 0) ? _('New host') : _('Host'))
	->addItem(new CPartial('configuration.host.edit.html', $data))
	->show();

(new CScriptTag('view.init('.json_encode([
		'form_name' => $data['form_name'],
		'host_interfaces' => $data['host']['interfaces'],
		'host_is_discovered' => ($data['host']['flags'] == ZBX_FLAG_DISCOVERY_CREATED)
	]).');'))
	->setOnDocumentReady()
	->show();
