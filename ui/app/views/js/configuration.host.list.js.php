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

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate() ?>
</script>

<script>
	const view = new class {
		#applied_filter_groupids = [];
		#datatable = null;
		#csrf_token = null;

		init({
			applied_filter_groupids,
			csrf_token,
			default_sort_field,
			default_sort_order,
			filter,
			page,
			sort_field,
			sort_order,
			storage_idx,
			user_configs
		}) {
			this.#applied_filter_groupids = applied_filter_groupids;
			this.#csrf_token = csrf_token;

			this.#initFilter();
			this.#initEvents();
			this.#initPopupListeners();
			this.#initDataTable({filter, page, default_sort_field, default_sort_order, sort_field, sort_order,
				storage_idx, user_configs});
		}

		enable(target, parameters, callback) {
			const url_params = objectToSearchParams({action: 'host.enable'});
			const url = new URL(`zabbix.php?${url_params}`, location.href);

			target.classList.add(ZBX_STYLE_LOADING);

			this.postAction(url.toString(), parameters)
				.then(response => callback(response))
				.catch(() => {
					target.classList.remove(ZBX_STYLE_LOADING);
					target.blur();
				});
		}

		disable(target, parameters, callback) {
			const url_params = objectToSearchParams({action: 'host.disable'});
			const url = new URL(`zabbix.php?${url_params}`, location.href);

			target.classList.add(ZBX_STYLE_LOADING);

			this.postAction(url.toString(), parameters)
				.then(response => callback(response))
				.catch(() => {
					target.classList.remove(ZBX_STYLE_LOADING);
					target.blur();
				});
		}

		postAction(url, data) {
			return fetch(url, {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({
					...data,
					[CSRF_TOKEN_NAME]: this.#csrf_token
				})
			})
				.then(response => response.json())
				.catch(error => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')); ?>]);

					addMessage(message_box);

					throw error;
				});
		}

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
		}

		#initFilter() {
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
				.on('change', function () {
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
		}

		#initEvents() {
			document.querySelector('.js-host-wizard').addEventListener('click', () => {
				ZABBIX.PopupManager.open('host.wizard.edit');
			});

			document.querySelector('.js-create-host').addEventListener('click', () => {
				ZABBIX.PopupManager.open('host.edit', {groupids: this.#applied_filter_groupids});
			});

			const form = document.forms['hosts'];

			form.querySelector('.js-massenable-host').addEventListener('click', e => {
				const hostids = Object.keys(chkbxRange.getSelectedIds());

				const message = hostids.length > 1
					? <?= json_encode(_('Enable selected hosts?')); ?>
					: <?= json_encode(_('Enable selected host?')); ?>;

				if (window.confirm(message)) {
					this.enable(e.target, {hostids}, this.reload);
				}
			});

			form.querySelector('.js-massdisable-host').addEventListener('click', e => {
				const hostids = Object.keys(chkbxRange.getSelectedIds());

				const message = hostids.length > 1
					? <?= json_encode(_('Disable selected hosts?')); ?>
					: <?= json_encode(_('Disable selected host?')); ?>;

				if (window.confirm(message)) {
					this.disable(e.target, {hostids}, this.reload);
				}
			});

			form.querySelector('.js-massupdate-host').addEventListener('click', e => {
				openMassupdatePopup('popup.massupdate.host', {
					[CSRF_TOKEN_NAME]: this.#csrf_token
				}, {
					dialogue_class: 'modal-popup-static',
					trigger_element: e.target
				})
			});

			form.querySelector('.js-massdelete-host').addEventListener('click', e => {
				this.massDeleteHosts(e.target);
			});
		}

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: () => uncheckTableRows('hosts')
			});

			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT,
					action: 'host.wizard.edit'
				},
				callback: ({data, event}) => {
					if (data.submit.redirect_latest) {
						const url = new URL('zabbix.php', location.href);

						url.searchParams.set('action', 'latest.view');
						url.searchParams.set('hostids[]', data.submit.hostid);
						url.searchParams.set('filter_set', '1');

						event.setRedirectUrl(url.href);
					}
				}
			});
		}

		#initDataTable({filter, page, default_sort_field, default_sort_order, sort_field, sort_order, storage_idx,
				user_configs}) {

			const data_provider_url = new URL('zabbix.php', location.href);
			data_provider_url.searchParams.set('action', 'host.list.data');
			data_provider_url.searchParams.set(CSRF_TOKEN_NAME, this.#csrf_token);

			const data_provider = new CDefaultDataProvider(data_provider_url.toString());

			this.#datatable = new CDataTable(document.getElementById('hosts'), data_provider)
				.setColumns([
					new CDataTableColumn('name', <?= json_encode(_('Name')); ?>)
						.setFields(['hostid', 'name', 'discovery', 'flags', 'maintenance', 'status', 'discoveryData',
							'discoveryRule', 'is_discovery_rule_editable', 'maintenanceid', 'maintenance_type',
							'maintenance_status'])
						.setRenderer('name')
						.setSortable(true)
						.setTogglable(false)
						.setWidth('auto'),
					new CDataTableColumn('items', <?= json_encode(_('Items')); ?>)
						.setFields(['hostid', 'items'])
						.setRenderer('items'),
					new CDataTableColumn('triggers', <?= json_encode(_('Triggers')); ?>)
						.setFields(['hostid', 'triggers'])
						.setRenderer('triggers'),
					new CDataTableColumn('graphs', <?= json_encode(_('Graphs')); ?>)
						.setFields(['hostid', 'graphs'])
						.setRenderer('graphs'),
					new CDataTableColumn('discovery', <?= json_encode(_('Discovery')); ?>)
						.setFields(['hostid', 'discoveryRules'])
						.setRenderer('discovery'),
					new CDataTableColumn('web', <?= json_encode(_('Web')); ?>)
						.setFields(['hostid', 'httpTests'])
						.setRenderer('web'),
					new CDataTableColumn('interface', <?= json_encode(_('Interface')); ?>)
						.setFields(['interface']),
					new CDataTableColumn('proxy', <?= json_encode(_('Proxy')); ?>)
						.setFields(['monitored_by', 'proxyid', 'proxy_groupid', 'assigned_proxyid', 'proxy',
							'proxy_group', 'assigned_proxy'])
						.setRenderer('proxy'),
					new CDataTableColumn('templates', <?= json_encode(_('Templates')); ?>)
						.setFields(['templates', 'parentTemplates'])
						.setRenderer('templates'),
					new CDataTableColumn('status', <?= json_encode(_('Status')); ?>)
						.setFields(['hostid', 'status', 'disabled_by_lld', 'disable_source', 'flags',
							'maintenance_status', 'discoveryData'])
						.setRenderer('status')
						.setSortable(true),
					new CDataTableColumn('availability', <?= json_encode(_('Availability')); ?>)
						.setFields(['availability', 'active_available'])
						.setRenderer('availability'),
					new CDataTableColumn('encryption', <?= json_encode(_('Agent encryption')); ?>)
						.setFields(['tls_accept', 'tls_connect'])
						.setRenderer('encryption'),
					new CDataTableColumn('info', <?= json_encode(_('Info')); ?>)
						.setFields(['info_icons'])
						.setRenderer('info'),
					new CDataTableColumnTags('tags', <?= json_encode(_('Tags')); ?>),
					new CDataTableColumnTagValue('tagvalue', <?= json_encode(_('Tag value')); ?>)
				])
				.setPage(page)
				.setFilter(filter)
				.setSelectable('hosts', 'hostids', ['hostid', 'data_actions'])
				.setDefaultSortField(default_sort_field)
				.setDefaultSortOrder(default_sort_order)
				.setSortField(sort_field)
				.setSortOrder(sort_order)
				.setStorageIdx(storage_idx)
				.setStickyHeader(true)
				.setStickyFooter(true)
				.setCellRenderer(CDataTableColumn.CHECKBOX, ({column, cell_data, cell, cell_inner}) => {
					const [object_id, data_actions] = cell_data;

					if (!object_id) {
						return;
					}

					const input_id = `${column.getId()}_${object_id}`;

					const checkbox = document.createElement('input');
					checkbox.classList.add(ZBX_STYLE_CHECKBOX_RADIO);
					checkbox.setAttribute('type', 'checkbox');
					checkbox.setAttribute('id', input_id);
					checkbox.setAttribute('name', `${column.getId()}[${object_id}]`);
					checkbox.setAttribute('data-field-type', 'checkbox');
					checkbox.value = object_id.toString();

					if (data_actions) {
						checkbox.setAttribute('data-actions', Object.keys(data_actions).join(' '));
					}

					const label = document.createElement('label');
					label.setAttribute('for', input_id);
					label.appendChild(document.createElement('span'));

					cell.classList.add(CDataTable.ZBX_STYLE_CELL_CHECKBOX);

					const button = document.createElement('button');
					button.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_MORE);
					button.setAttribute('data-menu-popup', JSON.stringify({
						type: 'host',
						data: {hostid: object_id}
					}));
					button.setAttribute('aria-expanded', 'false');
					button.setAttribute('aria-haspopup', 'true');

					cell_inner.append(checkbox, label, button);
				})
				.setCellRenderer('name', ({cell_data, cell_inner}) => {
					const [hostid, name, discovery, flags, maintenance, status] = cell_data;

					if (flags == ZBX_FLAG_DISCOVERY_CREATED) {
						if (discovery.rule) {
							if (discovery.editable) {
								const host_prototype_url = new URL('zabbix.php', location.href);
								host_prototype_url.searchParams.set('action', 'popup');
								host_prototype_url.searchParams.set('popup', 'host.prototype.edit');
								host_prototype_url.searchParams.set('parent_discoveryid', discovery.rule.itemid);
								host_prototype_url.searchParams.set('hostid', discovery.data.parent_hostid);
								host_prototype_url.searchParams.set('context', 'host');

								const discovery_rule_link = document.createElement('a');
								discovery_rule_link.classList.add(ZBX_STYLE_LINK_ALT, ZBX_STYLE_ORANGE);
								discovery_rule_link.setAttribute('href', host_prototype_url.toString());

								cell_inner.appendChild(discovery_rule_link);
							}
							else {
								const discovery_rule = document.createElement('span');
								discovery_rule.classList.add(ZBX_STYLE_ORANGE);
								discovery_rule.textContent = discovery.rule.name;

								cell_inner.appendChild(discovery_rule);
							}
						}
						else {
							const discovery_rule = document.createElement('span');
							discovery_rule.classList.add(ZBX_STYLE_ORANGE);
							discovery_rule.textContent = <?= json_encode(_('Inaccessible discovery rule')); ?>;

							cell_inner.appendChild(discovery_rule);
						}

						cell_inner.innerHTML += NAME_DELIMITER;
					}

					const url = new URL('zabbix.php', location.href);
					url.searchParams.set('action', 'popup');
					url.searchParams.set('popup', 'host.edit');
					url.searchParams.set('hostid', hostid);

					const edit_link = document.createElement('a');
					edit_link.setAttribute('href', url.toString())
					edit_link.textContent = name;

					cell_inner.appendChild(edit_link);

					if (maintenance && status == HOST_STATUS_MONITORED) {
						const maintenance_icon = document.createElement('button');
						maintenance_icon.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_WRENCH_ALT_SMALL,
							ZBX_STYLE_COLOR_WARNING, ZBX_STYLE_NO_INDENT);
						maintenance_icon.setAttribute('type', 'button');
						maintenance_icon.setAttribute('role', 'button');

						if (maintenance.status == HOST_MAINTENANCE_STATUS_ON) {
							let hint = `${maintenance.name} [${maintenance.type
								? <?= json_encode(_('Maintenance without data collection')); ?>
								: <?= json_encode(_('Maintenance with data collection')); ?>}]`;

							if (maintenance.description != '') {
								hint += "\n" + maintenance.description;
							}

							maintenance_icon.setAttribute('data-hintbox-html', hint);
						}
						else {
							maintenance_icon.setAttribute('data-hintbox-html',
								<?= json_encode(_('Inaccessible maintenance')); ?>);
						}

						maintenance_icon.setAttribute('data-hintbox', '1');
						maintenance_icon.setAttribute('data-hintbox-class', ZBX_STYLE_HINTBOX_WRAP);
						maintenance_icon.setAttribute('data-hintbox-static', '1');
						maintenance_icon.setAttribute('aria-expanded', 'false');

						cell_inner.appendChild(maintenance_icon);
					}
				})
				.setCellRenderer('items', ({cell_data, cell_inner}) => {
					const [hostid, items] = cell_data;

					const url = new URL('zabbix.php', location.href);
					url.searchParams.set('action', 'item.list');
					url.searchParams.set('filter_set', '1');
					url.searchParams.set('filter_hostids[0]', hostid);
					url.searchParams.set('context', 'host');

					const item_link = document.createElement('a');
					item_link.setAttribute('href', url.toString());
					item_link.textContent = <?= json_encode(_('Items')); ?>;

					cell_inner.appendChild(item_link);

					if (items > 0) {
						const count = document.createElement('sup');
						count.textContent = items;

						cell_inner.innerHTML += ' ';
						cell_inner.appendChild(count);
					}
				})
				.setCellRenderer('triggers', ({cell_data, cell_inner}) => {
					const [hostid, items] = cell_data;

					const url = new URL('zabbix.php', location.href);
					url.searchParams.set('action', 'trigger.list');
					url.searchParams.set('filter_set', '1');
					url.searchParams.set('filter_hostids[0]', hostid);
					url.searchParams.set('context', 'host');

					const item_link = document.createElement('a');
					item_link.setAttribute('href', url.toString());
					item_link.textContent = <?= json_encode(_('Triggers')); ?>;

					cell_inner.appendChild(item_link);

					if (items > 0) {
						const count = document.createElement('sup');
						count.textContent = items;

						cell_inner.innerHTML += ' ';
						cell_inner.appendChild(count);
					}
				})
				.setCellRenderer('graphs', ({cell_data, cell_inner}) => {
					const [hostid, items] = cell_data;

					const url = new URL('zabbix.php', location.href);
					url.searchParams.set('action', 'graph.list');
					url.searchParams.set('filter_set', '1');
					url.searchParams.set('filter_hostids[0]', hostid);
					url.searchParams.set('context', 'host');

					const item_link = document.createElement('a');
					item_link.setAttribute('href', url.toString());
					item_link.textContent = <?= json_encode(_('Graphs')); ?>;

					cell_inner.appendChild(item_link);

					if (items > 0) {
						const count = document.createElement('sup');
						count.textContent = items;

						cell_inner.innerHTML += ' ';
						cell_inner.appendChild(count);
					}
				})
				.setCellRenderer('discovery', ({cell_data, cell_inner}) => {
					const [hostid, items] = cell_data;

					const url = new URL('host_discovery.php', location.href);
					url.searchParams.set('filter_set', '1');
					url.searchParams.set('filter_hostids[0]', hostid);
					url.searchParams.set('context', 'host');

					const item_link = document.createElement('a');
					item_link.setAttribute('href', url.toString());
					item_link.textContent = <?= json_encode(_('Discovery')); ?>;

					cell_inner.appendChild(item_link);

					if (items > 0) {
						const count = document.createElement('sup');
						count.textContent = items;

						cell_inner.innerHTML += ' ';
						cell_inner.appendChild(count);
					}
				})
				.setCellRenderer('web', ({cell_data, cell_inner}) => {
					const [hostid, items] = cell_data;

					const url = new URL('httpconf.php', location.href);
					url.searchParams.set('filter_set', '1');
					url.searchParams.set('filter_hostids[0]', hostid);
					url.searchParams.set('context', 'host');

					const item_link = document.createElement('a');
					item_link.setAttribute('href', url.toString());
					item_link.textContent = <?= json_encode(_('Web')); ?>;

					cell_inner.appendChild(item_link);

					if (items > 0) {
						const count = document.createElement('sup');
						count.textContent = items;

						cell_inner.innerHTML += ' ';
						cell_inner.appendChild(count);
					}
				})
				.setCellRenderer('status', ({cell_data, cell_inner}) => {
					const [hostid, status, disabled_by_lld] = cell_data;
					const is_monitored = status == HOST_STATUS_MONITORED;

					const status_link = document.createElement('a');
					status_link.classList.add(ZBX_STYLE_LINK_ACTION, is_monitored ? ZBX_STYLE_GREEN : ZBX_STYLE_RED);
					status_link.setAttribute('href', 'javascript:void(0);');
					status_link.textContent = is_monitored
						? <?= json_encode(_('Enabled')); ?>
						: <?= json_encode(_('Disabled')); ?>;
					status_link.addEventListener('click', e => {
						e.preventDefault();

						status_link.classList.add(ZBX_STYLE_LOADING);

						const parameters = {hostids: [hostid]};
						const callback = response => {
							this.#datatable.dispatchEvent(CDataTable.EVENT_INIT, {force_load: true});

							if (response.error) {
								const title = response.error.title ?? '';
								CMessageHelper.error(this.#datatable.getElement(), response.error.messages, title);
							}
							else {
								const title = response.success.title ?? '';
								CMessageHelper.success(this.#datatable.getElement(), response.success.messages, title);
							}
						};

						if (is_monitored) {
							this.disable(status_link, parameters, callback);
						}
						else {
							this.enable(status_link, parameters, callback);
						}
					});

					cell_inner.appendChild(status_link);

					if (disabled_by_lld != 0) {
						const description_icon = document.createElement('button');
						description_icon.classList.add('btn-icon', ZBX_ICON_ALERT_WITH_CONTENT, ZBX_STYLE_HINTBOX_WRAP);
						description_icon.setAttribute('role', 'button');
						description_icon.setAttribute('data-content', '?');
						description_icon.setAttribute('data-hintbox-html',
							<?= json_encode(_('Disabled automatically by an LLD rule.')); ?>);
						description_icon.setAttribute('data-hintbox', '1');
						description_icon.setAttribute('data-hintbox-class', ZBX_STYLE_HINTBOX_WRAP);
						description_icon.setAttribute('data-hintbox-static', '1');
						description_icon.setAttribute('aria-expanded', 'false');

						cell_inner.innerHTML += ' ';
						cell_inner.appendChild(description_icon);
					}
				})
				.setCellRenderer('availability', ({cell_data, cell_inner}) => {
					const [availability] = cell_data;

					cell_inner.innerHTML = availability;
				})
				.setCellRenderer('proxy', ({cell_data, cell_inner, response}) => {
					const [monitored_by, proxyid, proxy_groupid, assigned_proxyid, proxy, proxy_group,
						assigned_proxy] = cell_data;

					if (!monitored_by) {
						return;
					}

					const {can_edit_proxies, can_edit_proxy_groups} = response;

					const proxy_url = new URL('zabbix.php', location.href);
					proxy_url.searchParams.set('action', 'popup');
					proxy_url.searchParams.set('popup', 'proxy.edit');

					if (monitored_by == ZBX_MONITORED_BY_PROXY) {
						if (can_edit_proxies) {
							proxy_url.searchParams.set('proxyid', proxyid);

							const proxy_link = document.createElement('a');
							proxy_link.setAttribute('href', proxy_url.toString());
							proxy_link.classList.add(ZBX_STYLE_LINK_ALT);
							proxy_link.classList.add(ZBX_STYLE_GREY);
							proxy_link.textContent = proxy.name;

							cell_inner.appendChild(proxy_link);
						}
						else {
							cell_inner.innerHTML = proxy.name;
						}
					}
					else if (monitored_by == ZBX_MONITORED_BY_PROXY_GROUP) {
						if (can_edit_proxy_groups) {
							const proxy_group_url = new URL('zabbix.php', location.href);
							proxy_group_url.searchParams.set('action', 'popup');
							proxy_group_url.searchParams.set('popup', 'proxygroup.edit');
							proxy_group_url.searchParams.set('proxy_groupid', proxy_groupid);

							const proxy_group_link = document.createElement('a');
							proxy_group_link.setAttribute('href', proxy_group_url.toString());
							proxy_group_link.classList.add(ZBX_STYLE_LINK_ALT);
							proxy_group_link.classList.add(ZBX_STYLE_GREY);
							proxy_group_link.textContent = proxy_group.name;

							cell_inner.appendChild(proxy_group_link);
						}
						else {
							cell_inner.innerHTML = proxy_group.name;
						}

						if (assigned_proxyid != 0) {
							cell_inner.innerHTML += NAME_DELIMITER;

							if (can_edit_proxies) {
								proxy_url.searchParams.set('proxyid', assigned_proxyid);

								const proxy_link = document.createElement('a');
								proxy_link.setAttribute('href', proxy_url.toString());
								proxy_link.classList.add(ZBX_STYLE_LINK_ALT);
								proxy_link.classList.add(ZBX_STYLE_GREY);
								proxy_link.textContent = assigned_proxy.name;

								cell_inner.appendChild(proxy_link);
							}
							else {
								cell_inner.innerHTML += assigned_proxy.name;
							}
						}
					}
				})
				.setCellRenderer('templates', ({cell_data, cell_inner, response}) => {
					const [templates] = cell_data;
					const {max_in_table} = response;
					const max_in_table_exceeded = templates.length > max_in_table;
					const visible_templates = Object.values(templates).slice(0, max_in_table);

					visible_templates.forEach(({templateid, name, parentTemplates, editable}, i) => {
						const element = editable ? document.createElement('a') : document.createElement('span');

						if (editable) {
							const url = new URL('zabbix.php', location.href);
							url.searchParams.set('action', 'popup');
							url.searchParams.set('popup', 'template.edit');
							url.searchParams.set('templateid', templateid);

							element.classList.add(ZBX_STYLE_LINK_ALT);
							element.setAttribute('href', url.toString());
						}

						element.classList.add('grey');
						element.textContent = name;

						cell_inner.appendChild(element);

						if (parentTemplates.length > 0) {
							cell_inner.innerHTML += ' (';

							parentTemplates.forEach(({templateid, name, editable}, j) => {
								const element = editable ? document.createElement('a') : document.createElement('span');

								if (editable) {
									const url = new URL('zabbix.php', location.href);
									url.searchParams.set('action', 'popup');
									url.searchParams.set('popup', 'template.edit');
									url.searchParams.set('templateid', templateid);

									element.classList.add(ZBX_STYLE_LINK_ALT);
									element.setAttribute('href', url.toString());
								}

								element.classList.add('grey');
								element.textContent = name;

								cell_inner.appendChild(element);

								if (j < parentTemplates.length - 1) {
									cell_inner.innerHTML += ', ';
								}
							});

							cell_inner.innerHTML += ')';
						}

						if (i < visible_templates.length - 1) {
							cell_inner.innerHTML += ', ';
						}
					});

					if (max_in_table_exceeded) {
						cell_inner.innerHTML += ' &hellip;';
					}
				})
				.setCellRenderer('info', ({cell_data, cell_inner}) => {
					const [info_icons] = cell_data;

					const info = document.createElement('div');
					info.classList.add(ZBX_STYLE_REL_CONTAINER);

					info_icons.forEach(icon => info.innerHTML += icon);

					cell_inner.appendChild(info);
				})
				.setCellRenderer('encryption', ({cell_data, cell_inner}) => {
					const [tls_accept, tls_connect] = cell_data;

					if (tls_connect == HOST_ENCRYPTION_NONE
						&& (tls_accept & HOST_ENCRYPTION_NONE) == HOST_ENCRYPTION_NONE
						&& (tls_accept & HOST_ENCRYPTION_PSK) != HOST_ENCRYPTION_PSK
						&& (tls_accept & HOST_ENCRYPTION_CERTIFICATE) != HOST_ENCRYPTION_CERTIFICATE) {

						const none = document.createElement('span');
						none.classList.add(ZBX_STYLE_STATUS_GREEN);
						none.textContent = <?= json_encode(_('None')); ?>;

						const encryption = document.createElement('div');
						encryption.classList.add(ZBX_STYLE_STATUS_CONTAINER);
						encryption.appendChild(none);

						cell_inner.appendChild(encryption);
					}
					else {
						const in_encryption = document.createElement('span');
						in_encryption.classList.add(ZBX_STYLE_STATUS_GREEN);

						// Incoming encryption.
						if (tls_connect == HOST_ENCRYPTION_NONE) {
							in_encryption.textContent = <?= json_encode(_('None')); ?>;
						}
						else if (tls_connect == HOST_ENCRYPTION_PSK) {
							in_encryption.textContent = <?= json_encode(_('PSK')); ?>;
						}
						else {
							in_encryption.textContent = <?= json_encode(_('CERT')); ?>;
						}

						in_encryption.classList.add('in');

						const out_encryption = document.createElement('span');
						out_encryption.classList.add('out');

						const none = document.createElement('span');
						none.textContent = <?= json_encode(_('None')); ?>;

						if ((tls_accept & HOST_ENCRYPTION_NONE) == HOST_ENCRYPTION_NONE) {
							none.classList.add(ZBX_STYLE_STATUS_GREEN);
						}
						else {
							none.classList.add(ZBX_STYLE_STATUS_GREY);
						}

						out_encryption.appendChild(none);

						const psk = document.createElement('span');
						psk.textContent = <?= json_encode(_('PSK')); ?>;

						if ((tls_accept & HOST_ENCRYPTION_PSK) == HOST_ENCRYPTION_PSK) {
							psk.classList.add(ZBX_STYLE_STATUS_GREEN);
						}
						else {
							psk.classList.add(ZBX_STYLE_STATUS_GREY);
						}

						out_encryption.appendChild(psk);

						const cert = document.createElement('span');
						cert.textContent = <?= json_encode(_('CERT')); ?>;

						if ((tls_accept & HOST_ENCRYPTION_CERTIFICATE) == HOST_ENCRYPTION_CERTIFICATE) {
							cert.classList.add(ZBX_STYLE_STATUS_GREEN);
						}
						else {
							cert.classList.add(ZBX_STYLE_STATUS_GREY);
						}

						out_encryption.appendChild(cert);

						const encryption = document.createElement('div');
						encryption.classList.add(ZBX_STYLE_STATUS_CONTAINER, ZBX_STYLE_NOWRAP);
						encryption.appendChild(in_encryption);
						encryption.innerHTML += ' ';
						encryption.appendChild(out_encryption);

						cell_inner.appendChild(encryption);
					}
				})
				.on(CMessageHelper.EVENT_MESSAGE, e => {
					e.stopPropagation();

					const {type, title, messages} = e.detail;

					clearMessages();
					addMessage(makeMessageBox(type, messages, title));
				})
				.on(CPager.EVENT_STATE_CHANGE, e => {
					const {page} = e.detail;

					new CState().setParams({page});
				})
				.init(user_configs);

			this.#datatable.getCheckboxColumn()
				.setWidth('58px')
				.getDefaults().setWidth('58px');
		}

		massDeleteHosts(button) {
			const confirm_text = Object.keys(chkbxRange.getSelectedIds()).length > 1
				? <?= json_encode(_('Delete selected hosts?')); ?>
				: <?= json_encode(_('Delete selected host?')); ?>;

			if (!confirm(confirm_text)) {
				return;
			}

			button.classList.add('is-loading');

			const url = new URL('zabbix.php', location.href);
			url.searchParams.set('action', 'host.massdelete');
			url.searchParams.set(CSRF_TOKEN_NAME, this.#csrf_token);

			fetch(url.toString(), {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: urlEncodeData({hostids: Object.keys(chkbxRange.getSelectedIds())})
			})
				.then(response => response.json())
				.then(response => this.reload(response))
				.catch(() => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')); ?>]);

					addMessage(message_box);
				})
				.finally(() => {
					button.classList.remove('is-loading');
				});
		}
	};
</script>
