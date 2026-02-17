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

		init({context, confirm_messages, field_switches, form_name, hostids, token, filter_values}) {
			this.confirm_messages = confirm_messages;
			this.token = token;
			this.context = context;
			this.hostids = hostids;

			this.form = document.forms[form_name];
			this.filter_form = document.querySelector('form[name="zbx_filter"]');

			this.initForm(field_switches);
			this.initEvents();
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
					const url = this.getPageUrl();
					const subfilter = target.closest('.subfilter');

					if (subfilter.matches('.subfilter-enabled')) {
						url.searchParams.delete(target.getAttribute('data-name'), target.getAttribute('data-value'));
					}
					else {
						url.searchParams.append(target.getAttribute('data-name'), target.getAttribute('data-value'));
					}

					this.#loadPageWithFilters(url, {subfilter_set: 1});
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

				const search_params = new URLSearchParams(new FormData(e.target));
				const url = new URL('', window.location.origin + window.location.pathname);

				url.searchParams.set('filter_set', '1');

				search_params.forEach((filter_value, filter_key) => {
					if (!filter_key.startsWith('subfilter_')) {
						url.searchParams.append(filter_key, filter_value);
					}
				});

				this.#loadPageWithFilters(url, {filter_set: 1}, false);
			});

			this.form.querySelectorAll('.list-table thead th a').forEach(link => {
				link.addEventListener('click', e => {
					e.preventDefault();

					const search_params = new URLSearchParams(e.currentTarget.href);

					this.#loadPageWithFilters(this.getPageUrl(), {
						subfilter_set: 1,
						sort: search_params.get('sort'),
						sortorder: search_params.get('sortorder')
					});
				});
			});

			this.form.addEventListener('click', (e) => {
				const target = e.target;
				const itemids = Object.keys(chkbxRange.getSelectedIds());

				if (target.classList.contains('js-update-item')) {
					this.#edit(target, {itemid: target.dataset.itemid, context: this.context});
				}
				else if (target.classList.contains('js-enable-item')) {
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
				else if (target.classList.contains('js-edit-host')) {
					this.editHost(e, target.dataset.hostid);
				}
				else if (target.classList.contains('js-edit-template')) {
					this.editTemplate(e, target.dataset.hostid);
				}
			});

			document.addEventListener('click', e => {
				const target = e.target;

				if (target.classList.contains('js-create-item')) {
					this.#edit(target, target.dataset);
				}
				else if (target.classList.contains('js-trigger-edit')) {
					this.editTrigger(target.dataset);
				}
			});
		}

		getInitFilterValues(filter_values) {
			const filters = Object.keys(filter_values).reduce((filtered, filter_key) => {
				if (filter_key.includes('filter_') || filter_key.startsWith('sort')) {
					if (Array.isArray(filter_values[filter_key])) {
						filter_values[filter_key].forEach(value => filtered.push({key: `${filter_key}[]`, value}));
					}
					else if (typeof filter_values[filter_key] === 'object') {
						Object.keys(filter_values[filter_key]).forEach(key =>
							filter_values[filter_key][key].forEach(value =>
								filtered.push({key: `${filter_key}[${key}][]`, value})
							)
						)
					}
					else {
						filtered.push({key: filter_key, value: filter_values[filter_key]});
					}
				}

				return filtered;
			}, []);

			return filters;
		}

		getPageUrl() {
			const url = new URL('', window.location.origin + window.location.pathname);

			url.searchParams.set('action', 'item.list');
			url.searchParams.set('context', this.context);

			this._init_filter_values.forEach(filter => url.searchParams.append(filter.key, filter.value));

			return url;
		}

		editItem(target, data) {
			this.#edit(target, data);
		}

		editTrigger(trigger_data) {
			clearMessages();

			const overlay = PopUp('trigger.edit', trigger_data, {
				dialogueid: 'trigger-edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.elementSuccess.bind(this), {once: true});
		}

		executeNow(target, data) {
			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'item.execute');
			this.#post(curl, data);
		}

		editHost(e, hostid) {
			e.preventDefault();
			const host_data = {hostid};

			this.openHostPopup(host_data);
		}

		editTemplate(e, templateid) {
			e.preventDefault();
			const template_data = {templateid};

			this.openTemplatePopup(template_data);
		}

		openHostPopup(host_data) {
			let original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', e => {
				history.replaceState({}, '', original_url);
				this.elementSuccess(e);
			}, {once: true});

			overlay.$dialogue[0].addEventListener('dialogue.close', e => history.replaceState({}, '', original_url));
		}

		openTemplatePopup(template_data) {
			const overlay =  PopUp('template.edit', template_data, {
				dialogueid: 'templates-form',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.elementSuccess.bind(this), {once: true});
		}

		#loadPageWithFilters(url, overwriteFilters = {}) {
			const clear_keys = ['filter_set', 'filter_rst', 'page', 'subfilter_set'];

			clear_keys.forEach(key => url.searchParams.delete(key));

			Object.keys(overwriteFilters).forEach(
				filter_key => url.searchParams.set(filter_key, overwriteFilters[filter_key])
			);

			window.location.href = url.href;
		}

		#edit(target, parameters = {}) {
			const overlay = PopUp('item.edit', parameters, {
				dialogueid: 'item-edit',
				dialogue_class: 'modal-popup-large',
				trigger_element: target,
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.elementSuccess.bind(this), {once: true});
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
					list_url.setArgument('filter_set', 1);
					new_href = list_url.getUrl();
				}
			}

			location.href = new_href;
		}
	};
</script>
