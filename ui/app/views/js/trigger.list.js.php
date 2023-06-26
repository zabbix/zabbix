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

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate(); ?>
</script>

<script>
	const view = {
		init({checkbox_hash, checkbox_object, context}) {
			this.checkbox_hash = checkbox_hash;
			this.checkbox_object = checkbox_object;
			this.context = context;

			$('#filter-tags')
				.dynamicRows({template: '#filter-tag-row-tmpl'})
				.on('afteradd.dynamicRows', function() {
					const rows = this.querySelectorAll('.form_row');
					new CTagFilterItem(rows[rows.length - 1]);
				});

			// Init existing fields once loaded.
			document.querySelectorAll('#filter-tags .form_row').forEach(row => {
				new CTagFilterItem(row);
			});

			this._initActions();
		},

		_initActions() {
			document.getElementById('filter_state').addEventListener('change', () => {
				const filter_state = document.querySelector('input[name=filter_state]:checked').value;
				const filter_status_fields = document.getElementsByName('filter_status');

				for (let i = 0; i < filter_status_fields.length; i++) {
					filter_status_fields[i].disabled = filter_state != -1;
				}
			})

			document.querySelector('.js-copy').addEventListener('click', () => {
				const overlay = this.openCopyPopup();
				const dialogue = overlay.$dialogue[0];

				dialogue.addEventListener('dialogue.submit', (e) => {
					postMessageOk(e.detail.title);

					const uncheckids = Object.keys(chkbxRange.getSelectedIds());
					uncheckTableRows('triggers_' + this.checkbox_hash, [], false);
					chkbxRange.checkObjects(this.checkbox_object, uncheckids, false);
					chkbxRange.update(this.checkbox_object);

					if ('messages' in e.detail) {
						postMessageDetails('success', e.detail.messages);
					}

					location.href = location.href;
				});
			});

			document.addEventListener('click', (e) => {
				if (e.target.id === 'js-create') {
					this._edit({'hostid': e.target.dataset.hostid, 'context': this.context})
				}
				else if (e.target.classList.contains('js-trigger-edit')) {
					this._edit({
						'triggerid': e.target.dataset.triggerid,
						'hostid': e.target.dataset.hostid,
						'context': this.context
					})
				}
				else if (e.target.classList.contains('js-massenable-trigger')) {
					this._enable(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (e.target.classList.contains('js-enable-trigger')) {
					this._enable(e.target, [e.target.dataset.triggerid]);
				}
				else if (e.target.classList.contains('js-massdisable-trigger')) {
					this._disable(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (e.target.classList.contains('js-disable-trigger')) {
					this._disable(e.target, [e.target.dataset.triggerid]);
				}
				else if (e.target.classList.contains('js-massdelete-trigger')) {
					this._delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			})
		},

		_edit(parameters = {}) {
			const overlay = PopUp('trigger.edit', parameters, {
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
		},

		_enable(target, triggerids) {
			const confirmation = triggerids.length > 1
				? <?= json_encode(_('Enable selected triggers?')) ?>
				: <?= json_encode(_('Enable selected trigger?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'trigger.enable');

			this._post(target, triggerids, curl);
		},

		_disable(target, triggerids) {
			const confirmation = triggerids.length > 1
				? <?= json_encode(_('Disable selected triggers?')) ?>
				: <?= json_encode(_('Disable selected trigger?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'trigger.disable');

			this._post(target, triggerids, curl);
		},

		_delete(target, triggerids) {
			const confirmation = triggerids.length > 1
				? <?= json_encode(_('Delete selected triggers?')) ?>
				: <?= json_encode(_('Delete selected trigger?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'trigger.delete');

			this._post(target, triggerids, curl);
		},

		_post(target, triggerids, url) {
			url.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
				<?= json_encode(CCsrfTokenHelper::get('trigger')) ?>
			);

			target.classList.add('is-loading');

			return fetch(url.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({triggerids: triggerids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('trigger', response.keepids ?? []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('trigger');
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

			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.hostDelete, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		},

		openCopyPopup() {
			const parameters = {
				triggerids: Object.keys(chkbxRange.getSelectedIds()),
				source: 'triggers'
			};

			return PopUp('copy.edit', parameters, {
				dialogueid: 'copy',
				dialogue_class: 'modal-popup-static'
			});
		},

		events: {
			hostSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				location.href = location.href;
			},

			hostDelete(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				const curl = new Curl('zabbix.php');
				curl.setArgument('action', 'host.list');

				location.href = curl.getUrl();
			}
		}
	};
</script>
