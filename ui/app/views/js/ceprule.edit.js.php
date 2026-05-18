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
	#initial_form_fields;

	/** @type {Object} */
	#condition_rules;

	/** @type {Template} */
	#condition_row_template;

	/** @type {Number} */
	#condition_row_index = 0;

	/** @type {Template} */
	#operation_row_template;

	/** @type {Object} */
	#operation_rules;

	/** @type {Number} */
	#operation_row_index = 0;

	init({rules, operation_rules, condition_rules, ceprule}) {
		this.#initTemplates();
		this.#condition_rules = condition_rules;
		this.#operation_rules = operation_rules;
		this.#overlay = overlays_stack.getById('ceprule.edit');
		this.form_element = this.#overlay.$dialogue.$body[0].querySelector('form');

		for (const condition of Object.values(ceprule.filter.conditions)) {
			this.#addConditionRow({...condition, formulaid: String.fromCharCode(65 + this.#condition_row_index++)});
		}

		for (const operation of Object.values(ceprule.operations)) {
			this.#addOperationRow(operation);
		}

		this.#initActions();
		this.form = new CForm(this.form_element, rules);
		this.#handleFilterChanged();

		this.#initial_form_fields = this.form.getAllValues(); // TODO: use at on-before page unload confirmation
		console.log([ceprule, '===', this.#initial_form_fields]);

		this.form_element.style.display = '';
	}

	#initTemplates() {
		this.#condition_row_template = new Template(`
			<tr data-formulaid="#{formulaid}">
				<td>#{formulaid}</td>
				<td>#{event_name_str} #{operator_name} <em>#{arguments_name}</em></td>
				<td>
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-condition-edit"><?= _('Edit') ?></button>
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-condition-remove"><?= _('Remove') ?></button>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][type]" type="hidden" value="#{type}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][operator]" type="hidden" value="#{operator}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][severity]" type="hidden" value="#{severity}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][event_name]" type="hidden" value="#{event_name}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][tag]" type="hidden" value="#{tag}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][tag_value]" type="hidden" value="#{tag_value}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][host_name]" type="hidden" value="#{host_name}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][host_group]" type="hidden" value="#{host_group}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][time_period]" type="hidden" value="#{time_period}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][formulaid]" type="hidden" value="#{formulaid}"/>
				</td>
			</tr>
		`);

		this.#operation_row_template = new Template(`
			<tr class="form_row" data-step="#{step}">
				<td class="td-drag-icon">
					<div class="drag-icon"></div>
					<span class="list-numbered-item">:</span>
				</td>
				<td><?= _('Execute when') ?> #{execute_when_str} : #{label_str}<em> #{arguments_str}</em></td>
				<td>
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-operation-edit"><?= _('Edit') ?></button>
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-operation-remove"><?= _('Remove') ?></button>

					#{*tags_input_html}

					<input data-field-type="hidden" name="operations[#{step}][step]" type="hidden" value="#{step}"/>
					<input data-field-type="hidden" name="operations[#{step}][execute_when]" type="hidden" value="#{execute_when}"/>
					<input data-field-type="hidden" name="operations[#{step}][event_type]" type="hidden" value="#{event_type}"/>
					<input data-field-type="hidden" name="operations[#{step}][eviction_cause]" type="hidden" value="#{eviction_cause}"/>
					<input data-field-type="hidden" name="operations[#{step}][type]" type="hidden" value="#{type}"/>
					<input data-field-type="hidden" name="operations[#{step}][evaltype]" type="hidden" value="#{evaltype}"/>
					<input data-field-type="hidden" name="operations[#{step}][event_name]" type="hidden" value="#{event_name}"/>
					<input data-field-type="hidden" name="operations[#{step}][tag]" type="hidden" value="#{tag}"/>
					<input data-field-type="hidden" name="operations[#{step}][new_tag]" type="hidden" value="#{new_tag_name}"/>
					<input data-field-type="hidden" name="operations[#{step}][tag_value]" type="hidden" value="#{tag_value}"/>
					<input data-field-type="hidden" name="operations[#{step}][severity]" type="hidden" value="#{severity}"/>
				</td>
			</tr>
		`);
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
				const formulaid = e.target.closest('tr').dataset.formulaid;
				const conditions = this.form.findFieldByName('filter[conditions]').getValue();

				this.#openConditionPopup(conditions[formulaid], e.target);
			}
			else if (e.target.classList.contains('js-condition-remove')) {
				e.target.closest('tr').remove();
				this.form.discoverAllFields();

				if (this.form.findFieldByName('filter[conditions]').getValue() === undefined) {
					this.#condition_row_index = 0;
				}

				this.form_element.dispatchEvent(new Event('filter.change'));
			}
			else if (e.target.classList.contains('js-historical-condition-add')) {
				this.#openHistoricalConditionPopup();
			}
			else if (e.target.classList.contains('js-historical-condition-edit')) {
				this.#openHistoricalConditionPopup({});
			}
			else if (e.target.classList.contains('js-historical-condition-remove')) {
				e.target.closest('tr').remove();
				this.form.discoverAllFields();
				this.form_element.dispatchEvent(new Event('historical.filter.change'));
			}
			else if (e.target.classList.contains('js-operation-add')) {
				this.#openOperationPopup(undefined, e.target);
			}
			else if (e.target.classList.contains('js-operation-edit')) {
				const {
					[e.target.closest('[data-step]').getAttribute('data-step')]: operation
				} = this.form.findFieldByName('operations').getValue();
				this.#openOperationPopup(operation, e.target);
			}
			else if (e.target.classList.contains('js-operation-remove')) {
				e.target.closest('tr').remove();
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
		// this.form_element.addEventListener('historical.conditions.change', () => this.#handleHistoricalConditionsChanged());

		new CSortable(window['ceprule-operations-table'].querySelector('tbody'), {selector_handle: 'div.drag-icon'});

		// Confirm / cancel dialog.
		this.#overlay.$dialogue.$footer.get(0).addEventListener('click', (e) => {
			const class_list = e.target.classList;

			if (class_list.contains('js-submit')) {
				this.#submit();
			}
			else if (class_list.contains('js-delete')) {
				console.log('TODO: js-delete');
			}
			else if (class_list.contains('js-clone')) {
				this.#clone();
			}
		});
	}

	#clone() {
		this.form.findFieldByName('cepruleid')._field.remove();

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
	}

	#submit() {
		// clearMessages();
		const fields = this.form.getAllValues();

		// Correct the sortorder.
		const operations = {};
		[...window['ceprule-operations-table'].querySelectorAll('[data-step]')]
			.map((row, index) => {
				operations[index + 1] = {...fields.operations[row.dataset.step], step: index + 1};
			});

		fields.operations = operations;

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.#unsetLoadingStatus();
					return;
				}

				const action = document.getElementById('roleid') !== null
					? 'ceprule.update'
					: 'ceprule.create';

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

							location.href = new URL(response.success.redirect, location.href).href;
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
		const evaltype_select = window['cep-filter-evaltype'];
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

		window['cep-filter-expression'].style.display = is_expression_evaltype ? '' : 'none';
		window['cep-filter-expression-preview'].style.display = !is_expression_evaltype ? '' : 'none';

		const identifiers = Object.values(conditions).map(condition => ({id: condition.formulaid}));

		window['cep-filter-expression-preview'].innerText = getConditionFormula(identifiers, evaltype);
	}

	#handleHistoricalConditionsChanged() {
		console.warn('handleHistoricalConditionsChanged');
	}

	#openConditionPopup(condition, trigger_element) {
		const is_new = condition === undefined;

		if (is_new) {
			condition = {
				formulaid: String.fromCharCode(65 + this.#condition_row_index++),
				type: '<?= ZBX_CEP_CONDITION_EVENT_NAME ?>',
				operator: '<?= CONDITION_OPERATOR_EQUAL ?>',
				host_group: '',
				host_name: '',
				severity: '<?= TRIGGER_SEVERITY_INFORMATION ?>',
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
					title: is_new ? t('Add') : t('Edit'),
					action: (overlay) => {
						const form = ceprule_condition_edit_popup.form;
						const fields = form.getAllValues();

						form.validateSubmit(fields)
							.then((result) => {
								if (!result) {
									overlay.unsetLoading();
									return;
								}

								overlayDialogueDestroy(overlay.dialogueid);

								is_new && this.#addConditionRow(fields) || this.#editConditionRow(fields);

								this.form.discoverAllFields();
								this.form_element.dispatchEvent(new Event('filter.change'));
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
			dialogueid: 'ceprule.condition.edit',
			trigger_element
		});

		ceprule_condition_edit_popup.init({rules: this.#condition_rules, condition, overlay});
	}

	#openHistoricalConditionPopup(historical_condition) {
		console.warn('openHistoricalConditionPopup', historical_condition);
	}

	#openOperationPopup(operation, trigger_element) {
		const is_new = operation === undefined;

		if (is_new) {
			operation = {
				step: 1 + Math.max(0, ...Object.keys(this.form.findFieldByName('operations').getValue())),
				event_type: '<?= ZBX_CEP_OP_SET_NAME ?>',
				evaltype: '<?= CONDITION_EVAL_TYPE_AND_OR ?>',
				event_name: '',
				eviction_cause: '<?= ZBX_CEP_EXECUTE_EVENT_TYPE_ANY ?>',
				execute_when: '<?= ZBX_CEP_OP_WHEN_EVENT_OCCURRED ?>',
				new_tag: '',
				severity: '<?= TRIGGER_SEVERITY_NOT_CLASSIFIED ?>',
				tag: '',
				tag_value: '',
				tags: [{tag: '', operator: <?= TAG_OPERATOR_EQUAL ?>, value: ''}]
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
					title: is_new ? t('Add') : t('Edit'),
					action: (overlay) => {
						const form = ceprule_operation_edit_popup.form;
						const fields = form.getAllValues();

						for (const tag_index in fields.tags) {
							const {tag, value} = fields.tags[tag_index];

							if (tag === '' && value === '') {
								delete fields.tags[tag_index];
							}
						}

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
			window_type: this.form.findFieldByName('window_type').getValue()
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
		console.warn('todo unsetLoadingStatus');
	}

	#editOperationRow(operation) {
		this.form_element.querySelector(`#ceprule-operations-table [data-step="${operation.step}"]`)
			.replaceWith(this.#buildOperationRow(operation));
	}

	#addOperationRow(operation) {
		this.form_element.querySelector('#ceprule-operations-table tbody')
			.insertAdjacentElement('beforeend', this.#buildOperationRow(operation));
	}

	#buildOperationRow(operation) {
		const execute_when_str = JSON.parse('<?= json_encode(
			CCepRuleHelper::getOperationExecuteWhenStrings()
		) ?>')[operation.execute_when];

		const label_str = JSON.parse('<?= json_encode(
			CCepRuleHelper::getOperationLabelStrings()
		) ?>')[operation.type];

		const severity_names_json = '<?= json_encode([
			TRIGGER_SEVERITY_NOT_CLASSIFIED => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_0)),
			TRIGGER_SEVERITY_INFORMATION => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_1)),
			TRIGGER_SEVERITY_WARNING => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_2)),
			TRIGGER_SEVERITY_AVERAGE => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_3)),
			TRIGGER_SEVERITY_HIGH => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_4)),
			TRIGGER_SEVERITY_DISASTER => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_5)),
		]) ?>';
		const severity_names = JSON.parse(severity_names_json);

		let arguments_str;
		if ([
			<?= ZBX_CEP_OP_INCREASE_SEVERITY ?>,
			<?= ZBX_CEP_OP_DECREASE_SEVERITY ?>,
			<?= ZBX_CEP_OP_SUPPRESS ?>,
			<?= ZBX_CEP_OP_COPY_FIRST ?>,
			<?= ZBX_CEP_OP_COPY_LAST ?>,
			<?= ZBX_CEP_OP_DISCARD ?>,
			<?= ZBX_CEP_OP_CLOSE ?>
		].includes(operation.type)) {
			arguments_str = '';
		}
		else if ([
			<?= ZBX_CEP_OP_INCREASE_TAG_VALUE ?>,
			<?= ZBX_CEP_OP_DECREASE_TAG_VALUE ?>,
			<?= ZBX_CEP_OP_REMOVE_TAG ?>
		].includes(operation.type)) {
			arguments_str = operation.tag;
		}
		else if (operation.type == <?= ZBX_CEP_OP_SET_NAME ?>) {
			arguments_str = operation.event_name;
		}
		else if (operation.type == <?= ZBX_CEP_OP_SET_SEVERITY ?>) {
			arguments_str = severity_names[operation.severity];
		}
		else if (operation.type == <?= ZBX_CEP_OP_RENAME_TAG ?>) {
			arguments_str = `${operation.tag}:${operation.new_tag}`;
		}
		else if ([
			<?= ZBX_CEP_OP_SET_TAG_VALUE ?>,
			<?= ZBX_CEP_OP_SET_TAG ?>,
			<?= ZBX_CEP_OP_ADD_TAG ?>
		].includes(operation.type)) {
			arguments_str = `${operation.tag}:${operation.tag_value}`;
		}

		const tags_input_html = Object.values(operation.tags).map((tag, tag_index) => (new Template(`
			<input data-field-type="hidden" name="operations[${operation.step}][tags][${tag_index}][tag]"
				type="hidden" value="#{tag}"/>
			<input data-field-type="hidden" name="operations[${operation.step}][tags][${tag_index}][operator]"
				type="hidden" value="#{operator}"/>
			<input data-field-type="hidden" name="operations[${operation.step}][tags][${tag_index}][value]"
				type="hidden" value="#{value}"/>
		`)).evaluate(tag)).join('');

		return this.#operation_row_template
			.evaluateToElement({execute_when_str, label_str, arguments_str, tags_input_html, ...operation});
	}

	#editConditionRow(condition) {
		this.form_element.querySelector(`#cep-filter-table [data-formulaid=${condition.formulaid}]`)
			.replaceWith(this.#buildConditionRow(condition));
	}

	#addConditionRow(condition) {
		this.form_element.querySelector('#cep-filter-table tbody')
			.insertAdjacentElement('beforeend', this.#buildConditionRow(condition));
	}

	#buildConditionRow(condition) {
		const event_name_str = JSON.parse('<?= json_encode([
			ZBX_CEP_CONDITION_EVENT_NAME => _('Event name'),
			ZBX_CEP_CONDITION_TAG_NAME => _('Tag name'),
			ZBX_CEP_CONDITION_TAG_VALUE => _('Tag value'),
			ZBX_CEP_CONDITION_SEVERITY => _('Severity'),
			ZBX_CEP_CONDITION_HOST => _('Host'),
			ZBX_CEP_CONDITION_HOST_GROUP => _('Host group'),
			ZBX_CEP_CONDITION_TIME_PERIOD => _('Time period')
		]) ?>')[condition.type];

		const operator_names_json = '<?= json_encode([
			CONDITION_OPERATOR_IN => _('In'),
			CONDITION_OPERATOR_NOT_IN => _('Not in'),
			CONDITION_OPERATOR_EQUAL => _('Equals'),
			CONDITION_OPERATOR_NOT_EQUAL => _('Does not equal'),
			CONDITION_OPERATOR_LIKE => _('Contains'),
			CONDITION_OPERATOR_NOT_LIKE => _('Does not contain'),
			CONDITION_OPERATOR_MORE_EQUAL => _('Is more than or equal'),
			CONDITION_OPERATOR_LESS_EQUAL => _('Is less than or equal'),
			CONDITION_OPERATOR_EXISTS => _('Exists'),
			CONDITION_OPERATOR_NOT_EXISTS => _('Does not exist')
		]) ?>';

		const severity_names_json = '<?= json_encode([
			TRIGGER_SEVERITY_NOT_CLASSIFIED => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_0)),
			TRIGGER_SEVERITY_INFORMATION => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_1)),
			TRIGGER_SEVERITY_WARNING => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_2)),
			TRIGGER_SEVERITY_AVERAGE => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_3)),
			TRIGGER_SEVERITY_HIGH => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_4)),
			TRIGGER_SEVERITY_DISASTER => _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_5)),
		]) ?>';
		const severity_names = JSON.parse(severity_names_json);

		const operator_name = JSON.parse(operator_names_json)[condition.operator];
		const arguments_name = (function condition_arguments(condition) {
			if (condition.type == <?= ZBX_CEP_CONDITION_EVENT_NAME ?>) {
				return condition.event_name;
			}

			if (condition.type == <?= ZBX_CEP_CONDITION_TAG_NAME ?>) {
				return condition.tag;
			}

			if (condition.type == <?= ZBX_CEP_CONDITION_TAG_VALUE ?>) {
				return condition.tag_value;
			}

			if (condition.type == <?= ZBX_CEP_CONDITION_SEVERITY ?>) {
				return severity_names[condition.severity];
			}

			if (condition.type == <?= ZBX_CEP_CONDITION_HOST ?>) {
				return condition.host_name;
			}

			if (condition.type == <?= ZBX_CEP_CONDITION_HOST_GROUP ?>) {
				return condition.host_group;
			}

			if (condition.type == <?= ZBX_CEP_CONDITION_TIME_PERIOD ?>) {
				return condition.time_period;
			}
		})(condition);

		return this.#condition_row_template
			.evaluateToElement({arguments_name, operator_name, event_name_str, ...condition});
	}
};
