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
		init({context, parent_discoveryid}) {
			this.context = context;
			this.parent_discoveryid = parent_discoveryid;

			this.#initActions();
			this.#initPopupListeners();
		}

		#initActions() {
			document.addEventListener('click', e => {
				if (e.target.classList.contains('js-enable')) {
					this.#enable(e.target, [e.target.dataset.hostid]);
				}
				else if (e.target.classList.contains('js-disable')) {
					this.#disable(e.target, [e.target.dataset.hostid]);
				}
				else if (e.target.classList.contains('js-massenable')) {
					this.#enable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
				else if (e.target.classList.contains('js-massdisable')) {
					this.#disable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
				else if (e.target.classList.contains('js-massdelete')) {
					this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			});

			document.getElementById('js-create').addEventListener('click', () => {
				ZABBIX.PopupManager.open('host.prototype.edit', {
					parent_discoveryid: this.parent_discoveryid,
					context: this.context
				});
			});
		}

		#enable(target, hostids, massenable = false) {
			if (massenable) {
				const confirmation = hostids.length > 1
					? <?= json_encode(_('Create hosts from selected prototypes as enabled?')) ?>
					: <?= json_encode(_('Create hosts from selected prototype as enabled?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'host.prototype.enable');

			this.#post(target, hostids, curl);
		}

		#disable(target, hostids, massdisable = false) {
			if (massdisable) {
				const confirmation = hostids.length > 1
					? <?= json_encode(_('Create hosts from selected prototypes as disabled?')) ?>
					: <?= json_encode(_('Create hosts from selected prototype as disabled?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'host.prototype.disable');

			this.#post(target, hostids, curl);
		}

		#delete(target, hostids) {
			const confirmation = hostids.length > 1
				? <?= json_encode(_('Delete selected host prototypes?')) ?>
				: <?= json_encode(_('Delete selected host prototype?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'host.prototype.delete');

			this.#post(target, hostids, curl);
		}

		#post(target, hostids, url) {
			const fields = {
				[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('host')) ?>,
				hostids,
				context: this.context
			};

			if (target.dataset.status !== undefined) {
				fields.status = target.dataset.status;
			}
			else if (target.dataset.discover !== undefined) {
				fields.discover = target.dataset.discover;
			}

			target.classList.add('is-loading');

			return fetch(url.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({...fields})
			})
				.then(response => response.json())
				.then(response => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows(`host_prototypes_${this.parent_discoveryid}`, response.keepids ?? []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows(`host_prototypes_${this.parent_discoveryid}`);
					}

					location.href = location.href;
				})
				.catch(() => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

					addMessage(message_box);
				})
				.finally(() => target.classList.remove('is-loading'));
		}

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: ({data, event}) => {
					uncheckTableRows(`host_prototypes_${this.parent_discoveryid}`);

					if (data.submit.success?.action === 'delete') {
						const url = new URL('host_discovery.php', location.href);

						url.searchParams.set('context', this.context);

						event.setRedirectUrl(url.href);
					}
				}
			});
		}
	}
</script>
