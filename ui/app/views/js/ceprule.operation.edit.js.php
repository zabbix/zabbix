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

	/** @type {Template} */
	#tag_template;

	/** @type {Template} */
	#property_template;

	/** @type {Template} */
	#error_row_template;

	init({rules, operation, overlay, window_type, operation_types_by_execute_when, execute_when_by_window_type}) {
		this.#operation_types_by_execute_when = operation_types_by_execute_when;
		this.#execute_when_for_window_type = execute_when_by_window_type[window_type];

		this.#overlay = overlay;
		this.#tag_template = new Template(window['ceprule-operation-condition-tag-template'].innerHTML);
		this.#property_template = new Template(window['ceprule-operation-condition-property-template'].innerHTML);
		this.#error_row_template = new Template(window['ceprule-operation-condition-error-row-template'].innerHTML);

		this.form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#setValues({...operation, window_type: String(window_type)});

		this.#initActions();

		this.form = new CForm(this.form_element, rules);
		this.#updateAvailablePropertyTypes();
		this.#updateHoistedLabelsView();

		this.#setAvailableOperationOptions();
		window['ceprule-operation-execute-when'].dispatchEvent(new Event('change'));
		window['ceprule-operation-type'].dispatchEvent(new Event('change'));

		window.requestAnimationFrame(() => {
			this.form_element.classList.remove(ZBX_STYLE_DISPLAY_NONE);
			Focuser.focus(this.form_element.querySelector('[autofocus]'));
		});
	}

	#initActions() {
		this.form_element.addEventListener('change', (e) => {
			e.target.id === 'ceprule-operation-execute-when' && this.#handleExecuteWhenChanged();
			e.target.id === 'ceprule-operation-type' && this.#handleOperationTypeChanged(e.target.value);
		}, {capture: true});

		this.form_element.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-add-tag')) {
				this.#addTagRow({
					type: <?= ZBX_CONDITION_TYPE_EVENT_TAG_VALUE ?>,
					tag: '',
					operator: <?= TAG_OPERATOR_EQUAL ?>,
					value: ''
				});
				this.#updateHoistedLabelsView();
			}
			else if (e.target.classList.contains('js-add-property')) {
				const type = this.#nextAvailablePropertyType();

				if (type !== undefined) {
					this.#addPropertyRow({type, operator: <?= CONDITION_OPERATOR_YES ?>});
					this.#updateAvailablePropertyTypes();
					this.#updateHoistedLabelsView();
				}
				else {
					throw 'No available property types';
				}
			}
			else if (e.target.classList.contains('js-tag-remove')) {
				const row = e.target.closest('tr');

				row.nextElementSibling.remove();
				row.remove();

				this.form.discoverAllFields();
				this.#updateAvailablePropertyTypes();
				this.#updateHoistedLabelsView();
			}
			else if (e.target.classList.contains('js-property-remove')) {
				const row = e.target.closest('tr');

				row.nextElementSibling.remove();
				row.remove();

				this.form.discoverAllFields();
				this.#updateAvailablePropertyTypes();
				this.#updateHoistedLabelsView();
			}
		});
	}

	#nextAvailablePropertyType() {
		const [available] = this.#getPropertyTypes();

		return available[0];
	}

	#addPropertyRow(property) {
		const row_index = this.form_element.querySelectorAll('#ceprule-operation-filter-table tbody tr').length;
		const last_tag = window['ceprule-operation-filter-table'].querySelector('.js-filter-tag-label:last-child');
		const row_errors = this.#error_row_template.evaluateToElement({row_index});
		const target = this.form_element.querySelector('#ceprule-operation-filter-table tbody');
		const row = this.#buildPropertyRow(property, row_index);

		target.insertAdjacentElement('beforeend', row);
		target.insertAdjacentElement('beforeend', row_errors);
	}

	#addTagRow(tag) {
		const row_index = this.form_element.querySelectorAll('#ceprule-operation-filter-table tbody tr').length;
		const first_property = window['ceprule-operation-filter-table']
			.querySelector('.js-filter-property-label:first-child');

		const row = this.#buildTagRow(tag, row_index);
		const row_errors = this.#error_row_template.evaluateToElement({row_index});

		if (first_property) {
			const target = first_property.closest('tr');

			target.insertAdjacentElement('beforebegin', row);
			target.insertAdjacentElement('beforebegin', row_errors);
		}
		else {
			const target = this.form_element.querySelector('#ceprule-operation-filter-table tbody');

			target.insertAdjacentElement('beforeend', row);
			target.insertAdjacentElement('beforeend', row_errors);
		}
	}

	#buildTagRow(tag, row_index) {
		const tag_row = this.#tag_template.evaluateToElement({...tag, row_index});
		const textbox = tag_row.querySelector(`[name="filter[conditions][${row_index}][value]"]`);
		const type = tag_row.querySelector(`[name="filter[conditions][${row_index}][type]"]`);
		const on_operator_change = value => {
			const operator_has_value = !([<?= CONDITION_OPERATOR_EXISTS ?>, <?= CONDITION_OPERATOR_NOT_EXISTS ?>]
				.includes(value));

			textbox.style.display = operator_has_value ? '' : 'none';
			textbox.style.disabled = !operator_has_value;
			type.value = operator_has_value
				? <?= ZBX_CONDITION_TYPE_EVENT_TAG_VALUE ?>
				: <?= ZBX_CONDITION_TYPE_EVENT_TAG ?>;
		};

		tag_row.querySelector('z-select').addEventListener('change', e => on_operator_change(Number(e.target.value)));
		on_operator_change(Number(tag.operator));

		return tag_row;
	}

	#buildPropertyRow(property, row_index) {
		const property_row = this.#property_template.evaluateToElement({...property, row_index});

		property_row.querySelector('z-select').addEventListener('change', () => this.#updateAvailablePropertyTypes());

		return property_row;
	}

	#getPropertyTypesForm() {
		this.form.discoverAllFields();

		const execute_when = Number(this.form.findFieldByName('execute_when').getValue());
		const conditions = this.form.findFieldByName('filter[conditions]').getValue();
		const used_property_types = Object.values(conditions).map(condition => Number(condition.type));

		return {execute_when, used_property_types};
	}

	#getPropertyTypes() {
		const {execute_when, used_property_types} = this.#getPropertyTypesForm();
		const all_property_types = execute_when == <?= CCepRuleHelper::WHEN_EVENT_OCCURRED ?>
			? [
				<?= ZBX_CONDITION_TYPE_EVENT_OPEN ?>,
				<?= ZBX_CONDITION_TYPE_EVENT_SYMPTOM ?>,
				<?= ZBX_CONDITION_TYPE_EVENT_SUPPRESSED ?>,
				<?= ZBX_CONDITION_TYPE_EVENT_COPIED ?>
			]
			: [
				<?= ZBX_CONDITION_TYPE_EVENT_OPEN ?>,
				<?= ZBX_CONDITION_TYPE_EVENT_SYMPTOM ?>,
				<?= ZBX_CONDITION_TYPE_EVENT_FIRST ?>,
				<?= ZBX_CONDITION_TYPE_EVENT_LAST ?>,
				<?= ZBX_CONDITION_TYPE_EVENT_SUPPRESSED ?>,
				<?= ZBX_CONDITION_TYPE_EVENT_COPIED ?>
			];

		const unused_property_types = all_property_types.filter(type => !used_property_types.includes(type));

		return [unused_property_types, used_property_types, all_property_types];
	}

	#updateAvailablePropertyTypes() {
		const [available, unavailable, all] = this.#getPropertyTypes();

		this.form_element.querySelectorAll('.js-property-type-select')
			.forEach(zselect => {
				const options = zselect.options.map(option => (
					{...option,
						is_disabled: (unavailable.includes(Number(option.value))
							|| !all.includes(Number(option.value)))
							&& zselect.value !== option.value
					}
			));
			zselect.clearOptions();
			zselect.addOptions(options);
			zselect.init();
		});

		window['ceprule-operation-filter-table'].querySelector('.js-add-property').disabled = available.length == 0;
	}

	#updateHoistedLabelsView() {
		window['ceprule-operation-filter-table'].querySelectorAll('.js-filter-tag-label')
			.forEach((label, index) => label.classList.toggle('<?= ZBX_STYLE_VISIBILITY_HIDDEN ?>', index != 0));

		window['ceprule-operation-filter-table'].querySelectorAll('.js-filter-property-label')
			.forEach((label, index) => label.classList.toggle('<?= ZBX_STYLE_VISIBILITY_HIDDEN ?>', index != 0));
	}

	#setAvailableOperationOptions() {
		const type = Number(this.form.findFieldByName('type').getValue());
		const execute_when = Number(this.form.findFieldByName('execute_when').getValue());
		const zselect = window['ceprule-operation-type'];
		const events_options = [];
		const window_options = [];
		const tags_options = [];
		const enable_if_allowed = (option) => {
			option.is_disabled = !this.#operation_types_by_execute_when[execute_when].includes(Number(option.value));
		};

		zselect.options.forEach(option => {
			enable_if_allowed(option);

			if (option.extra.optgroupid === 'optgroup_events') {
				events_options.push(option);
			}
			else if (option.extra.optgroupid === 'optgroup_window') {
				window_options.push(option);
			}
			else if (option.extra.optgroupid === 'optgroup_tags') {
				tags_options.push(option);
			}
		});

		const sorter = (option_left, option_right) => {
			if (option_left.label === option_right.label) {
				return 0;
			}

			return option_left.label > option_right.label ? 1 : -1;
		};

		events_options.sort(sorter);
		tags_options.sort(sorter);
		window_options.sort(sorter);

		zselect.clearOptions();
		zselect.addOptionGroup({label: <?= json_encode(_('Event')) ?>, options: events_options});
		zselect.addOptionGroup({label: <?= json_encode(_('Tag')) ?>, options: tags_options});
		zselect.addOptionGroup({label: <?= json_encode(_('Window')) ?>, options: window_options});
		zselect.value = type;

		// Select first enabled option, if previous selection got disabled.
		if (!zselect.value.length) {
			const enabled_option = [...events_options, ...tags_options].find(option => !option.is_disabled);

			zselect.value = enabled_option ? enabled_option.value : events_options[0].value;
		}
	}

	#setValues(operation) {
		for (const condition of Object.values(operation.filter.conditions || {})) {
			if (condition.type == <?= ZBX_CONDITION_TYPE_EVENT_TAG_VALUE ?>
					|| condition.type == <?= ZBX_CONDITION_TYPE_EVENT_TAG ?>) {
				this.#addTagRow(condition);
			}
			else {
				this.#addPropertyRow(condition);
			}
		}

		this.form_element.querySelector(`[name="type"]`).value = operation.type;
		this.form_element.querySelector(`[name="execute_when"]`).value = operation.execute_when;
		this.form_element.querySelector(`[name="window_type"]`).value = operation.window_type;
		this.form_element.querySelector(`[name="sortorder"]`).value = operation.sortorder;
		this.form_element.querySelector(`[name="tag"]`).value = operation.tag;
		this.form_element.querySelector(`[name="tag_name"]`).value = operation.tag_name;
		this.form_element.querySelector(`[name="tag_value"]`).value = operation.tag_value;
		this.form_element.querySelector(`[name="old_tag"]`).value = operation.old_tag;
		this.form_element.querySelector(`[name="new_tag"]`).value = operation.new_tag;
		this.form_element.querySelector(`[name="suppress_duration"]`).value = operation.suppress_duration;
		this.form_element.querySelectorAll(`[name="severity"]`).forEach(node => {
			node.checked = node.value === operation.severity;
		});
		this.form_element.querySelectorAll(`[name="filter[evaltype]"]`).forEach(node => {
			node.checked = node.value == operation.filter.evaltype;
		});
		this.form_element.querySelector(`[name="event_name"]`).value = operation.event_name;

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
			'suppress_duration': this.form.findFieldByName('suppress_duration').getField(),
			'old_tag': this.form.findFieldByName('old_tag').getField(),
			'new_tag': this.form.findFieldByName('new_tag').getField(),
			'tag_name': this.form.findFieldByName('tag_name').getField(),
			'tag_value': this.form.findFieldByName('tag_value').getField(),
			'severity': this.form.findFieldByName('severity').getField()
		};

		const dateselector_suppress_duration = document.getElementById('ceprule-operation-period-argument');
		const row_rename = document.getElementById('ceprule-operation-tag-rename-argument');
		const row_tag_pair = document.getElementById('ceprule-operation-tag-pair-argument');
		const row_severity = document.getElementById('ceprule-operation-severity-argument');

		Object.entries(fields).forEach(([field_name, field]) => {
			field.disabled = true;

			if (['event_name', 'tag'].includes(field_name)) {
				field.style.display = 'none';
			}
		});

		dateselector_suppress_duration.style.display = 'none';
		row_rename.style.display = 'none';
		row_tag_pair.style.display = 'none';
		row_severity.style.display = 'none';

		switch (value) {
			case <?= CCepRuleHelper::OP_CLONE_FIRST ?>:
			case <?= CCepRuleHelper::OP_CLONE_LAST ?>:
			case <?= CCepRuleHelper::OP_UNSUPPRESS ?>:
			case <?= CCepRuleHelper::OP_DECREASE_SEVERITY ?>:
			case <?= CCepRuleHelper::OP_INCREASE_SEVERITY ?>:
			case <?= CCepRuleHelper::OP_DISCARD ?>:
			case <?= CCepRuleHelper::OP_CLOSE_EVENT ?>:
				break;

			case <?= CCepRuleHelper::OP_SUPPRESS ?>:
				fields.suppress_duration.disabled = false;
				fields.suppress_duration.style.display = '';
				dateselector_suppress_duration.style.display = '';
				break;

			case <?= CCepRuleHelper::OP_SET_SEVERITY ?>:
				row_severity.style.display = '';
				fields.severity.disabled = false;
				break;

			case <?= CCepRuleHelper::OP_SET_NAME ?>:
				fields.event_name.disabled = false;
				fields.event_name.style.display = '';
				break;

			case <?= CCepRuleHelper::OP_DECREASE_TAG_VALUE ?>:
			case <?= CCepRuleHelper::OP_INCREASE_TAG_VALUE ?>:
			case <?= CCepRuleHelper::OP_REMOVE_TAG ?>:
				fields.tag.disabled = false;
				fields.tag.style.display = '';
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

	#handleExecuteWhenChanged() {
		this.#setAvailableOperationOptions();
		this.#updateAvailablePropertyTypes();
	}
};
