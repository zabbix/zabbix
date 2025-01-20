<?php
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


/**
 * @var CView $this
 */

?>
window.trigger_edit_popup = new class {

	init({triggerid, expression_popup_parameters, recovery_popup_parameters, readonly, dependencies, action,
			context, db_trigger, backurl, overlayid, parent_discoveryid
	}) {
		this.triggerid = triggerid;
		this.expression_popup_parameters = expression_popup_parameters;
		this.recovery_popup_parameters = recovery_popup_parameters;
		this.readonly = readonly;
		this.dependencies = dependencies;
		this.action = action;
		this.context = context;
		this.db_trigger = db_trigger;
		this.overlay = overlays_stack.getById(overlayid);
		this.overlay.backurl = backurl;
		this.parent_discoveryid = parent_discoveryid;
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.expression = this.form.querySelector('#expression');
		this.expr_temp = this.form.querySelector('#expr_temp');
		this.name = this.form.querySelector('#name');
		this.recovery_expression = this.form.querySelector('#recovery_expression');
		this.recovery_expr_temp = this.form.querySelector('#recovery_expr_temp');
		this.expression_constructor_active = false;
		this.recovery_expression_constructor_active = false;
		this.selected_dependencies = [];

		window.addPopupValues = (data) => {
			this.addPopupValues(data.values);
		}

		this.form.style.display = '';

		this.#initActions();
		this.#initTriggersTab();
		this.#changeRecoveryMode();
		this.#changeCorrelationMode();

		if (this.dependencies) {
			this.#loadDependencyTable(this.dependencies);
		}

		this.overlay.recoverFocus();
	}

	#initActions() {
		['input', 'keydown', 'paste'].forEach((event_type) => {
			this.name.addEventListener(event_type,
				(e) => {
					this.form.querySelector('#event_name').placeholder = e.target.value;
					$(this.form.querySelector('#event_name')).textareaFlexible('updateHeight');
				}
			);
			this.name.dispatchEvent(new Event('input'));
		});

		// Form submit on Enter for event_name field, because textareaflexible.js triggers JQuery event.
		this.form.querySelector('[name="event_name"]').addEventListener('keyup', e => {
			if (e.key === 'Enter') {
				$(this.form).submit();
			}
		});

		// Tags tab events.
		this.form.querySelectorAll('[name="show_inherited_tags"]')
			.forEach(o => o.addEventListener('change', e => this.#toggleInheritedTags()));

		this.form.addEventListener('click', (e) => {
			if (e.target.id === 'expression-constructor' || e.target.id === 'close-expression-constructor') {
				this.#toggleExpressionConstructor(e.target.id);
			}
			else if (e.target.id === 'insert-expression') {
				this.#openPopupTriggerExpr({
					dstfld1: this.expression_constructor_active ? 'expr_temp' : 'expression',
					expression: this.expression.value
				});
			}
			else if (e.target.id === 'add_expression' || e.target.id === 'and_expression'
					|| e.target.id === 'or_expression' || e.target.id === 'replace_expression') {
				const fields = {};
				fields[e.target.id] = '1';

				this.#expressionConstructor(fields);
			}
			else if (e.target.classList.contains('js_remove_expression')) {
				this.#expressionConstructor({'remove_expression': e.target.dataset.id});
			}
			else if (e.target.classList.contains('js-expression')) {
				this.#copy_expression(e.target);
			}
			else if (e.target.id === 'test-expression') {
				return PopUp('popup.testtriggerexpr', {expression: this.expr_temp.value},
					{dialogue_class: 'modal-popup-generic'}
				);
			}
			else if (e.target.name === 'correlation_mode' && !e.target.readOnly) {
				this.#changeCorrelationMode();
			}
			else if (e.target.name === 'recovery_mode' && !e.target.readOnly) {
				this.#changeRecoveryMode();
			}
			else if (e.target.id === 'recovery-expression-constructor'
					|| e.target.id === 'close-recovery-expression-constructor') {
				this.#toggleRecoveryExpressionConstructor(e.target.id);
			}
			else if (e.target.id === 'insert-recovery-expression') {
				this.#openPopupTriggerExpr({
					dstfld1: this.recovery_expression_constructor_active ? 'recovery_expr_temp' : 'recovery_expression',
					expression: this.recovery_expression.value
				});
			}
			else if (e.target.id === 'add_expression_recovery' || e.target.id === 'and_expression_recovery'
					|| e.target.id === 'or_expression_recovery' || e.target.id === 'replace_expression_recovery') {
				const fields = {};
				const parameter = e.target.id.split('_recovery');
				fields[parameter[0]] = '1';

				this.#expressionConstructor(fields, <?= TRIGGER_RECOVERY_EXPRESSION ?>);
			}
			else if (e.target.classList.contains('js_remove_recovery_expression')) {
				this.#expressionConstructor({'remove_expression': e.target.dataset.id},
					<?= TRIGGER_RECOVERY_EXPRESSION ?>
				);
			}
			else if (e.target.id === 'test-recovery-expression') {
				return PopUp('popup.testtriggerexpr', {expression: this.recovery_expr_temp.value},
					{dialogue_class: 'modal-popup-generic'}
				);
			}
			else if (e.target.classList.contains('js-recovery-expression')) {
				this.#copy_expression(e.target);
			}
			else if (e.target.id === 'add-dep-trigger' || e.target.id === 'add-dep-template-trigger'
					|| e.target.id === 'add-dep-host-trigger' || e.target.id === 'add-dep-trigger-prototype') {
				if (this.action === 'trigger.edit') {
					this.#addDepTrigger(e.target);
				}
				else {
					this.#addDepTriggerPrototype(e.target);
				}
			}
			else if (e.target.classList.contains('js-remove-dependency')) {
				this.#removeDependency(e.target.dataset.triggerid);
			}
			else if (e.target.classList.contains('js-check-target')) {
				check_target(e.target, <?= json_encode(TRIGGER_EXPRESSION) ?>);
			}
			else if (e.target.classList.contains('js-check-recovery-target')) {
				check_target(e.target, <?= json_encode(TRIGGER_RECOVERY_EXPRESSION) ?>);
			}
			else if (e.target.classList.contains('js-edit-template')
					|| e.target.classList.contains('js-related-trigger-edit')) {
				this.#setActions(e.target.dataset);
			}
		});

		this.expression.addEventListener('change', (e) => {
			const button_ids = ['#add_expression', '#and_expression', '#or_expression', '#replace_expression'];

			this.#disableExpressionConstructorButtons(button_ids, e.target);
		})

		this.recovery_expression.addEventListener('change', (e) => {
			const button_ids = ['#add_expression_recovery', '#and_expression_recovery', '#or_expression_recovery',
				'#replace_expression_recovery'
			];

			this.#disableExpressionConstructorButtons(button_ids, e.target);
		})
	}

	#setActions(dataset) {
		const {action, ...params} = dataset;

		window.popupManagerInstance.setAdditionalActions(() => {
			const url = new Curl('zabbix.php');

			url.setArgument('action', 'popup');
			url.setArgument('popup', action);

			for (const [key, value] of Object.entries(params)) {
				url.setArgument(key, value);
			}

			if (this.#isFormModified()) {
				if (!window.confirm(<?= json_encode(_('Any changes made in the current form will be lost.')) ?>)) {
					return false;
				}
				else {
					overlayDialogueDestroy(this.overlay.dialogueid);

					const url = new Curl(location.href);

					url.setArgument('action', 'popup');
					for (const [key, value] of Object.entries(params)) {
						url.setArgument(key, value);
					}

					history.replaceState(null, '', url.getUrl());

					return true;
				}
			}

			overlayDialogueDestroy(this.overlay.dialogueid);
			history.replaceState(null, '', url.getUrl());

			return true;
		});
	}

	#initTriggersTab() {
		$('#tabs').on('tabsactivate', (event, ui) => {
			if (ui.newPanel.is('#triggersTab')) {
				ui.newPanel.find('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>').textareaFlexible();
			}
		});
		$('#triggersTab .<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>').textareaFlexible();
	}

	#addDepTrigger(button) {
		let popup_parameters = {
			srctbl: 'triggers',
			srcfld1: 'triggerid',
			reference: 'deptrigger',
			multiselect: 1,
			with_triggers: 1,
			excludeids: [this.triggerid],
			disableids: this.selected_dependencies
		};

		if (button.id === 'add-dep-trigger') {
			popup_parameters.hostid = button.dataset.hostid;
			popup_parameters.real_hosts = 1;
		}
		else if (button.id === 'add-dep-template-trigger') {
			popup_parameters.srctbl = 'template_triggers';
			popup_parameters.templateid = button.dataset.templateid;
		}
		else {
			popup_parameters.real_hosts = 1;
		}

		PopUp('popup.generic', popup_parameters, {dialogue_class: 'modal-popup-generic'});
	}

	#addDepTriggerPrototype(button) {
		let popup_parameters = {
			srcfld1: 'triggerid',
			reference: 'deptrigger',
			multiselect: 1,
			excludeids: [this.triggerid],
			disableids: this.selected_dependencies
		};

		if (button.id === 'add-dep-trigger') {
			popup_parameters.srctbl = 'triggers';
			popup_parameters.hostid = button.dataset.hostid;
			popup_parameters.with_triggers = 1;
			popup_parameters.real_hosts = 1;
			popup_parameters.normal_only = 1;
		}
		else if (button.id === 'add-dep-trigger-prototype') {
			popup_parameters.srctbl = 'trigger_prototypes';
			popup_parameters.parent_discoveryid = button.dataset.parent_discoveryid;
		}
		else if (button.id === 'add-dep-template-trigger') {
			popup_parameters.srctbl = 'template_triggers';
			popup_parameters.templateid = button.dataset.templateid;
			popup_parameters.with_triggers = 1;
		}
		else {
			popup_parameters.srctbl = 'triggers';
			popup_parameters.with_triggers = 1;
			popup_parameters.real_hosts = 1;
			popup_parameters.normal_only = 1;
		}

		PopUp('popup.generic', popup_parameters, {dialogue_class: 'modal-popup-generic'});
	}

	#changeRecoveryMode() {
		const recovery_mode = this.form.querySelector('input[name=recovery_mode]:checked').value;
		const recovery_expression_row = this.form.querySelector('#recovery-expression-row');
		const ok_event_closes = this.form.querySelector('#ok-event-closes');
		const recovery_fields = [recovery_expression_row, recovery_expression_row.previousElementSibling,
			ok_event_closes, ok_event_closes.previousElementSibling];

		this.form.querySelector('#expression-row').previousElementSibling.textContent =
			(recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>)
				? <?= json_encode(_('Problem expression')) ?>
				: <?= json_encode(_('Expression')) ?>;

		if (recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>) {
			recovery_fields.forEach((field) => {
				field.style.display = '';
			})
		}
		else if (recovery_mode == <?= ZBX_RECOVERY_MODE_NONE ?>) {
			recovery_fields.forEach((field) => {
				field.style.display = 'none';
			})
		}
		else {
			recovery_expression_row.style.display = 'none';
			recovery_expression_row.previousElementSibling.style.display = 'none';
			ok_event_closes.style.display = '';
			ok_event_closes.previousElementSibling.style.display = '';
		}
	}

	#changeCorrelationMode() {
		const recovery_mode = this.form.querySelector('input[name=recovery_mode]:checked').value;
		const correlation_mode = this.form.querySelector('input[name=correlation_mode]:checked').value;
		const correlation_tag = this.form.querySelector('#correlation_tag');

		if ((recovery_mode == <?= ZBX_RECOVERY_MODE_EXPRESSION ?>
				|| recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>)
				&& correlation_mode == <?= ZBX_TRIGGER_CORRELATION_TAG ?>
		) {
			correlation_tag.style.display = '';
			correlation_tag.previousElementSibling.style.display = '';
		}
		else {
			correlation_tag.style.display = 'none';
			correlation_tag.previousElementSibling.style.display = 'none';
		}
	}

	#loadDependencyTable(data) {
		let dependencies = this.#prepareDependencies(data);

		this.#addDependencies(dependencies);
	}

	#removeDependency(triggerid) {
		const dependency_element = document.querySelector('#dependency_' + triggerid);

		dependency_element.parentNode.removeChild(dependency_element);
		this.selected_dependencies = this.selected_dependencies.filter((el) => el !== triggerid);
	}

	#prepareDependencies(data) {
		const dependencies = [];

		Object.values(data).forEach((dependency) => {
			const hosts = dependency.hosts.map(item => item['name']);
			const name = hosts.join(', ') + <?= json_encode(NAME_DELIMITER) ?> + dependency.description;
			const prototype = dependency.flags == <?= json_encode(ZBX_FLAG_DISCOVERY_PROTOTYPE)?> ? '1' : '0';

			dependencies.push({
				name: name,
				triggerid: dependency.triggerid,
				prototype: prototype,
				trigger_url: this.#constructTriggerUrl(dependency.triggerid, prototype === '1'),
				action: prototype === '1' ? 'trigger.prototype.edit' : 'trigger.edit'
			});
		});

		return dependencies;
	}

	#addDependencies(dependencies) {
		const template = new Template(document.getElementById('dependency-row-tmpl').innerHTML)
		const tbody = Object.values(dependencies).map(row => template.evaluate(row)).join('');

		this.form.querySelector('#dependency-table tbody').insertAdjacentHTML('beforeend', tbody);
		this.selected_dependencies = dependencies.map(({triggerid}) => triggerid);
	}

	#toggleExpressionConstructor(id) {
		const elements = [
			'#insert-macro', '#expression-constructor-buttons', '#expression-table',
			'#close-expression-constructor-field'
		];
		const expression_constructor = this.form.querySelector('#expression-constructor');
		const insert_expression = this.form.querySelector('#insert-expression');

		if (id === 'expression-constructor') {
			elements.forEach((element) => {
				this.form.querySelector(element).style.display = '';
			});

			expression_constructor.style.display = 'none';
			this.expression.readOnly = true;
			this.expression.name = 'expr_temp';
			this.expression.id = 'expr_temp';
			this.expr_temp.name = 'expression';
			this.expr_temp.id = 'expression';
			insert_expression.textContent = <?= json_encode(_('Edit')) ?>;
			this.expression_constructor_active = true;

			if (this.expression.value === '') {
				this.#showConstructorAddButton();
			}
			else {
				this.#showConstructorAddButton(false);
			}

			this.#expressionConstructor();
		}
		else {
			elements.forEach((element) => {
				this.form.querySelector(element).style.display = 'none';
			});

			expression_constructor.style.display = '';
			this.expression.name = 'expression';
			this.expression.id = 'expression';
			this.expr_temp.name = 'expr_temp';
			this.expr_temp.id = 'expr_temp';
			this.expression.readOnly = this.readonly;
			insert_expression.textContent = <?= json_encode(_('Add')) ?>;
			this.expression.value = this.expr_temp.value;
			this.expression_constructor_active = false;
		}
	}

	#toggleRecoveryExpressionConstructor(id) {
		const elements = [
			'#recovery-constructor-buttons', '#recovery-expression-table',
			'#close-recovery-expression-constructor-field'
		];

		const recovery_expression_constructor = this.form.querySelector('#recovery-expression-constructor');
		const insert_recovery_expression = this.form.querySelector('#insert-recovery-expression');

		if (id === 'recovery-expression-constructor') {
			elements.forEach((element) => {
				this.form.querySelector(element).style.display = '';
			});

			recovery_expression_constructor.style.display = 'none';
			this.recovery_expression.readOnly = true;
			this.recovery_expression.name = 'recovery_expr_temp';
			this.recovery_expression.id = 'recovery_expr_temp';
			this.recovery_expr_temp.name = 'recovery_expression';
			this.recovery_expr_temp.id = 'recovery_expression';
			insert_recovery_expression.textContent = <?= json_encode(_('Edit')) ?>;
			this.recovery_expression_constructor_active = true;

			if (this.recovery_expression.value === '') {
				this.#showRecoveryConstructorAddButton();
			}
			else {
				this.#showRecoveryConstructorAddButton(false);
			}

			this.#expressionConstructor({}, <?= TRIGGER_RECOVERY_EXPRESSION ?>);
		}
		else {
			elements.forEach((element) => {
				this.form.querySelector(element).style.display = 'none';
			});

			recovery_expression_constructor.style.display = '';
			this.recovery_expression.name = 'recovery_expression';
			this.recovery_expression.id = 'recovery_expression';
			this.recovery_expr_temp.name = 'recovery_expr_temp';
			this.recovery_expr_temp.id = 'recovery_expr_temp';
			this.recovery_expression.readOnly = this.readonly;
			insert_recovery_expression.textContent = <?= json_encode(_('Add')) ?>;
			this.recovery_expression.value = this.recovery_expr_temp.value;
			this.recovery_expression_constructor_active = false;
		}
	}

	#openPopupTriggerExpr(trigger_options) {
		PopUp('popup.triggerexpr', {...this.expression_popup_parameters, ...trigger_options},
			{dialogueid: 'trigger-expr', dialogue_class: 'modal-popup-generic'}
		);
	}

	#expressionConstructor(fields = {}, expression_type = <?= TRIGGER_EXPRESSION ?>) {
		if (fields.remove_expression !== undefined) {
			if (!window.confirm(<?= json_encode(_('Delete expression?')) ?>)) {
				return;
			}
		}

		if (expression_type === <?= TRIGGER_EXPRESSION ?>) {
			if (Object.keys(fields).length === 0 || fields.add_expression) {
				fields.expression = this.expression.value.trim();
			}
			else {
				fields.expression = this.expr_temp.value.trim();
				fields.expr_temp = this.expression.value.trim();
				fields.expr_target_single = this.form
					.querySelector('input[name="expr_target_single"]:checked').value;
			}

			this.expression.value = '';
			this.expression.dispatchEvent(new Event('change'));
		}
		else {
			if (Object.keys(fields).length === 0 || fields.add_expression) {
				fields.recovery_expression = this.recovery_expression.value.trim();
			}
			else {
				fields.recovery_expression = this.recovery_expr_temp.value.trim();
				fields.recovery_expr_temp = this.recovery_expression.value.trim();
				fields.recovery_expr_target_single = this.form
					.querySelector('input[name="recovery_expr_target_single"]:checked').value;
			}

			this.recovery_expression.value = '';
			this.recovery_expression.dispatchEvent(new Event('change'));
		}

		fields.readonly = this.readonly;

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'trigger.expression.constructor');

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(fields)
		})
			.then((response) => response.json())
			.then((response) => {

				if ('error' in response) {
					throw {error: response.error};
				}

				if (expression_type === <?= TRIGGER_EXPRESSION ?>) {
					const table = this.form.querySelector('#expression-table');
					table.innerHTML = response.body;
					this.expr_temp.value = response.expression;

					if (table.querySelector('tbody').innerHTML !== '') {
						this.#showConstructorAddButton(false);
					}
					else {
						this.#showConstructorAddButton();
					}
				}
				else {
					const table = this.form.querySelector('#recovery-expression-table');
					table.innerHTML = response.body;
					this.recovery_expr_temp.value = response.expression;

					if (table.querySelector('tbody').innerHTML !== '') {
						this.#showRecoveryConstructorAddButton(false);
					}
					else {
						this.#showRecoveryConstructorAddButton();
					}
				}

			})
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

				if (expression_type === <?= TRIGGER_EXPRESSION ?>) {
					this.expr_temp.value = exception.error.expression;
					this.#toggleExpressionConstructor();
				}
				else {
					this.recovery_expr_temp.value = exception.error.expression;
					this.#toggleRecoveryExpressionConstructor();
				}

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});

	}

	#showConstructorAddButton(show = true) {
		const and_button = this.form.querySelector('#and_expression');
		const or_button = this.form.querySelector('#or_expression');
		const replace_button = this.form.querySelector('#replace_expression');
		const add_button = this.form.querySelector('#add_expression');

		if (show) {
			and_button.style.display = 'none';
			or_button.style.display = 'none';
			replace_button.style.display = 'none';
			add_button.style.display = '';
		}
		else {
			and_button.style.display = '';
			or_button.style.display = '';
			replace_button.style.display = '';
			add_button.style.display = 'none';
		}
	}

	#showRecoveryConstructorAddButton(show = true) {
		const and_button = this.form.querySelector('#and_expression_recovery');
		const or_button = this.form.querySelector('#or_expression_recovery');
		const replace_button = this.form.querySelector('#replace_expression_recovery');
		const add_button = this.form.querySelector('#add_expression_recovery');

		if (show) {
			and_button.style.display = 'none';
			or_button.style.display = 'none';
			replace_button.style.display = 'none';
			add_button.style.display = '';
		}
		else {
			and_button.style.display = '';
			or_button.style.display = '';
			replace_button.style.display = '';
			add_button.style.display = 'none';
		}
	}

	#toggleInheritedTags() {
		const form_refresh = document.createElement('input');

		form_refresh.setAttribute('type', 'hidden');
		form_refresh.setAttribute('name', 'form_refresh');
		form_refresh.setAttribute('value', 1);
		this.form.append(form_refresh);

		reloadPopup(this.form, this.action);
	}

	#getFormFields() {
		const fields = getFormFields(this.form);

		for (let key in fields) {
			if (typeof fields[key] === 'string' && key !== 'confirmation') {
				fields[key] = fields[key].trim();
			}
			else if (key === 'tags') {
				for (let tag in fields['tags'] ) {
					fields['tags'][tag].tag = fields['tags'][tag].tag.trim();
					fields['tags'][tag].value = fields['tags'][tag].value.trim();
				}
			}
		}

		return fields;
	}

	#isFormModified() {
		if (this.triggerid === null) {
			return true;
		}

		let form_fields = {
			dependencies: [],
			discover: String(<?= TRIGGER_NO_DISCOVER ?>),
			manual_close: String(<?= ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED ?>),
			status: String(<?= TRIGGER_STATUS_DISABLED ?>),
			...this.#getFormFields()
		}

		// Values are modified to match this.db_trigger values.
		if (form_fields.tags) {
			// Remove empty tags and add tags in array instead of object.
			form_fields.tags = Object.values(form_fields.tags).filter(obj => {
				for (const key in obj) {
					if (obj[key] === '') {
						return false;
					}
					return true;
				}
			});
		}

		delete form_fields.context;
		delete form_fields[CSRF_TOKEN_NAME];

		this.db_trigger.dependencies = [];

		if (Object.keys(this.dependencies).length > 0) {
			// Dependencies are sorted alphabetically as in form to appear in the same order for JSON.stringify().
			let dependencies = this.#prepareDependencies(this.dependencies);
			this.db_trigger.dependencies = dependencies.map(obj => obj.triggerid);
		}

		if (form_fields.show_inherited_tags == 1) {
			form_fields.tags = form_fields.tags.filter((tag) => {
				return tag.type !== '1'; // 'ZBX_PROPERTY_INHERITED'
			});

			for (const tag of form_fields.tags) {
				delete tag.type;
			}
		}

		delete form_fields.show_inherited_tags;

		return JSON.stringify({...form_fields, ...this.db_trigger}) !== JSON.stringify(form_fields);
	}

	#disableExpressionConstructorButtons(buttons, target) {
		if (target.value != '') {
			buttons.forEach((button) => {
				this.form.querySelector(button).disabled = false;
			});
		}
		else {
			buttons.forEach((button) => {
				this.form.querySelector(button).disabled = true;
			});
		}
	}

	#copy_expression(target) {
		const element = (target.classList.contains('js-expression'))
			? this.expression
			: this.recovery_expression;

		if (element.value.length > 0
				&& !confirm(<?= json_encode(_('Do you wish to replace the conditional expression?')) ?>)) {
			return;
		}

		if (typeof target.textContent != 'undefined') {
			element.value = target.textContent;
		}
		else {
			element.value = target.innerText;
		}

		element.dispatchEvent(new Event('change'))
	}

	#post(url, data) {
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
				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
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

	submit() {
		const fields = this.#getFormFields();
		const curl = new Curl('zabbix.php');

		if (this.action === 'trigger.edit') {
			curl.setArgument('action', this.triggerid !== null ? 'trigger.update' : 'trigger.create');
		}
		else {
			curl.setArgument('action', this.triggerid !== null
				? 'trigger.prototype.update'
				: 'trigger.prototype.create'
			);
		}

		this.#post(curl.getUrl(), fields);
	}

	clone() {
		const form_refresh = document.createElement('input');

		form_refresh.setAttribute('type', 'hidden');
		form_refresh.setAttribute('name', 'form_refresh');
		form_refresh.setAttribute('value', 1);
		this.form.append(form_refresh);

		this.form.querySelector('[name="triggerid"]').remove();
		reloadPopup(this.form, this.action);
	}

	delete() {
		const action = this.action === 'trigger.edit' ? 'trigger.delete' : 'trigger.prototype.delete';

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', action);
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('trigger')) ?>);

		this.#post(curl.getUrl(), {triggerids: [this.triggerid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	/**
	 * @see init.js add.popup event
	 */
	addPopupValues(data) {
		const dependency_table = this.form.querySelector('#dependency-table tbody');
		let dependencies = [];

		dependency_table
			.querySelectorAll('.js-related-trigger-edit')
			.forEach(row => {
				const prototype = row.dataset.prototype && row.dataset.prototype === '1';

				dependencies.push({
					name: row.textContent,
					triggerid: row.dataset.triggerid,
					prototype: row.dataset.prototype,
					trigger_url: this.#constructTriggerUrl(row.dataset.triggerid, prototype),
					action: prototype ? 'trigger.prototype.edit' : 'trigger.edit'
				});
			});

		Object.values(data).forEach((new_dependency) => {
			if (dependencies.some(dependency => dependency.triggerid === new_dependency.triggerid)) {
				return;
			}

			const prototype = new_dependency.prototype === '1';

			new_dependency.action = prototype ? 'trigger.prototype.edit' : 'trigger.edit';
			new_dependency.trigger_url = this.#constructTriggerUrl(new_dependency.triggerid, prototype);

			dependencies.push(new_dependency);
		})

		dependency_table.innerHTML = '';

		this.#addDependencies(dependencies);
	}

	#constructTriggerUrl(triggerid, is_prototype) {
		const url = new Curl('zabbix.php');
		url.setArgument('action', 'popup');
		url.setArgument('popup', is_prototype ? 'trigger.prototype.edit' : 'trigger.edit');
		url.setArgument('triggerid', triggerid);
		url.setArgument('context', this.context);

		if (is_prototype) {
			url.setArgument('parent_discoveryid', this.parent_discoveryid);
		}

		return url.getUrl();
	}

	elementSuccess(context, discovery, e) {
		const data = e.detail;
		let curl = null;

		if ('success' in data) {
			postMessageOk(data.success.title);

			if ('messages' in data.success) {
				postMessageDetails('success', data.success.messages);
			}

			if ('action' in data.success && data.success.action === 'delete') {
				curl = discovery ? new Curl('host_discovery.php') : new Curl('zabbix.php?action=trigger.list')
				curl.setArgument('context', context);
			}
		}

		if (curl === null) {
			location.href = location.href;
		}
		else {
			location.href = curl.getUrl();
		}
	}
}
