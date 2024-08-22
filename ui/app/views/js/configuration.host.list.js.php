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
				if (e.target.classList.contains('js-edit-template')) {
					this.editTemplate({templateid: e.target.dataset.templateid});
				}
				else if (e.target.classList.contains('js-edit-proxy')) {
					this.editProxy(e.target.dataset.proxyid);
				}
				else if (e.target.classList.contains('js-edit-proxy-group')) {
					this.editProxyGroup(e.target.dataset.proxy_groupid);
				}
				else if (e.target.classList.contains('js-enable-host')) {
					if (window.confirm(<?= json_encode(_('Enable selected host?')) ?>)) {
						this.enable(e.target, {hostids: [e.target.dataset.hostid]});
					}
				}
				else if (e.target.classList.contains('js-disable-host')) {
					if (window.confirm(<?= json_encode(_('Disable selected host?')) ?>)) {
						this.disable(e.target, {hostids: [e.target.dataset.hostid]});
					}
				}
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
		},

		enable(target, parameters) {
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'host.enable');

			target.classList.add('is-loading');

			this.postAction(curl, parameters)
				.then(response => this.reload(response))
				.finally(() => {
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
				.finally(() => {
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
				.catch(() => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

					addMessage(message_box);
				});
		},

		editTemplate(parameters) {
			const overlay = PopUp('template.edit', parameters, {
				dialogueid: 'templates-form',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', e => this.reload({success: e.detail.success}));
		},

		editProxy(proxyid) {
			const overlay = PopUp('popup.proxy.edit', {proxyid}, {
				dialogueid: 'proxy_edit',
				dialogue_class: 'modal-popup-static',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', e => this.reload({success: e.detail.success}));
		},

		editProxyGroup(proxy_groupid) {
			const overlay = PopUp('popup.proxygroup.edit', {proxy_groupid}, {
				dialogueid: 'proxy-group-edit',
				dialogue_class: 'modal-popup-static',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', e => this.reload({success: e.detail.success}));
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

		createHost() {
			const host_data = this.applied_filter_groupids
				? {groupids: this.applied_filter_groupids}
				: {};

			this.openHostPopup(host_data);
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

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.elementSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
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

		events: {
			elementSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				uncheckTableRows('hosts');
				location.href = location.href;
			}
		}
	};
</script>
