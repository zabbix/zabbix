<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
		checkbox_object: null,
		checkbox_hash: null,
		token: null,

		init({checkbox_hash, checkbox_object, context, token}) {
			this.checkbox_hash = checkbox_hash;
			this.checkbox_object = checkbox_object;
			this.context = context;
			this.token = token;

			this.initEvents();
		},

		initEvents() {
			if (this.context === 'host') {
				document.getElementById('filter_state').addEventListener('change', e => this.updateFieldsVisibility());
				document.querySelector('.js-massexecute-item')
					.addEventListener('click', e => this.executeNow(e.target));
			}
		},

		updateFieldsVisibility() {
			const disabled = document.querySelector('[name="filter_state"]:checked').value != -1;

			document.querySelectorAll('[name="filter_status"]').forEach(radio => radio.disabled = disabled);
		},

		editHost(e, hostid) {
			e.preventDefault();
			const host_data = {hostid};

			this.openHostPopup(host_data);
		},

		openHostPopup(host_data) {
			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.hostDelete, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		},

		executeNow(button) {
			button.classList.add('is-loading');

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.execute');

			const data = {
				itemids: Object.keys(chkbxRange.getSelectedIds()),
				discovery_rule: 1
			}
			data[this.token[0]] = this.token[1];

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(data)
			})
				.then((response) => response.json())
				.then((response) => {
					clearMessages();

					if ('error' in response) {
						addMessage(makeMessageBox('bad', [response.error.messages], response.error.title, true, true));
					}
					else if('success' in response) {
						addMessage(makeMessageBox('good', [], response.success.title, true, false));

						const uncheckids = Object.keys(chkbxRange.getSelectedIds());
						uncheckTableRows('host_discovery_' + this.checkbox_hash, [], false);
						chkbxRange.checkObjects(this.checkbox_object, uncheckids, false);
						chkbxRange.update(this.checkbox_object);
					}
				})
				.catch(() => {
					const title = <?= json_encode(_('Unexpected server error.')) ?>;
					const message_box = makeMessageBox('bad', [], title)[0];

					clearMessages();
					addMessage(message_box);
				})
				.finally(() => {
					button.classList.remove('is-loading');

					// Deselect the "Execute now" button in both success and error cases, since there is no page reload.
					button.blur();
				});
		},

		events: {
			hostSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				location.href = location.href;
			},

			hostDelete(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				const curl = new Curl('zabbix.php');
				curl.setArgument('action', 'host.list');

				location.href = curl.getUrl();
			}
		}
	};
</script>
