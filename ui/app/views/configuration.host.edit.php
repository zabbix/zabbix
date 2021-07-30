<?php
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

$data += [
	'form_name' => 'host-form',
	'buttons' => ($data['hostid'] == 0)
		? [
			new CSubmit('add', _('Add')),
			(new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))->setArgument('action', 'host.list')))
		]
		: [
			new CSubmit('update', _('Update')),
			new CSubmit('clone', _('Clone')),
			new CSubmit('full_clone', _('Full clone')),
			(new CButton('delete', _('Delete selected host?')))
				->onClick("return confirm('".json_encode(_('Delete selected host?'))."')
					? host_edit.deleteHost()
					: false")
				->setAttribute('data-redirect', (new CUrl('zabbix.php'))
					->setArgument('action', 'host.massdelete')
					->setArgument('ids', [$data['hostid']])
					->setArgumentSID()
					->getUrl()
				)
				->addClass(ZBX_STYLE_BTN_ALT),
			(new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))->setArgument('action', 'host.list')))
		]
];

(new CScriptTag(
	'document.getElementById("'.$data['form_name'].'").addEventListener("submit", function (event) {'.
		'host_edit.submit(this, this.closest("main"));'.
		'event.preventDefault();'.
	'});'.

	'document.getElementById("'.$data['form_name'].'").addEventListener("formSubmitted", event => {'.
		'let response = event.detail;'.

		'clearMessages();'.
		'if ("errors" in response) {'.
			'addMessage(response.errors);'.
		'}'.
		'else if ("hostid" in response) {'.
			'postMessageOk(response.message);'.

			'const url = new Curl("zabbix.php");'.
			'url.setArgument("action", "host.list");'.
			'window.location = url.getUrl();'.
		'}'.
	'});'
))
	->setOnDocumentReady()
	->show();

(new CWidget())
	->setTitle(_('Host'))
	->addItem(new CPartial('configuration.host.edit.html', $data))
	->show();
