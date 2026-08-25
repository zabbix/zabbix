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
 * @var CPartial $this
 */
?>

var ItemEditPreprocessingTab = class {
	#container;
	#readonly;
	#preprocessing_list;
	#step_index;
	#default_type;
	/** @type {CForm} */
	#form;
	#test_type;
	#test_rules;

	constructor({container, preprocessing, readonly, form, test_type, test_rules}) {
		this.#container = container;
		this.#readonly = readonly;
		this.#step_index = 0;
		this.#preprocessing_list = this.#container.querySelector('#preprocessing');
		this.#default_type = this.#preprocessing_list.dataset.steptype;
		this.#form = form;
		this.#test_type = test_type;
		this.#test_rules = test_rules;

		if (this.#readonly) {
			this.#container.querySelector('.element-table-add').disabled = true;
			this.#container.querySelector('.js-item-preprocessing-type z-select')?.setAttribute('readonly', 'readonly');
		}

		this.#initEvents();

		preprocessing.forEach(item => this.#addRow(item));

		this.#update();
	}

	#initEvents() {
		new CSortable(this.#preprocessing_list, {
			selector_span: ':not(.error-container-row)',
			selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			enable_sorting: !this.#readonly,
			freeze_start: 1,
			freeze_end: 1
		})
			.on(CSortable.EVENT_SORT, () => this.#updatePreprocessingListSortorder());

		this.#preprocessing_list.addEventListener('click', (e) => {
			if (e.target.classList.contains('element-table-add')) {
				const step = {
					type: this.#default_type,
					error_handler: <?= ZBX_PREPROC_FAIL_DEFAULT; ?>,
					params: this.#getDefaultParamValues(this.#default_type)
				};

				this.#addRow(step);
			}
			else if (e.target.classList.contains('element-table-remove')) {
				e.target.closest('.preprocessing-list-item').remove();
				this.#updatePreprocessingListSortorder();
				this.#update();
			}
			else if (e.target.classList.contains('js-group-json-action-add')) {
				const sub_tmp = new Template(
					this.#container.querySelector('.preprocessing-steps-parameters-snmp-walk-to-json-row-tmpl').outerHTML
				);

				const row = e.target.closest('.preprocessing-list-item');
				const table = row.querySelector('.group-json-mapping');

				const group = {
					rowNum: row.dataset.step,
					rowIndex: table.dataset.index,
					format: '<?= ZBX_PREPROC_SNMP_UNCHANGED; ?>'
				};

				table.dataset.index++;
				e.target.closest('table').querySelector('tbody').append(sub_tmp.evaluateToElement(group).content);

				this.#updateRow(row.closest('.preprocessing-list-item'));
			}
			else if (e.target.classList.contains('js-group-json-action-delete')) {
				const preprocessing_row = e.target.closest('.preprocessing-list-item')
				e.target.closest('tr').remove();

				this.#updateRow(preprocessing_row);
			}
			else if (e.target.classList.contains('preprocessing-step-test')) {
				this.test(false, false, e.target,
					parseInt(e.target.closest('.preprocessing-list-item').dataset.step)
				)
			}
		});

		this.#container.querySelector('#preproc_test_all').addEventListener('click', (e) =>
			this.test(true, false, e.target, -1)
		);
	}

	getContainer() {
		return this.#container;
	}

	#update() {
		const steps = this.#preprocessing_list.querySelectorAll('.preprocessing-list-item').length;

		if (steps > 0) {
			this.#preprocessing_list.querySelector('.preprocessing-list-head').style.display = '';
			this.#preprocessing_list.querySelector('#preproc_test_all').style.display = '';
		}
		else {
			this.#preprocessing_list.querySelector('.preprocessing-list-head').style.display = 'none';
			this.#preprocessing_list.querySelector('#preproc_test_all').style.display = 'none';
		}
	}

	#updateRow (row) {
		const on_fail = row.querySelector('[name*="[on_fail]"]');
		const select = row.querySelector('z-select[name*="[error_handler]"');
		const checkbox = row.querySelector('.on-fail-options [name*="[error_handler_params]"]');

		if (on_fail.checked) {
			row.querySelector('.on-fail-options').style.display = '';
			select.removeAttribute('disabled');
			const error_handler = select.getAttribute('value');

			if (error_handler == '<?= ZBX_PREPROC_FAIL_DISCARD_VALUE ?>') {
				checkbox.disabled = true;
				checkbox.style.display = 'none'
			}
			else if (error_handler == '<?= ZBX_PREPROC_FAIL_SET_VALUE ?>') {
				checkbox.disabled = false;
				checkbox.style.display = '';
				checkbox.setAttribute('placeholder', <?= json_encode(_('value')) ?>);
				checkbox.setAttribute('data-error-label', '');
			}
			else if (error_handler == '<?= ZBX_PREPROC_FAIL_SET_ERROR ?>') {
				checkbox.disabled = false;
				checkbox.style.display = '';
				checkbox.setAttribute('placeholder', <?= json_encode(_('error message')) ?>);
				checkbox.setAttribute('data-error-label', <?= json_encode(_('Error message')) ?>);
			}
			else {
				select.setAttribute('value', '<?= ZBX_PREPROC_FAIL_DISCARD_VALUE ?>');
				checkbox.disabled = true;
				checkbox.style.display = 'none'
			}
		}
		else {
			row.querySelector('.on-fail-options').style.display = 'none';
			select.setAttribute('disabled', 'disabled');
			checkbox.disabled = true;
		}

		const subtable = row.querySelector('.group-json-mapping');

		if (subtable) {
			const remove_actions = subtable.querySelectorAll('.js-group-json-action-delete');
			const disabled = this.#readonly || remove_actions.length < 2;
			remove_actions.forEach(el => el.disabled = disabled);
			subtable.querySelector('.js-group-json-action-add').disabled = this.#readonly;
		}

		const error_matching = row.querySelector('.js-preproc-param-error-matching');

		if (error_matching) {
			const input = row.querySelector('.js-preproc-param-error-matching-input');

			if (error_matching.getAttribute('value') == '<?= ZBX_PREPROC_MATCH_ERROR_ANY ?>') {
				input.style.display = 'none';
			}
			else {
				input.style.display = '';
			}
		}

		const prometheus_pattern_function = row.querySelector('.js-preproc-param-prometheus-pattern-function');

		if (prometheus_pattern_function) {
			const input = row.querySelector('.js-preproc-param-prometheus-pattern-label');

			if (prometheus_pattern_function.getAttribute('value') == '<?= ZBX_PREPROC_PROMETHEUS_LABEL ?>') {
				input.disabled = false;
			}
			else {
				input.disabled = true;
			}
		}

		if (this.#readonly) {
			this.#readonlyAllInputs(row);
		}
	}

	#updatePreprocessingListSortorder () {
		this.#preprocessing_list.querySelectorAll('.preprocessing-list-item').forEach((list_item, index) => {
			list_item.querySelector('[name*="sortorder"]').value = index;
		});
	}

	#addRow(step) {
		step.rowNum = this.#step_index;
		step.sortorder = this.#step_index + 1;
		this.#step_index++;

		const preproc_row_tmpl = new Template(this.#container.querySelector('.js-preprocessing-steps-tmpl').innerHTML);
		const row = preproc_row_tmpl.evaluateToElement(step);
		row.querySelector('.step-parameters').innerHTML = this.#makeParametersInput(step);

		this.#initRow(row, step.type, step.error_handler != <?= ZBX_PREPROC_FAIL_DEFAULT; ?>);

		row.querySelector('z-select[name*="type"]').addEventListener('change', (e) => {
			const row = e.target.closest('.preprocessing-list-item');

			const step = {
				rowNum: row.dataset.step,
				type: e.target.value,
				params: this.#getDefaultParamValues(e.target.value)
			}

			row.querySelector('.step-parameters').innerHTML = this.#makeParametersInput(step);

			this.#initRow(row, e.target.value, false);
		})

		this.#preprocessing_list.insertBefore(row, this.#preprocessing_list.querySelector('.preprocessing-list-foot'));
		this.#preprocessing_list.querySelector('.preprocessing-list-head').style.display = '';

		this.#update();
	}

	#initRow(row, type, on_fail) {
		const on_fail_input = row.querySelector('[name*="[on_fail]"]');
		const test_button = row.querySelector('.preprocessing-step-test');

		switch (type) {
			case '<?= ZBX_PREPROC_RTRIM ?>':
			case '<?= ZBX_PREPROC_LTRIM ?>':
			case '<?= ZBX_PREPROC_TRIM ?>':
			case '<?= ZBX_PREPROC_THROTTLE_VALUE ?>':
			case '<?= ZBX_PREPROC_THROTTLE_TIMED_VALUE ?>':
			case '<?= ZBX_PREPROC_SCRIPT ?>':
			case '<?= ZBX_PREPROC_STR_REPLACE ?>':
				on_fail_input.checked = false;
				on_fail_input.removeAttribute('readonly');
				on_fail_input.disabled = true;
				test_button.disabled = false;
				break;

			case '<?= ZBX_PREPROC_VALIDATE_NOT_SUPPORTED ?>':
				on_fail_input.checked = true;
				on_fail_input.setAttribute('readonly', 'readonly');
				on_fail_input.disabled = false;
				test_button.disabled = false;
				break;

			default:
				on_fail_input.checked = on_fail;
				on_fail_input.removeAttribute('readonly');
				on_fail_input.disabled = false;
				test_button.disabled = false;
				break;
		}

		on_fail_input.addEventListener('change', (e) => this.#updateRow(e.target.closest('.preprocessing-list-item')));

		row.querySelector('z-select[name*="[error_handler]"]')
			.addEventListener('change', (e) => this.#updateRow(e.target.closest('.preprocessing-list-item')));

		row.querySelector('.js-preproc-param-error-matching')
			?.addEventListener('change', (e) => this.#updateRow(e.target.closest('.preprocessing-list-item')));

		row.querySelector('.js-preproc-param-prometheus-pattern-function')
			?.addEventListener('change', (e) => this.#updateRow(e.target.closest('.preprocessing-list-item')));

		const multiline = row.querySelector('.multilineinput-control');

		if (multiline) {
			const parameters = {
				title: <?= json_encode(_('JavaScript')) ?>,
				placeholder: <?= json_encode(_('script')) ?>,
				placeholder_textarea: 'return value',
				label_before: 'function (value) {',
				label_after: '}',
				grow: 'auto',
				rows: 0,
				value: multiline.getAttribute('data-value-init'),
				maxlength: <?= DB::getFieldLength('item_preproc', 'params') ?>
			};

			if (this.#readonly) {
				parameters.readonly = true;
			}

			$(multiline).multilineInput(parameters);
		}

		if (this.#readonly) {
			row.querySelector('.element-table-remove').disabled = true;
		}

		this.#updateRow(row);
	}

	#readonlyAllInputs(row) {
		row.querySelectorAll('input, z-select, z-textarea-flexible').forEach(input => {
			input.setAttribute('readonly', 'readonly');
		});
	}

	#getParameterTemplateName(type) {
		switch (type) {
			case'<?= ZBX_PREPROC_MULTIPLIER ?>':
				return 'preprocessing-steps-parameters-multiplier-tmpl';

			case '<?= ZBX_PREPROC_RTRIM ?>':
			case '<?= ZBX_PREPROC_LTRIM ?>':
			case '<?= ZBX_PREPROC_TRIM ?>':
				return 'preprocessing-steps-parameters-trim-tmpl';

			case '<?= ZBX_PREPROC_XPATH ?>':
			case '<?= ZBX_PREPROC_ERROR_FIELD_XML ?>':
				return 'preprocessing-steps-parameters-xpath-tmpl';

			case '<?= ZBX_PREPROC_JSONPATH ?>':
			case '<?= ZBX_PREPROC_ERROR_FIELD_JSON ?>':
				return 'preprocessing-steps-parameters-json-path-tmpl';

			case '<?= ZBX_PREPROC_REGSUB ?>':
			case '<?= ZBX_PREPROC_ERROR_FIELD_REGEX ?>':
				return 'preprocessing-steps-parameters-regsub-tmpl';

			case '<?= ZBX_PREPROC_VALIDATE_RANGE ?>':
				return 'preprocessing-steps-parameters-validate-range-tmpl';

			case '<?= ZBX_PREPROC_VALIDATE_REGEX ?>':
			case '<?= ZBX_PREPROC_VALIDATE_NOT_REGEX ?>':
				return 'preprocessing-steps-parameters-regex-tmpl';

			case '<?= ZBX_PREPROC_THROTTLE_TIMED_VALUE ?>':
				return 'preprocessing-steps-parameters-throttle-timed-value-tmpl';

			case '<?= ZBX_PREPROC_SCRIPT ?>':
				return 'preprocessing-steps-parameters-script-tmpl';

			case '<?= ZBX_PREPROC_PROMETHEUS_PATTERN ?>':
				return 'preprocessing-steps-parameters-prometheus-pattern-tmpl';

			case '<?= ZBX_PREPROC_PROMETHEUS_TO_JSON ?>':
				return 'preprocessing-steps-parameters-prometheus-to-json-tmpl';

			case '<?= ZBX_PREPROC_CSV_TO_JSON ?>':
				return 'preprocessing-steps-parameters-csv-to-json-tmpl';

			case '<?= ZBX_PREPROC_STR_REPLACE ?>':
				return 'preprocessing-steps-parameters-replace-tmpl';

			case '<?= ZBX_PREPROC_VALIDATE_NOT_SUPPORTED ?>':
				return 'preprocessing-steps-parameters-check-not-supported-tmpl';

			case '<?= ZBX_PREPROC_SNMP_WALK_VALUE ?>':
				return 'preprocessing-steps-parameters-snmp-walk-value-tmpl';

			case '<?= ZBX_PREPROC_SNMP_WALK_TO_JSON ?>':
				return 'preprocessing-steps-parameters-snmp-walk-to-json-tmpl';

			case '<?= ZBX_PREPROC_SNMP_GET_VALUE ?>':
				return 'preprocessing-steps-parameters-snmp-get-value-tmpl';
		}

		return null;
	}

	#getDefaultParamValues(type) {
		const params = ['', '', ''];

		switch (type) {
			case '<?= ZBX_PREPROC_PROMETHEUS_PATTERN ?>':
				params[1] = '<?= ZBX_PREPROC_PROMETHEUS_VALUE; ?>';
				break;

			case '<?= ZBX_PREPROC_CSV_TO_JSON ?>':
				params[0] = ',';
				params[1] = '"';
				params[2] = true;
				break;

			case '<?= ZBX_PREPROC_VALIDATE_NOT_SUPPORTED ?>':
				params[0] = '<?= ZBX_PREPROC_MATCH_ERROR_ANY; ?>';
				break;

			case '<?= ZBX_PREPROC_SNMP_WALK_VALUE ?>':
				params[1] = <?= ZBX_PREPROC_SNMP_UNCHANGED; ?>;
				break;

			case '<?= ZBX_PREPROC_SNMP_WALK_TO_JSON ?>':
				params[2] = '0';
				break;

			case '<?= ZBX_PREPROC_SNMP_GET_VALUE ?>':
				params[0] = <?= ZBX_PREPROC_SNMP_UTF8_FROM_HEX; ?>;
				break;
		}

		return params;
	}

	#makeParametersInput(step) {
		let template_name = this.#getParameterTemplateName(step.type);

		if (template_name == null) {
			return '';
		}

		const param_tmp = new Template(this.#container.querySelector(`.${template_name}`).outerHTML);
		const parameters = param_tmp.evaluateToElement(step);

		if (step.type === '<?= ZBX_PREPROC_CSV_TO_JSON ?>') {
			if (step.params[2] == 1) {
				parameters.content.querySelector('[name*=params_2]').setAttribute('checked', 'checked')
			}
			else {
				parameters.content.querySelector('[name*=params_2]').removeAttribute('checked')
			}
		}
		else if (step.type == '<?= ZBX_PREPROC_SNMP_WALK_TO_JSON ?>') {
			const sub_tmp = new Template(
				this.#container.querySelector('.preprocessing-steps-parameters-snmp-walk-to-json-row-tmpl').outerHTML
			);

			const tbody = parameters.content.querySelector('.group-json-mapping tbody');

			let rowIndex = 0;

			for (let i = 0; i < step.params.length; i+=3) {
				const sub_row = sub_tmp.evaluateToElement({
					name: step.params[i],
					oid_prefix: step.params[i+1],
					format: step.params[i+2],
					rowNum: step.rowNum,
					rowIndex
				});

				tbody.append(sub_row.content);
				rowIndex++;
			}

			parameters.content.querySelector('.group-json-mapping').dataset.index = rowIndex;
		}

		return parameters.innerHTML;
	}

	test(show_final_result, get_value, trigger_element, step_obj_nr, validate_fields = []) {
		const indexes = (step_obj_nr < 0) ? [] : [step_obj_nr];

		if (step_obj_nr < 0) {
			this.#container.querySelectorAll('.preprocessing-list-item').forEach(row => {
				indexes.push(row.dataset.step);
			});

			validate_fields.push('preprocessing');
		}
		else {
			validate_fields.push(`preprocessing/${step_obj_nr}`);
		}

		if (this.#form) {
			let validate_key = (step_obj_nr === -2);
			const types_test_key = <?= json_encode(CControllerPopupItemTest::$item_types_has_key_mandatory) ?>;

			for (const field of Object.values(this.#form.findFieldByName('preprocessing').getFields())) {
				if (step_obj_nr < 0 || field.getPath().startsWith(`/preprocessing/${step_obj_nr}`)) {
					field.setChanged();

					if (!validate_key && field.getPath().endsWith('/type')) {
						const type = parseInt(field.getValue());

						if (types_test_key.includes(type)) {
							validate_key = true;
						}
					}
				}
			}

			if (validate_key) {
				validate_fields.push('key');
				this.#form.findFieldByName('key').setChanged();
			}

			this.#form.validateFieldsForAction(validate_fields, this.#test_rules).then((result) => {
				this.#container.dispatchEvent(new CustomEvent('test.validated'));

				if (!result) {
					return;
				}

				// Method requires form name to be set to itemForm.
				openItemTestDialog(indexes, show_final_result, get_value, trigger_element, step_obj_nr);
			});
		}
		else {
			// Method requires form name to be set to itemForm.
			openItemTestDialog(indexes, show_final_result, get_value, trigger_element, step_obj_nr);
		}
	}
};

