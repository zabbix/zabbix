<?php
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
	const view = new class {
		init({context, hostid, parent_discoveryid, token}) {
			this.context = context;
			this.hostid = hostid;
			this.parent_discoveryid = parent_discoveryid;
			this.token = token;

			this.#initActions();
			this.#setSubmitCallback();
		}

		#initActions() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-enable-trigger')) {
					this.#enable(e.target, [e.target.dataset.triggerid]);
				}
				else if (e.target.classList.contains('js-disable-trigger')) {
					this.#disable(e.target, [e.target.dataset.triggerid]);
				}
				else if (e.target.id === 'js-massenable-trigger') {
					this.#enable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
				else if (e.target.id === 'js-massdisable-trigger') {
					this.#disable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
				else if (e.target.id === 'js-massupdate-trigger') {
					this.#massupdate(e.target);
				}
				else if (e.target.id === 'js-massdelete-trigger') {
					this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			});

			document.getElementById('js-create').addEventListener('click', (e) => {
				window.popupManagerInstance.openPopup('trigger.prototype.edit',
					{
						parent_discoveryid: this.parent_discoveryid,
						triggerid: e.target.dataset.triggerid,
						hostid: this.hostid,
						context: this.context
					}
				);
			});
		}

		#enable(target, triggerids, massenable = false) {
			if (massenable) {
				const confirmation = triggerids.length > 1
					? <?= json_encode(_('Create triggers from selected prototypes as enabled?')) ?>
					: <?= json_encode(_('Create triggers from selected prototype as enabled?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'trigger.prototype.enable');

			this.#post(target, triggerids, curl);
		}

		#disable(target, triggerids, massdisable = false) {
			if (massdisable) {
				const confirmation = triggerids.length > 1
					? <?= json_encode(_('Create triggers from selected prototypes as disabled?')) ?>
					: <?= json_encode(_('Create triggers from selected prototype as disabled?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'trigger.prototype.disable');

			this.#post(target, triggerids, curl);
		}

		#massupdate(target) {
			openMassupdatePopup('trigger.prototype.massupdate', {
				[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('trigger')) ?>
			}, {
				dialogue_class: 'modal-popup-static',
				trigger_element: target
			});
		}

		#delete(target, triggerids) {
			const confirmation = triggerids.length > 1
				? <?= json_encode(_('Delete selected trigger prototypes?')) ?>
				: <?= json_encode(_('Delete selected trigger prototype?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'trigger.prototype.delete');

			this.#post(target, triggerids, curl);
		}

		#post(target, triggerids, url) {
			let fields = {
				triggerids: triggerids
			};

			if (target.dataset.status !== null) {
				fields.status = target.dataset.status;
			}
			else if (target.dataset.discover !== null) {
				fields.discover = target.dataset.discover;
			}

			target.classList.add('is-loading');

			return fetch(url.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({...this.token, ...fields})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows(this.parent_discoveryid, response.keepids ?? []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows(this.parent_discoveryid);
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

		#setSubmitCallback() {
			window.popupManagerInstance.setSubmitCallback((e) => {
				const data = e.detail;
				let curl = null;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}

					if ('action' in data.success && data.success.action === 'delete') {
						curl = new Curl('host_discovery.php');
						curl.setArgument('context', this.context);
					}
				}

				uncheckTableRows('trigger_prototypes_' + this.parent_discoveryid, [] ,false);

				if (curl) {
					location.href = curl.getUrl();
				}
				else {
					location.href = location.href;
				}
			});
		}
	}
</script>
