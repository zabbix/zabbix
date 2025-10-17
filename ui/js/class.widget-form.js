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
 * Helper class for widget configuration forms.
 */
class CWidgetForm {

	static FORM_NAME_PRIMARY = 'widget_form';

	static EVENT_CONTEXT = 'widget-form';

	static EVENT_READY = 'dialogue.ready';
	static EVENT_CLOSE = 'dialogue.close';
	static EVENT_RELOAD_REQUEST = 'dialogue.reload-request';
	static EVENT_SUBMIT_REQUEST = 'dialogue.submit-request';

	/**
	 * Add widget form field to the respective widget form.
	 *
	 * @param {CWidgetField} field
	 */
	static addField(field) {
		const form_name = field.getFormName();

		if (document.forms[form_name].fields === undefined) {
			document.forms[form_name].fields = {};
		}

		document.forms[form_name].fields[field.getName()] = field;
	}

	/**
	 * @type {Overlay}
	 */
	overlay;

	/**
	 * @type {AbortController}
	 */
	#events_abort_controller;

	constructor({
		form_name = CWidgetForm.FORM_NAME_PRIMARY,
		tab_indicators_tabs_id = undefined
	} = {}) {
		// Form name is used as dialogue ID.
		this.overlay = overlays_stack.getById(form_name);

		this.#initialize({tab_indicators_tabs_id});

		if (form_name === CWidgetForm.FORM_NAME_PRIMARY) {
			this.#initializePrimary();
		}

		this.#events_abort_controller = new AbortController();

		const signal = this.#events_abort_controller.signal;

		ZABBIX.EventHub.subscribe({
			require: {
				context: CWidgetField.EVENT_CONTEXT,
				event: CWidgetFieldEvent.EVENT_UPDATE,
				form_name
			},
			callback: () => this.registerUpdateEvent(),
			signal
		});

		const dialogue = this.overlay.$dialogue[0];

		dialogue.addEventListener('dialogue.reload', () => this.#endScripting(), {signal});
		dialogue.addEventListener('dialogue.close', e => {
			if (!e.defaultPrevented) {
				this.#endScripting();
			}
		}, {signal});
	}

	/**
	 * Get form element. Dynamic, not stored locally.
	 *
	 * @returns {HTMLFormElement}
	 */
	getForm() {
		return this.overlay.$dialogue.$body[0].querySelector('form');
	}

	/**
	 * Get form field (descendant of CWidgetField class).
	 *
	 * @param {string} name
	 *
	 * @returns {CWidgetField}
	 */
	getField(name) {
		return this.getForm().fields[name];
	}

	/**
	 * Mark form as ready for working with. The framework will not access form fields unless it's ready.
	 *
	 * It's mandatory to invoke this method by dialogue itself as soon as the form is ready.
	 */
	ready() {
		this.fire(CWidgetForm.EVENT_READY);
	}

	/**
	 * Reload the dialogue.
	 *
	 * This method is meant for widget type switching, and it shall not be used by widgets.
	 */
	reload() {
		this.fire(CWidgetForm.EVENT_RELOAD_REQUEST);
	}

	/**
	 * Submit the dialogue.
	 *
	 * The caller must implement respective event listener.
	 */
	submit() {
		this.fire(CWidgetForm.EVENT_SUBMIT_REQUEST);
	}

	/**
	 * Dispatch dialogue event.
	 *
	 * @param {string} event_name
	 * @param {Object} data
	 */
	fire(event_name, data = {}) {
		this.overlay.$dialogue[0].dispatchEvent(
			new CustomEvent(event_name, {detail: {overlay: this.overlay, ...data}})
		);
	}

	/**
	 * Inform the framework about the update event on the form level.
	 *
	 * The framework will validate the configuration and update the widget on the fly.
	 */
	registerUpdateEvent() {
		ZABBIX.EventHub.publish(new CWidgetFormEvent({
			descriptor: {
				context: CWidgetForm.EVENT_CONTEXT,
				event: CWidgetFormEvent.EVENT_UPDATE,
				form_name: this.getForm().getAttribute('name')
			}
		}));
	}

	#initialize({tab_indicators_tabs_id}) {
		const form = this.getForm();

		form.addEventListener('change', e => {
			const do_trim = e.target.matches(
				'input[type="text"]:not([data-no-trim="1"]), textarea:not([data-no-trim="1"])'
			);

			if (do_trim) {
				e.target.value = e.target.value.trim();
			}
		}, {capture: true});

		for (const fieldset of form.querySelectorAll(`fieldset.${ZBX_STYLE_COLLAPSIBLE}`)) {
			new CFormFieldsetCollapsible(fieldset);
		}

		try {
			new TabIndicators(tab_indicators_tabs_id);
		}
		catch (error) {
		}
	}

	#initializePrimary() {
		const form = this.getForm();

		form.querySelector('[name="type"]').addEventListener('change', () => this.reload());
		form.querySelector('[name="show_header"]').addEventListener('change', () => this.registerUpdateEvent());
		form.querySelector('[name="name"]').addEventListener('input', () => this.registerUpdateEvent());

		this.overlay.$dialogue.$footer[0].querySelector('.js-button-submit')
			.addEventListener('click', () => this.submit());
	}

	#endScripting() {
		this.#events_abort_controller.abort();

		ZABBIX.EventHub.invalidateData({
			context: CWidgetForm.EVENT_CONTEXT,
			form_name: this.getForm().getAttribute('name')
		});
	}
}
