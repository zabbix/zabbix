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
?>


window.valuemap_edit_popup = {

	type_placeholder: <?= json_encode([
		VALUEMAP_MAPPING_TYPE_EQUAL => _('value'),
		VALUEMAP_MAPPING_TYPE_GREATER_EQUAL => _('value'),
		VALUEMAP_MAPPING_TYPE_LESS_EQUAL => _('value'),
		VALUEMAP_MAPPING_TYPE_IN_RANGE => _('value'),
		VALUEMAP_MAPPING_TYPE_REGEXP => _('regexp')
	]) ?>,

	init({rules, mappings}) {
		this.overlay = overlays_stack.getById('valuemap_edit');
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = rules !== null ? new CForm(this.form_element, rules) : null;
		this.mappings = mappings;

		this.initMappingTable();
	},

	submit() {
		this.removePopupMessages();
		jQuery(this.form_element).trimValues(['input[type="text"]']);

		if (this.form !== null) {
			const fields = this.form.getAllValues();

			this.form.validateSubmit(fields).then(result => {
				if (!result) {
					this.overlay.unsetLoading();

					return;
				}

				this.submitForm(fields);
			});
		}
		else {
			this.submitForm(getFormFields(this.form_element));
		}
	},

	submitForm(fields) {
		const curl = new Curl(this.form_element.getAttribute('action'), false);
		curl.setArgument('action', 'popup.valuemap.check');

		fields.source = this.form_element['source'].value;

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData(fields)
		})
			.then(response => response.json())
			.then(response => {
				if ('form_errors' in response) {
					this.form.setErrors(response.form_errors, true, true);
					this.form.renderErrors();

					return;
				}
				else if ('error' in response) {
					throw {error: response.error};
				}

				new AddValueMap(response, 'edit' in fields ? this.overlay.element.closest('tr') : null);

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.overlay.$dialogue[0].dispatchEvent(
					new CustomEvent('hostid' in fields ? 'dialogue.update' : 'dialogue.create', {
						detail: {
							success: response.success
						}
					})
				);
			})
			.catch(this.ajaxExceptionHandler)
			.finally(() => {
				this.overlay.unsetLoading();
			});
	},

	removePopupMessages() {
		for (const element of this.form_element.parentNode.children) {
			if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
				element.parentNode.removeChild(element);
			}
		}
	},

	initMappingTable() {
		this.table = document.getElementById('mappings-table');
		this.observer = new MutationObserver(this.mutationHandler);

		// Observe changes for form fields: type, value.
		this.observer.observe(this.table, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: ['value']
		});

		jQuery(this.table)
			.dynamicRows({
				template: '#mapping-row-tmpl',
				rows: this.mappings,
				allow_empty: true,
				sortable: true,
				sortable_options: {
					target: 'tbody',
					selector_span: ':not(.error-container-row)',
					selector_handle: '.<?= ZBX_STYLE_DRAG_ICON ?>',
					freeze_end: 1
				}
			})
			.on('tableupdate.dynamicRows', (e) => {
				e.target.querySelectorAll('.form_row').forEach((row, index) => {
					for (const field of row.querySelectorAll('[name^="mappings["]')) {
						field.name = field.name.replace(/\[\d+]/g, `[${index}]`);
					}
				});
			});

		this.updateOnTypeChange();
	},

	updateOnTypeChange() {
		const default_select = this.table.querySelector(`z-select[value="${<?= VALUEMAP_MAPPING_TYPE_DEFAULT ?>}"]`);

		this.table.querySelectorAll('tr').forEach((row) => {
			const select = row.querySelector('z-select[name$="[type]"]');
			const input = row.querySelector('input[name$="[value]"]');

			if (select) {
				select.getOptionByValue(<?= VALUEMAP_MAPPING_TYPE_DEFAULT ?>).disabled = (default_select
					&& select !== default_select
				);
				input.classList.toggle('visibility-hidden', (select === default_select));
				input.disabled = (select === default_select);
				input.setAttribute('placeholder', this.type_placeholder[select.value] || '');
			}
		});
	},

	mutationHandler(mutation_records) {
		const update = mutation_records.filter((mutation) => {
			return (mutation.target.tagName === 'INPUT' && mutation.target.getAttribute('name').substr(-6) === '[type]')
				|| (mutation.target.tagName === 'TBODY' && mutation.removedNodes.length > 0);
		});

		if (update.length) {
			valuemap_edit_popup.updateOnTypeChange();
		}
	},

	ajaxExceptionHandler: (exception) => {
		const form = valuemap_edit_popup.form_element;
		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		const message_box = makeMessageBox('bad', messages, title)[0];

		form.parentNode.insertBefore(message_box, form);
	}
};
