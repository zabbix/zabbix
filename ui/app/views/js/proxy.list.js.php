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
?>


<script>
	const view = new class {

		init() {
			this._initActions();
		}

		_initActions() {
			document
				.querySelector('.js-create-proxy')
				.addEventListener('click', () => this._edit());

			const form = document.getElementById('proxy-list');

			form.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-edit-proxy')) {
					this._edit({proxyid: e.target.dataset.proxyid});
				}
				else if (e.target.classList.contains('js-edit-host')) {
					this._editHost(e.target.dataset.hostid);
				}
			});

			form
				.querySelector('.js-refresh-proxy-config')
				.addEventListener('click', (e) => {
					this._refreshConfig(e.target, Object.keys(chkbxRange.getSelectedIds()));
				});

			form
				.querySelector('.js-massenable-proxy-host')
				.addEventListener('click', (e) => {
					this._enableHosts(e.target, Object.keys(chkbxRange.getSelectedIds()));
				});

			form
				.querySelector('.js-massdisable-proxy-host')
				.addEventListener('click', (e) => {
					this._disableHosts(e.target, Object.keys(chkbxRange.getSelectedIds()));
				});

			form
				.querySelector('.js-massdelete-proxy')
				.addEventListener('click', (e) => {
					this._delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				});
		}

		_edit(parameters = {}) {
			const overlay = PopUp('popup.proxy.edit', parameters, {
				dialogueid: 'proxy_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => this._reload(e.detail));
			overlay.$dialogue[0].addEventListener('dialogue.configRefresh', (e) => this._reload(e.detail));
			overlay.$dialogue[0].addEventListener('dialogue.delete', (e) => {
				uncheckTableRows('proxy');

				this._reload(e.detail);
			});
		}

		_editHost(hostid) {
			const original_url = location.href;

			const overlay = PopUp('popup.host.edit', {hostid}, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.create', (e) => this._reload(e.detail.success));
			overlay.$dialogue[0].addEventListener('dialogue.update', (e) => this._reload(e.detail.success));
			overlay.$dialogue[0].addEventListener('dialogue.delete', (e) => this._reload(e.detail.success));
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			});
		}

		_refreshConfig(target, proxyids) {
			const confirmation = proxyids.length > 1
				? <?= json_encode(_('Refresh configuration of the selected proxies?')) ?>
				: <?= json_encode(_('Refresh configuration of the selected proxy?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'proxy.config.refresh');

			this._post(target, proxyids, curl.getUrl());
		}

		_enableHosts(target, proxyids) {
			const confirmation = proxyids.length > 1
				? <?= json_encode(_('Enable hosts monitored by selected proxies?')) ?>
				: <?= json_encode(_('Enable hosts monitored by selected proxy?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'proxy.host.enable');

			this._post(target, proxyids, curl.getUrl());
		}

		_disableHosts(target, proxyids) {
			const confirmation = proxyids.length > 1
				? <?= json_encode(_('Disable hosts monitored by selected proxies?')) ?>
				: <?= json_encode(_('Disable hosts monitored by selected proxy?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'proxy.host.disable');

			this._post(target, proxyids, curl.getUrl());
		}

		_delete(target, proxyids) {
			const confirmation = proxyids.length > 1
				? <?= json_encode(_('Delete selected proxies?')) ?>
				: <?= json_encode(_('Delete selected proxy?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'proxy.delete');

			this._post(target, proxyids, curl.getUrl());
		}

		_post(target, proxyids, url) {
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

						uncheckTableRows('proxy', response.keepids ?? []);
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
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

					addMessage(message_box);
				})
				.finally(() => {
					target.classList.remove('is-loading');
				});
		}

		_reload(success) {
			postMessageOk(success.title);

			if ('messages' in success) {
				postMessageDetails('success', success.messages);
			}

			location.href = location.href;
		}
	};
</script>
