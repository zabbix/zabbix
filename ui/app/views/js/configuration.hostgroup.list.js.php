<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
			this.initPopupListeners();
		},

		initActionButtons() {
			document.addEventListener('click', e => {
				if (e.target.classList.contains('js-create-hostgroup')) {
					ZABBIX.PopupManager.open('hostgroup.edit');
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

					target.classList.remove('is-loading');
					target.blur();
				});
		},

		initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: () => uncheckTableRows('hostgroup')
			});
		},
	};
</script>
