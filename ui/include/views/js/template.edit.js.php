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

	init({template}) {
		this.overlay = overlays_stack.getById('templates-form');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.templateid = template.templateid;
		this.template = template;
		this.linked_templateids = Object.keys(this.template.linked_templates);
		this.clone_template = 0;

		this.#initActions();
	}

	#initActions() {
		this.#initMacrosTab();
		this.#updateMultiselect();
		this.unlink_clear_templateids = {};

		this.form.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-edit-linked')) {
				this.#editLinkedTemplate({templateid: e.target.dataset.templateid}, e.target.dataset.templateid);

			}
			else if (e.target.classList.contains('unlink')) {
				e.target.closest('tr').remove();

				this.linked_templateids = this.linked_templateids.filter(value =>
					value !== e.target.dataset.templateid
				);

				this.form.querySelector('#show_inherited_macros').dispatchEvent(new Event('change'));

			}
			else if (e.target.classList.contains('unlink-and-clear')) {
				e.target.closest('tr').remove();

				this.linked_templateids = this.linked_templateids.filter(value =>
					value !== e.target.dataset.templateid
				);

				this.form.querySelector('#show_inherited_macros').dispatchEvent(new Event('change'));
				this.unlink_clear_templateids[`${e.target.dataset.templateid}`] = e.target.dataset.templateid
				this.#unlinkAndClearTemplate(e.target, e.target.dataset.templateid)
			}
		});

		// Add visible name input field placeholder.
		const template_name = this.form.querySelector('#template_name');
		const visible_name = this.form.querySelector('#visiblename');

		template_name.addEventListener('input', () => visible_name.placeholder = template_name.value);
		template_name.dispatchEvent(new Event('input'));
	}

	#unlinkAndClearTemplate(button, templateid) {
		const clear_tmpl = document.createElement('input');
		clear_tmpl.type = 'hidden';
		clear_tmpl.name = 'clear_templates[]';
		clear_tmpl.value = templateid;
		button.form.appendChild(clear_tmpl);
	}

	#editLinkedTemplate(parameters) {
		// todo - get approval about warning message.
		// todo - wait for decision about checking changes in form.
		const confirmation = <?= json_encode(
			_('Open the linked template configuration form? Any changes you made may not be saved.')
		) ?>;

		if (!window.confirm(confirmation)) {
			return;
		}

		this.dialogue.dispatchEvent(new CustomEvent('edit.linked', {detail: {templateid: parameters.templateid}}));
	}

	#initMacrosTab() {
		this.macros_manager = new HostMacrosManager({
			readonly: this.template.readonly,
			parent_hostid: this.template.parent_hostid ?? null
		});

		$('#tabs').on('tabscreate tabsactivate', (event, ui) => {
			let panel = (event.type === 'tabscreate') ? ui.panel : ui.newPanel;

			if (panel.attr('id') === 'macroTab') {
				const macros_initialized = panel.data('macros_initialized') || false;

				// Please note that macro initialization must take place once and only when the tab is visible.
				if (event.type === 'tabsactivate') {
					let panel_templateids = panel.data('templateids') || [];
					const templateids = this.#getAddTemplates();

					if (panel_templateids.xor(templateids).length > 0) {
						panel.data('templateids', templateids);

						this.macros_manager.load(
							this.form.querySelector('input[name=show_inherited_macros]:checked').value == 1,
							this.linked_templateids.concat(templateids)
						);

						panel.data('macros_initialized', true);
					}
				}

				if (macros_initialized) {
					return;
				}

				// Initialize macros.
				if (this.template.readonly) {
					$('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', '#tbl_macros').textareaFlexible();
				}
				else {
					this.macros_manager.initMacroTable(
						this.form.querySelector('input[name=show_inherited_macros]:checked').value == 1
					);
				}

				panel.data('macros_initialized', true);
			}
		});

		this.form.querySelector('#show_inherited_macros').onchange = () => {
			this.macros_manager.load(
				this.form.querySelector('input[name=show_inherited_macros]:checked').value == 1,
				this.linked_templateids.concat(this.#getAddTemplates())
			);
		}
	}

	clone({title, buttons}) {
		this.templateid = null;
		this.clone_template = 1;

		this.overlay.setProperties({title, buttons});
		this.overlay.unsetLoading();
		this.overlay.containFocus();
		this.overlay.recoverFocus();
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

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.delete', {detail: response.success}));
		});
	}

	submit() {
		const fields = getFormFields(this.form);

		if (this.templateid !== null) {
			fields.templateid = this.templateid;
		}

		if (this.clone_template === 1) {
			fields.clone = 1;
		}

		this.#trimFields(fields);
		this.overlay.setLoading();

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', this.templateid === null ? 'template.create' : 'template.update');

		this.#post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.success}));
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
		const $groups_ms = $('#groups_, #group_links_');
		const $template_ms = $('#add_templates_');

		$template_ms.on('change', () => $template_ms.multiSelect('setDisabledEntries', this.#getAllTemplates()));

		$groups_ms.on('change', () =>
			$groups_ms.multiSelect('setDisabledEntries',
				[...document.querySelectorAll('[name^="groups["], [name^="group_links["]')].map((input) => input.value)
			)
		);
	}

	/**
	 * Collects IDs selected in "Add templates" multiselect.
	 *
	 * @return {array}  Templateids.
	 */
	#getAddTemplates() {
		const $ms = $('#add_templates_');
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
		const $template_multiselect = $('#add_templates_');
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
