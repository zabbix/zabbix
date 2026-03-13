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

window.correlation_edit_popup = new class {

	init({rules, clone_rules, templates_data, templates_types}) {
		this.overlay = overlays_stack.getById('correlation.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.footer = this.overlay.$dialogue.$footer[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);
		this.clone_rules = clone_rules;

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'correlation.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		this.row_templates = {}
		for (const type of templates_types) {
			this.row_templates[type] = new Template(document.getElementById(`condition-row-tmpl-${type}`).innerHTML);
		}

		templates_data.forEach((condition, index) => this.#addConditionRow(condition, index));
		this.#processTypeOfCalculation();

		this.#initActions();
	}

	#initActions() {
		this.dialogue.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-condition-add')) {
				const overlay = PopUp('correlation.condition.edit', {}, {
					dialogueid: 'correlation-condition-form',
					dialogue_class: 'modal-popup-medium'
				});

				// Get values from condition popup.
				overlay.$dialogue[0].addEventListener('condition.dialogue.submit', (e) => {
					this.#addConditionRow(e.detail);
					this.#processTypeOfCalculation();
				});
			}
			else if (e.target.classList.contains('js-condition-remove')) {
				e.target.closest('tr').remove();
				this.#processTypeOfCalculation();
			}
		});

		let condition_add_timeout_id = null;

		this.dialogue.querySelector('.js-condition-add').addEventListener('blur', () => {
			clearTimeout(condition_add_timeout_id);
			condition_add_timeout_id = setTimeout(() => {
				if (document.getElementById('correlation-condition-form') === null) {
					this.form.validateChanges(['conditions'], true);
				}
			}, 250);
		});

		this.dialogue.querySelector('.js-condition-add').addEventListener('focusin', () => {
			clearTimeout(condition_add_timeout_id);
			condition_add_timeout_id = null;
		});

		document.getElementById('evaltype').onchange = () => this.#processTypeOfCalculation();

		this.footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());
		this.footer.querySelector('.js-clone')?.addEventListener('click', () => this.#clone());
		this.footer.querySelector('.js-delete')?.addEventListener('click', () => this.#delete());
	}

	/**
	 * Checks the conditions list and shows either expression or custom input formula field.
	 */
	#processTypeOfCalculation() {
		const condition_count = this.form_element.querySelectorAll('#condition_table>tbody>tr').length;
		const evaltype = this.form_element.querySelector('#evaltype');

		if (condition_count <= 1) {
			evaltype.value = <?= CONDITION_EVAL_TYPE_AND_OR ?>;
		}

		const show_formula = condition_count > 1 && evaltype.value == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>;
		const evaltype_label = this.form_element.querySelector('#label-evaltype');
		const expression = this.form_element.querySelector('#expression');
		const formula = this.form_element.querySelector('#formula');

		if (condition_count > 1) {
			evaltype.closest('.form-field').style.display = '';
			evaltype_label.style.display = '';
		}
		else {
			evaltype.closest('.form-field').style.display = 'none';
			evaltype_label.style.display = 'none';
		}

		if (show_formula) {
			expression.style.display = 'none';
			formula.style.display = '';
			formula.disabled = false;
		}
		else {
			expression.style.display = '';
			formula.style.display = 'none';
			formula.disabled = true;
		}

		const conditions = [];
		const labels = this.form_element.querySelectorAll('#condition_table [data-conditiontype]');

		[...labels].forEach((label) => {
			conditions.push({
				id: label.dataset.formulaid,
				type: label.dataset.conditiontype
			});
		});

		expression.innerHTML = getConditionFormula(conditions, + evaltype.value);
	}

	#getConditionNextIndex() {
		let next_index = 0;

		while (true) {
			if (!document.getElementById('condition_table').querySelector(`[data-row-index="${next_index}"]`)) {
				break;
			}
			else {
				next_index++;
			}
		}

		return next_index;
	}

	#addConditionRow(condition) {
		// Multiple entries added per host group type condition.
		if (is_object(condition.groupid)) {
			for (const groupid of Object.keys(condition.groupid)) {
				this.#addConditionRow({...condition, groupid, group_name: condition.groupid[groupid]});
			}

			return;
		}

		if (this.#hasConditionDuplicate(condition)) {
			return;
		}

		const template = this.row_templates[condition.type];

		condition.row_index = this.#getConditionNextIndex();
		condition.formulaid = num2letter(condition.row_index);

		this.form_element
			.querySelector('#condition_table tbody')
			.insertAdjacentHTML('beforeend', template.evaluate(condition));
	}

	#hasConditionDuplicate(condition) {
		const result = [];

		[...this.form_element.querySelectorAll('#condition_table>tbody>tr')].forEach((element) => {
			const type = element.querySelector('input[name*="type"]').value;
			const same_type = parseInt(condition.type, 10) == parseInt(type, 10);

			switch (parseInt(type)) {
				case <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG ?>:
				case <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG ?>:
					result.push(same_type && condition.tag === element.querySelector('input[name*="tag"]').value);
					break;

				case <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE ?>:
				case <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE ?>:
					result.push(same_type
						&& condition.tag === element.querySelector('input[name*="tag"]').value
						&& condition.value === element.querySelector('input[name*="value"]').value
					);
					break;

				case <?= ZBX_CORR_CONDITION_EVENT_TAG_PAIR ?>:
					result.push(same_type
						&& condition.oldtag === element.querySelector('input[name*="oldtag"]').value
						&& condition.newtag === element.querySelector('input[name*="newtag"]').value
					);
					break;

				case <?= ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP ?>:
					result.push(same_type
						&& condition.groupid === element.querySelector('input[name*="groupid"]').value
					);
					break;
			}
		});

		return result.some((element) => element === true);
	}

	#clone() {
		document.getElementById('correlationid').remove();

		const title = <?= json_encode(_('New event correlation')) ?>;
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

		this.overlay.unsetLoading();
		this.overlay.setProperties({title, buttons});

		this.footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());

		this.overlay.recoverFocus();
		this.overlay.containFocus();
		this.form.reload(this.clone_rules);
	}

	#delete() {
		if (window.confirm(<?= json_encode(_('Delete event correlation?')) ?>)) {
			this.#removePopupMessages();
			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'correlation.delete');
			curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('correlation')) ?>);

			const correlationid = this.form.findFieldByName('correlationid').getValue();

			this.#post(curl.getUrl(), {correlationids: [correlationid]}, (response) => {
				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			});
		}
		else {
			this.overlay.unsetLoading();
		}
	}

	#submit() {
		this.#removePopupMessages();

		const fields = this.form.getAllValues();
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', fields.correlationid ? 'correlation.update' : 'correlation.create');

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();
					return;
				}

				this.#post(curl.getUrl(), fields, (response) => {
					overlayDialogueDestroy(this.overlay.dialogueid);

					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
				});
		});
	}

	/**
	 * Sends a POST request to the specified URL with the provided data and executes the success_callback function.
	 *
	 * @param {string}   url               The URL to send the POST request to.
	 * @param {object}   data              The data to send with the POST request.
	 * @param {callback} success_callback  The function to execute when a successful response is received.
	 */
	#post(url, data, success_callback) {
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

				if ('form_errors' in response) {
					this.form.setErrors(response.form_errors, true, true);
					this.form.renderErrors();

					return;
				}

				success_callback(response);
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => this.overlay.unsetLoading());
	}

	#removePopupMessages() {
		for (const el of this.form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
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
};
