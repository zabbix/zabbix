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
class CWidgetEditDialogue extends EventTarget {

	static INPUT_THROTTLE_MS = 500;

	static EVENT_LOAD = 'load';
	static EVENT_READY = 'ready';

	static VALIDATOR_PRIORITY_UPDATE = 0;
	static VALIDATOR_PRIORITY_SUBMIT = 1;

	#dashboard;
	#dashboard_data;

	#resolve;

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

	#overlay = null;

	#abort_controller = new AbortController();

	#input_throttle_timeout = null;

	#messages = [];

	#last_type = null;
	#last_type_reference = null;

	#is_ready = false;
	#is_validated = false;

	constructor({dashboard}) {
		super();

		this.#dashboard = dashboard;
		this.#dashboard_data = dashboard.getData();
	}

	run({type, name = null, view_mode = null, fields = null, is_new, sandbox, validator, position_fix}) {
		return new Promise(resolve => {
			this.#resolve = resolve;

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

			this.dispatchEvent(new CustomEvent(CWidgetEditDialogue.EVENT_LOAD));
		});
	}

	promiseTrySubmit() {
		if (!this.#is_ready) {
			return Promise.resolve(false);
		}

		this.#overlay.setLoading();

		return this.#promiseSubmitReadiness()
			.then(() => {
				this.#overlay.unsetLoading();

				return this.#trySubmit();
			});
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
		this.#is_ready = true;

		const {type, name, view_mode, fields} = this.#getProperties();

		this.#type = type;
		this.#name = name;
		this.#view_mode = view_mode;
		this.#fields = fields;

		this.#validator.check({type, name, fields});

		this.dispatchEvent(new CustomEvent(CWidgetEditDialogue.EVENT_READY));
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
		this.#overlay.setLoading();

		this.#promiseSubmitReadiness()
			.then(() => {
				this.#overlay.unsetLoading();

				this.#trySubmit();
			});
	}

	#onClose(e) {
		const is_submit = e.detail.close_by === Overlay.prototype.CLOSE_BY_SCRIPT;

		if (!is_submit) {
			this.#sandbox.cancel();
		}

		this.#validator.stop();
		this.#deactivate();

		this.#resolve({
			is_submit,
			position_fix: e.detail.position_fix
		});
	}

	#onInput() {
		this.#validator.stop();

		if (this.#input_throttle_timeout !== null) {
			clearTimeout(this.#input_throttle_timeout);
		}

		this.#input_throttle_timeout = setTimeout(() => this.#validate(), CWidgetEditDialogue.INPUT_THROTTLE_MS);
	}

	#onValidatorResult(result) {
		this.#is_validated = true;

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
				if (!this.#is_new && this.#type === this.#type_original) {
					fields.reference = this.#fields_original.reference;
				}
				else {
					if (this.#last_type !== this.#type) {
						this.#last_type = this.#type;
						this.#last_type_reference = this.#dashboard.createReference();
					}

					fields.reference = this.#last_type_reference;
				}
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

	#promiseSubmitReadiness() {
		return new Promise(resolve => {
			this.#validate();

			if (this.#validator.inProgress()) {
				this.#validator.onResult({
					callback: resolve,
					priority: CWidgetEditDialogue.VALIDATOR_PRIORITY_SUBMIT,
					once: true
				});
			}
			else {
				resolve();
			}
		});
	}

	#trySubmit() {
		if (this.#messages.length > 0) {
			this.#setError({messages: this.#messages});

			return false;
		}
		else {
			this.#sandbox.apply();

			overlayDialogueDestroy(this.#overlay.dialogueid);

			return true;
		}
	}

	#validate() {
		const {type, name, view_mode, fields} = this.#getProperties();

		const is_modified = type !== this.#type || name !== this.#name || view_mode !== this.#view_mode
			|| JSON.stringify(fields) !== JSON.stringify(this.#fields);

		if (is_modified) {
			this.#type = type;
			this.#name = name;
			this.#view_mode = view_mode;
			this.#fields = fields;

			this.#is_unsaved = true;
		}

		if (is_modified || (!this.#is_validated && !this.#validator.inProgress())) {
			this.#validator.check({type, name, fields});
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
