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

	init({rules, operation, overlay, window_type, operation_types_by_execute_when, execute_when_by_window_type}) {
		this.#operation_types_by_execute_when = operation_types_by_execute_when;
		this.#execute_when_for_window_type = execute_when_by_window_type[window_type];

		this.#overlay = overlay;
		this.#tag_template = new Template(window['ceprule-operation-condition-tag-template'].innerHTML);
		this.#property_template = new Template(window['ceprule-operation-condition-property-template'].innerHTML);

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
			e.target.id === 'ceprule-operation-execute-when' && this.#handleExecuteWhenChanged(e.target.value);
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
			}
			else if (e.target.classList.contains('js-add-property')) {
				this.#addPropertyRow({
					type: this.#nextAvailablePropertyType(),
					operator: <?= CONDITION_OPERATOR_YES ?>
				});

				this.#updateAvailablePropertyTypes();
			}
			else if (e.target.classList.contains('js-tag-remove')) {
				e.target.closest('tr').remove();
				this.form.discoverAllFields();
			}
			else if (e.target.classList.contains('js-property-remove')) {
				e.target.closest('tr').remove();
				this.form.discoverAllFields();
				this.#updateAvailablePropertyTypes();
			}
		});
	}

	#addPropertyRow(property) {
		const row_index = this.form_element.querySelectorAll('#ceprule-operation-filter-table tbody tr').length;
		const last_tag = window['ceprule-operation-filter-table'].querySelector('.js-filter-tag-label:last-child');

		this.form_element.querySelector('#ceprule-operation-filter-table tbody')
			.insertAdjacentElement('beforeend', this.#buildPropertyRow(property, row_index));
		this.form_element.querySelector('#ceprule-operation-filter-table tbody')
			.insertAdjacentHTML('beforeend', `<tr><td class="<?= ZBX_STYLE_ERROR_CONTAINER ?>"></td></tr>`);
	}

	#addTagRow(tag) {
		const row_index = this.form_element.querySelectorAll('#ceprule-operation-filter-table tbody tr').length;
		const first_property = window['ceprule-operation-filter-table']
			.querySelector('.js-filter-property-label:first-child');

		const row = this.#buildTagRow(tag, row_index);
		const row_errors = (new Template(`<tr><td class="<?= ZBX_STYLE_ERROR_CONTAINER ?>"></td></tr>`))
			.evaluateToElement();

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
		const on_operator_change = value => {
			const hidden = [<?= TAG_OPERATOR_EXISTS ?>, <?= TAG_OPERATOR_NOT_EXISTS ?>].includes(value);

			textbox.style.display = hidden ? 'none' : '';
			textbox.style.disabled = hidden;
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

	#nextAvailablePropertyType() {
		const execute_when = Number(this.form.findFieldByName('execute_when').getValue());
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
		const used_property_types = this.#getActivePropertyTypes();
		const available_types = all_property_types.filter(type => !used_property_types.includes(type));

		if (available_types.length) {
			return available_types[0];
		}

		throw 'No available property types';
	}

	#getActivePropertyTypes() {
		const conditions = this.form.findFieldByName('filter[conditions]').getValue();

		return Object.values(conditions).map(condition => Number(condition.type));
	}

	#updateAvailablePropertyTypes() {
		// if (window.x){debugger;}
		const used_property_types = this.#getActivePropertyTypes();
		// let has_enabled_option = false;

		this.form_element.querySelectorAll('.js-property-type-select').forEach(zselect => {
			const options = zselect.options.map(option => {
				const is_value_option = zselect.value === option.value;
				const is_disabled = !is_value_option && used_property_types.includes(Number(option.value));

				// has_enabled_option |= !is_disabled;

				return {...option, is_disabled}
			});

			zselect.clearOptions();
			zselect.addOptions(options);
			zselect.init();
		});

		// this.form_element.querySelector('.js-add-property').disabled = !has_enabled_option;
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
		const tags_options = [];
		const enable_if_allowed = (option) => {
			option.is_disabled = !this.#operation_types_by_execute_when[execute_when].includes(Number(option.value));
		};

		zselect.options.forEach(option => {
			enable_if_allowed(option);
			option.extra.is_events_group ? events_options.push(option) : tags_options.push(option);
		});

		zselect.clearOptions();
		zselect.addOptionGroup({label: <?= json_encode(_('Events')) ?>, options: events_options});
		zselect.addOptionGroup({label: <?= json_encode(_('Tags')) ?>, options: tags_options});
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
		this.form_element.querySelector(`[name="tag_value"]`).value = operation.tag_value;
		this.form_element.querySelector(`[name="tag"]`).value = operation.tag;
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
		const name = window['ceprule-operation-name-argument'];
		const tag = window['ceprule-operation-tag-argument'];
		const severity = window['ceprule-operation-severity-argument'];
		const period = window['ceprule-operation-period-argument'];
		const tag_pair = window['ceprule-operation-tag-pair-argument'];
		const tag_rename = window['ceprule-operation-tag-rename-argument'];

		name.style.display = 'none';
		tag.style.display = 'none';
		tag.disabled = true;
		severity.style.display = 'none';
		period.style.display = 'none';
		tag_pair.style.display = 'none';
		tag_rename.style.display = 'none';

		if ([
			<?= CCepRuleHelper::OP_CLONE_FIRST ?>,
			<?= CCepRuleHelper::OP_CLONE_LAST ?>,
			<?= CCepRuleHelper::OP_UNSUPPRESS ?>,
			<?= CCepRuleHelper::OP_DECREASE_SEVERITY ?>,
			<?= CCepRuleHelper::OP_INCREASE_SEVERITY ?>,
			<?= CCepRuleHelper::OP_DISCARD ?>,
			<?= CCepRuleHelper::OP_CLOSE_EVENT ?>
		].includes(value)) {
			return;
		}

		if ([
			<?= CCepRuleHelper::OP_SUPPRESS ?>
		].includes(value)) {
			period.style.display = '';
			return;
		}

		if ([
			<?= CCepRuleHelper::OP_SET_SEVERITY ?>
		].includes(value)) {
			severity.style.display = '';
			return;
		}

		if ([
			<?= CCepRuleHelper::OP_SET_NAME ?>
		].includes(value)) {
			name.style.display = '';
			return;
		}

		if ([
			<?= CCepRuleHelper::OP_REMOVE_TAG ?>,
			<?= CCepRuleHelper::OP_DECREASE_TAG_VALUE ?>,
			<?= CCepRuleHelper::OP_INCREASE_TAG_VALUE ?>,
			<?= CCepRuleHelper::OP_REMOVE_TAG ?>
		].includes(value)) {
			tag.style.display = '';
			tag.disabled = false;
			return;
		}

		if ([
			<?= CCepRuleHelper::OP_RENAME_TAG ?>,
		].includes(value)) {
			tag_rename.style.display = '';
			return;
		}

		if ([
			<?= CCepRuleHelper::OP_ADD_TAG ?>,
			<?= CCepRuleHelper::OP_SET_TAG ?>,
			<?= CCepRuleHelper::OP_SET_TAG_VALUE ?>
		].includes(value)) {
			tag_pair.style.display = '';
			return;
		}
	}

	#handleExecuteWhenChanged(value) {
		this.#setAvailableOperationOptions();
	}
};
