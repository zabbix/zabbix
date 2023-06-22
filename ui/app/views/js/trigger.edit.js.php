<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
?>


	window.trigger_edit_popup = new class {

		init({form_name, triggerid, expression_popup_parameters, recovery_popup_parameters, readonly,
				db_dependencies
		}) {
			this.expression_popup_parameters = expression_popup_parameters;
			this.recovery_popup_parameters = recovery_popup_parameters;
			this.form_name = form_name;
			this.readonly = readonly;
			this.triggerid = triggerid;
			this.db_dependencies = db_dependencies;
			this.overlay = overlays_stack.getById('trigger-edit');
			this.dialogue = this.overlay.$dialogue[0];
			this.form = this.overlay.$dialogue.$body[0].querySelector('form');
			this.expression = this.form.querySelector('#expression');
			this.expression_full = this.form.querySelector('#expression-full');
			this.description = this.form.querySelector('#description');

			window.addPopupValues = (data) => {
				this.addPopupValues(data.values);
			}

			this.#initActions();
			this.#changeRecoveryMode();
			this.#changeCorrelationMode();

			if (this.db_dependencies) {
				this.#loadDependencyTable(this.db_dependencies);
			}
		}

		#initActions() {
			['input', 'keydown', 'paste'].forEach((event_type) => {
				this.description.addEventListener(event_type,
					(e) => this.form.querySelector('#event_name').placeholder = e.target.value
				);
				this.description.dispatchEvent(new Event('input'));
			});

			this.form.querySelector('#close-expression-constructor').addEventListener('click',
				(e) => this.#toggleExpressionConstructor(e.target.id)
			);

			this.form.querySelector('#close-recovery-expression-constructor').addEventListener('click',
				(e) => this.#toggleRecoveryExpressionConstructor(e.target.id)
			);

			this.form.addEventListener('click', (e) => {
				if (e.target.id === 'expression-constructor') {
					this.#toggleExpressionConstructor(e.target.id);
				}
				else if (e.target.id === 'insert-expression') {
					this.#openPopupTriggerExpr();
				}
				else if (e.target.id === 'add-expression') {
					this.#expressionConstructor({'add_expression': '1'});
				}
				else if (e.target.id === 'and-expression') {
					this.#expressionConstructor({'and_expression': '1'});
				}
				else if (e.target.id === 'or-expression') {
					this.#expressionConstructor({'or_expression': '1'});
				}
				else if (e.target.id === 'replace-expression') {
					this.#expressionConstructor({'replace_expression': '1'});
				}
				else if (e.target.classList.contains('js_remove_expression')) {
					this.#expressionConstructor({'remove_expression': e.target.dataset.id});
				}
				else if (e.target.classList.contains('js-expression')) {
					copy_expression(e.target.id, <?= json_encode(TRIGGER_EXPRESSION) ?>);
				}
				else if (e.target.id === 'test-expression') {
					return PopUp('popup.testtriggerexpr',
						{expression: this.expression_full.value}, {
						dialogue_class: 'modal-popup-generic'
					});
				}
				else if (e.target.name === 'correlation_mode') {
					this.#changeCorrelationMode();
				}
				else if (e.target.name === 'recovery_mode') {
					this.#changeRecoveryMode();
				}
				else if (e.target.id === 'recovery-expression-constructor') {
					this.#toggleRecoveryExpressionConstructor(e.target.id);
				}
				else if (e.target.id === 'insert-recovery-expression') {
					this.#openPopupTriggerExpr(false);
				}
				else if (e.target.id === 'add-recovery-expression') {
					this.#expressionConstructor({'add_expression': '1'}, false);
				}
				else if (e.target.id === 'and-recovery-expression') {
					this.#expressionConstructor({'and_expression': '1'}, false);
				}
				else if (e.target.id === 'or-recovery-expression') {
					this.#expressionConstructor({'or_expression': '1'}, false);
				}
				else if (e.target.id === 'replace-recovery-expression') {
					this.#expressionConstructor({'replace_expression': '1'}, false);
				}
				else if (e.target.classList.contains('js_remove_recovery_expression')) {
					this.#expressionConstructor({'remove_expression': e.target.dataset.id}, false);
				}
				else if (e.target.id === 'test-recovery-expression') {
					return PopUp('popup.testtriggerexpr',
						{expression: this.form.querySelector('#recovery-expression-full').value}, {
							dialogue_class: 'modal-popup-generic'
						});
				}
				else if (e.target.classList.contains('js-recovery-expression')) {
					copy_expression(e.target.id, <?= json_encode(TRIGGER_RECOVERY_EXPRESSION) ?>);
				}
				else if (e.target.id === 'add-dep-trigger' || e.target.id === 'add-dep-template-trigger'
						|| e.target.id === 'add_dep_host_trigger') {
					this.#addDepTrigger(e.target);
				}
				else if (e.target.classList.contains('js-remove-dependency')) {
					this.#removeDependency(e.target.dataset.triggerid);
				}
			});
		}

		#addDepTrigger(button) {
			let popup_parameters = {
				srctbl: 'triggers',
				srcfld1: 'triggerid',
				reference: 'deptrigger',
				multiselect: 1,
				with_triggers: 1
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
				this.#toggleRecoveryExpressionConstructor('close-recovery-expression-constructor');
			}
			else if (recovery_mode == <?= ZBX_RECOVERY_MODE_NONE ?>) {
				recovery_fields.forEach((field) => {
					field.style.display = 'none';
				})
				this.#toggleRecoveryExpressionConstructor('close-recovery-expression-constructor');
			}
			else {
				recovery_expression_row.style.display = 'none';
				recovery_expression_row.previousElementSibling.style.display = 'none';
				ok_event_closes.style.display = '';
				ok_event_closes.previousElementSibling.style.display = '';
				this.#toggleRecoveryExpressionConstructor('close-recovery-expression-constructor');
			}
		}

		#changeCorrelationMode() {
			const recovery_mode = this.form.querySelector('input[name=recovery_mode]:checked').value;
			const correlation_mode = this.form.querySelector('input[name=correlation_mode]:checked').value;
			const correlation_tag = this.form.querySelector('#correlation-tag');

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

		/**
		 * @see init.js add.popup event
		 */
		addPopupValues(data) {
			let template;

			Object.values(data).forEach((dependency) => {
				const element = {description: dependency.description, triggerid: dependency.triggerid};

				template = new Template(document.getElementById('dependency-row-tmpl').innerHTML)

				document
					.querySelector('#dependency-table tbody')
					.insertAdjacentHTML('beforeend', template.evaluate(element));
			})
		}

		#loadDependencyTable(data) {
			const dependencies = [];

			Object.values(data).forEach((dependency) => {
				let hosts = dependency.hosts.map(item => item['name']);
				let description = hosts.join(', ') + <?= json_encode(NAME_DELIMITER) ?> + dependency.description;

				dependencies.push({description: description, triggerid: dependency.triggerid});
			})

			this.addPopupValues(dependencies);
		}

		#removeDependency(triggerid) {
			const dependency_element = document.querySelector('#dependency_' + triggerid);

			dependency_element.parentNode.removeChild(dependency_element);
		}

		refresh() {
			const url = new Curl('');
			const form = document.getElementsByName(this.form_name)[0];
			const fields = getFormFields(form);

			post(url.getUrl(), fields);
		}

		#toggleExpressionConstructor(id) {
			const elements = [
				'#insert-macro', '#expression-constructor-buttons', '#expression-table',
				'#close-expression-constructor'
			];

			const expression_constructor = this.form.querySelector('#expression-constructor');
			const close_expression_constructor = this.form.querySelector('#close-expression-constructor');
			const insert_expression = this.form.querySelector('#insert-expression');

			if (id === 'expression-constructor') {
				elements.forEach((element) => {
					this.form.querySelector(element).style.display = '';
				});

				expression_constructor.style.display = 'none';
				close_expression_constructor.style.display = '';
				this.expression.readOnly = true;
				insert_expression.textContent = <?= json_encode(_('Edit')) ?>;

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
				close_expression_constructor.style.display = 'none';
				this.expression.readOnly = this.readonly ? true : false;
				insert_expression.textContent = <?= json_encode(_('Add')) ?>;
				this.expression.value = this.expression_full.value;
			}
		}

		#toggleRecoveryExpressionConstructor(id) {
			const elements = [
				'#recovery-constructor-buttons', '#recovery-expression-table',
				'#close-recovery-expression-constructor'
			];

			const recovery_expression_constructor = this.form.querySelector('#recovery-expression-constructor');
			const close_recovery_expression_constructor = this.form.querySelector('#close-recovery-expression-constructor');
			const insert_recovery_expression = this.form.querySelector('#insert-recovery-expression');
			const recovery_expression = this.form.querySelector('#recovery_expression');

			if (id === 'recovery-expression-constructor') {
				elements.forEach((element) => {
					this.form.querySelector(element).style.display = '';
				});

				recovery_expression_constructor.style.display = 'none';
				close_recovery_expression_constructor.style.display = '';
				recovery_expression.readOnly = true;
				insert_recovery_expression.textContent = <?= json_encode(_('Edit')) ?>;

				if (recovery_expression.value === '') {
					this.#showRecoveryConstructorAddButton();
				}
				else {
					this.#showRecoveryConstructorAddButton(false);
				}

				this.#expressionConstructor({}, false);
			}
			else {
				elements.forEach((element) => {
					this.form.querySelector(element).style.display = 'none';
				});

				recovery_expression_constructor.style.display = '';
				close_recovery_expression_constructor.style.display = 'none';
				recovery_expression.readOnly = this.readonly ? true : false;
				insert_recovery_expression.textContent = <?= json_encode(_('Add')) ?>;
				recovery_expression.value = this.form.querySelector('#recovery-expression-full').value;
			}
		}

		#openPopupTriggerExpr(type_expression = true) {
			const popup_parameters = type_expression
				? this.expression_popup_parameters
				: this.recovery_popup_parameters;

			const expression = type_expression
				? this.form.querySelector('[name="expression"]').value
				: this.form.querySelector('[name="recovery_expression"]').value;

			PopUp('popup.triggerexpr', {...popup_parameters, expression: expression},
				{dialogueid: 'trigger-expr', dialogue_class: 'modal-popup-generic'}
			);
		}

		#expressionConstructor(fields = {}, type_expression = true) {
			if (type_expression) {
				if (Object.keys(fields).length === 0 || fields.add_expression) {
					fields.expression = this.expression.value;
				}
				else {
					fields.expression = this.expression_full.value;
					fields.expr_temp = this.expression.value;
					fields.expr_target_single = this.form
						.querySelector('input[name="expr_target_single"]:checked').value;
				}

				this.expression.value = '';
			}
			else {
				const recovery_expression = this.form.querySelector('#recovery_expression');

				if (Object.keys(fields).length === 0 || fields.add_expression) {
					fields.recovery_expression = recovery_expression.value;
				}
				else {
					fields.recovery_expression = this.form.querySelector('#recovery-expression-full').value;
					fields.recovery_expr_temp = recovery_expression.value;
					fields.recovery_expr_target_single = this.form
						.querySelector('input[name="recovery_expr_target_single"]:checked').value;
				}

				recovery_expression.value = '';
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

					if (type_expression) {
						const table = this.form.querySelector('#expression-table');
						table.innerHTML = response.body;
						this.expression_full.value = response.expression;

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
						this.form.querySelector('#recovery-expression-full').value = response.recovery_expression;

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

					this.form.parentNode.insertBefore(message_box, this.form);
				})
				.finally(() => {
					this.overlay.unsetLoading();
				});

		}

		#showConstructorAddButton(show = true) {
			const and_button = this.form.querySelector('#and-expression');
			const or_button = this.form.querySelector('#or-expression');
			const replace_button = this.form.querySelector('#replace-expression');
			const add_button = this.form.querySelector('#add-expression');

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
			const and_button = this.form.querySelector('#and-recovery-expression');
			const or_button = this.form.querySelector('#or-recovery-expression');
			const replace_button = this.form.querySelector('#replace-recovery-expression');
			const add_button = this.form.querySelector('#add-recovery-expression');

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

		submit() {
			const fields = getFormFields(this.form);
			fields.description = fields.description.trim();

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', this.triggerid !== 0 ? 'trigger.update' : 'trigger.create');

			this.#post(curl.getUrl(), fields);
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

					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.success}));
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

		clone() {
			this.triggerid = 0;
			const title = <?= json_encode(_('New action')) ?>;
			const buttons = [
				{
					title: <?= json_encode(_('Add')) ?>,
					class: '',
					keepOpen: true,
					isSubmit: true,
					action: () => this.submit()
				},
				{
					title: <?= json_encode(_('Cancel')) ?>,
					class: 'btn-alt',
					cancel: true,
					action: () => ''
				}
			];

			this.overlay.unsetLoading();
			this.overlay.setProperties({title, buttons});
		}
	}
