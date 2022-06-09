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


/**
 * @var CView $this
 */
?>

<script>
	const view = {
		enable_url: null,
		disable_url: null,
		delete_url: null,

		init({enable_url, disable_url, delete_url}) {
			this.enable_url = enable_url;
			this.disable_url = disable_url;
			this.delete_url = delete_url;

			this.initActionButtons();
		},

		initActionButtons() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-create-hostgroup')) {
					this.edit();
				}
				else if (e.target.classList.contains('js-edit-hostgroup')) {
					e.preventDefault();
					this.edit({groupid: e.target.dataset.groupid});
				}
				else if (e.target.classList.contains('js-massenable-hostgroup')) {
					this.enable(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (e.target.classList.contains('js-massdisable-hostgroup')) {
					this.disable(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (e.target.classList.contains('js-massdelete-hostgroup')) {
					this.delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			});
		},

		edit(parameters = {}) {
			const original_url = location.href;
			const overlay = PopUp('popup.hostgroup.edit', parameters, {
				dialogueid: 'hostgroup_edit',
				dialogue_class: 'modal-popup-static',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => this._reload(e.detail));
			overlay.$dialogue[0].addEventListener('dialogue.delete', (e) => {
				uncheckTableRows('hostgroup');

				this._reload(e.detail);
			});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		},

		enable(target, groupids) {
			const confirmation =  <?= json_encode(_('Enable selected hosts?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			this._post(target, groupids, this.enable_url);
		},

		disable(target, groupids) {
			const confirmation = <?= json_encode(_('Disable hosts in the selected host groups?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			this._post(target, groupids, this.disable_url);
		},

		delete(target, groupids) {
			const confirmation = groupids.length > 1
				? <?= json_encode(_('Delete selected host groups?')) ?>
				: <?= json_encode(_('Delete selected host group?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			this._post(target, groupids, this.delete_url);
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

			overlay.$dialogue[0].addEventListener('dialogue.create', (e) => this._reload(e.detail.success));
			overlay.$dialogue[0].addEventListener('dialogue.update', (e) => this._reload(e.detail.success));
			overlay.$dialogue[0].addEventListener('dialogue.delete', (e) => this._reload(e.detail.success));
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			});
		},

		_post(target, groupids, url) {
			target.classList.add('is-loading');

			return fetch(url, {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({groupids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('hostgroup', response.error.keepids);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('hostgroup');
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
		},

		_reload(success) {
			postMessageOk(success.title);

			if ('messages' in success) {
				postMessageDetails('success', success.messages);
			}

			location.href = location.href;
		}
	};
</script>
