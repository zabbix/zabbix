<?php declare(strict_types = 0);
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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */
?>

window.template_edit_popup = new class {

	init({templateid, linked_templates, readonly, parent_hostid, warnings}) {
		this.overlay = overlays_stack.getById('templates-form');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.templateid = templateid;
		this.linked_templateids = Object.keys(linked_templates);
		this.readonly = readonly;
		this.parent_hostid = parent_hostid;

		if (warnings && warnings.length > 0) {
			const message_box = warnings.length > 1
				? makeMessageBox('warning', warnings,
					<?= json_encode(_('Cloned template parameter values have been modified.')) ?>, true, false
				)[0]
				: makeMessageBox('warning', warnings, null, true, false)[0];

			this.form.parentNode.insertBefore(message_box, this.form);
		}

		this.#initActions();
		this.initial_form_fields = getFormFields(this.form);
	}

	#initActions() {
		this.#initMacrosTab();
		this.#updateMultiselect();
		this.unlink_clear_templateids = {};

		this.form.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-edit-linked')) {
				this.#editLinkedTemplate({templateid: e.target.dataset.templateid});
			}
			else if (e.target.classList.contains('unlink')) {
				e.target.closest('tr').remove();

				this.linked_templateids = this.linked_templateids.filter(value =>
					value !== e.target.dataset.templateid
				);

				this.form.querySelector('#show_inherited_template_macros').dispatchEvent(new Event('change'));
				$('#template_add_templates_').trigger('change');
			}
			else if (e.target.classList.contains('unlink-and-clear')) {
				e.target.closest('tr').remove();

				this.linked_templateids = this.linked_templateids.filter(value =>
					value !== e.target.dataset.templateid
				);

				this.form.querySelector('#show_inherited_template_macros').dispatchEvent(new Event('change'));
				this.unlink_clear_templateids[`${e.target.dataset.templateid}`] = e.target.dataset.templateid;
				this.#unlinkAndClearTemplate(e.target.dataset.templateid);
				$('#template_add_templates_').trigger('change');
			}
		});

		// Add visible name input field placeholder.
		const template_name = this.form.querySelector('#template_name');
		const visible_name = this.form.querySelector('#visiblename');

		template_name.addEventListener('input', () => visible_name.placeholder = template_name.value);
		template_name.dispatchEvent(new Event('input'));
	}

	#unlinkAndClearTemplate(templateid) {
		const clear_template = document.createElement('input');

		clear_template.type = 'hidden';
		clear_template.name = 'clear_templates[]';
		clear_template.value = templateid;
		this.form.appendChild(clear_template);

		$('#add_templates_').trigger('change');
	}

	#editLinkedTemplate(data) {
		const form_fields = getFormFields(this.form);
		const diff = JSON.stringify(this.initial_form_fields) === JSON.stringify(form_fields);

		if (!diff) {
			if (!window.confirm(<?= json_encode(_('Any changes made in the current form will be lost.')) ?>)) {
				return;
			}
		}

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'template.edit');

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				response.buttons.push({
					'title': t('Cancel'),
					'class': 'btn-alt js-cancel',
					'cancel': true,
					'action': function() {}
				});

				const new_data = {
					content: response.body,
					buttons: response.buttons,
					title: response.header,
					script_inline: response.script_inline
				};

				this.overlay.setProperties(new_data);
			})
			.catch((exception) =>  {
				clearMessages();

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title);

				addMessage(message_box);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	#initMacrosTab() {
		this.macros_manager = new HostMacrosManager({
			readonly: this.readonly,
			parent_hostid: this.parent_hostid,
			source: 'templates-form'
		});

		$('#template-tabs').on('tabscreate tabsactivate', (event, ui) => {
			let panel = (event.type === 'tabscreate') ? ui.panel : ui.newPanel;

			if (panel.attr('id') === 'template-macro-tab') {
				const macros_initialized = panel.data('macros_initialized') || false;

				// Please note that macro initialization must take place once and only when the tab is visible.
				if (event.type === 'tabsactivate') {
					let panel_templateids = panel.data('templateids') || [];
					const templateids = this.#getAddTemplates();

					const merged_templateids = panel_templateids.concat(templateids).filter(function(e) {
						return !(panel_templateids.includes(e) && templateids.includes(e));
					});

					if (merged_templateids.length > 0) {
						panel.data('templateids', templateids);
						this.macros_manager.load(
							this.form.querySelector('input[name=show_inherited_template_macros]:checked').value == 1,
							this.linked_templateids.concat(templateids),
							'templates-form'
						);

						panel.data('macros_initialized', true);
					}
				}

				if (macros_initialized) {
					return;
				}

				// Initialize macros.
				if (this.readonly) {
					$('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', '#template_tbl_macros').textareaFlexible();
				}
				else {
					this.macros_manager.initMacroTable(
						this.form.querySelector('input[name=show_inherited_template_macros]:checked').value == 1
					);
				}

				panel.data('macros_initialized', true);
			}
		});

		this.form.querySelector('#show_inherited_template_macros').onchange = () => {
			this.macros_manager.load(
				this.form.querySelector('input[name=show_inherited_template_macros]:checked').value == 1,
				this.linked_templateids.concat(this.#getAddTemplates()),
				'templates-form'
			);
		}
	}

	clone() {
		this.overlay.setLoading();
		const parameters = this.#trimFields(getFormFields(this.form));

		parameters.clone = 1;
		parameters.templateid = this.templateid;
		this.#prepareFields(parameters);

		this.overlay = PopUp('template.edit', parameters, {
			dialogueid: 'templates-form',
			dialogue_class: 'modal-popup-large',
			prevent_navigation: true
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

	delete(clear = false) {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'template.delete');
		curl.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
			<?= json_encode(CCsrfTokenHelper::get('template'), JSON_THROW_ON_ERROR) ?>
		);

		let data = {templateids: [this.templateid]}
		if (clear) {
			data.clear = 1;
		}

		this.#post(curl.getUrl(), data, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.delete', {detail: response}));
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

	#updateMultiselect() {
		const $groups_ms = $('#template_groups_, #template_group_links_');
		const $template_ms = $('#template_add_templates_');

		$template_ms.on('change', () => {
			$template_ms.multiSelect('setDisabledEntries', this.#getAllTemplates());
		});

		$groups_ms.on('change', () => {
			$groups_ms.multiSelect('setDisabledEntries',
				[...document.querySelectorAll('[name^="template_groups["], [name^="template_group_links["]')]
					.map((input) => input.value)
			)
		});
	}

	/**
	 * Collects IDs selected in "Add templates" multiselect.
	 *
	 * @return {array}  Templateids.
	 */
	#getAddTemplates() {
		const $ms = $('#template_add_templates_');
		let templateids = [];

		// Readonly forms don't have multiselect.
		if ($ms.length) {
			// Collect IDs from Multiselect.
			$ms.multiSelect('getData').forEach(function (template) {
				templateids.push(template.id);
			});
		}

		return templateids;
	}

	/**
	 * Collects ids of currently active (linked + new) templates.
	 *
	 * @return {array}  Templateids.
	 */
	#getAllTemplates() {
		return this.#getLinkedTemplates().concat(this.#getNewTemplates());
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
		const $template_multiselect = $('#template_add_templates_');
		const templateids = [];

		// Readonly forms don't have multiselect.
		if ($template_multiselect.length) {
			$template_multiselect.multiSelect('getData').forEach(template => {
				templateids.push(template.id);
			});
		}

		return templateids;
	}
}
