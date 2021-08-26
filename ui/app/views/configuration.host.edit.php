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

$cancel_button = (new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))->setArgument('action', 'host.list')))
	->setAttribute('id', 'host-cancel');

$data += [
	'form_name' => 'host-form',
	'buttons' => ($data['hostid'] == 0)
		? [
			(new CSubmit('add', _('Add')))->setAttribute('id', 'host-add'),
			$cancel_button
		]
		: [
			(new CSubmit('update', _('Update')))->setAttribute('id', 'host-update'),
			(new CButton('clone', _('Clone')))->setAttribute('id', 'host-clone'),
			(new CButton('full_clone', _('Full clone')))->setAttribute('id', 'host-full_clone'),
			(new CButton('delete', _('Delete')))
				->onClick('return confirm('.json_encode(_('Delete selected host?')).')
					? host_edit.deleteHost()
					: false')
				->setAttribute('id', 'host-delete')
				->setAttribute('data-redirect', (new CUrl('zabbix.php'))
					->setArgument('action', 'host.massdelete')
					->setArgument('ids', [$data['hostid']])
					->setArgumentSID()
					->getUrl()
				),
			$cancel_button
		]
];

if ($data['warning']) {
	CMessageHelper::addWarning($data['warning']);
	show_messages();

	$data['warning'] = null;
}

(new CWidget())
	->setTitle(_('Host'))
	->addItem(new CPartial('configuration.host.edit.html', $data))
	->show();
