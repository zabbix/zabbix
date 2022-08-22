<?php
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
 */
?>

<script>
	const view = {
		init({eventsource}) {
			this.eventsource = eventsource;

			document.addEventListener('click', (e) => {

				if (e.target.classList.contains('js-action-create')) {

					// todo: clean init -> make this edit function

					const overlay = this.openActionPopup({eventsource: eventsource});

					overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
						postMessageOk(e.detail.title);

						if ('messages' in e.detail) {
							postMessageDetails('success', e.detail.messages);
						}
						location.href = location.href;
					});
				}
				else if (e.target.classList.contains('js-action-edit')) {

					// todo: clean init -> make this edit function

					const overlay = this.openActionPopup({
						eventsource: eventsource,
						actionid: e.target.attributes.actionid.nodeValue
					});
					overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
						postMessageOk(e.detail.title);

						if ('messages' in e.detail) {
							postMessageDetails('success', e.detail.messages);
						}
						location.href = location.href;
					});

					overlay.$dialogue[0].addEventListener('dialogue.delete', this.actionDelete, {once: true});
				}

				else if (e.target.classList.contains('action-delete')) {
					this.massDeleteActions()
				}

			})
		},

		actionDelete(e) {
			const data = e.detail;

			if ('success' in data) {
				postMessageOk(data.success.title);

				if ('messages' in data.success) {
					postMessageDetails('success', data.success.messages);
				}
			}

			uncheckTableRows('actionForm');
			location.href = location.href;
		},

		openActionPopup(parameters = {}) {
			return PopUp('popup.action.edit', parameters, {
				dialogueid: 'action-edit',
				dialogue_class: 'modal-popup-large'
			});
		},

		massDeleteActions() {
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'action.delete');
			curl.setArgument('eventsource', this.eventsource);

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: urlEncodeData({g_actionid: Object.keys(chkbxRange.getSelectedIds())})
			})
				.then((response) => response.json())
				.then((response) => {
					debugger;
					console.log(chkbxRange.getSelectedIds());
					debugger;
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('g_actionid');
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('g_actionid');
					}

					location.href = location.href;
				})
				.catch(() => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

					addMessage(message_box);
				})
				.finally(() => {
					uncheckTableRows('g_actionid');
				});
		},
	};
</script>
