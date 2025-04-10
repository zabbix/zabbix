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
 * Widget editor component for dialogue management and event coordination.
 */
class CWidgetEditDialogue {

	static VALIDATOR_PRIORITY_UPDATE = 0;
	static VALIDATOR_PRIORITY_SUBMIT = 1;

	#dashboard;
	#dashboard_data;

	#type;
	#type_original;
	#name;
	#view_mode;
	#fields;
	#fields_original;

	#is_new;
	#is_unsaved;

	/**
	 * @type {CWidgetEditSandbox}
	 */
	#sandbox;

	/**
	 * @type {CWidgetEditValidator}
	 */
	#validator;

	#position_fix;

	#overlay;

	#abort_controller = new AbortController();

	#input_throttle_timeout = null;

	#messages;

	constructor({dashboard}) {
		this.#dashboard = dashboard;
		this.#dashboard_data = dashboard.getData();
	}

	run({type, name = null, view_mode = null, fields = null, is_new, sandbox, validator, position_fix}) {
		this.#type = type;
		this.#type_original = type;
		this.#name = name;
		this.#view_mode = view_mode;
		this.#fields = fields;
		this.#fields_original = fields;

		this.#is_new = is_new;
		this.#is_unsaved = is_new;

		this.#sandbox = sandbox;
		this.#validator = validator;

		this.#position_fix = position_fix;

		this.#validator.onResult({
			callback: result => this.#onValidatorResult(result),
			priority: CWidgetEditDialogue.VALIDATOR_PRIORITY_UPDATE
		});

		this.#open();
		this.#activate();
	}

	#open() {
		this.#overlay = PopUp(`widget.${this.#type}.edit`, {
			templateid: this.#dashboard_data.templateid ?? undefined,
			type: this.#type,
			name: this.#name ?? undefined,
			view_mode: this.#view_mode ?? undefined,
			fields: this.#fields ?? undefined,
			is_new: this.#is_new ? 1 : undefined
		}, {
			// Form name is used as dialogue ID.
			dialogueid: CWidgetForm.FORM_NAME_PRIMARY,
			dialogue_class: 'modal-widget-configuration',
			is_modal: false,
			is_draggable: true,
			position_fix: this.#position_fix,
			prevent_navigation: true
		});
	}

	#activate() {
		const overlay_dialogue = this.#overlay.$dialogue[0];

		overlay_dialogue.addEventListener(CWidgetForm.EVENT_READY, () => this.#onReady());
		overlay_dialogue.addEventListener(CWidgetForm.EVENT_RELOAD_REQUEST, () => this.#onReloadRequest());
		overlay_dialogue.addEventListener(CWidgetForm.EVENT_SUBMIT_REQUEST, () => this.#onSubmitRequest());
		overlay_dialogue.addEventListener(CWidgetForm.EVENT_CLOSE, e => this.#onClose(e));

		ZABBIX.EventHub.subscribe({
			require: {
				context: CWidgetForm.EVENT_CONTEXT,
				event: CWidgetFormEvent.EVENT_UPDATE,
				form_name: CWidgetForm.FORM_NAME_PRIMARY
			},
			callback: () => this.#onInput(),
			signal: this.#abort_controller.signal
		});
	}

	#deactivate() {
		this.#abort_controller.abort();

		if (this.#input_throttle_timeout !== null) {
			clearTimeout(this.#input_throttle_timeout);
		}
	}

	#onReady() {
		const {type, name, view_mode, fields} = this.#getProperties();

		this.#type = type;
		this.#name = name;
		this.#view_mode = view_mode;
		this.#fields = fields;

		this.#validator.check({type, name, fields});
	}

	#onReloadRequest() {
		const {type, name, view_mode, fields} = this.#getProperties();

		if (this.#type === type) {
			this.#name = name;
			this.#view_mode = view_mode;
			this.#fields = fields;
		}
		else {
			this.#type = type;
			this.#name = null;
			this.#view_mode = null;
			this.#fields = null;
		}

		this.#validator.stop();
		this.#open();

		this.#is_unsaved = true;
	}

	#onSubmitRequest() {
		const {type, name, view_mode, fields} = this.#getProperties();

		if (type !== this.#type || name !== this.#name || view_mode !== this.#view_mode
				|| JSON.stringify(fields) !== JSON.stringify(this.#fields)) {
			this.#type = type;
			this.#name = name;
			this.#view_mode = view_mode;
			this.#fields = fields;

			this.#validator.check({type, name, fields});

			this.#is_unsaved = true;
		}

		const on_result = () => {
			if (this.#messages.length > 0) {
				this.#overlay.unsetLoading();

				this.#setError({messages: this.#messages});
			}
			else {
				this.#sandbox.apply();

				overlayDialogueDestroy(this.#overlay.dialogueid);
			}
		};

		if (this.#validator.inProgress()) {
			this.#validator.onResult({
				callback: on_result,
				priority: CWidgetEditDialogue.VALIDATOR_PRIORITY_SUBMIT,
				once: true
			});
		}
		else {
			on_result();
		}
	}

	#onClose(e) {
		if (e.detail.close_by === Overlay.prototype.CLOSE_BY_USER) {
			if (!this.#is_new && this.#is_unsaved && !confirm(t('Widget configuration will be reverted.'))) {
				e.preventDefault();

				return;
			}

			this.#sandbox.cancel();
		}

		this.#validator.stop();
		this.#deactivate();
	}

	#onInput() {
		this.#validator.stop();

		if (this.#input_throttle_timeout !== null) {
			clearTimeout(this.#input_throttle_timeout);
		}

		this.#input_throttle_timeout = setTimeout(() => {
			const {type, name, view_mode, fields} = this.#getProperties();

			this.#type = type;
			this.#name = name;
			this.#view_mode = view_mode;
			this.#fields = fields;

			this.#validator.check({type, name, fields});
		}, CDashboard.WIDGET_EDIT_INPUT_THROTTLE_MS);

		this.#is_unsaved = true;
	}

	#onValidatorResult(result) {
		this.#messages = [];

		if ('error' in result) {
			this.#setError(result.error);
		}
		else {
			if ('messages' in result) {
				this.#messages = result.messages;

				if (!this.#is_unsaved && this.#messages.length > 0) {
					this.#setError({messages: this.#messages});
				}
			}

			const fields = result.fields;

			if ('reference' in fields) {
				fields.reference = !this.#is_new && this.#type === this.#type_original
					? this.#fields_original.reference
					: this.#dashboard.createReference();
			}

			if (this.#is_new || this.#is_unsaved) {
				this.#sandbox.update({
					type: this.#type,
					name: this.#name,
					view_mode: this.#view_mode,
					fields,
					is_configured: this.#messages.length === 0
				});
			}
		}
	}

	#getProperties() {
		const form = this.#overlay.$dialogue.$body[0].querySelector('form');
		const fields = getFormFields(form);

		const type = fields.type;
		const name = fields.name;
		const view_mode = fields.show_header === '1'
			? ZBX_WIDGET_VIEW_MODE_NORMAL
			: ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER;

		delete fields.type;
		delete fields.name;
		delete fields.show_header;

		return {type, name, view_mode, fields};
	}

	#setError({messages, title = null}) {
		const form = this.#overlay.$dialogue.$body[0].querySelector('form');

		for (const element of form.parentNode.children) {
			if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
				element.parentNode.removeChild(element);
			}
		}

		form.parentNode.insertBefore(makeMessageBox('bad', messages, title)[0], form);
	}
}
