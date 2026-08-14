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

window.ceprule_edit_popup = new class {

	/** @type {HTMLFormElement} */
	form_element;

	/** @type {CForm} */
	form;

	/** @type {Overlay} */
	#overlay;

	/** @type {Object} */
	#condition_rules;

	/** @type {Object} */
	#execute_when_by_window_type;

	/** @type {Object} */
	#operation_types_by_execute_when;

	/** @type {Template} */
	#condition_row_template;

	/** @type {Template} */
	#condition_row_template_value;

	/** @type {Template} */
	#condition_row_template_tag;

	/** @type {Template} */
	#condition_row_template_tag_exists;

	/** @type {Number} */
	#condition_row_index = 0;

	/** @type {Template} */
	#operation_row_template;

	/** @type {Object} */
	#operation_rules;

	/** @type {Object} */
	#rules_for_clone;

	init({rules, rules_for_clone, operation_rules, condition_rules, operation_types_by_execute_when,
			execute_when_by_window_type, ceprule}) {
		this.#rules_for_clone = rules_for_clone;
		this.#initTemplates();
		this.#condition_rules = condition_rules;
		this.#operation_types_by_execute_when = operation_types_by_execute_when;
		this.#execute_when_by_window_type = execute_when_by_window_type;
		this.#operation_rules = operation_rules;
		this.#overlay = overlays_stack.getById('ceprule.edit');
		this.form_element = this.#overlay.$dialogue.$body[0].querySelector('form');

		for (const condition of Object.values(ceprule.filter.conditions)) {
			const formulaid = num2letter(this.#condition_row_index++);

			this.#addConditionRow({...condition, formulaid, row_index: this.#condition_row_index});
		}

		for (const operation of Object.values(ceprule.operations)) {
			this.#addOperationRow(operation);
		}

		jQuery(window['ceprule-script']).multilineInput({
			placeholder: '<?= _('script') ?>',
			value: ceprule.window.script
		});

		this.#initActions();
		this.form = new CForm(this.form_element, rules);
		this.form.findFieldByName('operations').setButtonOnBlur('js-operation-add', 'ceprule.operation.edit');

		this.#handleFilterChanged();
		this.#handleOptgroupTagChange.call(window['ceprule-window-groupby-opt-tag']);
		this.#handleWindowTypeChanged(Number(ceprule.window_type));

		window['ceprule-window-counttag-toggle'].dispatchEvent(new Event('change'));
		window['ceprule-window-capacity-toggle'].dispatchEvent(new Event('change'));

		window.requestAnimationFrame(() => {
			this.form_element.classList.remove(ZBX_STYLE_DISPLAY_NONE);
			Focuser.focus(this.form_element.querySelector('[autofocus]'));
		});
	}

	#initTemplates() {
		this.#condition_row_template_value = new Template(
			`<div class="text">#{name} #{operator} <em>#{value}</em></div>`
		);
		this.#condition_row_template_tag = new Template(
			`<div class="text">#{name} <em>#{tag_name}</em> #{operator} <em>#{tag_value}</em></div>`
		);
		this.#condition_row_template_tag_exists = new Template(
			`<div class="text">#{name} <em>#{tag_name}</em> #{operator}</div>`
		);
		this.#condition_row_template = new Template(window['ceprule-condition-row-template'].innerHTML);
		this.#operation_row_template = new Template(window['ceprule-operation-row-template'].innerHTML);
	}

	#initActions() {
		const return_url = new URL('zabbix.php', location.href);

		return_url.searchParams.set('action', 'ceprule.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		// Primitive event handlers.
		this.form_element.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-condition-add')) {
				this.#openConditionPopup(undefined, e.target);
			}
			else if (e.target.classList.contains('js-condition-edit')) {
				const row_index = e.target.closest('tr').dataset.row_index;
				const conditions = this.form.findFieldByName('filter[conditions]').getValue();

				this.#openConditionPopup(conditions[row_index], e.target);
			}
			else if (e.target.classList.contains('js-condition-remove')) {
				e.target.closest('tr').remove();
				this.form.discoverAllFields();

				if (!window['ceprule-filter-conditions'].querySelector('[data-row_index]')) {
					this.#condition_row_index = 0;
				}

				this.form_element.dispatchEvent(new Event('filter.change'));
			}
			else if (e.target.classList.contains('js-operation-add')) {
				this.#openOperationPopup(undefined, e.target);
			}
			else if (e.target.classList.contains('js-operation-edit')) {
				const {
					[e.target.closest('[data-sortorder]').getAttribute('data-sortorder')]: operation
				} = this.form.findFieldByName('operations').getValue();
				this.#openOperationPopup(operation, e.target);
			}
			else if (e.target.classList.contains('js-operation-remove')) {
				const row = e.target.closest('tr');
				row.nextElementSibling.remove();
				row.remove();
				this.#renumberOperationRows();
				this.form.discoverAllFields();
			}
		});

		// Add event proxies.
		this.form_element.addEventListener('change', (e) => {
			if (e.target.name === 'filter[evaltype]') {
				this.form_element.dispatchEvent(new Event('filter.change'));
			}
		});

		// Add proxied form event handlers.
		this.form_element.addEventListener('filter.change', () => this.#handleFilterChanged());

		window['ceprule-window-counttag-toggle'].addEventListener('change', (e) => {
			const enabled = window['ceprule-window-counttag-toggle'].querySelector('[value="1"]').checked;
			window['ceprule-window-counttag'].style.display = enabled ? '' : 'none';
			window['ceprule-window-counttag'].disabled = !enabled;
		});

		window['ceprule-window-capacity-toggle'].addEventListener('change', (e) => {
			const enabled = window['ceprule-window-capacity-toggle'].querySelector('[value="1"]').checked;
			window['ceprule-window-capacity'].style.display = enabled ? '' : 'none';
			window['ceprule-window-capacity'].disabled = !enabled;
		});

		window['ceprule-window-groupby-opt-tag']
			.addEventListener('change', e => this.#handleOptgroupTagChange.call(e.target));

		window['ceprule-window-groupby-opt-host'].addEventListener('change', e => {
			this.form.findFieldByName('window[group_by_tags]').setChanged();
		});
		window['ceprule-window-groupby-opt-group'].addEventListener('change', e => {
			this.form.findFieldByName('window[group_by_tags]').setChanged();
		});

		window['ceprule-window-type']
			.addEventListener('change', e => this.#handleWindowTypeChanged(Number(e.target.value)));

		new CSortable(window['ceprule-operations-table'].querySelector('tbody'), {
			selector_span: ':not(.error-container-row)',
			selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>'
		}).on(CSortable.EVENT_SORT, () => {
			this.#renumberOperationRows();
			this.form.discoverAllFields();
		});

		// Confirm / cancel dialog.
		this.#overlay.$dialogue.$footer.get(0).addEventListener('click', (e) => {
			const class_list = e.target.classList;

			if (class_list.contains('js-submit')) {
				this.#submit();
			}
			else if (class_list.contains('js-delete')) {
				if (window.confirm(<?= json_encode('Delete complex event processing rule?') ?>)) {
					this.#delete();
				}
				else {
					this.#overlay.unsetLoading();
				}
			}
			else if (class_list.contains('js-clone')) {
				this.#clone();
			}
			else if (class_list.contains('js-reset-time-windows')) {
				this.#resetTimeWindwos();
			}
		});
	}

	#handleOptgroupTagChange() {
		window['ceprule-window-groupby-tag'].style.display = this.checked ? '' : 'none';
		jQuery(window['ceprule-window-groupby-tag']).multiSelect(this.checked ? 'enable' : 'disable');
	}

	#handleWindowTypeChanged(type) {
		window['ceprule-operations-label']
			.classList.toggle('form-label-asterisk', type != <?= CCepRuleHelper::WINDOW_CAUSE_SYMPTOM ?>);

		window['ceprule-groupby-label']
			.classList.toggle('form-label-asterisk', type == <?= CCepRuleHelper::WINDOW_CAUSE_SYMPTOM ?>);

		{
			const form_field = window['ceprule-script'].closest('.form-field');
			const display = type == <?= CCepRuleHelper::WINDOW_PATTERN_MATCH ?> ? '' : 'none';

			form_field.style.display = display;
			form_field.previousSibling.style.display = display;
			jQuery(window['ceprule-script']).multilineInput(display === '' ? 'enable' : 'disable');
		}
		{
			const form_field = window['ceprule-window-counttag'].closest('.form-field');
			const display = type == <?= CCepRuleHelper::WINDOW_CAUSE_SYMPTOM ?> ? '' : 'none';

			form_field.style.display = display;
			form_field.previousSibling.style.display = display;
		}
		{
			const form_field = window['ceprule-window-groupby'].closest('.form-field');
			const display = [
				<?= CCepRuleHelper::WINDOW_SIMPLE ?>,
				<?= CCepRuleHelper::WINDOW_CAUSE_SYMPTOM ?>,
				<?= CCepRuleHelper::WINDOW_TAG_MATCH ?>,
				<?= CCepRuleHelper::WINDOW_PATTERN_MATCH ?>
			].includes(type) ? '' : 'none';

			form_field.style.display = display;
			form_field.previousSibling.style.display = display;
		}
		{
			const form_field = window['ceprule-window-capacity'].closest('.form-field');
			const display = [
				<?= CCepRuleHelper::WINDOW_SIMPLE ?>,
				<?= CCepRuleHelper::WINDOW_CAUSE_SYMPTOM ?>,
				<?= CCepRuleHelper::WINDOW_TAG_MATCH ?>,
				<?= CCepRuleHelper::WINDOW_PATTERN_MATCH ?>
			].includes(type) ? '' : 'none';

			form_field.style.display = display;
			form_field.previousSibling.style.display = display;
		}
		{
			const form_field = window['ceprule-window-duration'].closest('.form-field');
			const display = [
				<?= CCepRuleHelper::WINDOW_SIMPLE ?>,
				<?= CCepRuleHelper::WINDOW_CAUSE_SYMPTOM ?>,
				<?= CCepRuleHelper::WINDOW_TAG_MATCH ?>,
				<?= CCepRuleHelper::WINDOW_PATTERN_MATCH ?>
			].includes(type) ? '' : 'none';

			form_field.style.display = display;
			form_field.previousSibling.style.display = display;
		}

		const group_field_names = ['window[group_by_host]', 'window[group_by_host_group]', 'window[group_by_tags]'];

		if (group_field_names.find(name => this.form.findFieldByName(name).hasChanged())) {
			group_field_names.forEach(name => this.form.findFieldByName(name).setChanged());
			this.form.validateChanges(group_field_names);
		}

		this.#handleOptgroupTagChange.call(window['ceprule-window-groupby-opt-tag']);
	}

	#delete() {
		this.#removePopupMessages();
		fetch(zabbixUrl({action: 'ceprule.delete'}), {
			method: 'POST',
			headers: {'Content-Type': 'application/json; charset=UTF-8'},
			body: JSON.stringify({
				cepruleids: [this.form.findFieldByName('cepruleid').getValue()],
				[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('ceprule')) ?>
			})
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				if ('success' in response) {
					postMessageOk(response.success.title);

					if ('messages' in response.success) {
						postMessageDetails('success', response.success.messages);
					}

					overlayDialogueDestroy(this.#overlay.dialogueid);
					this.#overlay.$dialogue[0]
						.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
				}
				else {
					throw new Error();
				}
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => {
				this.#overlay.unsetLoading();
			});
	}

	#resetTimeWindwos() {
		this.#removePopupMessages();
		fetch(zabbixUrl({action: 'ceprule.resettimewindows'}), {
			method: 'POST',
			headers: {'Content-Type': 'application/json; charset=UTF-8'},
			body: JSON.stringify({
				cepruleids: [this.form.findFieldByName('cepruleid').getValue()],
				[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('ceprule')) ?>
			})
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				if ('success' in response) {
					const message_box = makeMessageBox('good', response.success.messages, response.success.title)[0];

					this.form_element.parentNode.insertBefore(message_box, this.form_element);
				}
				else {
					throw new Error();
				}
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => {
				this.#overlay.unsetLoading();
			});
	}

	#clone() {
		this.form.findFieldByName('cepruleid')._field.remove();
		this.#removePopupMessages();

		const title = <?= json_encode(_('New complex event processing')) ?>;
		const buttons = [
			{
				title: <?= json_encode(_('Add')) ?>,
				class: 'js-submit',
				keepOpen: true,
				isSubmit: true
			},
			{
				title: <?= json_encode(_('Cancel')) ?>,
				class: ZBX_STYLE_BTN_ALT,
				cancel: true,
				action: ''
			}
		];

		this.#overlay.setProperties({title, buttons});
		this.form.reload(this.#rules_for_clone);
	}

	#submit() {
		const fields = this.form.getAllValues();
		fields[CSRF_TOKEN_NAME] = <?= json_encode(CCsrfTokenHelper::get('ceprule')) ?>;

		this.#removePopupMessages();
		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.#unsetLoadingStatus();
					return;
				}

				const action = fields.cepruleid === undefined
					? 'ceprule.create'
					: 'ceprule.update';

				fetch(zabbixUrl({action}), {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify(fields)
				})
					.then((response) => response.json())
					.then((response) => {
						if ('error' in response) {
							throw {error: response.error};
						}

						if ('form_errors' in response) {
							this.form.setErrors(response.form_errors, true, true);
							this.form.renderErrors();
							return;
						}

						if ('success' in response) {
							postMessageOk(response.success.title);

							if ('messages' in response.success) {
								postMessageDetails('success', response.success.messages);
							}

							overlayDialogueDestroy(this.#overlay.dialogueid);
							this.#overlay.$dialogue[0]
								.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
						}
					})
					.catch((exception) => this.#ajaxExceptionHandler(exception))
					.finally(() => this.#unsetLoadingStatus());
			});
	}

	/**
	 * Method ensures filter view is correct with data:
	 *	- conditions formula preview string.
	 *	- type of calculation row.
	 */
	#handleFilterChanged() {
		const evaltype_select = window['ceprule-filter-evaltype'];
		const evaltype_field = evaltype_select.closest('.form-field');

		const conditions = Object.values(this.form.findFieldByName('filter[conditions]').getValue() ?? {});

		if (conditions.length < 2) {
			evaltype_field.style.display = 'none';
			evaltype_field.previousElementSibling.style.display = 'none';

			return;
		}

		evaltype_field.style.display = '';
		evaltype_field.previousElementSibling.style.display = '';

		const evaltype = Number(evaltype_select.value);
		const is_expression_evaltype = evaltype == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>;

		window['ceprule-filter-expression'].style.display = is_expression_evaltype ? '' : 'none';
		window['ceprule-filter-expression-preview'].style.display = !is_expression_evaltype ? '' : 'none';

		const identifiers = Object.values(conditions).map(condition => ({id: condition.formulaid}));

		window['ceprule-filter-expression-preview'].innerText = getConditionFormula(identifiers, evaltype);
	}

	#openConditionPopup(condition, trigger_element) {
		const is_new = condition === undefined;

		if (is_new) {
			condition = {
				row_index: this.#condition_row_index + 1,
				formulaid: num2letter(this.#condition_row_index),
				type: '<?= CCepRuleHelper::CONDITION_EVENT_NAME ?>',
				operator: '<?= CONDITION_OPERATOR_EQUAL ?>',
				host_group: '',
				host: '',
				severity: '<?= TRIGGER_SEVERITY_NOT_CLASSIFIED ?>',
				tag_operator: '<?= CONDITION_OPERATOR_EQUAL ?>',
				tag: '',
				tag_value: '',
				time_period: ''
			};
		}

		const template = document.getElementById('ceprule-condition-modal-template');
		const form_element = template.content.querySelector('form').cloneNode(true);

		const overlay = overlayDialogue({
			class: 'modal-popup modal-popup-medium',
			title: t('Condition details'),
			content: form_element,
			buttons: [
				{
					title: is_new ? t('Add') : t('Update'),
					isSubmit: true,
					action: overlay => ceprule_condition_edit_popup.submit()
						.then(fields => {
							is_new && this.#addConditionRow(fields) || this.#editConditionRow(fields);
							is_new && (this.#condition_row_index++);
							this.form.discoverAllFields();
							this.form_element.dispatchEvent(new Event('filter.change'));
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
			dialogueid: 'ceprule.condition.edit',
			trigger_element
		});

		ceprule_condition_edit_popup.init({rules: this.#condition_rules, condition, overlay});
	}

	#openOperationPopup(operation, trigger_element) {
		const is_new = operation === undefined;

		if (is_new) {
			const operations = this.form.findFieldByName('operations').getValue();

			operation = {
				sortorder: 1 + Math.max(0, ...Object.values(operations).map(({sortorder}) => sortorder)),
				evaltype: '<?= CONDITION_EVAL_TYPE_AND_OR ?>',
				event_name: '',
				execute_when: '<?= CCepRuleHelper::WHEN_EVENT_OCCURRED ?>',
				new_tag: '',
				suppress_duration: '',
				severity: '<?= TRIGGER_SEVERITY_NOT_CLASSIFIED ?>',
				tag: '',
				tag_value: '',
				filter: {
					evaltype: <?= CONDITION_EVAL_TYPE_AND_OR ?>,
					conditions: [{
						type: <?= ZBX_CONDITION_TYPE_EVENT_OPEN ?>,
						tag: '',
						operator: <?= CONDITION_OPERATOR_YES ?>,
						value: ''
					}]
				}
			};
		}

		const template = document.getElementById('ceprule-operation-modal-template');
		const form_element = template.content.querySelector('form').cloneNode(true);

		const overlay = overlayDialogue({
			class: 'modal-popup modal-popup-medium',
			title: t('Operation details'),
			content: form_element,
			buttons: [
				{
					title: is_new ? t('Add') : t('Update'),
					isSubmit: true,
					action: (overlay) => {
						const form = ceprule_operation_edit_popup.form;
						const fields = form.getAllValues();

						form.validateSubmit(fields)
							.then((result) => {
								if (!result) {
									overlay.unsetLoading();
									return;
								}

								overlayDialogueDestroy(overlay.dialogueid);

								is_new && this.#addOperationRow(fields) || this.#editOperationRow(fields);
								this.form.discoverAllFields();
							});

						return false;
					}
				},
				{
					title: t('Cancel'),
					class: 'btn-alt',
					cancel: true,
					action: () => {}
				}
			]
		}, {
			dialogueid: 'ceprule.operation.edit',
			trigger_element
		});

		ceprule_operation_edit_popup.init({rules: this.#operation_rules, operation, overlay,
			window_type: this.form.findFieldByName('window_type').getValue(),
			operation_types_by_execute_when: this.#operation_types_by_execute_when,
			execute_when_by_window_type: this.#execute_when_by_window_type
		});
	}

	#ajaxExceptionHandler(exception) {
		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		const message_box = makeMessageBox('bad', messages, title)[0];

		this.form_element.parentNode.insertBefore(message_box, this.form_element);
	}

	#unsetLoadingStatus() {
		this.#overlay.unsetLoading();
	}

	#editOperationRow(operation) {
		const row = this.form_element
			.querySelector(`#ceprule-operations-table [data-sortorder="${operation.sortorder}"]`);

		row.nextElementSibling.remove();
		row.replaceWith(this.#buildOperationRow(operation));
	}

	#addOperationRow(operation) {
		this.form_element.querySelector('#ceprule-operations-table tbody')
			.append(this.#buildOperationRow(operation));
	}

	#buildOperationRow(operation) {
		const operation_type = Number(operation.type);
		const execute_when_str = JSON.parse('<?= json_encode(
			CCepRuleHelper::getOperationExecuteWhenStrings()
		) ?>')[operation.execute_when];

		const label_str = JSON.parse('<?= json_encode(
			CCepRuleHelper::getOperationLabelStrings()
		) ?>')[operation_type];

		const severity_names_json = '<?= json_encode([
			TRIGGER_SEVERITY_NOT_CLASSIFIED => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_0)),
			TRIGGER_SEVERITY_INFORMATION => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_1)),
			TRIGGER_SEVERITY_WARNING => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_2)),
			TRIGGER_SEVERITY_AVERAGE => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_3)),
			TRIGGER_SEVERITY_HIGH => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_4)),
			TRIGGER_SEVERITY_DISASTER => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_5))
		]) ?>';
		const severity_names = JSON.parse(severity_names_json);

		let arguments_str;
		if ([
			<?= CCepRuleHelper::OP_INCREASE_SEVERITY ?>,
			<?= CCepRuleHelper::OP_DECREASE_SEVERITY ?>,
			<?= CCepRuleHelper::OP_UNSUPPRESS ?>,
			<?= CCepRuleHelper::OP_CLONE_FIRST ?>,
			<?= CCepRuleHelper::OP_CLONE_LAST ?>,
			<?= CCepRuleHelper::OP_DISCARD ?>,
			<?= CCepRuleHelper::OP_CLOSE_EVENT ?>
		].includes(operation_type)) {
			arguments_str = '';
		}
		else if ([
			<?= CCepRuleHelper::OP_SUPPRESS ?>
		].includes(operation_type)) {
			arguments_str = operation.suppress_duration
				? <?= json_encode(_('until')) ?> + ' ' + operation.suppress_duration
				: <?= json_encode(_('Indefinately')) ?>;
		}
		else if ([
			<?= CCepRuleHelper::OP_INCREASE_TAG_VALUE ?>,
			<?= CCepRuleHelper::OP_DECREASE_TAG_VALUE ?>,
			<?= CCepRuleHelper::OP_REMOVE_TAG ?>
		].includes(operation_type)) {
			arguments_str = operation.tag;
		}
		else if (operation_type == <?= CCepRuleHelper::OP_SET_NAME ?>) {
			arguments_str = operation.event_name;
		}
		else if (operation_type == <?= CCepRuleHelper::OP_SET_SEVERITY ?>) {
			arguments_str = severity_names[operation.severity];
		}
		else if (operation_type == <?= CCepRuleHelper::OP_RENAME_TAG ?>) {
			arguments_str = `${operation.tag}:${operation.new_tag}`;
		}
		else if ([
			<?= CCepRuleHelper::OP_SET_TAG_VALUE ?>,
			<?= CCepRuleHelper::OP_SET_TAG ?>,
			<?= CCepRuleHelper::OP_ADD_TAG ?>
		].includes(operation_type)) {
			arguments_str = `${operation.tag}:${operation.tag_value}`;
		}

		const conditions_input_html = Object.values(operation.filter.conditions)
			.map((condition, condition_index) => (new Template(`
				<input data-field-type="hidden" name="operations[${operation.sortorder}][filter][conditions][${condition_index}][type]"
					type="hidden" value="#{type}"/>
				<input data-field-type="hidden" name="operations[${operation.sortorder}][filter][conditions][${condition_index}][tag]"
					type="hidden" value="#{tag}"/>
				<input data-field-type="hidden" name="operations[${operation.sortorder}][filter][conditions][${condition_index}][operator]"
					type="hidden" value="#{operator}"/>
				<input data-field-type="hidden" name="operations[${operation.sortorder}][filter][conditions][${condition_index}][value]"
					type="hidden" value="#{value}"/>
			`)).evaluate(condition)).join('');

		const template_args = {execute_when_str, label_str, arguments_str, conditions_input_html, ...operation};
		const row = this.#operation_row_template.evaluateToElement(template_args);
		const error_container_id = `ceprule-operations-${template_args.sortorder}-error-container`;
		const rows = new DocumentFragment();

		row.querySelector('[name$="[execute_when]"]').setAttribute('data-error-container', error_container_id);
		rows.append(row);
		rows.append((new Template(`
			<tr class="error-container-row"><td colspan="3" id="${error_container_id}"></td></tr>
		`)).evaluateToElement());

		return rows;
	}

	#editConditionRow(condition) {
		window['ceprule-filter-conditions'].querySelector(`[data-row_index="${condition.row_index}"]`)
			.replaceWith(this.#buildConditionRow(condition));
	}

	#addConditionRow(condition) {
		window['ceprule-filter-conditions'].querySelector('tbody')
			.insertAdjacentElement('beforeend', this.#buildConditionRow(condition));
	}

	#buildConditionRow(condition) {
		const label_names = JSON.parse('<?= json_encode(
			CCepRuleHelper::getConditionLabels()
		) ?>');

		const operator_names = JSON.parse('<?= json_encode(
			CCepRuleHelper::getConditionOperatorLabels()
		) ?>');

		const severity_names = JSON.parse('<?= json_encode(
			array_column(CSeverityHelper::getSeverities(), 'label', 'value')
		) ?>');

		let description_template = this.#condition_row_template_value;
		const description_view = {
			name: label_names[condition.type],
			operator: condition.type == <?= CCepRuleHelper::CONDITION_TAG ?>
				? operator_names[condition.tag_operator]
				: operator_names[condition.operator],
			value: undefined,
			tag_name: undefined,
			tag_value: undefined
		};

		if (condition.type == <?= CCepRuleHelper::CONDITION_EVENT_NAME ?>) {
			description_view.value = condition.event_name;
		}
		else if (condition.type == <?= CCepRuleHelper::CONDITION_SEVERITY ?>) {
			description_view.value = severity_names[condition.severity];
		}
		else if (condition.type == <?= CCepRuleHelper::CONDITION_HOST ?>) {
			description_view.value = condition.host;
		}
		else if (condition.type == <?= CCepRuleHelper::CONDITION_HOST_GROUP ?>) {
			description_view.value = condition.host_group;
		}
		else if (condition.type == <?= CCepRuleHelper::CONDITION_TIME_PERIOD ?>) {
			description_view.value = condition.time_period;
		}
		else if (condition.type == <?= CCepRuleHelper::CONDITION_TAG ?>) {
			description_view.tag_name = condition.tag;
			description_template = this.#condition_row_template_tag_exists;

			if (condition.tag_operator != <?= CONDITION_OPERATOR_EXISTS ?>
					&& condition.tag_operator != <?= CONDITION_OPERATOR_NOT_EXISTS ?>) {
				description_view.tag_value = condition.tag_value;
				description_template = this.#condition_row_template_tag;
			}
		}

		description_view.operator = description_view.operator.toLocaleLowerCase();

		return this.#condition_row_template.evaluateToElement({
			description_html: description_template.evaluate(description_view),
			...condition
		});
	}

	#renumberOperationRows() {
		window['ceprule-operations-table'].querySelectorAll('[data-sortorder]')
			.forEach(function(row, index) {
				row.dataset.sortorder = index;
				row.querySelectorAll('[data-field-type="hidden"]').forEach(function(input) {
					input.name = input.name.replace( /^operations\[\d+\]/, `operations[${index}]`);

					if (input.name === `operations[${index}][sortorder]`) {
						input.value = index;
					}
				});
			});
	}

	#removePopupMessages() {
		for (const el of this.form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	}
};
