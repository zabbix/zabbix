<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
		init() {
			this.#initActions();
		}

		#initActions() {
			document.getElementById('js-create-cep').addEventListener('click', () => {
				ZABBIX.PopupManager.open('ceprule.edit');
			});
			document.getElementById('js-create').addEventListener('click', () => {
				ZABBIX.PopupManager.open('correlation.edit');
			});

			window['js-massdelete'].addEventListener('click', (e) => {
				clearMessages();
				this.#setLoadingActionButtons(e.target);
				this.#massdelete(Object.keys(chkbxRange.getSelectedIds()))
					.then(response => this.#ajaxResponseHandler(response))
					.then(response => uncheckTableRows('ceprules', response.keepids ?? []))
					.then(() => location.href = location.href)
					.catch(exception => exception !== AbortSignal && this.#ajaxExceptionHandler(exception))
					.finally(() => this.#unsetLoadingActionButtons(e.target));
			});

			window['js-massenable'].addEventListener('click', (e) => {
				clearMessages();
				this.#setLoadingActionButtons(e.target);
				this.#massenable(Object.keys(chkbxRange.getSelectedIds()))
					.then(response => this.#ajaxResponseHandler(response))
					.then(response => uncheckTableRows('ceprules', response.keepids ?? []))
					.then(() => location.href = location.href)
					.catch(exception => exception !== AbortSignal && this.#ajaxExceptionHandler(exception))
					.finally(() => this.#unsetLoadingActionButtons(e.target));
			});

			window['js-massdisable'].addEventListener('click', (e) => {
				clearMessages();
				this.#setLoadingActionButtons(e.target);
				this.#massdisable(Object.keys(chkbxRange.getSelectedIds()))
					.then(response => this.#ajaxResponseHandler(response))
					.then(response => uncheckTableRows('ceprules', response.keepids ?? []))
					.then(() => location.href = location.href)
					.catch(exception => exception !== AbortSignal && this.#ajaxExceptionHandler(exception))
					.finally(() => this.#unsetLoadingActionButtons(e.target));
			});

			document.querySelectorAll('.js-toggle-disabled')
				.forEach(node => node.addEventListener('click', () => this.#toggleEnabled(node)));

			this.#initPopupListeners();
		}

		#setLoadingActionButtons(target) {
			target.classList.add('is-loading');
			window['action_buttons'].querySelectorAll('button').forEach(node => node.disabled = true);
		}

		#unsetLoadingActionButtons(target) {
			target.classList.remove('is-loading');
			window['action_buttons'].querySelectorAll('button').forEach(node => node.disabled = false);
		}

		#branchLegacyIds(cepruleids_mixed) {
			const correlationids = [];
			const cepruleids = [];

			cepruleids_mixed.map(id => {
				if (id.startsWith('legacy-')) {
					correlationids.push(id.replace('legacy-', ''));
				}
				else {
					cepruleids.push(id);
				}
			});

			return {correlationids, cepruleids};
		}

		#massdelete(cepruleids_mixed) {
			const {correlationids, cepruleids} = this.#branchLegacyIds(cepruleids_mixed);
			const confirmation = (correlationids.length + cepruleids.length) > 1
				? <?= json_encode(_('Delete selected complex event processing rules?')) ?>
				: <?= json_encode(_('Delete selected complex event processing rule?')) ?>;

			if (!window.confirm(confirmation)) {
				return Promise.reject(AbortSignal);
			}

			const payload = {cepruleids, correlationids,
				[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('ceprule')) ?>
			};

			return fetch(zabbixUrl({action: 'ceprule.delete'}), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(payload)
			}).then(response => response.json());
		}

		#massenable(cepruleids_mixed) {
			const {correlationids, cepruleids} = this.#branchLegacyIds(cepruleids_mixed);
			const confirmation = (correlationids.length + cepruleids.length) > 1
				? <?= json_encode(_('Enable selected complex event processing rules?')) ?>
				: <?= json_encode(_('Enable selected complex event processing rule?')) ?>;

			if (!window.confirm(confirmation)) {
				return Promise.reject(AbortSignal);
			}

			const payload = {cepruleids, correlationids,
				[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('ceprule')) ?>
			};

			return fetch(zabbixUrl({action: 'ceprule.enable'}), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(payload)
			}).then(response => response.json());
		}

		#massdisable(cepruleids_mixed) {
			const {correlationids, cepruleids} = this.#branchLegacyIds(cepruleids_mixed);
			const confirmation = (correlationids.length + cepruleids.length) > 1
				? <?= json_encode(_('Disable selected complex event processing rules?')) ?>
				: <?= json_encode(_('Disable selected complex event processing rule?')) ?>;

			if (!window.confirm(confirmation)) {
				return Promise.reject(AbortSignal);
			}

			const payload = {cepruleids, correlationids,
				[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('ceprule')) ?>
			};

			return fetch(zabbixUrl({action: 'ceprule.disable'}), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(payload)
			}).then(response => response.json());
		}

		#toggleEnabled(target) {
			const {action, id} = target.dataset;
			const payload = {[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('ceprule')) ?>};

			if (id.startsWith('legacy-')) {
				payload.correlationids = [id.replace('legacy-', '')];
			}
			else {
				payload.cepruleids = [id];
			}

			target.classList.add('is-loading');
			fetch(zabbixUrl({action}), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(payload)
			})
				.then(response => response.json())
				.then(response => this.#ajaxResponseHandler(response))
				.then(() => location.href = location.href)
				.finally(() => {
					target.classList.remove('is-loading');
					target.blur();
					clearMessages();
				})
				.catch((exception) => this.#ajaxExceptionHandler(exception));
		}

		#ajaxResponseHandler(response) {
			if ('error' in response) {
				if ('title' in response.error) {
					postMessageError(response.error.title);
				}

				postMessageDetails('error', response.error.messages);
			}
			else if ('success' in response) {
				postMessageOk(response.success.title);

				if ('messages' in response.success) {
					postMessageDetails('success', response.success.messages);
				}
			}
			else {
				throw new Error();
			}

			return response;
		}

		#ajaxExceptionHandler(exception) {
			let title, messages;

			if (typeof exception === 'object' && 'error' in exception) {
				title = exception.error.title;
				messages = exception.error.messages;
			}
			else {
				messages = [<?= json_encode(_('Unexpected server error.')) ?>];
			}

			const message_box = makeMessageBox('bad', messages, title)[0];

			addMessage(message_box);
		}

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: () => uncheckTableRows('ceprules')
			});
		}
	};
</script>
