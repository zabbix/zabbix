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
?>


window.maintenance_edit = new class {

	init({maintenanceid, timeperiods, tags, allowed_edit}) {
		this._maintenanceid = maintenanceid;

		this._overlay = overlays_stack.getById('maintenance.edit');
		this._dialogue = this._overlay.$dialogue[0];
		this._form = this._overlay.$dialogue.$body[0].querySelector('form');

		const backurl = new Curl('zabbix.php');

		backurl.setArgument('action', 'maintenance.list');
		this._overlay.backurl = backurl.getUrl();

		timeperiods.forEach((timeperiod, row_index) => {
			this._addTimePeriod({row_index, ...timeperiod});
		});

		// Setup Tags.
		jQuery(document.getElementById('tags')).dynamicRows({
			template: '#tag-row-tmpl',
			rows: tags,
			allow_empty: true
		});

		if (allowed_edit) {
			// Setup Time periods.
			document.getElementById('timeperiods').addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this._editTimePeriod();
				}
				else if (e.target.classList.contains('js-edit')) {
					this._editTimePeriod(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					e.target.closest('tr').remove();
				}
			});

			// Preselect already selected data in multiselect popups.

			const $groupids = $('#groupids_');

			$groupids.on('change', () => this._updateMultiselect($groupids));
			this._updateMultiselect($groupids);

			const $hostids = $('#hostids_');

			$hostids.on('change', () => this._updateMultiselect($hostids));
			this._updateMultiselect($hostids);

			// Update form field state according to the form data.

			document.getElementById('maintenance_type').addEventListener('change', () => this._update());

			this._update();
		}

		this._form.style.display = '';
		this._overlay.recoverFocus();
	}

	_update () {
		const tags_enabled =
			this._form.querySelector('[name="maintenance_type"]:checked').value == <?= MAINTENANCE_TYPE_NORMAL ?>;

		const tags_container = document.getElementById('tags');

		tags_container.querySelectorAll('[name$="[tag]"], [name$="[value]"]').forEach((text_input) => {
			text_input.disabled = !tags_enabled;
		});

		tags_container.querySelectorAll('[name="tags_evaltype"], [name$="[operator]"]').forEach((radio_button) => {
			radio_button.disabled = !tags_enabled;
		});

		tags_container.querySelectorAll('.element-table-add, .element-table-remove').forEach((button) => {
			button.disabled = !tags_enabled;
		});

		tags_container.querySelectorAll('[name$="[tag]"]').forEach((tag_text_input) => {
			tag_text_input.placeholder = tags_enabled ? <?= json_encode(_('tag')) ?> : '';
		});

		tags_container.querySelectorAll('[name$="[value]"]').forEach((value_text_input) => {
			value_text_input.placeholder = tags_enabled ? <?= json_encode(_('value')) ?> : '';
		});
	}

	_editTimePeriod(row = null) {
		let popup_params;

		if (row !== null) {
			const row_index = row.dataset.row_index;

			popup_params = {
				edit: '1',
				row_index,
				timeperiod_type: row.querySelector(`[name="timeperiods[${row_index}][timeperiod_type]"`).value,
				every: row.querySelector(`[name="timeperiods[${row_index}][every]"`).value,
				month: row.querySelector(`[name="timeperiods[${row_index}][month]"`).value,
				dayofweek: row.querySelector(`[name="timeperiods[${row_index}][dayofweek]"`).value,
				day: row.querySelector(`[name="timeperiods[${row_index}][day]"`).value,
				start_time: row.querySelector(`[name="timeperiods[${row_index}][start_time]"`).value,
				period: row.querySelector(`[name="timeperiods[${row_index}][period]"`).value,
				start_date: row.querySelector(`[name="timeperiods[${row_index}][start_date]"`).value
			};
		}
		else {
			let row_index = 0;

			while (document.querySelector(`#timeperiods [data-row_index="${row_index}"]`) !== null) {
				row_index++;
			}

			popup_params = {row_index};
		}

		const overlay = PopUp('maintenance.timeperiod.edit', popup_params, {
			dialogueid: 'maintenance-timeperiod-edit',
			dialogue_class: 'modal-popup-medium'
		});

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			if (row !== null) {
				this._updateTimePeriod(row, e.detail)
			}
			else {
				this._addTimePeriod(e.detail);
			}
		});
	}

	_addTimePeriod(timeperiod) {
		const template = new Template(document.getElementById('timeperiod-row-tmpl').innerHTML);

		document
			.querySelector('#timeperiods tbody')
			.insertAdjacentHTML('beforeend', template.evaluate(timeperiod));
	}

	_updateTimePeriod(row, timeperiod) {
		const template = new Template(document.getElementById('timeperiod-row-tmpl').innerHTML);

		row.insertAdjacentHTML('afterend', template.evaluate(timeperiod));
		row.remove();
	}

	clone({title, buttons}) {
		this._maintenanceid = null;

		this._overlay.unsetLoading();
		this._overlay.setProperties({title, buttons});
		this._overlay.recoverFocus();
		this._overlay.containFocus();
	}

	delete() {
		const post_data = {
			maintenanceids: [this._maintenanceid],
			[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('maintenance')) ?>
		};

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'maintenance.delete');

		this._post(curl.getUrl(), post_data, (response) => {
			overlayDialogueDestroy(this._overlay.dialogueid);

			this._dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	submit() {
		const fields = getFormFields(this._form);

		if (this._maintenanceid !== null) {
			fields.maintenanceid = this._maintenanceid;
		}

		fields.name = fields.name.trim();
		fields.description = fields.description.trim();

		if ('tags' in fields) {
			for (const tag of Object.values(fields.tags)) {
				tag.tag = tag.tag.trim();
				tag.value = tag.value.trim();
			}
		}

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', this._maintenanceid !== null ? 'maintenance.update' : 'maintenance.create');

		this._post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this._overlay.dialogueid);

			this._dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	_post(url, data, success_callback) {
		this._overlay.setLoading();

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
				for (const element of this._form.parentNode.children) {
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

				this._form.parentNode.insertBefore(message_box, this._form);
			})
			.finally(() => {
				this._overlay.unsetLoading();
			});
	}

	_updateMultiselect($ms) {
		$ms.multiSelect('setDisabledEntries', [...$ms.multiSelect('getData').map((entry) => entry.id)]);
	}
}
