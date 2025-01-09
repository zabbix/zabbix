<?php declare(strict_types = 0);
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

window.correlation_edit_popup = new class {

	init({correlation}) {
		this.overlay = overlays_stack.getById('correlation.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.correlation = correlation;
		this.correlationid = correlation.correlationid;

		const backurl = new Curl('zabbix.php');

		backurl.setArgument('action', 'correlation.list');
		this.overlay.backurl = backurl.getUrl();

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

		for (let i = 0; i < Object.values(correlation.conditions).length; i++) {
			this.#addConditionRow(correlation.conditions[i]);
		}

		this.form.querySelector('#evaltype').onchange = () => this.#processTypeOfCalculation();
		this.#processTypeOfCalculation();
	}

	/**
	 * Checks the conditions list and shows either expression or custom input formula field.
	 */
	#processTypeOfCalculation() {
		const condition_count = this.form.querySelectorAll('#condition_table tr[id^=conditions_]').length;
		const evaltype = this.form.querySelector('#evaltype');

		if (condition_count <= 1) {
			evaltype.value = <?= CONDITION_EVAL_TYPE_AND_OR ?>;
		}

		const show_formula = condition_count > 1 && evaltype.value == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>;
		const evaltype_label = this.form.querySelector('#label-evaltype');
		const expression = this.form.querySelector('#expression');
		const formula = this.form.querySelector('#formula');

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
			formula.removeAttribute('disabled');
		}
		else {
			expression.style.display = '';
			formula.style.display = 'none';
			formula.setAttribute('disabled', true);
		}

		const conditions = [];
		const labels = this.form.querySelectorAll('#condition_table .label');

		[...labels].forEach((label) => {
			conditions.push({
				id: label.dataset.formulaid,
				type: label.dataset.conditiontype
			});
		});

		expression.innerHTML = getConditionFormula(conditions, + evaltype.value);
	}

	/**
	 * Adds a correlation condition row when condition popup is closed.
	 *
	 * @param {object} condition  Condition object.
	 */
	#addConditionRow(condition) {
		const row_ids = [];

		this.form.querySelectorAll('#condition_table tr[id^=conditions_]').forEach((row) => row_ids.push(row.id));

		condition.row_index ??= 0;

		/*
		 * When controller passes data ir gives only one group ID for each condition. Condition popup can give multiple
		 * IDs and then they are split into rows.
		 */
		if (condition.groupid) {
			condition.groupids = condition.groupid;
		}

		if (condition.groupids) {
			Object.keys(condition.groupids).forEach((key) => {
				let element = {...condition, name: condition.groupids[key], value: key};

				element.groupid = key;

				let has_row = this.#checkConditionRow(element);
				const result = [has_row.some((element) => element === true)];

				if (result[0] === true) {
					return;
				}
				else {
					while (row_ids.some((id) => id === `conditions_${condition.row_index}`)) {
						element.row_index++;
						condition.row_index++;
					}

					element.condition_name = this.#getConditionData(condition);
					element.data = element.name;
					element.conditiontype = condition.conditiontype;
					element.label = num2letter(element.row_index);
					element.groupid = key;
					condition.row_index++;

					const template = new Template(this.form.querySelector('#condition-hostgr-row-tmpl').innerHTML);

					this.form
						.querySelector('#condition_table tbody')
						.insertAdjacentHTML('beforeend', template.evaluate(element));
				}
			});
		}
		else {
			let has_row = this.#checkConditionRow(condition);
			let template;
			const result = [has_row.some((element) => element === true)];

			if (result[0] === true) {
				return;
			}
			else {
				while (row_ids.some((id) => id === `conditions_${condition.row_index}`)) {
					condition.row_index++;
				}

				condition.label = num2letter(condition.row_index);

				switch (parseInt(condition.conditiontype)) {
					case <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG ?>:
					case <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG ?>:
						template = new Template(this.form.querySelector('#condition-tag-row-tmpl').innerHTML);
						break;

					case <?= ZBX_CORR_CONDITION_EVENT_TAG_PAIR ?>:
						condition.condition_name2 = this.#getConditionData(condition)[1];
						condition.condition_operator = this.#getConditionData(condition)[2];
						condition.data_old_tag = this.#getConditionData(condition)[3];
						condition.data_new_tag = this.#getConditionData(condition)[4];
						template = new Template(this.form.querySelector('#condition-tag-pair-row-tmpl').innerHTML);
						break;

					case <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE ?>:
					case <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE ?>:
						condition.condition_name = this.#getConditionData(condition)[0];
						condition.condition_operator = this.#getConditionData(condition)[1];
						condition.tag = this.#getConditionData(condition)[2];
						condition.value = this.#getConditionData(condition)[3];
						template = new Template(this.form.querySelector('#condition-old-new-tag-row-tmpl').innerHTML);
						break;
				}

				condition.condition_name = this.#getConditionData(condition)[0];
				condition.data = this.#getConditionData(condition)[1];
				condition.conditiontype = condition.conditiontype;

				this.form
					.querySelector('#condition_table tbody')
					.insertAdjacentHTML('beforeend', template.evaluate(condition));

				condition.row_index++;
			}
		}
	}

	/**
	 * Returns condition name, value and operator depending on condition type.
	 *
	 * @param {object} condition  Condition object.
	 *
	 * @return {array}
	 */
	#getConditionData(condition) {
		let condition_name;
		let condition_name2;
		let condition_data;
		let operator;
		let value;
		let value2;

		switch (parseInt(condition.conditiontype)) {
			case <?= ZBX_CORR_CONDITION_EVENT_TAG_PAIR ?>:
				condition_name = <?= json_encode(_('Value of old event tag')) ?>;
				condition_name2 = <?= json_encode(_('value of new event tag')) ?>;
				operator = condition.operator_name;
				value = condition.oldtag;
				value2 = condition.newtag;

				return [condition_name, condition_name2, operator, value, value2];

			case <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG ?>:
				condition_name = <?= json_encode(_('Old event tag name')) ?> + ' ' + condition.operator_name;
				condition_data = condition.tag;

				return [condition_name, condition_data];

			case <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG ?>:
				condition_name = <?= json_encode(_('New event tag name')) ?> + ' ' + condition.operator_name;
				condition_data = condition.tag;

				return [condition_name, condition_data];

			case <?= ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP ?>:
				condition_name = <?= json_encode(_('New event host group')) ?> + ' ' + condition.operator_name;

				return [condition_name];

			case <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE ?>:
				condition_name = <?= json_encode(_('Value of old event tag')) ?>;
				value = condition.tag;
				operator = condition.operator_name;
				value2 = condition.value;

				return [condition_name, operator, value, value2];

			case <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE ?>:
				condition_name = <?= json_encode(_('Value of new event tag')) ?>;
				value = condition.tag;
				operator = condition.operator_name;
				value2 = condition.value;

				return [condition_name, operator, value, value2];
		}
	}

	/**
	 * Checks if given condition already exists.
	 *
	 * @param {object} condition  Condition object.
	 *
	 * @return {array}
	 */
	#checkConditionRow(condition) {
		const result = [];

		[...this.form.querySelectorAll('#condition_table tr[id^=conditions_]')].forEach((element) => {
			const type = element.querySelector('input[name*="type"]').value;

			switch (parseInt(type)) {
				case <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG ?>:
				case <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG ?>:
					result.push(condition.conditiontype === type
						&& condition.tag === element.querySelector('input[name*="tag"]').value
					);
					break;

				case <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE ?>:
				case <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE ?>:
					result.push(condition.conditiontype === type
						&& condition.tag === element.querySelector('input[name*="tag"]').value
						&& condition.value === element.querySelector('input[name*="value"]').value
					);
					break;

				case <?= ZBX_CORR_CONDITION_EVENT_TAG_PAIR ?>:
					result.push(condition.conditiontype === type
						&& condition.oldtag === element.querySelector('input[name*="oldtag"]').value
						&& condition.newtag === element.querySelector('input[name*="newtag"]').value
					);
					break;

				case <?= ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP ?>:
					result.push(condition.conditiontype === type
						&& condition.groupid === element.querySelector('input[name*="groupid"]').value
					);
					break;
			}
		});

		return result;
	}

	clone({title, buttons}) {
		this.correlationid = null;

		this.overlay.setProperties({title, buttons});
		this.overlay.unsetLoading();
		this.overlay.recoverFocus();
	}

	delete() {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'correlation.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('correlation')) ?>);

		this.#post(curl.getUrl(), {correlationids: [this.correlationid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	submit() {
		const fields = getFormFields(this.form);

		['name', 'description'].forEach((field) => fields[field] = fields[field].trim());

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', this.correlationid === null ? 'correlation.create' : 'correlation.update');

		this.#post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
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

				return response;
			})
			.then(success_callback)
			.catch((exception) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title,
					messages;

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
			.finally(() => this.overlay.unsetLoading());
	}
}
