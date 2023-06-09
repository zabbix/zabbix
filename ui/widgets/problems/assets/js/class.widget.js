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

	onInitialize() {
		this._opened_eventids = [];
	}

	onStart() {
		this._events = {
			...this._events,

			acknowledgeCreated: (e, response) => {
				clearMessages();
				addMessage(makeMessageBox('good', [], response.success.title));

				if (this._state === WIDGET_STATE_ACTIVE) {
					this._startUpdating();
				}
			},

			rankChanged: () => {
				if (this._state === WIDGET_STATE_ACTIVE) {
					this._startUpdating();
				}
			},

			showSymptoms: (e) => {
				const button = e.target;

				// Disable the button to prevent multiple clicks.
				button.disabled = true;

				const rows = this._body.querySelectorAll("tr[data-cause-eventid='" + button.dataset.eventid + "']");

				if (rows[0].classList.contains('hidden')) {
					button.classList.remove(ZBX_ICON_CHEVRON_DOWN, ZBX_STYLE_COLLAPSED);
					button.classList.add(ZBX_ICON_CHEVRON_UP);
					button.title = t('Collapse');

					this._opened_eventids.push(button.dataset.eventid);

					[...rows].forEach(row => row.classList.remove('hidden'));
				}
				else {
					button.classList.remove(ZBX_ICON_CHEVRON_UP);
					button.classList.add(ZBX_ICON_CHEVRON_DOWN, ZBX_STYLE_COLLAPSED);
					button.title = t('Expand');

					this._opened_eventids = this._opened_eventids.filter((id) => id !== button.dataset.eventid);

					[...rows].forEach(row => row.classList.add('hidden'));
				}

				// When complete enable button again.
				button.disabled = false;
			}
		}
	}

	onActivate() {
		$.subscribe('acknowledge.create', this._events.acknowledgeCreated);
		$.subscribe('event.rank_change', this._events.rankChanged);

		this._activateContentsEvents();
	}

	onDeactivate() {
		$.unsubscribe('acknowledge.create', this._events.acknowledgeCreated);
		$.unsubscribe('event.rank_change', this._events.rankChanged);

		this._deactivateContentsEvents();
	}

	processUpdateResponse(response) {
		super.processUpdateResponse(response);

		this._activateContentsEvents();
	}

	_activateContentsEvents() {
		for (const button of this._body.querySelectorAll("button[data-action='show_symptoms']")) {
			button.addEventListener('click', this._events.showSymptoms);

			// Open the symptom block for previously clicked problems when content is reloaded.
			if (this._opened_eventids.includes(button.dataset.eventid)) {
				const rows = this._body
					.querySelectorAll("tr[data-cause-eventid='" + button.dataset.eventid + "']");

				[...rows].forEach(row => row.classList.remove('hidden'));

				button.classList.remove(ZBX_ICON_CHEVRON_DOWN, ZBX_STYLE_COLLAPSED);
				button.classList.add(ZBX_ICON_CHEVRON_UP);
				button.title = t('Collapse');
			}
		}
	}

	_deactivateContentsEvents() {
		for (const button of this._body.querySelectorAll("button[data-action='show_symptoms']")) {
			button.removeEventListener('click', this._events.showSymptoms);
		}
	}
}
