<?php
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

		init({context, confirm_messages, field_switches, form_name, hostid, token, filter_values}) {
			this.confirm_messages = confirm_messages;
			this.token = token;
			this.context = context;
			this.hostid = hostid;

			this.form = document.forms[form_name];
			this.filter_form = document.querySelector('form[name="<?= CFilter::FORM_NAME; ?>"]');

			this.initForm(field_switches);
			this.initEvents();
			this.#initPopupListeners();

			this._init_filter_values = this.getInitFilterValues(filter_values);
		}

		initForm(field_switches) {
			new CViewSwitcher('filter_type', 'change', field_switches.for_type);

			$('#filter-tags')
				.dynamicRows({template: '#filter-tag-row-tmpl'})
				.on('afteradd.dynamicRows', function() {
					const rows = this.querySelectorAll('.form_row');

					new CTagFilterItem(rows[rows.length - 1]);
				});

			document.querySelectorAll('#filter-tags .form_row').forEach(row => {
				new CTagFilterItem(row);
			});
		}

		initEvents() {
			this.filter_form.addEventListener('click', e => {
				const target = e.target;

				if (target.matches('.link-action') && target.closest('.subfilter') !== null) {
					const filters = {...this._init_filter_values, subfilter_set: 1};
					const subfilter = target.closest('.subfilter');

					const key_parts = [...target.getAttribute('data-name').matchAll(/[^\[\]]+|\[\]/g)];

					const update_filter = (current_filter, key_parts, i, value, remove) => {
						const key_name = key_parts[i][0];

						if (i == key_parts.length - 1) {
							if (remove) {
								current_filter.forEach((value, idx) => {
									if (value == target.getAttribute('data-value')) {
										delete current_filter[idx];
									}
								});
							}
							else {
								current_filter.push(value);
							}

							return current_filter;
						}
						else {
							if (!(key_name in current_filter)) {
								current_filter[key_name] = [];
							}

							update_filter(current_filter[key_name], key_parts, i + 1, value, remove);

							if (i == key_parts.length - 2) {
								current_filter[key_name] = Object.values(current_filter[key_name]);
							}
							else {
								current_filter[key_name] = Object.assign({}, current_filter[key_name]);
							}

							if (Object.values(current_filter[key_name]).length == 0) {
								delete current_filter[key_name];
							}
						}
					};

					update_filter(filters, key_parts, 0, target.getAttribute('data-value'),
						subfilter.matches('.subfilter-enabled')
					);

					location.href = zabbixUrl(filters);
				}
				else if (target.matches('[name="filter_state"]')) {
					const disabled = e.target.getAttribute('value') != -1;

					this.filter_form.querySelectorAll('input[name=filter_status]').forEach(checkbox => {
						checkbox.toggleAttribute('disabled', disabled);
					});
				}
			});

			this.filter_form.addEventListener('submit', e => {
				e.preventDefault();

				const filters = {
					...getFormFields(e.target),
					filter_set: 1,
					sort: this._init_filter_values.sort,
					sortorder: this._init_filter_values.sortorder
				};

				Object.keys(filters).forEach(key => {
					if (key.startsWith('subfilter_')) {
						delete filters[key];
					}
				});

				location.href = zabbixUrl(filters);
			});

			this.form.querySelectorAll('.list-table thead th a').forEach(link => {
				link.addEventListener('click', e => {
					e.preventDefault();

					const search_params = new URLSearchParams(e.currentTarget.href);

					const filters = {...this._init_filter_values,
						subfilter_set: 1,
						sort: search_params.get('sort'),
						sortorder: search_params.get('sortorder')
					};

					location.href = zabbixUrl(filters);
				});
			});

			this.form.addEventListener('click', (e) => {
				const target = e.target;
				const itemids = Object.keys(chkbxRange.getSelectedIds());

				if (target.classList.contains('js-enable-item')) {
					this.#enable(null, {itemids: [target.dataset.itemid], context: this.context});
				}
				else if (target.classList.contains('js-disable-item')) {
					this.#disable(null, {itemids: [target.dataset.itemid], context: this.context});
				}
				else if (target.classList.contains('js-massenable-item')) {
					this.#enable(target, {itemids: itemids, context: this.context});
				}
				else if (target.classList.contains('js-massdisable-item')) {
					this.#disable(target, {itemids: itemids, context: this.context});
				}
				else if (target.classList.contains('js-massexecute-item')) {
					this.#execute(target, {itemids: itemids, context: this.context});
				}
				else if (target.classList.contains('js-massclearhistory-item')) {
					this.#clear(target, {itemids: itemids, context: this.context});
				}
				else if (target.classList.contains('js-masscopy-item')) {
					this.#copy(target, itemids);
				}
				else if (target.classList.contains('js-massupdate-item')) {
					this.#massupdate(target, {ids: itemids, context: this.context});
				}
				else if (target.classList.contains('js-massdelete-item')) {
					this.#delete(target, {itemids: itemids, context: this.context});
				}
			});

			document.querySelector('.js-create-item')?.addEventListener('click', () => {
				ZABBIX.PopupManager.open('item.edit', {hostid: this.hostid, context: this.context});
			});
		}

		getInitFilterValues(filter_values) {
			filter_values.action = 'item.list';
			filter_values.context = this.context;

			const clear_keys = ['filter_set', 'filter_rst', 'page', 'subfilter_set'];

			clear_keys.forEach(key => delete filter_values[key]);

			return filter_values;
		}

		executeNow(target, data) {
			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'item.execute');
			this.#post(curl, data);
		}

		#enable(target, parameters) {
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.enable');

			if (target !== null) {
				this.#confirmAction(curl, parameters, target);
			}
			else {
				this.#post(curl, parameters);
			}
		}

		#disable(target, parameters) {
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.disable');

			if (target !== null) {
				this.#confirmAction(curl, parameters, target);
			}
			else {
				this.#post(curl, parameters);
			}
		}

		#execute(target, parameters) {
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.execute');

			this.#confirmAction(curl, parameters, target);
		}

		#clear(target, parameters) {
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.clear');

			this.#confirmAction(curl, parameters, target);
		}

		#copy(target, itemids) {
			const overlay = PopUp('copy.edit', {source: 'items', itemids}, {
				dialogueid: 'copy',
				dialogue_class: 'modal-popup-static',
				trigger_element: target
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.elementSuccess.bind(this), {once: true});
		}

		#massupdate(target, parameters) {
			const overlay = PopUp('item.massupdate', {...this.token, ...parameters, prototype: 0}, {
				dialogue_class: 'modal-popup-preprocessing',
				trigger_element: target
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit',
				e => this.elementSuccess('title' in e.detail ? {detail: {success: e.detail}} : e),
				{once: true}
			);
		}

		#delete(target, parameters) {
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.delete');

			this.#confirmAction(curl, parameters, target);
		}

		#confirmAction(curl, data, target) {
			const confirm = this.confirm_messages[curl.getArgument('action')];
			const message = confirm ? confirm[data.itemids.length > 1 ? 1 : 0] : '';

			if (message != '' && !window.confirm(message)) {
				return;
			}

			target.classList.add('is-loading');
			this.#post(curl, data)
				.finally(() => {
					target.classList.remove('is-loading');
					target.blur();
				});
		}

		#post(curl, data) {
			const action = curl.getArgument('action');

			return fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({...this.token, ...data})
			})
				.then((response) => response.json())
				.then((response) => this.elementSuccess({detail: {action, ...response}}))
				.catch(() => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

					addMessage(message_box);
				});
		}

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: ({data, descriptor, event}) => {
					if ('error' in data.submit) {
						if ('title' in data.submit.error) {
							postMessageError(data.submit.error.title);
						}

						postMessageDetails('error', data.submit.error.messages);
					}
					else {
						chkbxRange.clearSelectedOnFilterChange();
					}

					// If host or template was deleted while being in item list, redirect to item list.
					if (descriptor.action !== 'item.delete' && data.submit.success?.action === 'delete') {
						const url = new URL('zabbix.php', location.href);

						url.searchParams.set('action', 'item.list');
						url.searchParams.set('context', this.context);

						event.setRedirectUrl(url.href);
					}
				}
			});
		}

		elementSuccess(e) {
			let new_href = location.href;
			const response = e.detail;

			if ('error' in response) {
				if ('title' in response.error) {
					postMessageError(response.error.title);
				}

				postMessageDetails('error', response.error.messages);
			}
			else if ('success' in response) {
				chkbxRange.clearSelectedOnFilterChange();
				postMessageOk(response.success.title);

				if ('messages' in response.success) {
					postMessageDetails('success', response.success.messages);
				}

				if (response.success.action === 'delete' && response.action !== 'item.delete') {
					// Items template or host were removed, redirect to list of items.
					let list_url = new Curl('zabbix.php');

					list_url.setArgument('action', 'item.list');
					list_url.setArgument('context', this.context);
					new_href = list_url.getUrl();
				}
			}

			location.href = new_href;
		}
	};
</script>
