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
	const view = new class {
		init({context, hostid}) {
			this.context = context;
			this.hostid = hostid;

			this.#initActions();
		}

		#initActions() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-trigger-edit')) {
					this.#edit('trigger.edit', {
						triggerid: e.target.dataset.triggerid,
						hostid: this.hostid,
						context: e.target.dataset.context
					})
				}
				else if (e.target.id === 'js-create') {
					this.#edit('trigger.prototype.edit', {
						parent_discoveryid: e.target.dataset.parent_discoveryid,
						hostid: this.hostid,
						context: this.context
					})
				}
				else if (e.target.classList.contains('js-trigger-prototype-edit')) {
					this.#edit('trigger.prototype.edit', {
						parent_discoveryid: e.target.dataset.parent_discoveryid,
						triggerid: e.target.dataset.triggerid,
						hostid: this.hostid,
						context: this.context
					})
				}
			})
		}

		#edit(action, parameters) {
			const overlay = PopUp(action, parameters, {
				dialogueid: 'trigger-edit',
				dialogue_class: 'modal-popup-medium',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});

			overlay.$dialogue[0].addEventListener('dialogue.delete', (e) => {
				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});
		}

		editHost(e, hostid) {
			e.preventDefault();
			const host_data = {hostid};

			this.openHostPopup(host_data);
		}

		openHostPopup(host_data) {
			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			['dialogue.create', 'dialogue.update', 'dialogue.delete'].forEach((event_type) => {
				overlay.$dialogue[0].addEventListener(event_type, (e) => {
					const data = e.detail;

					if ('success' in data) {
						postMessageOk(data.success.title);

						if ('messages' in data.success) {
							postMessageDetails('success', data.success.messages);
						}
					}

					if (event_type === 'dialogue.delete') {
						const curl = new Curl('zabbix.php');
						curl.setArgument('action', 'host.list');

						location.href = curl.getUrl();
					}
					else {
						location.href = location.href;
					}

				}, {once: true});
			});

			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		}
	}
</script>
