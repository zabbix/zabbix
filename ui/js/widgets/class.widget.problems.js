/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CWidgetProblems extends CWidget {

	_init() {
		super._init();

		this._has_contents = false;
		this._opened_eventids = [];
	}

	_registerEvents() {
		super._registerEvents();

		this._events = {
			...this._events,

			acknowledgeCreated: (e, response) => {
				for (let i = overlays_stack.length - 1; i >= 0; i--) {
					const overlay = overlays_stack.getById(overlays_stack.stack[i]);

					if (overlay.type === 'hintbox') {
						const element = overlay.element instanceof jQuery ? overlay.element[0] : overlay.element;

						if (this._content_body.contains(element)) {
							hintBox.deleteHint(overlay.element);
						}
					}
				}

				clearMessages();
				addMessage(makeMessageBox('good', [], response.success.title));

				if (this._state === WIDGET_STATE_ACTIVE) {
					this._startUpdating();
				}
			},

			showSymptoms: (e) => {
				const button = e.target;

				// Disable the button to prevent multiple clicks.
				button.disabled = true;

				const rows = this._target.querySelectorAll("tr[data-cause-eventid='" + button.dataset.eventid + "']");
				let state = false;

				// Show symptom rows for current cause. Sliding animations are not supported on table rows.
				rows.forEach((row) => {
					if (row.getAttribute('style').indexOf('display:') != -1) {
						row.style.display = null;
						state = true;
					}
					else {
						row.style.display = 'none';
						state = false;
					}
				});

				// Store or remove opened cause event IDs localy.
				if (state) {
					button.classList.remove('btn-widget-expand');
					button.classList.add('btn-widget-collapse');

					this._opened_eventids.push(button.dataset.eventid);
				}
				else {
					button.classList.remove('btn-widget-collapse');
					button.classList.add('btn-widget-expand');

					this._opened_eventids = this._opened_eventids.filter((id) => id !== button.dataset.eventid);
				}

				// When complete enable button again.
				button.disabled = false;
			}
		}
	}

	_activateEvents() {
		super._activateEvents();

		$.subscribe('acknowledge.create', this._events.acknowledgeCreated);
	}

	_doActivate() {
		super._doActivate();

		if (this._has_contents) {
			this._activateContentsEvents();
		}
	}

	_doDeactivate() {
		super._doDeactivate();

		this._deactivateContentsEvents();
	}

	_deactivateEvents() {
		super._deactivateEvents();

		$.unsubscribe('acknowledge.create', this._events.acknowledgeCreated);
	}

	_processUpdateResponse(response) {
		if (this._has_contents) {
			this._deactivateContentsEvents();
			this._has_contents = false;
		}

		super._processUpdateResponse(response);

		if ('name' in response) {
			this._has_contents = true;
			this._activateContentsEvents();
		}
	}

	_activateContentsEvents() {
		if (this._state === WIDGET_STATE_ACTIVE && this._has_contents) {
			for (const button of this._target.querySelectorAll("button[data-action='show_symptoms']")) {
				button.addEventListener('click', this._events.showSymptoms);

				// Open the symptom block for previously clicked problems when content is reloaded.
				if (this._opened_eventids.includes(button.dataset.eventid)) {
					const rows = this._target
						.querySelectorAll("tr[data-cause-eventid='" + button.dataset.eventid + "']");

					rows.forEach((row) => {
						row.style.display = null;
					});

					button.classList.remove('btn-widget-expand');
					button.classList.add('btn-widget-collapse');
				}
			}
		}
	}

	_deactivateContentsEvents() {
		if (this._has_contents) {
			for (const button of this._target.querySelectorAll("button[data-action='show_symptoms']")) {
				button.removeEventListener('click', this._events.showSymptoms);
			}
		}
	}
}
