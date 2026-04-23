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

	init({ceprule}) {
		this.overlay = overlays_stack.getById('cep_rule_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.ceprule = ceprule;
		this.cep_ruleid = ceprule.cep_ruleid;

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'ceprule.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		this.dialogue.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-condition-add')) {
				this.#openConditionPopup();
			}
			else if (e.target.classList.contains('js-condition-remove')) {
				e.target.closest('tr').remove();
			}
			else if (e.target.classList.contains('js-operation-add')) {
				this.#openOperationPopup();
			}
			else if (e.target.classList.contains('js-operation-remove')) {
				e.target.closest('tr').remove();
			}
		});

		for (const condition of Object.values(ceprule.conditions || [])) {
			this.#addConditionRow(condition);
		}
	}

	#openConditionPopup() {
		const overlay = PopUp('popup.ceprule.condition.edit', {}, {
			dialogueid: 'cep-condition-form',
			dialogue_class: 'modal-popup-medium'
		});

		overlay.$dialogue[0].addEventListener('condition.dialogue.submit', (e) => {
			this.#addConditionRow(e.detail);
		});
	}

	#openOperationPopup() {
		const overlay = PopUp('popup.ceprule.operation.edit', {}, {
			dialogueid: 'cep-operation-form',
			dialogue_class: 'modal-popup-medium'
		});

		overlay.$dialogue[0].addEventListener('operation.dialogue.submit', (e) => {
			this.#addOperationRow(e.detail);
		});
	}

	#addConditionRow(condition) {
		const row_index = this.form.querySelectorAll('#condition_table tr[id^=conditions_]').length;
		const label = String.fromCharCode(65 + row_index);

		const template = `
			<tr id="conditions_${row_index}">
				<td class="label" data-conditiontype="${condition.conditiontype}" data-formulaid="${label}">${label}</td>
				<td>${this.#getConditionDescription(condition)}</td>
				<td>
					<button type="button" class="<?= ZBX_STYLE_LINK_ACTION ?> js-condition-remove"><?= _('Remove') ?></button>
					<input type="hidden" name="conditions[${row_index}][type]" value="${condition.conditiontype}">
					<input type="hidden" name="conditions[${row_index}][operator]" value="${condition.operator || 0}">
				</td>
			</tr>
		`;

		this.form.querySelector('#condition_table tbody').insertAdjacentHTML('beforeend', template);
	}

	#addOperationRow(operation) {
		const row_index = this.form.querySelectorAll('#operation_table tr[id^=operations_]').length;

		const template = `
			<tr id="operations_${row_index}">
				<td>${this.#getOperationDescription(operation)}</td>
				<td>
					<button type="button" class="<?= ZBX_STYLE_LINK_ACTION ?> js-operation-remove"><?= _('Remove') ?></button>
					<input type="hidden" name="operations[${row_index}][type]" value="${operation.type}">
					<input type="hidden" name="operations[${row_index}][execute_when]" value="${operation.execute_when || 0}">
				</td>
			</tr>
		`;

		this.form.querySelector('#operation_table tbody').insertAdjacentHTML('beforeend', template);
	}

	#getConditionDescription(condition) {
		const type_labels = {
			<?= ZBX_CEP_CONDITION_EVENT_NAME ?>: '<?= _('Event name') ?>',
			<?= ZBX_CEP_CONDITION_TAG_NAME ?>: '<?= _('Tag') ?>',
			<?= ZBX_CEP_CONDITION_TAG_VALUE ?>: '<?= _('Tag value') ?>',
			<?= ZBX_CEP_CONDITION_SEVERITY ?>: '<?= _('Severity') ?>',
			<?= ZBX_CEP_CONDITION_HOST ?>: '<?= _('Host') ?>',
			<?= ZBX_CEP_CONDITION_HOST_GROUP ?>: '<?= _('Host group') ?>',
			<?= ZBX_CEP_CONDITION_TIME_PERIOD ?>: '<?= _('Time period') ?>'
		};

		return type_labels[condition.conditiontype] || condition.conditiontype;
	}

	#getOperationDescription(operation) {
		const type_labels = {
			<?= ZBX_CEP_OP_SET_NAME ?>: '<?= _('Set name') ?>',
			<?= ZBX_CEP_OP_CLOSE ?>: '<?= _('Close event') ?>',
			<?= ZBX_CEP_OP_DISCARD ?>: '<?= _('Discard event') ?>',
			<?= ZBX_CEP_OP_SET_SEVERITY ?>: '<?= _('Set severity') ?>',
			<?= ZBX_CEP_OP_INCREASE_SEVERITY ?>: '<?= _('Increase severity') ?>',
			<?= ZBX_CEP_OP_DECREASE_SEVERITY ?>: '<?= _('Decrease severity') ?>',
			<?= ZBX_CEP_OP_SUPPRESS ?>: '<?= _('Suppress') ?>'
		};

		return type_labels[operation.type] || operation.type;
	}

	clone({title, buttons}) {
		this.cep_ruleid = null;

		this.overlay.setProperties({title, buttons});
		this.overlay.unsetLoading();
		this.overlay.recoverFocus();
		this.overlay.containFocus();
	}

	delete() {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'ceprule.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('ceprule')) ?>);

		this.#post(curl.getUrl(), {cep_ruleids: [this.cep_ruleid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	submit(force = false) {
		const fields = getFormFields(this.form);

		fields.name = (fields.name || '').trim();
		fields.description = (fields.description || '').trim();

		if (force) {
			fields.force_update = 1;
		}

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', this.cep_ruleid === null ? 'ceprule.create' : 'ceprule.update');

		this.#post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

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
};
