<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

window.service_edit_popup = {
	status_rule_template: null,
	time_template: null,
	uptime_template: null,
	downtime_template: null,
	onetime_downtime_template: null,
	child_template: null,

	serviceid: null,
	children: new Map(),

	algorithm_names: null,
	search_limit: null,

	overlay: null,
	dialogue: null,
	form: null,

	init({serviceid, children, children_problem_tags_html, problem_tags, status_rules, service_times, algorithm_names,
			search_limit}) {
		this.initTemplates();

		this.serviceid = serviceid;

		this.algorithm_names = algorithm_names;
		this.search_limit = search_limit;

		this.overlay = overlays_stack.getById('service_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		for (const status_rule of status_rules) {
			this.addStatusRule(status_rule);
		}

		for (const service_time of service_times) {
			this.addTime(service_time);
		}

		for (const service of children) {
			this.children.set(service.serviceid, {
				serviceid: service.serviceid,
				name: service.name,
				algorithm: service.algorithm,
				problem_tags_html: children_problem_tags_html[service.serviceid]
			});
		}

		this.filterChildren();

		jQuery('#parent_serviceids_')
			.multiSelect('getSelectButton')
			.addEventListener('click', () => {
				this.selectParents();
			});

		const $tags = jQuery(document.getElementById('tags-table'));

		$tags.dynamicRows({template: '#tag-row-tmpl'});
		$tags.on('click', '.element-table-add', () => {
			$tags
				.find('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>')
				.textareaFlexible();
		});

		// Update form field state according to the form data.

		for (const id of ['advanced_configuration', 'propagation_rule', 'algorithm', 'showsla']) {
			document
				.getElementById(id)
				.addEventListener('change', () => this.update());
		}

		this.update();

		// Setup Problem tags.
		jQuery(document.getElementById('problem_tags')).dynamicRows({
			template: '#problem-tag-row-tmpl',
			rows: problem_tags
		});

		// Setup Service rules.
		document
			.getElementById('status_rules')
			.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this.editStatusRule();
				}
				else if (e.target.classList.contains('js-edit')) {
					this.editStatusRule(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					e.target.closest('tr').remove();
				}
			});

		// Setup Service times.
		document
			.getElementById('times')
			.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this.editTime();
				}
				else if (e.target.classList.contains('js-edit')) {
					this.editTime(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					e.target.closest('tr').remove();
				}
			});

		// Setup Child services.
		document
			.getElementById('children-filter')
			.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-filter')) {
					this.filterChildren();
				}
				else if (e.target.classList.contains('js-reset')) {
					document.getElementById('children-filter-name').value = '';
					this.filterChildren();
				}
			});

		document
			.getElementById('children-filter-name')
			.addEventListener('keypress', (e) => {
				if (e.key === 'Enter') {
					this.filterChildren();
					e.preventDefault();
				}
			}, {passive: false});

		document
			.getElementById('children')
			.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this.selectChildren();
				}
				else if (e.target.classList.contains('js-remove')) {
					this.removeChild(e.target.closest('tr').dataset.serviceid);
				}
			});
	},

	initTemplates() {
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

		this.time_template = new Template(`
			<tr data-row_index="#{row_index}">
				<td>
					#{*time_type}
					<input type="hidden" id="times_#{row_index}_type" name="times[#{row_index}][type]" value="#{type}">
					<input type="hidden" id="times_#{row_index}_ts_from" name="times[#{row_index}][ts_from]" value="#{ts_from}">
					<input type="hidden" id="times_#{row_index}_ts_to" name="times[#{row_index}][ts_to]" value="#{ts_to}">
					<input type="hidden" id="times_#{row_index}_note" name="times[#{row_index}][note]" value="#{note}">
				</td>
				<td>#{from} - #{till}</td>
				<td class="wordwrap" style="max-width: 540px;"></td>
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

		this.uptime_template = `<span class="enabled"><?= _('Uptime') ?></span>`;

		this.downtime_template = `<span class="disabled"><?= _('Downtime') ?></span>`;

		this.onetime_downtime_template = `<span class="disabled"><?= _('One-time downtime') ?></span>`;

		this.child_template = new Template(`
			<tr data-serviceid="#{serviceid}">
				<td class="<?= ZBX_STYLE_WORDWRAP ?>" style="max-width: <?= ZBX_TEXTAREA_BIG_WIDTH ?>px;">#{name}</td>
				<td>#{algorithm}</td>
				<td class="<?= ZBX_STYLE_WORDWRAP ?>">#{*problem_tags_html}</td>
				<td>
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
				</td>
			</tr>
		`);
	},

	update() {
		const advanced_configuration = document.getElementById('advanced_configuration').checked;
		const propagation_rule = document.getElementById('propagation_rule').value;
		const status_enabled = document.getElementById('algorithm').value != <?= ZBX_SERVICE_STATUS_CALC_SET_OK ?>;
		const showsla = document.getElementById('showsla').checked;

		document.getElementById('additional_rules_label').style.display = advanced_configuration ? '' : 'none';
		document.getElementById('additional_rules_field').style.display = advanced_configuration ? '' : 'none';
		document.getElementById('status_propagation_rules_label').style.display = advanced_configuration ? '' : 'none';
		document.getElementById('status_propagation_rules_field').style.display = advanced_configuration ? '' : 'none';
		document.getElementById('status_propagation_value_field').style.display = advanced_configuration ? '' : 'none';
		document.getElementById('weight_label').style.display = advanced_configuration ? '' : 'none';
		document.getElementById('weight_field').style.display = advanced_configuration ? '' : 'none';

		switch (propagation_rule) {
			case '<?= ZBX_SERVICE_STATUS_PROPAGATION_INCREASE ?>':
			case '<?= ZBX_SERVICE_STATUS_PROPAGATION_DECREASE ?>':
				document.getElementById('propagation_value_number').style.display = '';
				document.getElementById('propagation_value_status').style.display = 'none';
				break;

			case '<?= ZBX_SERVICE_STATUS_PROPAGATION_FIXED ?>':
				document.getElementById('propagation_value_number').style.display = 'none';
				document.getElementById('propagation_value_status').style.display = '';
				break;

			default:
				document.getElementById('propagation_value_number').style.display = 'none';
				document.getElementById('propagation_value_status').style.display = 'none';
				document.getElementById('status_propagation_value_field').style.display = 'none';
		}

		document.getElementById('showsla').disabled = !status_enabled;
		document.getElementById('goodsla').disabled = !status_enabled || !showsla;
	},

	editStatusRule(row = null) {
		let popup_params;

		if (row !== null) {
			const row_index = row.dataset.row_index;

			popup_params = {
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

			popup_params = {row_index};
		}

		const overlay = PopUp('popup.service.statusrule.edit', popup_params, 'service_status_rule_edit',
			document.activeElement
		);

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			if (row !== null) {
				this.updateStatusRule(row, e.detail)
			}
			else {
				this.addStatusRule(e.detail);
			}
		});
	},

	editTime(row = null) {
		let popup_params;

		if (row !== null) {
			const row_index = row.dataset.row_index;

			popup_params = {
				edit: '1',
				row_index,
				type: row.querySelector(`[name="times[${row_index}][type]"`).value,
				ts_from: row.querySelector(`[name="times[${row_index}][ts_from]"`).value,
				ts_to: row.querySelector(`[name="times[${row_index}][ts_to]"`).value,
				note: row.querySelector(`[name="times[${row_index}][note]"`).value
			};
		}
		else {
			let row_index = 0;

			while (document.querySelector(`#times [data-row_index="${row_index}"]`) !== null) {
				row_index++;
			}

			popup_params = {row_index};
		}

		const overlay = PopUp('popup.service.time.edit', popup_params, 'service_time_edit', document.activeElement);

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			if (row !== null) {
				this.updateTime(row, e.detail)
			}
			else {
				this.addTime(e.detail);
			}
		});
	},

	addStatusRule(status_rule) {
		document
			.querySelector('#status_rules tbody')
			.insertAdjacentHTML('beforeend', this.status_rule_template.evaluate(status_rule));
	},

	updateStatusRule(row, status_rule) {
		row.insertAdjacentHTML('afterend', this.status_rule_template.evaluate(status_rule));
		row.remove();
	},

	addTime(time) {
		document
			.querySelector('#times tbody')
			.insertAdjacentHTML('beforeend', this.time_template.evaluate({
				...time,
				time_type: this.makeServiceTimeType(parseInt(time.type))
			}));
	},

	updateTime(row, time) {
		row.insertAdjacentHTML('afterend', this.time_template.evaluate({
			...time,
			time_type: this.makeServiceTimeType(parseInt(time.type))
		}));
		row.remove();
	},

	makeServiceTimeType(type) {
		switch (type) {
			case <?= SERVICE_TIME_TYPE_UPTIME ?>:
				return this.uptime_template;
			case <?= SERVICE_TIME_TYPE_DOWNTIME ?>:
				return this.downtime_template;
			case <?= SERVICE_TIME_TYPE_ONETIME_DOWNTIME ?>:
				return this.onetime_downtime_template;
		}
	},

	renderChild(service) {
		document
			.querySelector('#children tbody')
			.insertAdjacentHTML('beforeend', this.child_template.evaluate({
				serviceid: service.serviceid,
				name: service.name,
				algorithm: this.algorithm_names[service.algorithm],
				problem_tags_html: service.problem_tags_html
			}));
	},

	removeChild(serviceid) {
		const child = this.form.querySelector(`#children tbody tr[data-serviceid="${serviceid}"]`);

		if (child !== null) {
			child.remove();
		}

		this.children.delete(serviceid);
		this.updateChildrenFilterStats();
	},

	filterChildren() {
		const container = document.querySelector('#children tbody');

		container.innerHTML = '';

		const filter_name = document.getElementById('children-filter-name').value.toLowerCase();

		let count = 0;

		for (const service of this.children.values()) {
			if (!service.name.toLowerCase().includes(filter_name)) {
				continue;
			}

			this.renderChild(service);

			if (++count == this.search_limit) {
				break;
			}
		}

		this.updateChildrenFilterStats();
	},

	updateChildrenFilterStats() {
		const container = document.querySelector('#children tbody');

		const stats_template = <?= json_encode(_('Displaying %1$s of %2$s found')) ?>;

		document.querySelector('#children tfoot .inline-filter-stats').textContent = this.children.size > 0
			? sprintf(stats_template, container.childElementCount, this.children.size)
			: '';
	},

	selectChildren() {
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
		}, 'services', document.activeElement);

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			for (const service of e.detail) {
				if (!this.children.has(service.serviceid)) {
					this.children.set(service.serviceid, service);
					this.renderChild(service);
				}
			}

			this.updateChildrenFilterStats();
		});
	},

	selectParents() {
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
		}, 'services', document.activeElement);

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			const data = [];

			for (const service of e.detail) {
				data.push({id: service.serviceid, name: service.name});
			}

			jQuery('#parent_serviceids_').multiSelect('addData', data);
		});
	},

	submit() {
		const fields = getFormFields(this.form);

		fields.name = fields.name.trim();
		fields.child_serviceids = [...this.children.keys()];

		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}

		this.overlay.setLoading();

		const curl = new Curl(this.form.getAttribute('action'));

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(fields)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('errors' in response) {
					throw {html_string: response.errors};
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {
					detail: {
						title: response.title,
						messages: ('messages' in response) ? response.messages : null
					}
				}));
			})
			.catch((error) => {
				let message_box;

				if (typeof error === 'object' && 'html_string' in error) {
					message_box =
						new DOMParser().parseFromString(error.html_string, 'text/html').body.firstElementChild;
				}
				else {
					const error = <?= json_encode(_('Unexpected server error.')) ?>;

					message_box = makeMessageBox('bad', [], error, true, false)[0];
				}

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}
};
