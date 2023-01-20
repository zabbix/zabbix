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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CWidgetProblems extends CWidget {

	// Must be synced with PHP ZBX_STYLE_BTN_WIDGET_EXPAND;
	static ZBX_STYLE_BTN_WIDGET_EXPAND = 'btn-widget-expand';

	// Must be synced with PHP ZBX_STYLE_BTN_WIDGET_COLLAPSE;
	static ZBX_STYLE_BTN_WIDGET_COLLAPSE = 'btn-widget-collapse';

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

			rankChanged: (e) => {
				if (this._state === WIDGET_STATE_ACTIVE) {
					this._startUpdating();
				}
			},

			showSymptoms: (e) => {
				const button = e.target;

				// Disable the button to prevent multiple clicks.
				button.disabled = true;

				const rows = this._target.querySelectorAll("tr[data-cause-eventid='" + button.dataset.eventid + "']");

				if (rows[0].classList.contains('hidden')) {
					button.classList.replace(CWidgetProblems.ZBX_STYLE_BTN_WIDGET_EXPAND,
						CWidgetProblems.ZBX_STYLE_BTN_WIDGET_COLLAPSE
					);
					button.title = t('Collapse');

					this._opened_eventids.push(button.dataset.eventid);

					[...rows].forEach(row => row.classList.remove('hidden'));
				}
				else {
					button.classList.replace(CWidgetProblems.ZBX_STYLE_BTN_WIDGET_COLLAPSE,
						CWidgetProblems.ZBX_STYLE_BTN_WIDGET_EXPAND
					);
					button.title = t('Expand');

					this._opened_eventids = this._opened_eventids.filter((id) => id !== button.dataset.eventid);

					[...rows].forEach(row => row.classList.add('hidden'));
				}

				// When complete enable button again.
				button.disabled = false;
			}
		}
	}

	_activateEvents() {
		super._activateEvents();

		$.subscribe('acknowledge.create', this._events.acknowledgeCreated);
		$.subscribe('event.rank_change', this._events.rankChanged);
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
		$.unsubscribe('event.rank_change', this._events.rankChanged);
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

					[...rows].forEach(row => row.classList.remove('hidden'));

					button.classList.replace(CWidgetProblems.ZBX_STYLE_BTN_WIDGET_EXPAND,
						CWidgetProblems.ZBX_STYLE_BTN_WIDGET_COLLAPSE
					);
					button.title = t('Collapse');
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
