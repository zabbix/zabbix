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
			new CButton('clone', _('Clone')),
			new CButton('full_clone', _('Full clone')),
			(new CButton('delete', _('Delete')))
				->onClick("return confirm(".json_encode(_('Delete selected host?')).")
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
		'host_edit.submit(this);'.
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
	'});'.

	'var cloneBtn = document.getElementById("clone");'.
	'if (cloneBtn) {'.
		'cloneBtn.addEventListener("click", function () {'.
			'var curl = new Curl("zabbix.php", false),'.
				'fields = host_edit.getCloneData(this.form);'.

			'curl.setArgument("action", "host.edit");'.
			'curl.setArgument("clone", 1);'.

			'for (const [k, v] of Object.entries(fields)) {'.
				'curl.setArgument(k, v);'.
			'}'.

			'redirect(curl.getUrl(), "post");'.
		'});'.
	'}'.

	'var fullCloneBtn = document.getElementById("full_clone");'.
	'if (fullCloneBtn) {'.
		'fullCloneBtn.addEventListener("click", function () {'.
			'var curl = new Curl("zabbix.php", false),'.
				'fields = host_edit.getCloneData(this.form);'.

			'curl.setArgument("action", "host.edit");'.
			'curl.setArgument("full_clone", 1);'.

			'for (const [k, v] of Object.entries(fields)) {'.
				'curl.setArgument(k, v);'.
			'}'.

			'redirect(curl.getUrl(), "post");'.
		'});'.
	'}'
))
	->setOnDocumentReady()
	->show();

(new CWidget())
	->setTitle(_('Host'))
	->addItem(new CPartial('configuration.host.edit.html', $data))
	->show();
