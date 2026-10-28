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

window.ceprule_operation_edit_popup = new class {

	/** @type {Object} */
	#execute_when_for_window_type;

	/** @type {Object} */
	#operation_types_by_execute_when;

	/** @type {HTMLFormElement} */
	form_element;

	/** @type {CForm} */
	form;

	/** @type {Overlay} */
	#overlay;

	/** @type {Object} */
	#operation_conditon_rules;

	/** @type {Number} */
	#condition_row_index;

	/** @type {Number} */
	#scope_condition_row_index;

	/** @type {Template} */
	#condition_row_template;

	/** @type {Template} */
	#scope_condition_row_template;

	/** @type {Template} */
	#condition_error_container_row_template;

	/** @type {Template} */
	#scope_condition_error_container_row_template;

	/** @type {Template} */
	#condition_row_template_property;

	/** @type {Template} */
	#condition_row_template_tag;

	/** @type {Template} */
	#condition_row_template_tag_value;

	init({rules, operation, overlay, window_type, operation_types_by_execute_when, execute_when_by_window_type,
			operation_conditon_rules}) {
		this.#operation_types_by_execute_when = operation_types_by_execute_when;
		this.#execute_when_for_window_type = execute_when_by_window_type[window_type];
		this.#operation_conditon_rules = operation_conditon_rules;
		this.#condition_row_index = 0;
		this.#scope_condition_row_index = 0;

		this.#overlay = overlay;
		this.#initTemplates();

		this.form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#setValues({...operation, window_type: String(window_type)});

		this.form = new CForm(this.form_element, rules);

		this.#initActions();

		this.#refreshExpressionPreview();
		this.#refreshScopeExpressionPreview();
		this.#setAvailableOperationOptions();
		window['ceprule-operation-execute-when'].dispatchEvent(new Event('change'));
		window['ceprule-operation-type'].dispatchEvent(new Event('change'));

		window.requestAnimationFrame(() => {
			this.form_element.classList.remove(ZBX_STYLE_DISPLAY_NONE);
			Focuser.focus(this.form_element.querySelector('[autofocus]'));
		});
	}

	#initTemplates() {
		this.#condition_row_template_property = new Template(
			`<div class="text">#{text}</div>`
		);
		this.#condition_row_template_tag = new Template(
			`<div class="text">#{name} #{operator} <em>#{tag}</em></div>`
		);
		this.#condition_row_template_tag_value = new Template(
			`<div class="text">#{name} <em>#{tag_name}</em> #{operator} <em>#{tag_value}</em></div>`
		);

		this.#condition_row_template = new Template(window['ceprule-operation-condition-row-template'].innerHTML);
		this.#scope_condition_row_template = new Template(
			window['ceprule-operation-scope-condition-row-template'].innerHTML
		);
		this.#condition_error_container_row_template = new Template(
			window['ceprule-operation-condition-row-error-container-template'].innerHTML
		);
		this.#scope_condition_error_container_row_template = new Template(
			window['ceprule-operation-scope-condition-row-error-container-template'].innerHTML
		);
	}

	#initActions() {
		document.getElementById('ceprule-operation-execute-when').addEventListener('change', () => {
			this.#handleExecuteWhenChanged();
		});

		document.getElementById('ceprule-operation-type').addEventListener('change', (e) => {
			this.#handleOperationTypeChanged(e.target.value);
		});

		this.form_element.querySelectorAll('input[name="suppress_time_option"]').forEach((radio) => {
			radio.addEventListener('change', (e) => {
				if (e.target.checked) {
					this.#handleSuppressTimeOptionChanged();
				}
			});
		});

		this.form.findFieldByName('filter[evaltype]').getField()
			.addEventListener('change', () => this.#refreshExpressionPreview());

		this.form.findFieldByName('scope_filter[evaltype]').getField()
			.addEventListener('change', () => this.#refreshScopeExpressionPreview());

		this.form.findFieldByName('scope').getField()
			.addEventListener('change', () => this.#handleScopeChanged());

		this.form.findFieldByName('use_scope_filter').getField()
			.addEventListener('change', () => this.#handleFilterChanged());

		this.form_element.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-condition-add')) {
				this.#openConditionPopup('execute_when', undefined, e.target);
			}
			else if (e.target.classList.contains('js-condition-edit')) {
				const row_index = e.target.closest('tr').dataset.row_index;

				this.#openConditionPopup('execute_when', row_index, e.target);
			}
			else if (e.target.classList.contains('js-condition-remove')) {
				e.target.closest('tr').nextSibling.remove();
				e.target.closest('tr').remove();
				this.form.discoverAllFields();
				this.#refreshExpressionPreview();

				if (!this.form_element.querySelector('#ceprule-operation-filter-conditions [data-row_index]')) {
					this.#condition_row_index = 0;
				}
			}
			else if (e.target.classList.contains('js-scope-condition-add')) {
				this.#openConditionPopup('scope', undefined, e.target);
			}
			else if (e.target.classList.contains('js-scope-condition-edit')) {
				const row_index = e.target.closest('tr').dataset.row_index;

				this.#openConditionPopup('scope', row_index, e.target);
			}
			else if (e.target.classList.contains('js-scope-condition-remove')) {
				e.target.closest('tr').nextSibling.remove();
				e.target.closest('tr').remove();
				this.form.discoverAllFields();
				this.#refreshScopeExpressionPreview();

				if (!this.form_element.querySelector('#ceprule-operation-scope-filter-conditions [data-row_index]')) {
					this.#scope_condition_row_index = 0;
				}
			}
		});
	}

	#listConditionIdentifiers() {
		const conditions = Object.values(this.form.findFieldByName('filter[conditions]').getValue() ?? {});

		return conditions.map(condition => ({
			id: condition.formulaid,
			type: condition.type
		}));
	}

	#refreshExpressionPreview() {
		const identifiers = this.#listConditionIdentifiers();
		const evaltype = Number(this.form.findFieldByName('filter[evaltype]').getValue());

		if (evaltype == <?= CONDITION_EVAL_TYPE_AND_OR ?>
				|| evaltype == <?= CONDITION_EVAL_TYPE_AND ?>
				|| evaltype == <?= CONDITION_EVAL_TYPE_OR ?>) {
			window['ceprule-operation-filter-expression-preview'].innerText = getConditionFormula(identifiers, evaltype);
			window['ceprule-operation-filter-expression-preview'].style.display = ''
			window['ceprule-operation-filter-expression'].disabled = true;
			window['ceprule-operation-filter-expression'].style.display = 'none';
		}
		else {
			window['ceprule-operation-filter-expression'].disabled = identifiers.length < 2;
			window['ceprule-operation-filter-expression'].style.display = '';
			window['ceprule-operation-filter-expression-preview'].style.display = 'none'

			if (identifiers.length < 2) {
				window['ceprule-operation-filter-evaltype'].value = <?= CONDITION_EVAL_TYPE_AND_OR ?>;
			}
		}

		const evaltype_field = window['ceprule-operation-filter-evaltype'].closest('.form-field');

		evaltype_field.style.display = identifiers.length < 2 ? 'none' : '';
		evaltype_field.previousElementSibling.style.display = identifiers.length < 2 ? 'none' : '';
	}

	#listScopeConditionIdentifiers() {
		const conditions = Object.values(this.form.findFieldByName('scope_filter[conditions]').getValue() ?? {});

		return conditions.map(condition => ({
			id: condition.formulaid,
			type: condition.type
		}));
	}

	#refreshScopeExpressionPreview() {
		const identifiers = this.#listScopeConditionIdentifiers();
		const evaltype = Number(this.form.findFieldByName('scope_filter[evaltype]').getValue());

		if (evaltype == <?= CONDITION_EVAL_TYPE_AND_OR ?>
				|| evaltype == <?= CONDITION_EVAL_TYPE_AND ?>
				|| evaltype == <?= CONDITION_EVAL_TYPE_OR ?>) {
			window['ceprule-operation-scope-filter-expression-preview'].innerText = getConditionFormula(identifiers, evaltype);
			window['ceprule-operation-scope-filter-expression-preview'].style.display = ''
			window['ceprule-operation-scope-filter-expression'].disabled = true;
			window['ceprule-operation-scope-filter-expression'].style.display = 'none';
		}
		else {
			window['ceprule-operation-scope-filter-expression'].disabled = identifiers.length < 2;
			window['ceprule-operation-scope-filter-expression'].style.display = '';
			window['ceprule-operation-scope-filter-expression-preview'].style.display = 'none'

			if (identifiers.length < 2) {
				window['ceprule-operation-scope-filter-evaltype'].value = <?= CONDITION_EVAL_TYPE_AND_OR ?>;
			}
		}

		window['ceprule-operation-scope-filter-evaltype'].style.display = identifiers.length < 2 ? 'none' : '';
	}

	#editConditionRow(condition, index) {
		this.form_element.querySelector(`#ceprule-operation-filter-conditions [data-row_index="${index}"]`)
			.replaceWith(this.#buildConditionRow('execute_when', condition, index));
	}

	#addConditionRow(condition) {
		const row_index = this.#condition_row_index++;
		const table = this.form_element.querySelector('#ceprule-operation-filter-conditions tbody');

		table.insertAdjacentElement('beforeend', this.#buildConditionRow('execute_when', condition, row_index));
		table.insertAdjacentElement('beforeend',
			this.#condition_error_container_row_template.evaluateToElement({row_index})
		);
	}

	#editScopeConditionRow(condition, index) {
		this.form_element.querySelector(`#ceprule-operation-scope-filter-conditions [data-row_index="${index}"]`)
			.replaceWith(this.#buildConditionRow('scope', condition, index));
	}

	#addScopeConditionRow(condition) {
		const row_index = this.#scope_condition_row_index++;
		const table = this.form_element.querySelector('#ceprule-operation-scope-filter-conditions tbody');

		table.insertAdjacentElement('beforeend', this.#buildConditionRow('scope', condition, row_index));
		table.insertAdjacentElement('beforeend',
			this.#scope_condition_error_container_row_template.evaluateToElement({row_index})
		);
	}

	#buildConditionRow(condition_context, condition, row_index) {
		const label_names = JSON.parse('<?= json_encode(
			CCepRuleHelper::getOperationConditionLabels()
		) ?>');

		const operator_names = JSON.parse('<?= json_encode(
			CCepRuleHelper::getConditionOperatorLabels()
		) ?>');

		const descriptions = JSON.parse('<?= json_encode(
			CCepRuleHelper::getOperationConditionDescriptions()
		) ?>');

		let description_template = null;
		const description_view = {};

		switch (Number(condition.type)) {
			case <?= ZBX_CONDITION_TYPE_EVENT_OPEN ?>:
			case <?= ZBX_CONDITION_TYPE_EVENT_SYMPTOM ?>:
			case <?= ZBX_CONDITION_TYPE_EVENT_FIRST ?>:
			case <?= ZBX_CONDITION_TYPE_EVENT_LAST ?>:
			case <?= ZBX_CONDITION_TYPE_EVENT_SUPPRESSED ?>:
			case <?= ZBX_CONDITION_TYPE_EVENT_COPIED ?>:
				description_template = this.#condition_row_template_property;
				description_view.text = descriptions[condition.type][condition.operator];
				break;

			case <?= ZBX_CONDITION_TYPE_EVENT_TAG ?>:
				description_template = this.#condition_row_template_tag;
				description_view.name = label_names[condition.type];
				description_view.operator = operator_names[condition.operator].toLocaleLowerCase();
				description_view.tag = condition.tag;
				break;

			case <?= ZBX_CONDITION_TYPE_EVENT_TAG_VALUE ?>:
				description_template = this.#condition_row_template_tag_value;
				description_view.name = label_names[condition.type];
				description_view.operator = operator_names[condition.operator].toLocaleLowerCase();
				description_view.tag_name = condition.tag_name;
				description_view.tag_value = condition.tag_value;
				break;
		}

		const row_template = condition_context === 'execute_when'
			? this.#condition_row_template
			: this.#scope_condition_row_template;

		const element = row_template.evaluateToElement({
			...condition,
			row_index: row_index,
			formulaid: num2letter(row_index),
			description_html: description_template.evaluate(description_view)
		});

		return element;
	}

	#setAvailableOperationOptions() {
		const execute_when = Number(this.form.findFieldByName('execute_when').getValue());
		const zselect = document.getElementById('ceprule-operation-type');

		zselect.getOptions().forEach(option => {
			option.disabled = !this.#operation_types_by_execute_when[execute_when].includes(Number(option.value));
		});
	}

	#setValues(operation) {
		for (const condition of Object.values(operation.filter.conditions || {})) {
			this.#addConditionRow(condition);
		}

		for (const condition of Object.values(operation.scope_filter.conditions || {})) {
			this.#addScopeConditionRow(condition);
		}

		this.form_element.querySelector(`[name="type"]`).value = operation.type;
		this.form_element.querySelector(`[name="execute_when"]`).value = operation.execute_when;
		this.form_element.querySelector(`[name="filter[evaltype]"]`).value = operation.filter.evaltype;
		this.form_element.querySelector(`[name="filter[formula]"]`).value = operation.filter.formula;
		this.form_element.querySelector(`[name="scope_filter[evaltype]"]`).value = operation.scope_filter.evaltype;
		this.form_element.querySelector(`[name="scope_filter[formula]"]`).value = operation.scope_filter.formula;
		this.form_element.querySelector(`[name="window_type"]`).value = operation.window_type;
		this.form_element.querySelector(`[name="sortorder"]`).value = operation.sortorder;
		this.form_element.querySelector(`[name="tag"]`).value = operation.tag;
		this.form_element.querySelector(`[name="tag_name"]`).value = operation.tag_name;
		this.form_element.querySelector(`[name="tag_value"]`).value = operation.tag_value;
		this.form_element.querySelector(`[name="old_tag"]`).value = operation.old_tag;
		this.form_element.querySelector(`[name="new_tag"]`).value = operation.new_tag;
		this.form_element.querySelectorAll(`[name="suppress_time_option"]`).forEach(node => {
			node.checked = node.value === operation.suppress_time_option;
		});
		this.form_element.querySelector(`[name="suppress_duration"]`).value = operation.suppress_duration;
		this.form_element.querySelectorAll(`[name="severity"]`).forEach(node => {
			node.checked = node.value === operation.severity;
		});
		this.form_element.querySelector(`[name="event_name"]`).value = operation.event_name;

		this.form_element.querySelectorAll(`[name="scope"]`).forEach(node => {
			node.checked = node.value === operation.scope;
		});
		this.form_element.querySelector(`[name="use_scope_filter"]`).checked
			= Object.values(operation.scope_filter.conditions || {}).length > 0;

		// Set enabled options.
		const zselect = window['ceprule-operation-execute-when'];
		const options = zselect.options.map(option => ({...option,
			is_disabled: !this.#execute_when_for_window_type.includes(Number(option.value)),
		}));

		zselect.clearOptions();
		zselect.addOptions(options);
		zselect.init();
	}

	#handleOperationTypeChanged(value) {
		value = Number(value);

		const fields = {
			'event_name': this.form.findFieldByName('event_name').getField(),
			'tag': this.form.findFieldByName('tag').getField(),
			'suppress_time_option': this.form.findFieldByName('suppress_time_option').getField(),
			'suppress_duration': this.form.findFieldByName('suppress_duration').getField(),
			'old_tag': this.form.findFieldByName('old_tag').getField(),
			'new_tag': this.form.findFieldByName('new_tag').getField(),
			'tag_name': this.form.findFieldByName('tag_name').getField(),
			'tag_value': this.form.findFieldByName('tag_value').getField(),
			'severity': this.form.findFieldByName('severity').getField()
		};

		const row_rename = document.getElementById('ceprule-operation-tag-rename-argument');
		const row_tag_pair = document.getElementById('ceprule-operation-tag-pair-argument');
		const row_severity = document.getElementById('ceprule-operation-severity-argument');
		const operation_hint = document.getElementById('ceprule-operation-type-hint');

		Object.entries(fields).forEach(([field_name, field]) => {
			field.disabled = true;

			if (['event_name', 'tag', 'suppress_time_option', 'suppress_duration'].includes(field_name)) {
				field.parentElement.style.display = 'none';
			}
		});

		row_rename.style.display = 'none';
		row_tag_pair.style.display = 'none';
		row_severity.style.display = 'none';
		operation_hint.style.display = 'none';

		switch (value) {
			case <?= CCepRuleHelper::OP_UNSUPPRESS ?>:
			case <?= CCepRuleHelper::OP_DECREASE_SEVERITY ?>:
			case <?= CCepRuleHelper::OP_INCREASE_SEVERITY ?>:
			case <?= CCepRuleHelper::OP_DISCARD ?>:
			case <?= CCepRuleHelper::OP_CLOSE_EVENT ?>:
				break;

			case <?= CCepRuleHelper::OP_CLONE_FIRST ?>:
			case <?= CCepRuleHelper::OP_CLONE_LAST ?>:
				operation_hint.style.display = '';
				break;

			case <?= CCepRuleHelper::OP_SUPPRESS ?>:
				fields.suppress_time_option.disabled = false;
				fields.suppress_time_option.parentElement.style.display = '';
				this.#handleSuppressTimeOptionChanged();
				break;

			case <?= CCepRuleHelper::OP_SET_SEVERITY ?>:
				row_severity.style.display = '';
				fields.severity.disabled = false;
				break;

			case <?= CCepRuleHelper::OP_SET_NAME ?>:
				fields.event_name.disabled = false;
				fields.event_name.parentElement.style.display = '';
				break;

			case <?= CCepRuleHelper::OP_DECREASE_TAG_VALUE ?>:
			case <?= CCepRuleHelper::OP_INCREASE_TAG_VALUE ?>:
			case <?= CCepRuleHelper::OP_REMOVE_TAG ?>:
				fields.tag.disabled = false;
				fields.tag.parentElement.style.display = '';
				break;

			case <?= CCepRuleHelper::OP_RENAME_TAG ?>:
				row_rename.style.display = '';
				fields.old_tag.disabled = false;
				fields.new_tag.disabled = false;
				break;

			case <?= CCepRuleHelper::OP_ADD_TAG ?>:
			case <?= CCepRuleHelper::OP_SET_TAG ?>:
			case <?= CCepRuleHelper::OP_SET_TAG_VALUE ?>:
				row_tag_pair.style.display = '';
				fields.tag_name.disabled = false;
				fields.tag_value.disabled = false;
				break;
		}
	}

	#handleSuppressTimeOptionChanged() {
		const suppress_duration_field = this.form.findFieldByName('suppress_duration').getField();
		const suppress_time_option = Number(this.form.findFieldByName('suppress_time_option').getValue());

		if (suppress_time_option === <?= ZBX_PROBLEM_SUPPRESS_TIME_DEFINITE ?>) {
			suppress_duration_field.disabled = false;
			suppress_duration_field.parentElement.style.display = '';
		}
		else {
			suppress_duration_field.disabled = true;
			suppress_duration_field.parentElement.style.display = 'none';
		}
	}

	#handleExecuteWhenChanged() {
		this.#setAvailableOperationOptions();
		this.#setScopeState();
	}

	#setScopeState() {
		let set_readonly;
		let set_value = null;

		const execute_when = Number(this.form.findFieldByName('execute_when').getValue());

		if (execute_when == <?= CCepRuleHelper::WHEN_EVENT_OCCURRED ?>) {
			set_readonly = true;
			set_value = <?= CCepRuleHelper::SCOPE_CURRENT_EVENT ?>;
		}
		else if ([
			<?= CCepRuleHelper::WHEN_WINDOW_CLOSED ?>,
			<?= CCepRuleHelper::WHEN_PATTERN_MATCHED ?>
		].includes(execute_when)) {
			set_readonly = true;
			set_value = <?= CCepRuleHelper::SCOPE_ALL_EVENTS ?>;
		}
		else {
			set_readonly = false;
		}

		const scope_field = document.getElementById('ceprule-operation-scope');

		scope_field.querySelectorAll('input').forEach(node => {
			if (set_value !== null) {
				node.checked = Number(node.value) === set_value;
			}

			node.readOnly = set_readonly;
		});

		this.#handleScopeChanged();
	}

	#handleScopeChanged() {
		const scope = Number(this.form.findFieldByName('scope').getValue());
		const show_filter = scope != <?= CCepRuleHelper::SCOPE_CURRENT_EVENT ?>;

		this.form_element.querySelector('label[for="ceprule-operation-use-scope-filter"]').style.display = show_filter
			? '' : 'none';
		this.form.findFieldByName('use_scope_filter').getField().closest('.form-field').style.display
			= show_filter ? '' : 'none';

		if (!show_filter) {
			this.#handleFilterChanged(<?= CCepRuleHelper::SCOPE_FILTER_NO ?>);
		}
		else {
			this.#handleFilterChanged();
		}
	}

	#handleFilterChanged(forced_state) {
		const use_scope_filter = forced_state !== undefined
			? forced_state
			: Number(this.form.findFieldByName('use_scope_filter').getValue());

		const show_filter = use_scope_filter === <?= CCepRuleHelper::SCOPE_FILTER_YES ?>;
		const table_node = document.getElementById('ceprule-operation-scope-filter-conditions');

		table_node.closest('.form-field').style.display = show_filter ? '' : 'none';
		document.getElementById('ceprule-operation-scope-filter-evaltype-li').style.display = show_filter ? '' : 'none';
		document.getElementById('ceprule-operation-scope-filter-expression-li').style.display = show_filter
			? '' : 'none';
	}

	#openConditionPopup(condition_context, index, trigger_element) {
		const is_new = index === undefined;
		const execute_when = Number(this.form.findFieldByName('execute_when').getValue());
		const conditions = condition_context === 'execute_when'
			? this.form.findFieldByName('filter[conditions]').getValue()
			: this.form.findFieldByName('scope_filter[conditions]').getValue();
		const condition = is_new ? null : conditions[index];
		const used_property_types = Object.values(conditions)
			.filter(({type}) => {
				const allowed_types = [<?= ZBX_CONDITION_TYPE_EVENT_TAG ?>, <?= ZBX_CONDITION_TYPE_EVENT_TAG_VALUE ?>];
				if (!is_new) {
					allowed_types.push(Number(condition.type));
				}

				return !allowed_types.includes(Number(type));
			})
			.map(condition => Number(condition.type));

		const template = document.getElementById('ceprule-operation-condition-modal-template');
		const form_element = template.content.querySelector('form').cloneNode(true);

		const overlay = overlayDialogue({
			class: 'modal-popup modal-popup-medium',
			title: <?= json_encode(_('Condition details')) ?>,
			content: form_element,
			buttons: [
				{
					title: is_new ? <?= json_encode(_('Add')) ?> : <?= json_encode(_('Update')) ?>,
					isSubmit: true,
					action: overlay => ceprule_operation_condition_edit_popup.submit()
							.then(fields => {
								switch (condition_context) {
									case 'execute_when':
										if (is_new) {
											this.#addConditionRow(fields);
										}
										else {
											this.#editConditionRow(fields, index);
										}

										this.form.discoverAllFields();
										this.#refreshExpressionPreview();
										break;

									case 'scope':
										if (is_new) {
											this.#addScopeConditionRow(fields);
										}
										else {
											this.#editScopeConditionRow(fields, index);
										}

										this.form.discoverAllFields();
										this.#refreshScopeExpressionPreview();
										break;
								}
							})
							.then(() => overlayDialogueDestroy(overlay.dialogueid))
							.catch(() => overlay.unsetLoading())
						&& false
				},
				{
					title: t('Cancel'),
					class: 'btn-alt',
					cancel: true,
					action: () => {}
				}
			]
		}, {
			dialogueid: 'ceprule.operation.condition.edit',
			trigger_element
		});

		overlay.$dialogue.$head.$close_button.show();
		overlay.$dialogue[0].addEventListener('dialogue.close', () => {
			for (const form of overlay.$dialogue.$body[0].querySelectorAll('form')) {
				form.dispatchEvent(new CustomEvent('form.destroyed'));
			}
		});
		overlay.recoverFocus();
		overlay.containFocus();

		const rules = this.#operation_conditon_rules;

		ceprule_operation_condition_edit_popup.init({rules, condition, overlay, execute_when, used_property_types});
	}
};
