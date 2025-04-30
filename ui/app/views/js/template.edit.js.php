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

window.template_edit_popup = new class {

	init({templateid, warnings}) {
		this.overlay = overlays_stack.getById('template.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.templateid = templateid;
		this.linked_templateids = this.#getLinkedTemplates();
		this.macros_templateids = null;
		this.show_inherited_macros = false;

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'template.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		if (warnings.length > 0) {
			const message_box = warnings.length > 1
				? makeMessageBox('warning', warnings,
					<?= json_encode(_('Cloned template parameter values have been modified.')) ?>, true, false
				)[0]
				: makeMessageBox('warning', warnings, null, true, false)[0];

			this.form.parentNode.insertBefore(message_box, this.form);
		}

		this.#initActions();
		this.#initTemplateTab();
		this.#initMacrosTab();
		this.#initPopupListeners();

		this.initial_form_fields = getFormFields(this.form);
	}

	#initActions() {
		this.form.addEventListener('click', e => {
			if (e.target.classList.contains('js-unlink')) {
				this.#unlink(e);
			}
			else if (e.target.classList.contains('js-unlink-and-clear')) {
				this.#unlink(e);
				this.#clear(e.target.dataset.templateid);
			}
		});

		// Add visible name input field placeholder.
		const template_name = this.form.querySelector('#template_name');
		const visible_name = this.form.querySelector('#visiblename');

		template_name.addEventListener('input', () => visible_name.placeholder = template_name.value);
		template_name.dispatchEvent(new Event('input'));
	}

	#initTemplateTab() {
		const $groups_ms = $('#template_groups_', this.form);
		const $template_ms = $('#template_add_templates_', this.form);

		$template_ms.on('change', () => {
			$template_ms.multiSelect('setDisabledEntries', this.#getLinkedTemplates().concat(this.#getNewTemplates()));
		});

		$groups_ms.on('change', () => {
			$groups_ms.multiSelect('setDisabledEntries',
				[...document.querySelectorAll('[name^="template_groups["]')]
					.map((input) => input.value)
			);
		});
	}

	#initMacrosTab() {
		this.macros_manager = new HostMacrosManager({
			container: $('#template_macros_container .table-forms-td-right')
		});

		const show_inherited_macros_element = document.getElementById('show_inherited_template_macros');
		this.show_inherited_macros = show_inherited_macros_element.querySelector('input:checked').value == 1;

		this.macros_manager.initMacroTable(this.show_inherited_macros);

		const observer = new IntersectionObserver(entries => {
			if (entries[0].isIntersecting && this.show_inherited_macros) {
				const templateids = this.linked_templateids.concat(this.#getNewTemplates());

				if (this.macros_templateids === null || this.macros_templateids.xor(templateids).length > 0) {
					this.macros_templateids = templateids;

					this.macros_manager.load(this.show_inherited_macros, templateids);
				}
			}
		});
		observer.observe(document.getElementById('template-macro-tab'));

		show_inherited_macros_element.addEventListener('change', e => {
			this.show_inherited_macros = e.target.value == 1;
			this.macros_templateids = this.linked_templateids.concat(this.#getNewTemplates());

			this.macros_manager.load(this.show_inherited_macros, this.macros_templateids);
		});
	}

	#initPopupListeners() {
		const subscriptions = [];

		subscriptions.push(
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_OPEN,
					action: 'template.edit'
				},
				callback: ({data, event}) => {
					if (data.action_parameters.templateid === this.templateid || this.templateid === null) {
						return;
					}

					if (!this.#isConfirmed()) {
						event.preventDefault();
					}
				}
			})
		);

		subscriptions.push(
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_END_SCRIPTING,
					action: this.overlay.dialogueid
				},
				callback: () => ZABBIX.EventHub.unsubscribeAll(subscriptions)
			})
		);
	}

	#isConfirmed() {
		return JSON.stringify(this.initial_form_fields) === JSON.stringify(getFormFields(this.form))
			|| window.confirm(<?= json_encode(_('Any changes made in the current form will be lost.')) ?>);
	}

	/**
	 * Removes the linked template row and the template ID from linked templates object, triggers multiselect change
	 * event.
	 *
	 * @param {object} e  Event object.
	 */
	#unlink(e) {
		e.target.closest('tr').remove();

		this.linked_templateids = this.linked_templateids.filter(value =>
			value != e.target.dataset.templateid
		);

		$('#template_add_templates_', this.form).trigger('change');
	}

	/**
	 * Adds template ID s a hidden input to form within the clear_templates array.
	 *
	 * @param {string} templateid
	 */
	#clear(templateid) {
		const clear_template = document.createElement('input');

		clear_template.type = 'hidden';
		clear_template.name = 'clear_templates[]';
		clear_template.value = templateid;
		this.form.appendChild(clear_template);
	}

	/**
	 * Helper to get linked template IDs as an array.
	 *
	 * @return {array}  Templateids.
	 */
	#getLinkedTemplates() {
		const linked_templateids = [];

		this.form.querySelectorAll('[name^="templates["').forEach((input) => {
			linked_templateids.push(input.value);
		});

		return linked_templateids;
	}

	/**
	 * Helper to get added template IDs as an array.
	 *
	 * @return {array}  Templateids.
	 */
	#getNewTemplates() {
		const $template_multiselect = $('#template_add_templates_', this.form);
		const templateids = [];

		if ($template_multiselect.length) {
			$template_multiselect.multiSelect('getData').forEach(template => {
				templateids.push(template.id);
			});
		}

		return templateids;
	}

	clone() {
		const parameters = this.#trimFields(getFormFields(this.form));

		parameters.clone = 1;
		parameters.templateid = this.templateid;
		this.#prepareFields(parameters);

		this.overlay = ZABBIX.PopupManager.open('template.edit', parameters);
	}

	deleteAndClear() {
		this.delete(true);
	}

	delete(clear = false) {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'template.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('template')) ?>);

		let data = {templateids: [this.templateid]}
		if (clear) {
			data.clear = 1;
		}

		this.#post(curl.getUrl(), data, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	submit() {
		const fields = getFormFields(this.form);

		if (this.templateid !== null) {
			fields.templateid = this.templateid;
		}

		this.#prepareFields(fields);
		this.#trimFields(fields);
		this.overlay.setLoading();

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', this.templateid === null ? 'template.create' : 'template.update');

		this.#post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	#prepareFields(parameters) {
		const mappings = [
			{from: 'template_groups', to: 'groups'},
			{from: 'template_add_templates', to: 'add_templates'},
			{from: 'show_inherited_template_macros', to: 'show_inherited_macros'}
		];

		for (const mapping of mappings) {
			parameters[mapping.to] = parameters[mapping.from];
			delete parameters[mapping.from];
		}

		return parameters;
	}

	#trimFields(fields) {
		// Trim all string fields.
		for (let key in fields) {
			if (typeof fields[key] === 'string') {
				fields[key] = fields[key].trim();
			}
		}

		// Trim tag input fields.
		if ('tags' in fields) {
			for (const key in fields.tags) {
				const tag = fields.tags[key];
				tag.tag = tag.tag.trim();

				if ('name' in tag) {
					tag.name = tag.name.trim();
				}
				if ('value' in tag) {
					tag.value = tag.value.trim();
				}

				delete tag.automatic;
			}
		}

		// Trim macro input fields.
		if ('macros' in fields) {
			for (const key in fields.macros) {
				const macro = fields.macros[key];
				macro.macro = macro.macro.trim();

				if ('value' in macro) {
					macro.value = macro.value.trim();
				}
				if ('description' in macro) {
					macro.description = macro.description.trim();
				}
			}
		}

		return fields;
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
}
