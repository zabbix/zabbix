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
?>

window.host_edit_popup = {
	overlay: null,
	dialogue: null,
	form: null,

	init() {
		this.overlay = overlays_stack.getById('host_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		host_edit.init();
	},

	deleteHost(e, hostid) {
		debugger;
		// const original_curl = new Curl(host_popup.original_url);

		// if (basename(original_curl.getPath()) === 'hostinventories.php') {
			// original_curl.unsetArgument('hostid');
			// original_curl.unsetArgument('sid');
			// host_popup.original_url = original_curl.getUrl();
		// }

		// return hosts_delete(document.getElementById('<?//= $data['form_name'] ?>//'));


		const button = e.target;
		button.classList.add('is-loading');

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'host.massdelete');

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData({ids: [hostid]})
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				this.form.dispatchEvent(new CustomEvent('dialogue.delete', {
					detail: {
						success: response.success
					}
				}));
			})
			.catch((exception) => {
				let title;
				let messages = [];

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					title = <?= json_encode(_('Unexpected server error.')) ?>;
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.form.parentNode.insertBefore(message_box, this.form);

				clearMessages();
				addMessage(message_box);
			})
			.finally(() => {
				button.classList.remove('is-loading');
			});
	}
}
