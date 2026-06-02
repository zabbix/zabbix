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

	/** @type {Object} */
	#window_condition_rules;

	/** @type {Template} */
	#condition_row_template;

	/** @type {Template} */
	#window_condition_row_template;

	/** @type {Number} */
	#condition_row_index = 0;

	/** @type {Number} */
	#window_condition_row_index = 0;

	/** @type {Template} */
	#operation_row_template;

	/** @type {Object} */
	#operation_rules;

	/** @type {Object} */
	#rules_for_clone;

	init({rules, rules_for_clone, operation_rules, condition_rules, window_condition_rules, ceprule}) {
		this.#rules_for_clone = rules_for_clone;
		this.#initTemplates();
		this.#condition_rules = condition_rules;
		this.#window_condition_rules = window_condition_rules;
		this.#operation_rules = operation_rules;
		this.#overlay = overlays_stack.getById('ceprule.edit');
		this.form_element = this.#overlay.$dialogue.$body[0].querySelector('form');

		for (const condition of Object.values(ceprule.filter.conditions)) {
			const formulaid = this.#indexToFormulaId(this.#condition_row_index++);

			this.#addConditionRow({...condition, formulaid});
		}

		for (const window_condition of Object.values(ceprule.window.filter.conditions ?? {})) {
			const formulaid = this.#indexToFormulaId(this.#window_condition_row_index++);

			this.#addWindowConditionRow({...window_condition, formulaid});
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
		this.#handleFilterChanged();
		this.#handleWindowConditionsChanged();
		window['ceprule-window-counttag-toggle'].dispatchEvent(new Event('change'));
		window['ceprule-window-capacity-toggle'].dispatchEvent(new Event('change'));
		window['ceprule-window-groupby-opt-tag'].dispatchEvent(new Event('change'));

		this.#handleWindowTypeChanged();

		this.#initial_form_fields = this.form.getAllValues(); // TODO: use at on-before page unload confirmation
		console.log([ceprule, '===', this.#initial_form_fields]);

		this.form_element.style.display = '';
	}

	/**
	 * Formula ID from large number.
	 */
	#indexToFormulaId(index) {
		let formulaid = '';

		for (index++; index; index = Math.floor(index / 26)) {
			formulaid = String.fromCharCode(65 + --index % 26) + formulaid
		};

		return formulaid;
	}

	#initTemplates() {
		this.#window_condition_row_template = new Template(`
			<tr data-formulaid="#{formulaid}">
				<td>#{formulaid}</td>
				<td>
					<div class="text">#{type_str} <em>#{arg1}</em>#{operator_name} <em>#{arg2}</em></div>
				</td>
				<td>
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-window-condition-edit"><?= _('Edit') ?></button>
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-window-condition-remove"><?= _('Remove') ?></button>
					<input type="hidden" data-field-type="hidden" name="window[filter][conditions][#{formulaid}][type]" type="hidden" value="#{type}"/>
					<input type="hidden" data-field-type="hidden" name="window[filter][conditions][#{formulaid}][operator]" type="hidden" value="#{operator}"/>
					<input type="hidden" data-field-type="hidden" name="window[filter][conditions][#{formulaid}][past_tag]" type="hidden" value="#{past_tag}"/>
					<input type="hidden" data-field-type="hidden" name="window[filter][conditions][#{formulaid}][tag]" type="hidden" value="#{tag}"/>
					<input type="hidden" data-field-type="hidden" name="window[filter][conditions][#{formulaid}][tag_value]" type="hidden" value="#{tag_value}"/>
					<input type="hidden" data-field-type="hidden" name="window[filter][conditions][#{formulaid}][formulaid]" type="hidden" value="#{formulaid}"/>
				</td>
			</tr>
		`);

		this.#condition_row_template = new Template(`
			<tr data-formulaid="#{formulaid}">
				<td>#{formulaid}</td>
				<td>
					<div class="text">#{event_name_str} #{operator_name} <em>#{arguments_name}</em></div>
				</td>
				<td>
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-condition-edit"><?= _('Edit') ?></button>
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-condition-remove"><?= _('Remove') ?></button>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][type]" type="hidden" value="#{type}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][operator]" type="hidden" value="#{operator}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][severity]" type="hidden" value="#{severity}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][event_name]" type="hidden" value="#{event_name}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][tag]" type="hidden" value="#{tag}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][tag_value]" type="hidden" value="#{tag_value}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][host]" type="hidden" value="#{host}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][host_group]" type="hidden" value="#{host_group}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][time_period]" type="hidden" value="#{time_period}"/>
					<input type="hidden" data-field-type="hidden" name="filter[conditions][#{formulaid}][formulaid]" type="hidden" value="#{formulaid}"/>
				</td>
			</tr>
		`);

		this.#operation_row_template = new Template(`
			<tr data-step="#{step}">
				<td class="td-drag-icon">
					<div class="drag-icon"></div>
					<span class="list-numbered-item">:</span>
				</td>
				<td>
					<div class="text"><?= _('Execute when') ?> #{execute_when_str} : #{label_str}<em> #{arguments_str}</em></div>
				</td>
				<td>
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-operation-edit"><?= _('Edit') ?></button>
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-operation-remove"><?= _('Remove') ?></button>

					#{*tags_input_html}

					<input data-field-type="hidden" name="operations[#{step}][step]" type="hidden" value="#{step}"/>
					<input data-field-type="hidden" name="operations[#{step}][execute_when]" type="hidden" value="#{execute_when}"/>
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
			else if (e.target.classList.contains('js-window-condition-add')) {
				this.#openWindowConditionPopup(undefined, e.target);
			}
			else if (e.target.classList.contains('js-window-condition-edit')) {
				const formulaid = e.target.closest('tr').dataset.formulaid;
				const window_conditions = this.form.findFieldByName('window[filter][conditions]').getValue();

				this.#openWindowConditionPopup(window_conditions[formulaid], e.target);
			}
			else if (e.target.classList.contains('js-window-condition-remove')) {
				e.target.closest('tr').remove();
				this.form.discoverAllFields();

				if (this.form.findFieldByName('window[filter][conditions]').getValue() === undefined) {
					this.#window_condition_row_index = 0;
				}

				this.form_element.dispatchEvent(new Event('window.filter.change'));
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
			else if (e.target.name === 'window[filter][evaltype]') {
				this.form_element.dispatchEvent(new Event('window.filter.change'));
			}
		});

		// Add proxied form event handlers.
		this.form_element.addEventListener('filter.change', () => this.#handleFilterChanged());
		this.form_element.addEventListener('window.filter.change', () => this.#handleWindowConditionsChanged());

		window['ceprule-window-counttag-toggle'].addEventListener('change', (e) => {
			const enabled = window['ceprule-window-counttag-toggle'].querySelector('[value="1"]').checked;
			window['ceprule-window-counttag'].style.display = enabled ? '' : 'none';
		});

		window['ceprule-window-capacity-toggle'].addEventListener('change', (e) => {
			const enabled = window['ceprule-window-capacity-toggle'].querySelector('[value="1"]').checked;
			window['ceprule-window-capacity'].style.display = enabled ? '' : 'none';
		});

		window['ceprule-window-groupby-opt-tag'].addEventListener('change', (e) => {
			window['ceprule-window-groupby-tag'].style.display = e.target.checked ? '' : 'none';
		});

		window['ceprule-window-type'].addEventListener('change', () => this.#handleWindowTypeChanged());

		new CSortable(window['ceprule-operations-table'].querySelector('tbody'), {selector_handle: 'div.drag-icon'});

		// Confirm / cancel dialog.
		this.#overlay.$dialogue.$footer.get(0).addEventListener('click', (e) => {
			const class_list = e.target.classList;

			if (class_list.contains('js-submit')) {
				if (class_list.contains('js-submit-force')) {
					const $target = jQuery(e.target);
					const item = {
						label: <?= json_encode(_('Force update')) ?>,
						clickCallback: () => this.#submit(true)
					};
					const options = {
						position: {at: 'left bottom', my: 'left top', of: $target},
						closeCallback: () => { $target.focus(); }
					};

					$target.menuPopup([{items: [item]}], jQuery(e), options);
				}
				else {
					this.#submit(false);
				}
			}
			else if (class_list.contains('js-delete')) {
				window.confirm(<?= json_encode('Delete	complex event processing rule?') ?>) && this.#delete();
			}
			else if (class_list.contains('js-clone')) {
				this.#clone();
			}
		});
	}

	#handleWindowTypeChanged() {
		const input = [...window['ceprule-window-type'].querySelectorAll('[name="window_type"]')]
			.find(node => node.checked);
		const type = Number(input.value);

		window['ceprule-operations-label']
			.classList.toggle('form-label-asterisk', type != <?= CCepRuleHelper::WINDOW_CAUSE_SYMPTOM ?>);

		{
			const form_field = window['ceprule-script'].closest('.form-field');
			const display = type == <?= CCepRuleHelper::WINDOW_PATTERN_MATCH ?> ? '' : 'none';

			form_field.style.display = display;
			form_field.previousSibling.style.display = display;
		}
		{
			const form_field = window['ceprule-window-filter-evaltype'].closest('.form-field');
			const display = type == <?= CCepRuleHelper::WINDOW_TAG_MATCH ?> ? '' : 'none';

			form_field.style.display = display;
			form_field.previousSibling.style.display = display;
		}
		{
			const form_field = window['ceprule-window-condition-table'].closest('.form-field');
			const display = type == <?= CCepRuleHelper::WINDOW_TAG_MATCH ?> ? '' : 'none';

			form_field.style.display = display;
			form_field.previousSibling.style.display = display;

			type == <?= CCepRuleHelper::WINDOW_TAG_MATCH ?>
				&& this.form_element.dispatchEvent(new Event('window.filter.change'));
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
	}

	#delete() {
		this.#removePopupMessages();
		fetch(zabbixUrl({action: 'ceprule.delete'}), {
			method: 'POST',
			headers: {'Content-Type': 'application/json; charset=UTF-8'},
			body: JSON.stringify({
				cepruleids: [this.form.findFieldByName('cepruleid').getValue()],
				[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('ceprule.edit')) ?>
			})
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.#overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
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

	#submit(force_sumbit) {
		const fields = this.form.getAllValues();
		fields[CSRF_TOKEN_NAME] = <?= json_encode(CCsrfTokenHelper::get('ceprule.edit')) ?>;

		// Correct the sortorder.
		const operations = {};
		[...window['ceprule-operations-table'].querySelectorAll('[data-step]')]
			.map((row, index) => {
				operations[index + 1] = {...fields.operations[row.dataset.step], step: index + 1};
			});

		fields.operations = operations;

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
		const evaltype_select = window['ceprule-filter-evaltype']; // TODO fix IDs to static string cep -> ceprule ..
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

	/**
	 * Method ensures filter view is correct with data:
	 *	- window conditions formula preview string.
	 *	- type of calculation row.
	 */
	#handleWindowConditionsChanged() {
		const evaltype_select = window['ceprule-window-filter-evaltype'];
		const evaltype_field = evaltype_select.closest('.form-field');

		const conditions = Object.values(this.form.findFieldByName('window[filter][conditions]').getValue() ?? {});

		if (conditions.length < 2) {
			evaltype_field.style.display = 'none';
			evaltype_field.previousElementSibling.style.display = 'none';

			return;
		}

		evaltype_field.style.display = '';
		evaltype_field.previousElementSibling.style.display = '';

		const evaltype = Number(evaltype_select.value);
		const is_expression_evaltype = evaltype == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>;

		window['ceprule-window-filter-expression'].style.display = is_expression_evaltype ? '' : 'none';
		window['ceprule-window-filter-expression-preview'].style.display = !is_expression_evaltype ? '' : 'none';

		const identifiers = Object.values(conditions).map(condition => ({id: condition.formulaid}));

		window['ceprule-window-filter-expression-preview'].innerText = getConditionFormula(identifiers, evaltype);
	}

	#openConditionPopup(condition, trigger_element) {
		const is_new = condition === undefined;

		if (is_new) {
			condition = {
				formulaid: this.#indexToFormulaId(this.#condition_row_index++),
				type: '<?= CCepRuleHelper::CONDITION_EVENT_NAME ?>',
				operator: '<?= CONDITION_OPERATOR_EQUAL ?>',
				host_group: '',
				host: '',
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

	#openWindowConditionPopup(window_condition, trigger_element) {
		const is_new = window_condition === undefined;

		if (is_new) {
			window_condition = {
				formulaid: this.#indexToFormulaId(this.#window_condition_row_index++),
				type: '<?= CCepRuleHelper::CONDITION_EVENT_NAME ?>',
				type: '<?= CCepRuleHelper::WINDOW_CONDITION_TAG_PAIR ?>',
				past_tag: '',
				operator: '<?= CONDITION_OPERATOR_EQUAL ?>',
				tag: '',
				tag_value: ''
			};
		}

		const template = document.getElementById('ceprule-window-condition-modal-template');
		const form_element = template.content.querySelector('form').cloneNode(true);

		const overlay = overlayDialogue({
			class: 'modal-popup modal-popup-medium',
			title: t('Historical condition details'),
			content: form_element,
			buttons: [
				{
					title: is_new ? t('Add') : t('Edit'),
					action: (overlay) => {
						const form = ceprule_window_condition_edit_popup.form;
						const fields = form.getAllValues();

						form.validateSubmit(fields)
							.then((result) => {
								if (!result) {
									overlay.unsetLoading();
									return;
								}

								overlayDialogueDestroy(overlay.dialogueid);

								is_new && this.#addWindowConditionRow(fields) || this.#editWindowConditionRow(fields);

								this.form.discoverAllFields();
								this.form_element.dispatchEvent(new Event('window.filter.change'));
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
			dialogueid: 'ceprule.window.condition.edit',
			trigger_element
		});

		ceprule_window_condition_edit_popup.init({rules: this.#window_condition_rules, window_condition, overlay});

		console.warn('openWindowConditionPopup', window_condition);
	}

	#openOperationPopup(operation, trigger_element) {
		const is_new = operation === undefined;

		if (is_new) {
			operation = {
				step: 1 + Math.max(0, ...Object.keys(this.form.findFieldByName('operations').getValue())),
				evaltype: '<?= CONDITION_EVAL_TYPE_AND_OR ?>',
				event_name: '',
				execute_when: '<?= CCepRuleHelper::OP_WHEN_EVENT_OCCURRED ?>',
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
		this.#overlay.unsetLoading();
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
			<?= CCepRuleHelper::OP_INCREASE_SEVERITY ?>,
			<?= CCepRuleHelper::OP_DECREASE_SEVERITY ?>,
			<?= CCepRuleHelper::OP_SUPPRESS ?>,
			<?= CCepRuleHelper::OP_COPY_FIRST ?>,
			<?= CCepRuleHelper::OP_COPY_LAST ?>,
			<?= CCepRuleHelper::OP_DISCARD ?>,
			<?= CCepRuleHelper::OP_CLOSE ?>
		].includes(operation.type)) {
			arguments_str = '';
		}
		else if ([
			<?= CCepRuleHelper::OP_INCREASE_TAG_VALUE ?>,
			<?= CCepRuleHelper::OP_DECREASE_TAG_VALUE ?>,
			<?= CCepRuleHelper::OP_REMOVE_TAG ?>
		].includes(operation.type)) {
			arguments_str = operation.tag;
		}
		else if (operation.type == <?= CCepRuleHelper::OP_SET_NAME ?>) {
			arguments_str = operation.event_name;
		}
		else if (operation.type == <?= CCepRuleHelper::OP_SET_SEVERITY ?>) {
			arguments_str = severity_names[operation.severity];
		}
		else if (operation.type == <?= CCepRuleHelper::OP_RENAME_TAG ?>) {
			arguments_str = `${operation.tag}:${operation.new_tag}`;
		}
		else if ([
			<?= CCepRuleHelper::OP_SET_TAG_VALUE ?>,
			<?= CCepRuleHelper::OP_SET_TAG ?>,
			<?= CCepRuleHelper::OP_ADD_TAG ?>
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
		window['ceprule-filter-conditions'].querySelector(`[data-formulaid=${condition.formulaid}]`)
			.replaceWith(this.#buildConditionRow(condition));
	}

	#addConditionRow(condition) {
		window['ceprule-filter-conditions'].querySelector('tbody')
			.insertAdjacentElement('beforeend', this.#buildConditionRow(condition));
	}

	#buildConditionRow(condition) {
		const event_name_str = JSON.parse('<?= json_encode(
			CCepRuleHelper::getConditionLabelStrings()
		) ?>')[condition.type];
		const operator_names = JSON.parse('<?= json_encode(
			CCepRuleHelper::getConditionOperatorStrings()
		) ?>');
		const severity_names = JSON.parse('<?= json_encode(
			array_column(CSeverityHelper::getSeverities(), 'label', 'value')
		) ?>');

		const operator_name = operator_names[condition.operator];
		const arguments_name = (function condition_arguments(condition) {
			if (condition.type == <?= CCepRuleHelper::CONDITION_EVENT_NAME ?>) {
				return condition.event_name;
			}

			if (condition.type == <?= CCepRuleHelper::CONDITION_TAG_NAME ?>) {
				return condition.tag;
			}

			if (condition.type == <?= CCepRuleHelper::CONDITION_TAG_VALUE ?>) {
				return condition.tag_value;
			}

			if (condition.type == <?= CCepRuleHelper::CONDITION_SEVERITY ?>) {
				return severity_names[condition.severity];
			}

			if (condition.type == <?= CCepRuleHelper::CONDITION_HOST ?>) {
				return condition.host;
			}

			if (condition.type == <?= CCepRuleHelper::CONDITION_HOST_GROUP ?>) {
				return condition.host_group;
			}

			if (condition.type == <?= CCepRuleHelper::CONDITION_TIME_PERIOD ?>) {
				return condition.time_period;
			}
		})(condition);

		return this.#condition_row_template
			.evaluateToElement({arguments_name, operator_name, event_name_str, ...condition});
	}

	#editWindowConditionRow(window_condition) {
		window['ceprule-window-condition-table'].querySelector(`[data-formulaid=${window_condition.formulaid}]`)
			.replaceWith(this.#buildWindowConditionRow(window_condition));
	}

	#addWindowConditionRow(window_condition) {
		window['ceprule-window-condition-table'].querySelector('tbody')
			.insertAdjacentElement('beforeend', this.#buildWindowConditionRow(window_condition));
	}

	#buildWindowConditionRow(window_condition) {
		const type_str = JSON.parse('<?=
			json_encode(CCepRuleHelper::getWindowConditionLabelStrings())
		?>')[window_condition.type];

		const operator_name = JSON.parse('<?= json_encode([
			CONDITION_OPERATOR_EQUAL => _('Equals'),
			CONDITION_OPERATOR_NOT_EQUAL => _('Does not equal')
		]) ?>')[window_condition.operator];

		let arg1 = '';
		let arg2 = '';

		if (window_condition.type == <?= CCepRuleHelper::WINDOW_CONDITION_TAG_PAIR ?>) {
			arg1 = window_condition.past_tag;
			arg2 = window_condition.tag;
		}
		else if (window_condition.type == <?= CCepRuleHelper::WINDOW_CONDITION_OLD_TAG ?>) {
			arg2 = window_condition.tag;
		}
		else if (window_condition.type == <?= CCepRuleHelper::WINDOW_CONDITION_OLD_TAG_VALUE ?>) {
			arg1 = window_condition.tag;
			arg2 = window_condition.tag_value;
		}

		return this.#window_condition_row_template
			.evaluateToElement({arg1: `${arg1} `, arg2, operator_name, type_str, ...window_condition});
	}

	#removePopupMessages() {
		for (const el of this.form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	}
};
