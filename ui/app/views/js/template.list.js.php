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
 * @var array $data
 */
?>

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate() ?>
</script>

<script>
	const view = new class {
		#csrf_token = null;

		init({
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
			this.#csrf_token = csrf_token;

			this.#initActions();
			this.#initFilter();
			this.#initPopupListeners();
			this.#initDataTable({filter, page, default_sort_field, default_sort_order, sort_field, sort_order,
				storage_idx, user_configs});
		}

		#initActions() {
			document.addEventListener('click', e => {
				if (e.target.classList.contains('js-massupdate')) {
					openMassupdatePopup('template.massupdate', {
						[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('template')); ?>
					}, {
						dialogue_class: 'modal-popup-static',
						trigger_element: e.target
					});
				}
				else if (e.target.classList.contains('js-massdelete')) {
					this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()), false);
				}
				else if (e.target.classList.contains('js-massdelete-clear')) {
					this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
			});

			document.getElementById('js-create').addEventListener('click', e => {
				ZABBIX.PopupManager.open('template.edit', {groupids: JSON.parse(e.target.dataset.groupids)});
			});

			document.getElementById('js-import').addEventListener('click', () => {
				PopUp("popup.import", {
					rules_preset: "template",
					[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('import')); ?>
				}, {
					dialogueid: "popup_import",
					dialogue_class: "modal-popup-generic"
				});
			});
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

			const filter_fields = ['#filter_groups_', '#filter_templates_']

			filter_fields.forEach(filter => {
				$(filter).on('change', () => this.#updateMultiselect($(filter)));
				this.#updateMultiselect($(filter));
			})
		}

		#initDataTable({filter, page, default_sort_field, default_sort_order, sort_field, sort_order, storage_idx,
				user_configs}) {

			const data_provider_url = new URL('zabbix.php', location.href);
			data_provider_url.searchParams.set('action', 'template.list.data');
			data_provider_url.searchParams.set(CSRF_TOKEN_NAME, this.#csrf_token);

			const data_provider = new CDefaultDataProvider(data_provider_url.toString());

			this.datatable = new CDataTable(document.getElementById('templates'), data_provider)
				.setColumns([
					new CDataTableColumn('name', <?= json_encode(_('Name')); ?>)
						.setFields(['templateid', 'name'])
						.setRenderer('name')
						.setSortable(true)
						.setTogglable(false)
						.setWidth('auto'),
					new CDataTableColumn('hosts', <?= json_encode(_('Hosts')); ?>)
						.setFields(['templateid', 'hosts'])
						.setRenderer('hosts'),
					new CDataTableColumn('items', <?= json_encode(_('Items')); ?>)
						.setFields(['templateid', 'items'])
						.setRenderer('items'),
					new CDataTableColumn('triggers', <?= json_encode(_('Triggers')); ?>)
						.setFields(['templateid', 'triggers'])
						.setRenderer('triggers'),
					new CDataTableColumn('graphs', <?= json_encode(_('Graphs')); ?>)
						.setFields(['templateid', 'graphs'])
						.setRenderer('graphs'),
					new CDataTableColumn('dashboards', <?= json_encode(_('Dashboards')); ?>)
						.setFields(['templateid', 'dashboards'])
						.setRenderer('dashboards'),
					new CDataTableColumn('discovery', <?= json_encode(_('Discovery')); ?>)
						.setFields(['templateid', 'discoveryRules'])
						.setRenderer('discovery'),
					new CDataTableColumn('web', <?= json_encode(_('Web')); ?>)
						.setFields(['templateid', 'httpTests'])
						.setRenderer('web'),
					new CDataTableColumn('vendor', <?= json_encode(_('Vendor')); ?>)
						.setFields(['vendor_name']),
					new CDataTableColumn('version', <?= json_encode(_('Version')); ?>)
						.setFields(['vendor_version']),
					new CDataTableColumn('linked_templates', <?= json_encode(_('Linked templates')); ?>)
						.setFields(['parentTemplates'])
						.setRenderer('linked_templates'),
					new CDataTableColumn('linked_to_templates', <?= json_encode(_('Linked to templates')); ?>)
						.setFields(['templates'])
						.setRenderer('linked_to_templates'),
					new CDataTableColumnTags('tags', <?= json_encode(_('Tags')); ?>),
					new CDataTableColumnTagValue('tagvalue', <?= json_encode(_('Tag value')); ?>),
					new CDataTableColumnCustomText('custom_text', <?= json_encode(_('Custom text')); ?>)
				])
				.setPage(page)
				.setFilter(filter)
				.setSelectable('templates', 'templates', ['templateid'])
				.setDefaultSortField(default_sort_field)
				.setDefaultSortOrder(default_sort_order)
				.setSortField(sort_field)
				.setSortOrder(sort_order)
				.setStorageIdx(storage_idx)
				.setStickyHeader(true)
				.setStickyFooter(true)
				.setCellRenderer('name', ({cell_data, cell_inner}) => {
					const [templateid, name] = cell_data;

					const url = new URL('zabbix.php', location.href);
					url.searchParams.set('action', 'popup');
					url.searchParams.set('popup', 'template.edit');
					url.searchParams.set('templateid', templateid);

					const edit_link = document.createElement('a');
					edit_link.setAttribute('href', url.toString())
					edit_link.textContent = name;

					cell_inner.appendChild(edit_link);
				})
				.setCellRenderer('hosts', ({cell_data, cell_inner, response}) => {
					const [templateid, editable_hosts] = cell_data;
					const {allowed_ui_conf_hosts} = response;

					let items = Object.keys(editable_hosts).length;

					if (allowed_ui_conf_hosts) {
						const url = new URL('zabbix.php', location.href);
						url.searchParams.set('action', 'host.list');
						url.searchParams.set('filter_set', '1');
						url.searchParams.set('filter_templates[0]', templateid);

						const item_link = document.createElement('a');
						item_link.setAttribute('href', url.toString());
						item_link.textContent = <?= json_encode(_('Hosts')); ?>;

						cell_inner.appendChild(item_link);
					}
					else {
						cell_inner.innerHTML += <?= json_encode(_('Hosts')); ?>;
					}

					if (items > 0) {
						const count = document.createElement('sup');
						count.textContent = items;

						cell_inner.innerHTML += ' ';
						cell_inner.appendChild(count);
					}
				})
				.setCellRenderer('items', ({cell_data, cell_inner}) => {
					const [templateid, items] = cell_data;

					const url = new URL('zabbix.php', location.href);
					url.searchParams.set('action', 'item.list');
					url.searchParams.set('filter_set', '1');
					url.searchParams.set('filter_hostids[0]', templateid);
					url.searchParams.set('context', 'template');

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
					const [templateid, triggers] = cell_data;

					const url = new URL('zabbix.php', location.href);
					url.searchParams.set('action', 'trigger.list');
					url.searchParams.set('filter_set', '1');
					url.searchParams.set('filter_hostids[0]', templateid);
					url.searchParams.set('context', 'template');

					const item_link = document.createElement('a');
					item_link.setAttribute('href', url.toString());
					item_link.textContent = <?= json_encode(_('Triggers')); ?>;

					cell_inner.appendChild(item_link);

					if (triggers > 0) {
						const count = document.createElement('sup');
						count.textContent = triggers;

						cell_inner.innerHTML += ' ';
						cell_inner.appendChild(count);
					}
				})
				.setCellRenderer('graphs', ({cell_data, cell_inner}) => {
					const [templateid, graphs] = cell_data;

					const url = new URL('zabbix.php', location.href);
					url.searchParams.set('action', 'graph.list');
					url.searchParams.set('filter_set', '1');
					url.searchParams.set('filter_hostids[0]', templateid);
					url.searchParams.set('context', 'template');

					const item_link = document.createElement('a');
					item_link.setAttribute('href', url.toString());
					item_link.textContent = <?= json_encode(_('Graphs')); ?>;

					cell_inner.appendChild(item_link);

					if (graphs > 0) {
						const count = document.createElement('sup');
						count.textContent = graphs;

						cell_inner.innerHTML += ' ';
						cell_inner.appendChild(count);
					}
				})
				.setCellRenderer('dashboards', ({cell_data, cell_inner}) => {
					const [templateid, dashboards] = cell_data;

					const url = new URL('zabbix.php', location.href);
					url.searchParams.set('action', 'template.dashboard.list');
					url.searchParams.set('templateid', templateid);
					url.searchParams.set('context', 'template');

					const item_link = document.createElement('a');
					item_link.setAttribute('href', url.toString());
					item_link.textContent = <?= json_encode(_('Dashboards')); ?>;

					cell_inner.appendChild(item_link);

					if (dashboards > 0) {
						const count = document.createElement('sup');
						count.textContent = dashboards;

						cell_inner.innerHTML += ' ';
						cell_inner.appendChild(count);
					}
				})
				.setCellRenderer('discovery', ({cell_data, cell_inner}) => {
					const [templateid, discovery_rules] = cell_data;

					const url = new URL('host_discovery.php', location.href);
					url.searchParams.set('filter_set', '1');
					url.searchParams.set('filter_hostids[0]', templateid);
					url.searchParams.set('context', 'template');

					const item_link = document.createElement('a');
					item_link.setAttribute('href', url.toString());
					item_link.textContent = <?= json_encode(_('Discovery')); ?>;

					cell_inner.appendChild(item_link);

					if (discovery_rules > 0) {
						const count = document.createElement('sup');
						count.textContent = discovery_rules;

						cell_inner.innerHTML += ' ';
						cell_inner.appendChild(count);
					}
				})
				.setCellRenderer('web', ({cell_data, cell_inner}) => {
					const [templateid, http_tests] = cell_data;

					const url = new URL('httpconf.php', location.href);
					url.searchParams.set('filter_set', '1');
					url.searchParams.set('filter_hostids[0]', templateid);
					url.searchParams.set('context', 'template');

					const item_link = document.createElement('a');
					item_link.setAttribute('href', url.toString());
					item_link.textContent = <?= json_encode(_('Web')); ?>;

					cell_inner.appendChild(item_link);

					if (http_tests > 0) {
						const count = document.createElement('sup');
						count.textContent = http_tests;

						cell_inner.innerHTML += ' ';
						cell_inner.appendChild(count);
					}
				})
				.setCellRenderer('linked_templates', ({cell_data, cell_inner, response}) => {
					const [parent_templates] = cell_data;
					const {max_in_table} = response;
					const length = Math.min(max_in_table, parent_templates.length);

					for (let i = 0; i < length; i++) {
						const template = parent_templates[i];

						if (template.editable) {
							const url = new URL('zabbix.php', location.href);
							url.searchParams.set('action', 'popup');
							url.searchParams.set('popup', 'template.edit');
							url.searchParams.set('templateid', template.templateid);

							const template_link = document.createElement('a');
							template_link.classList.add(ZBX_STYLE_LINK_ALT, ZBX_STYLE_GREY);
							template_link.setAttribute('href', url.toString());
							template_link.textContent = template.name;

							cell_inner.appendChild(template_link);
						}
						else {
							const template_link = document.createElement('span');
							template_link.classList.add(ZBX_STYLE_GREY);
							template_link.textContent = template.name;

							cell_inner.appendChild(template_link);
						}

						if (i < length - 1) {
							cell_inner.innerHTML += ', ';
						}
					}

					if (parent_templates.length > max_in_table) {
						cell_inner.innerHTML += ' &hellip;';
					}
				})
				.setCellRenderer('linked_to_templates', ({cell_data, cell_inner, response}) => {
					const [templates] = cell_data;
					const {max_in_table} = response;
					const length = Math.min(max_in_table, templates.length);

					for (let i = 0; i < length; i++) {
						const template = templates[i];

						if (template.editable) {
							const url = new URL('zabbix.php', location.href);
							url.searchParams.set('action', 'popup');
							url.searchParams.set('popup', 'template.edit');
							url.searchParams.set('templateid', template.templateid);

							const template_link = document.createElement('a');
							template_link.classList.add(ZBX_STYLE_LINK_ALT, ZBX_STYLE_GREY);
							template_link.setAttribute('href', url.toString());
							template_link.textContent = template.name;

							cell_inner.appendChild(template_link);
						}
						else {
							const template_link = document.createElement('span');
							template_link.classList.add(ZBX_STYLE_GREY);
							template_link.textContent = template.name;

							cell_inner.appendChild(template_link);
						}

						if (i < length - 1) {
							cell_inner.innerHTML += ', ';
						}
					}

					if (templates.length > max_in_table) {
						cell_inner.innerHTML += ' &hellip;';
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
		}

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: () => uncheckTableRows('templates')
			});
		}

		#delete(target, templateids, clear) {
			let confirmation;
			const curl = new Curl('zabbix.php');

			if (clear) {
				confirmation = templateids.length > 1
					? <?= json_encode(
						_('Delete and clear selected templates? (Warning: all linked hosts will be cleared!)')
					) ?>
					: <?= json_encode(
						_('Delete and clear selected template? (Warning: all linked hosts will be cleared!)')
					) ?>;

				curl.setArgument('action', 'template.delete');
				curl.setArgument('clear', 1);
			}
			else {
				confirmation = templateids.length > 1
					? <?= json_encode(_('Delete selected templates?')); ?>
					: <?= json_encode(_('Delete selected template?')); ?>;

				curl.setArgument('action', 'template.delete');
			}

			curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('template')); ?>);

			if (!window.confirm(confirmation)) {
				return;
			}

			target.classList.add('is-loading');

			return fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({templateids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('templates', response.keepids);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('templates');
					}

					location.href = location.href;
				})
				.catch(() => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')); ?>]);

					addMessage(message_box);
				})
				.finally(() => {
					target.classList.remove('is-loading');
				});
		}

		#updateMultiselect($ms) {
			$ms.multiSelect('setDisabledEntries', [...$ms.multiSelect('getData').map((entry) => entry.id)]);
		}
	};
</script>
