<?php declare(strict_types = 0);
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
?>


window.maintenance_edit = new class {

	init({maintenanceid, timeperiods, maintenance_tags, allowed_edit}) {
		this._initTemplates();

		this.maintenanceid = maintenanceid;

		this.overlay = overlays_stack.getById('maintenance-edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.footer = this.overlay.$dialogue.$footer[0];

		// Add existing periods in maintenance edit form.
		if (typeof(timeperiods) === 'object') {
			timeperiods = Object.values(timeperiods);
		}

		for (const timeperiod of timeperiods) {
			this._addPeriod(timeperiod);
		}

		// Setup tags.
		const $maintenance_tags = jQuery(document.getElementById('maintenance-tags'));

		$maintenance_tags.dynamicRows({
			template: '#maintenance-tag-row-tmpl',
			rows: maintenance_tags
		});

		if (!allowed_edit) {
			document.querySelectorAll('[id^="tags_"], [id^="maintenance_tags_"], .js-edit, .js-remove')
				.forEach((element) => {
					element.disabled = true;
					element.setAttribute('readonly', 'readonly')
				});
		}

		if (document.querySelector('input[name=maintenance_type]:checked').value  == <?= MAINTENANCE_TYPE_NODATA ?>) {
			document.querySelectorAll('[id^="tags_"], [id^="maintenance_tags_"]').forEach((element) => {
				element.disabled = true;
			});
			document.querySelectorAll('input[name$="[tag]"], input[name$="[value]').forEach((element) => {
				element.removeAttribute('placeholder');
			});
		}

		// Update form field state according to the form data and allowed_edit.

		document.getElementById('maintenance_type').addEventListener('change', () => {
			var maintenance_type = document.querySelector('input[name=maintenance_type]:checked').value;
			var tags_table_disabled = maintenance_type == <?= MAINTENANCE_TYPE_NODATA ?>;

			document.querySelectorAll('[id^="tags_"], [id^="maintenance_tags_"]').forEach((element) => {
				element.disabled = tags_table_disabled;
			});

			if (tags_table_disabled) {
				document.querySelectorAll('input[name$="[tag]"], input[name$="[value]').forEach((element) => {
					element.removeAttribute('placeholder');
				});
			}
			else {
				document.querySelectorAll('input[name$="[tag]"]').forEach((element) => {
					element.setAttribute('placeholder', <?= json_encode(_('tag')) ?>);
				});
				document.querySelectorAll('input[name$="[value]"]').forEach((element) => {
					element.setAttribute('placeholder', <?= json_encode(_('value')) ?>);
				});
			}
		});

		this._initPeriodActionButtons();
	}

	_initTemplates() {
		this.periods_template = new Template(`
			<tr data-row_index="#{row_index}">
				<td>#{period_type}</td>
				<td class="wordwrap">#{schedule}</td>
				<td>#{period_table_entry}</td>
				<td>
					<input type="hidden" name="timeperiods[#{row_index}][timeperiod_type]" value="#{timeperiod_type}">
					<input type="hidden" name="timeperiods[#{row_index}][every]" value="#{every}">
					<input type="hidden" name="timeperiods[#{row_index}][month]" value="#{month}">
					<input type="hidden" name="timeperiods[#{row_index}][dayofweek]" value="#{dayofweek}">
					<input type="hidden" name="timeperiods[#{row_index}][day]" value="#{day}">
					<input type="hidden" name="timeperiods[#{row_index}][start_time]" value="#{start_time}">
					<input type="hidden" name="timeperiods[#{row_index}][period]" value="#{period}">
					<input type="hidden" name="timeperiods[#{row_index}][start_date]" value="#{start_date}">
					<ul class="<?= ZBX_STYLE_HOR_LIST ?>">
						<li>
							<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-edit"><?= _('Edit') ?></button>
						</li>
						<li>
							<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
						</li>
					</ul>
				</td>
			</tr>
		`);
	}

	_initPeriodActionButtons() {
		document
			.getElementById('periods')
			.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this._editPeriod();
				}
				else if (e.target.classList.contains('js-edit')) {
					this._editPeriod(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					e.target.closest('tr').remove();
				}
			});
	}

	_editPeriod(row = null) {
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

			while (document.querySelector(`#periods [data-row_index="${row_index}"]`) !== null) {
				row_index++;
			}

			popup_params = {row_index};
		}

		const overlay = PopUp('maintenance.period.edit', popup_params, {
			dialogueid: 'maintenance_period_edit'
		});

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			if (row !== null) {
				this._updatePeriod(row, e.detail)
			}
			else {
				this._addPeriod(e.detail);
			}
		});
	}

	_addPeriod(period) {
		document
			.querySelector('#periods tbody')
			.insertAdjacentHTML('beforeend', this.periods_template.evaluate(period));
	}


	_updatePeriod(row, period) {
		row.insertAdjacentHTML('afterend', this.periods_template.evaluate(period));
		row.remove();
	}

	submit() {
		const fields = getFormFields(this.form);

		if (this.maintenanceid !== null) {
			fields.maintenanceid = this.maintenanceid;
		}
		else {
			fields.maintenanceid = 0;
		}

		fields.mname = fields.mname.trim();
		fields.description = fields.description.trim();

		if ('maintenance_tags' in fields) {
			for (const maintenance_tag of Object.values(fields.maintenance_tags)) {
				maintenance_tag.tag = maintenance_tag.tag.trim();
				maintenance_tag.value = maintenance_tag.value.trim();
			}
		}

		if (typeof(fields.timeperiods) === 'object') {
			fields.timeperiods = Object.values(fields.timeperiods);
		}

		this.overlay.setLoading();

		const curl = new Curl('zabbix.php', false);
		curl.setArgument('action', fields.maintenanceid === 0 ? 'maintenance.create' : 'maintenance.update');

		this._post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.success}));
		});
	}

	_post(url, data, success_callback) {
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

	clone({title, buttons}) {
		this.maintenanceid = null;

		this.overlay.unsetLoading();
		this.overlay.setProperties({title, buttons});
	}

	delete() {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'maintenance.delete');

		this._post(curl.getUrl(), {maintenanceids: [this.maintenanceid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.delete', {detail: response.success}));
		});
	}

}
