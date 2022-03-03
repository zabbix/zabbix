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
?>


<script>
	const view = new class {

		constructor() {
			this.enable_hosts_url = null;
			this.disable_hosts_url = null;
			this.delete_url = null;
		}

		init({enable_hosts_url, disable_hosts_url, delete_url}) {
			this.enable_hosts_url = enable_hosts_url;
			this.disable_hosts_url = disable_hosts_url;
			this.delete_url = delete_url;

			this.registerEvents();

			this.initActionButtons();
		}

		initActionButtons() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-create-proxy')) {
					this.edit();
				}
				else if (e.target.classList.contains('js-edit-proxy')) {
					this.edit({proxyid: e.target.dataset.proxyid});
				}
				else if (e.target.classList.contains('js-edit-host')) {
					this.editHost(e.target.dataset.hostid);
				}
				else if (e.target.classList.contains('js-massenable-proxy-host')) {
					this.enableHosts(e.target, Object.values(chkbxRange.getSelectedIds()));
				}
				else if (e.target.classList.contains('js-massdisable-proxy-host')) {
					this.disableHosts(e.target, Object.values(chkbxRange.getSelectedIds()));
				}
				else if (e.target.classList.contains('js-massdelete-proxy')) {
					this.delete(e.target, Object.values(chkbxRange.getSelectedIds()));
				}
			});
		}

		edit(parameters = {}) {
			const overlay = PopUp('popup.proxy.edit', parameters, {
				dialogueid: 'proxy_edit',
				dialogue_class: 'modal-popup-medium'
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				postMessageOk(e.detail.title);

				if (e.detail.messages !== null) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});

			overlay.$dialogue[0].addEventListener('dialogue.delete', (e) => {
				uncheckTableRows('service');

				postMessageOk(e.detail.title);

				if (e.detail.messages !== null) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});
		}

		editHost(hostid) {
			const original_url = location.href;

			const overlay = PopUp('popup.host.edit', {hostid}, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large'
			});

			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.editHostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.editHostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.editHostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		}

		enableHosts(target, proxyids) {
			if (!window.confirm(<?= json_encode(_('Enable hosts monitored by selected proxies?')) ?>)) {
				return;
			}

			this.post(target, proxyids, this.enable_hosts_url);
		}

		disableHosts(target, proxyids) {
			if (!window.confirm(<?= json_encode(_('Disable hosts monitored by selected proxies?')) ?>)) {
				return;
			}

			this.post(target, proxyids, this.disable_hosts_url);
		}

		delete(target, proxyids) {
			const confirmation = proxyids.length > 1
				? <?= json_encode(_('Delete selected proxies?')) ?>
				: <?= json_encode(_('Delete selected proxy?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			this.post(target, proxyids, this.delete_url);
		}

		post(target, proxyids, url) {
			target.classList.add('is-loading');

			return fetch(url, {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({proxyids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('proxy', 'keepids' in response.error ? response.error.keepids : []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('proxy');
					}

					location.href = location.href;
				})
				.catch(() => {
					const title = <?= json_encode(_('Unexpected server error.')) ?>;
					const message_box = makeMessageBox('bad', [], title, true, false)[0];

					clearMessages();
					addMessage(message_box);
				})
				.finally(() => {
					target.classList.remove('is-loading');
				});
		}

		registerEvents() {
			this.events = {
				editHostSuccess(e) {
					const data = e.detail;

					if ('success' in data) {
						postMessageOk(data.success.title);

						if ('messages' in data.success) {
							postMessageDetails('success', data.success.messages);
						}
					}

					location.href = location.href;
				}
			};
		}
	};
</script>
