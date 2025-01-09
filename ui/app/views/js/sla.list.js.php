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

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate() ?>
</script>

<script>
	const view = new class {

		init() {
			this.#initTagFilter();
			this.#initActions();
			this.#setSubmitCallback();
		}

		#initTagFilter() {
			$('#filter-tags')
				.dynamicRows({template: '#filter-tag-row-tmpl'})
				.on('afteradd.dynamicRows', function () {
					const rows = this.querySelectorAll('.form_row');

					new CTagFilterItem(rows[rows.length - 1]);
				});

			document.querySelectorAll('#filter-tags .form_row').forEach((row) => {
				new CTagFilterItem(row);
			});
		}

		#initActions() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-create-sla')) {
					window.popupManagerInstance.openPopup('sla.edit', {});
				}
				else if (e.target.classList.contains('js-enable-sla')) {
					this.#enable(e.target, [e.target.dataset.slaid]);
				}
				else if (e.target.classList.contains('js-disable-sla')) {
					this.#disable(e.target, [e.target.dataset.slaid]);
				}
				else if (e.target.classList.contains('js-massenable-sla')) {
					this.#enable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
				else if (e.target.classList.contains('js-massdisable-sla')) {
					this.#disable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
				else if (e.target.classList.contains('js-massdelete-sla')) {
					this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			});
		}

		#enable(target, slaids, massenable = false) {
			if (massenable) {
				const confirmation = slaids.length > 1
					? <?= json_encode(_('Enable selected SLAs?')) ?>
					: <?= json_encode(_('Enable selected SLA?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'sla.enable');

			this.#post(target, slaids, curl);
		}

		#disable(target, slaids, massdisable = false) {
			if (massdisable) {
				const confirmation = slaids.length > 1
					? <?= json_encode(_('Disable selected SLAs?')) ?>
					: <?= json_encode(_('Disable selected SLA?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'sla.disable');

			this.#post(target, slaids, curl);
		}

		#delete(target, slaids) {
			const confirmation = slaids.length > 1
				? <?= json_encode(_('Delete selected SLAs?')) ?>
				: <?= json_encode(_('Delete selected SLA?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'sla.delete');

			this.#post(target, slaids, curl);
		}

		#post(target, slaids, curl) {
			target.classList.add('is-loading');

			curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('sla')) ?>);

			return fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({slaids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('sla', response.keepids ?? []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('sla');
					}

					location.href = location.href;
				})
				.catch(() => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

					addMessage(message_box);

					target.classList.remove('is-loading');
					target.blur();
				});
		}

		#setSubmitCallback() {
			window.popupManagerInstance.setSubmitCallback((e) => {
				if ('success' in e.detail) {
					postMessageOk(e.detail.success.title);

					if ('messages' in e.detail.success) {
						postMessageDetails('success', e.detail.success.messages);
					}
				}

				uncheckTableRows('sla');
				location.href = location.href;
			});
		}
	};
</script>
