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

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate() ?>
</script>

<script>
	const view = new class {

		init() {
			this._initTagFilter();
			this._initActions();
		}

		_initTagFilter() {
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

		_initActions() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-create-sla')) {
					this._edit();
				}
				else if (e.target.classList.contains('js-edit-sla')) {
					this._edit({slaid: e.target.dataset.slaid});
				}
				else if (e.target.classList.contains('js-enable-sla')) {
					this._enable(e.target, [e.target.dataset.slaid]);
				}
				else if (e.target.classList.contains('js-disable-sla')) {
					this._disable(e.target, [e.target.dataset.slaid]);
				}
				else if (e.target.classList.contains('js-massenable-sla')) {
					this._enable(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (e.target.classList.contains('js-massdisable-sla')) {
					this._disable(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (e.target.classList.contains('js-massdelete-sla')) {
					this._delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			});
		}

		_edit(parameters = {}) {
			const overlay = PopUp('popup.sla.edit', parameters, {
				dialogueid: 'sla_edit',
				dialogue_class: 'modal-popup-static'
			});

			const dialogue = overlay.$dialogue[0];

			dialogue.addEventListener('dialogue.submit', (e) => {
				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});

			dialogue.addEventListener('dialogue.delete', (e) => {
				uncheckTableRows('sla');

				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});
		}

		_enable(target, slaids) {
			const confirmation = slaids.length > 1
				? <?= json_encode(_('Enable selected SLAs?')) ?>
				: <?= json_encode(_('Enable selected SLA?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'sla.enable');

			this._post(target, slaids, curl.getUrl());
		}

		_disable(target, slaids) {
			const confirmation = slaids.length > 1
				? <?= json_encode(_('Disable selected SLAs?')) ?>
				: <?= json_encode(_('Disable selected SLA?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'sla.disable');

			this._post(target, slaids, curl.getUrl());
		}

		_delete(target, slaids) {
			const confirmation = slaids.length > 1
				? <?= json_encode(_('Delete selected SLAs?')) ?>
				: <?= json_encode(_('Delete selected SLA?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'sla.delete');

			this._post(target, slaids, curl.getUrl());
		}

		_post(target, slaids, url) {
			target.classList.add('is-loading');

			return fetch(url, {
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
				})
				.finally(() => {
					target.classList.remove('is-loading');
				});
		}
	};
</script>
