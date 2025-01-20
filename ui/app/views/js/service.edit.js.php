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
?>


window.service_edit_popup = new class {

	init({tabs_id, serviceid, children, children_problem_tags_html, problem_tags, status_rules, search_limit}) {
		this.#initTemplates();

		this.serviceid = serviceid;

		this.search_limit = search_limit;

		this.overlay = overlays_stack.getById('service.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.footer = this.overlay.$dialogue.$footer[0];

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'service.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		for (const status_rule of status_rules) {
			this.#addStatusRule(status_rule);
		}

		this.children = new Map();

		for (const service of children) {
			this.children.set(service.serviceid, {
				serviceid: service.serviceid,
				name: service.name,
				problem_tags_html: children_problem_tags_html[service.serviceid]
			});
		}

		this.#filterChildren();

		// Setup parent services.

		jQuery('#parent_serviceids_')
			.multiSelect('getSelectButton')
			.addEventListener('click', () => {
				this.#selectParents();
			});

		// Setup problem tags.

		const $problem_tags = jQuery(document.getElementById('problem_tags'));

		$problem_tags.dynamicRows({
			template: '#problem-tag-row-tmpl',
			allow_empty: true,
			rows: problem_tags,
		});

		$problem_tags.on('tableupdate.dynamicRows', () => this.#update());

		document.getElementById('problem_tags').addEventListener('change', () => this.#update());

		// Setup service rules.

		document
			.getElementById('status_rules')
			.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this.#editStatusRule();
				}
				else if (e.target.classList.contains('js-edit')) {
					this.#editStatusRule(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					e.target.closest('tr').remove();
				}
			});

		// Setup tags tab.

		const tabs = jQuery('#' + tabs_id);

		const initialize_tags = (event, ui) => {
			const $panel = event.type === 'tabscreate' ? ui.panel : ui.newPanel;

			if ($panel.attr('id') === 'tags-tab') {
				const $tags = $panel.find('.tags-table');

				$tags
					.dynamicRows({template: '#tag-row-tmpl', allow_empty: true})
					.on('afteradd.dynamicRows', () => {
						$tags
							.find('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>')
							.textareaFlexible();
					})
					.find('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>')
					.textareaFlexible();

					tabs.off('tabscreate tabsactivate', initialize_tags);
			}
		};

		tabs.on('tabscreate tabsactivate', initialize_tags);

		// Setup child services.

		document
			.getElementById('children-filter')
			.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-filter')) {
					this.#filterChildren();
				}
				else if (e.target.classList.contains('js-reset')) {
					document.getElementById('children-filter-name').value = '';
					this.#filterChildren();
				}
			});

		document
			.getElementById('children-filter-name')
			.addEventListener('keypress', (e) => {
				if (e.key === 'Enter') {
					this.#filterChildren();
					e.preventDefault();
				}
			}, {passive: false});

		document
			.getElementById('children')
			.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this.#selectChildren();
				}
				else if (e.target.classList.contains('js-remove')) {
					this.#removeChild(e.target.closest('tr').dataset.serviceid);
				}
			});

		// Update form field state according to the form data.

		for (const id of ['propagation_rule', 'algorithm']) {
			document.getElementById(id).addEventListener('change', () => this.#update());
		}

		this.#update();

		new CFormFieldsetCollapsible(document.getElementById('advanced-configuration'));
	}

	#initTemplates() {
		this.status_rule_template = new Template(`
			<tr data-row_index="#{row_index}">
				<td>
					#{*name}
					<input type="hidden" id="status_rules_#{row_index}_new_status" name="status_rules[#{row_index}][new_status]" value="#{new_status}">
					<input type="hidden" id="status_rules_#{row_index}_type" name="status_rules[#{row_index}][type]" value="#{type}">
					<input type="hidden" id="status_rules_#{row_index}_limit_value" name="status_rules[#{row_index}][limit_value]" value="#{limit_value}">
					<input type="hidden" id="status_rules_#{row_index}_limit_status" name="status_rules[#{row_index}][limit_status]" value="#{limit_status}">
				</td>
				<td>
					<ul class="<?= ZBX_STYLE_HOR_LIST ?>">
						<li>
							<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-edit"><?= _('Edit') ?></button>
						</li>
						<li>
							<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
						</li>
					</ul>
				</td>
			</tr>
		`);

		this.child_template = new Template(`
			<tr data-serviceid="#{serviceid}">
				<td class="<?= ZBX_STYLE_WORDWRAP ?>" style="max-width: <?= ZBX_TEXTAREA_BIG_WIDTH ?>px;">#{name}</td>
				<td class="<?= ZBX_STYLE_WORDWRAP ?>">#{*problem_tags_html}</td>
				<td>
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
				</td>
			</tr>
		`);
	}

	#update() {
		const propagation_rule = document.getElementById('propagation_rule').value;

		let has_problem_tags = false;

		for (const problem_tag of document.querySelectorAll('#problem_tags .js-problem-tag-tag')) {
			if (problem_tag.value !== '') {
				has_problem_tags = true;

				break;
			}
		}

		document
			.getElementById('problem_tags')
			.querySelectorAll('.js-problem-tag-input, .element-table-remove, .element-table-add')
			.forEach((element) => {
				element.disabled = this.children.size > 0;
			});

		document.getElementById('algorithm-not-applicable-warning').style.display =
			this.children.size > 0 ? 'none' : '';

		switch (propagation_rule) {
			case '<?= ZBX_SERVICE_STATUS_PROPAGATION_INCREASE ?>':
			case '<?= ZBX_SERVICE_STATUS_PROPAGATION_DECREASE ?>':
				document.getElementById('status_propagation_value_field').style.display = '';
				document.getElementById('propagation_value_number').style.display = '';
				document.getElementById('propagation_value_status').style.display = 'none';
				break;

			case '<?= ZBX_SERVICE_STATUS_PROPAGATION_FIXED ?>':
				document.getElementById('status_propagation_value_field').style.display = '';
				document.getElementById('propagation_value_number').style.display = 'none';
				document.getElementById('propagation_value_status').style.display = '';
				break;

			default:
				document.getElementById('status_propagation_value_field').style.display = 'none';
				document.getElementById('propagation_value_number').style.display = 'none';
				document.getElementById('propagation_value_status').style.display = 'none';
		}

		document.querySelector('#children .js-add').disabled = has_problem_tags;
	}

	#editStatusRule(row = null) {
		let parameters;

		if (row !== null) {
			const row_index = row.dataset.row_index;

			parameters = {
				edit: '1',
				row_index,
				new_status: row.querySelector(`[name="status_rules[${row_index}][new_status]"`).value,
				type: row.querySelector(`[name="status_rules[${row_index}][type]"`).value,
				limit_value: row.querySelector(`[name="status_rules[${row_index}][limit_value]"`).value,
				limit_status: row.querySelector(`[name="status_rules[${row_index}][limit_status]"`).value
			};
		}
		else {
			let row_index = 0;

			while (document.querySelector(`#status_rules [data-row_index="${row_index}"]`) !== null) {
				row_index++;
			}

			parameters = {row_index};
		}

		const overlay = PopUp('popup.service.statusrule.edit', parameters, {
			dialogueid: 'service_status_rule_edit',
			dialogue_class: 'modal-popup-medium'
		});

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			if (row !== null) {
				this.#updateStatusRule(row, e.detail)
			}
			else {
				this.#addStatusRule(e.detail);
			}
		});
	}

	#addStatusRule(status_rule) {
		document
			.querySelector('#status_rules tbody')
			.insertAdjacentHTML('beforeend', this.status_rule_template.evaluate(status_rule));
	}

	#updateStatusRule(row, status_rule) {
		row.insertAdjacentHTML('afterend', this.status_rule_template.evaluate(status_rule));
		row.remove();
	}

	#renderChild(service) {
		document
			.querySelector('#children tbody')
			.insertAdjacentHTML('beforeend', this.child_template.evaluate({
				serviceid: service.serviceid,
				name: service.name,
				problem_tags_html: service.problem_tags_html
			}));
	}

	#removeChild(serviceid) {
		const child = this.form.querySelector(`#children tbody tr[data-serviceid="${serviceid}"]`);

		if (child !== null) {
			child.remove();
		}

		this.children.delete(serviceid);
		this.#updateChildrenFilterStats();
		this.#updateTabIndicator();
		this.#update();
	}

	#removeAllChildren() {
		document.querySelector('#children tbody').innerHTML = '';

		this.children.clear();
		this.#updateChildrenFilterStats();
		this.#updateTabIndicator();
		this.#update();
	}

	#filterChildren() {
		const container = document.querySelector('#children tbody');

		container.innerHTML = '';

		const filter_name = document.getElementById('children-filter-name').value.toLowerCase();

		let count = 0;

		for (const service of this.children.values()) {
			if (!service.name.toLowerCase().includes(filter_name)) {
				continue;
			}

			this.#renderChild(service);

			if (++count == this.search_limit) {
				break;
			}
		}

		this.#updateChildrenFilterStats();
		this.#updateTabIndicator();
	}

	#updateChildrenFilterStats() {
		const container = document.querySelector('#children tbody');

		const stats_template = <?= json_encode(_('Displaying %1$s of %2$s found')) ?>;

		document.querySelector('#children tfoot .inline-filter-stats').textContent = this.children.size > 0
			? sprintf(stats_template, container.childElementCount, this.children.size)
			: '';
	}

	#updateTabIndicator() {
		document
			.querySelector('#children')
			.setAttribute('data-tab-indicator', this.children.size);
	}

	#selectChildren() {
		const exclude_serviceids = [];

		if (this.serviceid !== null) {
			exclude_serviceids.push(this.serviceid);
		}

		for (const input of this.form.querySelectorAll('#children tbody input')) {
			exclude_serviceids.push(input.value);
		}

		const overlay = PopUp('popup.services', {
			title: <?= json_encode(_('Add child services')) ?>,
			exclude_serviceids
		}, {dialogueid: 'services'});

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			for (const service of e.detail) {
				if (!this.children.has(service.serviceid)) {
					this.children.set(service.serviceid, service);
					this.#renderChild(service);
				}
			}

			this.#updateChildrenFilterStats();
			this.#updateTabIndicator();
			this.#update();
		});
	}

	#selectParents() {
		const exclude_serviceids = [];

		if (this.serviceid !== null) {
			exclude_serviceids.push(this.serviceid);
		}

		for (const service of jQuery('#parent_serviceids_').multiSelect('getData')) {
			exclude_serviceids.push(service.id);
		}

		const overlay = PopUp('popup.services', {
			title: <?= json_encode(_('Add parent services')) ?>,
			exclude_serviceids
		}, {dialogueid: 'services'});

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			const data = [];

			for (const service of e.detail) {
				data.push({id: service.serviceid, name: service.name});
			}

			jQuery('#parent_serviceids_').multiSelect('addData', data);
		});
	}

	clone({title, buttons}) {
		this.serviceid = null;

		this.#removeAllChildren();

		this.overlay.unsetLoading();
		this.overlay.setProperties({title, buttons});
		this.overlay.recoverFocus();
		this.overlay.containFocus();
	}

	delete() {
		this.overlay.setLoading();

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'service.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('service')) ?>);

		this.#post(curl.getUrl(), {serviceids: [this.serviceid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	submit() {
		const fields = getFormFields(this.form);

		if (this.serviceid !== null) {
			fields.serviceid = this.serviceid;
		}

		fields.name = fields.name.trim();
		fields.child_serviceids = [...this.children.keys()];

		if ('tags' in fields) {
			for (const tag of Object.values(fields.tags)) {
				tag.tag = tag.tag.trim();
				tag.value = tag.value.trim();
			}
		}

		if ('problem_tags' in fields) {
			for (const problem_tag of Object.values(fields.problem_tags)) {
				problem_tag.tag = problem_tag.tag.trim();
				problem_tag.value = problem_tag.value.trim();
			}
		}

		this.overlay.setLoading();

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', this.serviceid !== null ? 'service.update' : 'service.create');

		this.#post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	#post(url, data, success_callback) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				return response;
			})
			.then(success_callback)
			.catch((exception) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}
};
