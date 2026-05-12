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

	init({rules, condition_rules, ceprule}) {
		this.#initTemplates();
		this.#condition_rules = condition_rules;
		this.#overlay = overlays_stack.getById('ceprule.edit');
		this.form_element = this.#overlay.$dialogue.$body[0].querySelector('form');

		for (const condition of Object.values(ceprule.filter.conditions || [])) {
			this.#addConditionRow({...condition, formulaid: String.fromCharCode(65 + this.#condition_row_index++)});
		}

		this.#initActions();
		this.form = new CForm(this.form_element, rules);
		this.#handleFilterChanged();

		// for (const operation of Object.values(ceprule.operations || [])) {
		// 	this.#addOperationRow(operation);
		// }

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
	}

	#initActions() {
		const return_url = new URL('zabbix.php', location.href);

		return_url.searchParams.set('action', 'ceprule.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		// Primitive event handlers.
		this.form_element.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-condition-add')) {
				this.#openConditionPopup();
			}
			else if (e.target.classList.contains('js-condition-edit')) {
				const formulaid = e.target.closest('tr').dataset.formulaid;
				const conditions = this.form.findFieldByName('filter').getValue().conditions;

				this.#openConditionPopup(conditions[formulaid]);
			}
			else if (e.target.classList.contains('js-condition-remove')) {
				e.target.closest('tr').remove();
				this.form.discoverAllFields();

				if (this.form.findFieldByName('filter').getValue().conditions === undefined) {
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
				this.#openOperationPopup();
			}
			else if (e.target.classList.contains('js-operation-edit')) {
				this.#openOperationPopup({});
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

		this.#overlay.$dialogue.$footer.get(0).addEventListener('click', (e) => {
			const class_list = e.target.classList;

			if (class_list.contains('js-submit')) {
				this.#submit();
			}
			else if (class_list.contains('js-delete')) {
				console.log('TODO: js-delete');
			}
			else if (class_list.contains('js-clone')) {
				console.log('TODO: js-clone');
			}
		});
	}

	#submit() {
		// clearMessages();
		const fields = this.form.getAllValues();

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
		const conditions = Object.values(this.form.findFieldByName('filter').getValue().conditions ?? {});

		if (conditions.length == 0) {
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

	#openConditionPopup(condition) {
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

		const template = document.getElementById('cep-filter-condition-modal-template');
		const form_element = template.content.querySelector('form').cloneNode(true);

		const overlay = overlayDialogue({
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
			is_modal: true
		});

		ceprule_condition_edit_popup.init({rules: this.#condition_rules, condition, overlay});
	}

	#openHistoricalConditionPopup(historical_condition) {
		console.warn('openHistoricalConditionPopup', historical_condition);
	}

	#openOperationPopup(condition) {
		console.warn('openOperationPopup', condition);
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
