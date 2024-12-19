<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
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
	const view = {
		applied_filter_groupids: [],

		init({applied_filter_groupids, csrf_token}) {
			this.applied_filter_groupids = applied_filter_groupids;
			this.csrf_token = csrf_token;

			this.initFilter();

			const form = document.forms['hosts'];

			form.addEventListener('click', e => {
				if (e.target.classList.contains('js-enable-host')) {
					this.enable(e.target, {hostids: [e.target.dataset.hostid]});
				}
				else if (e.target.classList.contains('js-disable-host')) {
					this.disable(e.target, {hostids: [e.target.dataset.hostid]});
				}
			});

			document.querySelector('.js-create-host').addEventListener('click', () => {
				window.popupManagerInstance.openPopup('host.edit',
					this.applied_filter_groupids ? {groupids: this.applied_filter_groupids} : {}
				);
			});

			form.querySelector('.js-massenable-host').addEventListener('click', e => {
				const hostids = Object.keys(chkbxRange.getSelectedIds());

				const message = hostids.length > 1
					? <?= json_encode(_('Enable selected hosts?')) ?>
					: <?= json_encode(_('Enable selected host?')) ?>;

				if (window.confirm(message)) {
					this.enable(e.target, {hostids});
				}
			});

			form.querySelector('.js-massdisable-host').addEventListener('click', e => {
				const hostids = Object.keys(chkbxRange.getSelectedIds());

				const message = hostids.length > 1
					? <?= json_encode(_('Disable selected hosts?')) ?>
					: <?= json_encode(_('Disable selected host?')) ?>;

				if (window.confirm(message)) {
					this.disable(e.target, {hostids});
				}
			});

			form.querySelector('.js-massupdate-host').addEventListener('click', e => {
				openMassupdatePopup('popup.massupdate.host', {
					[CSRF_TOKEN_NAME]: this.csrf_token
				}, {
					dialogue_class: 'modal-popup-static',
					trigger_element: e.target
				})
			});

			form.querySelector('.js-massdelete-host').addEventListener('click', e => {
				this.massDeleteHosts(e.target);
			});

			this.setSubmitCallback();
		},

		enable(target, parameters) {
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'host.enable');

			target.classList.add('is-loading');

			this.postAction(curl, parameters)
				.then(response => this.reload(response))
				.catch(() => {
					target.classList.remove('is-loading');
					target.blur();
				});
		},

		disable(target, parameters) {
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'host.disable');

			target.classList.add('is-loading');

			this.postAction(curl, parameters)
				.then(response => this.reload(response))
				.catch(() => {
					target.classList.remove('is-loading');
					target.blur();
				});
		},

		postAction(curl, data) {
			return fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({
					...data,
					[CSRF_TOKEN_NAME]: this.csrf_token
				})
			})
				.then(response => response.json())
				.catch(error => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

					addMessage(message_box);

					throw error;
				});
		},

		reload(result) {
			if ('error' in result) {
				if ('title' in result.error) {
					postMessageError(result.error.title);
				}

				postMessageDetails('error', result.error.messages);

				uncheckTableRows('hosts', result.keepids ?? []);
			}
			else if ('success' in result) {
				postMessageOk(result.success.title);

				if ('messages' in result.success) {
					postMessageDetails('success', result.success.messages);
				}

				uncheckTableRows('hosts');
			}

			location.href = location.href;
		},

		initFilter() {
			$('#filter-tags')
				.dynamicRows({template: '#filter-tag-row-tmpl'})
				.on('afteradd.dynamicRows', function () {
					const rows = this.querySelectorAll('.form_row');
					new CTagFilterItem(rows[rows.length - 1]);
				});

			// Init existing fields once loaded.
			document.querySelectorAll('#filter-tags .form_row').forEach(row => {
				new CTagFilterItem(row);
			});

			$('#filter_monitored_by')
				.on('change', function() {
					const filter_monitored_by = $('input[name=filter_monitored_by]:checked').val();

					for (const field of document.querySelectorAll('.js-filter-proxyids')) {
						field.style.display = filter_monitored_by == <?= ZBX_MONITORED_BY_PROXY ?> ? '' : 'none';
					}

					$('#filter_proxyids_').multiSelect(
						filter_monitored_by == <?= ZBX_MONITORED_BY_PROXY ?> ? 'enable' : 'disable'
					);

					for (const field of document.querySelectorAll('.js-filter-proxy-groupids')) {
						field.style.display = filter_monitored_by == <?= ZBX_MONITORED_BY_PROXY_GROUP ?> ? '' : 'none';
					}

					$('#filter_proxy_groupids_').multiSelect(
						filter_monitored_by == <?= ZBX_MONITORED_BY_PROXY_GROUP ?> ? 'enable' : 'disable'
					);
				})
				.trigger('change');
		},

		massDeleteHosts(button) {
			const confirm_text = Object.keys(chkbxRange.getSelectedIds()).length > 1
				? <?= json_encode(_('Delete selected hosts?')) ?>
				: <?= json_encode(_('Delete selected host?')) ?>;

			if (!confirm(confirm_text)) {
				return;
			}

			button.classList.add('is-loading');

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'host.massdelete');
			curl.setArgument(CSRF_TOKEN_NAME, this.csrf_token);

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: urlEncodeData({hostids: Object.keys(chkbxRange.getSelectedIds())})
			})
				.then(response => response.json())
				.then(response => this.reload(response))
				.catch(() => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

					addMessage(message_box);
				})
				.finally(() => {
					button.classList.remove('is-loading');
				});
		},

		setSubmitCallback() {
			window.popupManagerInstance.setSubmitCallback((e) => {
				if ('success' in e.detail) {
					postMessageOk(e.detail.success.title);

					if ('messages' in e.detail.success) {
						postMessageDetails('success', e.detail.success.messages);
					}
				}

				uncheckTableRows('hosts');
				location.href = location.href;
			});
		}
	};
</script>
