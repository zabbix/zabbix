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


/**
 * @var CView $this
 */
?>

window.sla_edit_popup = new class {

	init({slaid, service_tags, excluded_downtimes}) {
		this._initTemplates();

		this.slaid = slaid;

		this.overlay = overlays_stack.getById('sla.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.footer = this.overlay.$dialogue.$footer[0];

		const backurl = new Curl('zabbix.php');

		backurl.setArgument('action', 'sla.list');
		this.overlay.backurl = backurl.getUrl();

		for (const excluded_downtime of excluded_downtimes) {
			this._addExcludedDowntime(excluded_downtime);
		}

		// Update form field state according to the form data.

		for (const element of document.querySelectorAll('#schedule_mode input[type="radio"')) {
			element.addEventListener('change', () => this._update());
		}

		for (const element of document.querySelectorAll('#schedule input[type="checkbox"]')) {
			element.addEventListener('change', () => this._update());
		}

		// Setup Problem tags.

		const $service_tags = jQuery(document.getElementById('service-tags'));

		$service_tags.dynamicRows({
			template: '#service-tag-row-tmpl',
			rows: service_tags,
			allow_empty: true
		});

		// Setup Excluded downtimes.
		document
			.getElementById('excluded-downtimes')
			.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this._editExcludedDowntime();
				}
				else if (e.target.classList.contains('js-edit')) {
					this._editExcludedDowntime(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					e.target.closest('tr').remove();
				}
			});

		this._update();

		this.form.style.display = '';
		this.overlay.recoverFocus();
	}

	_initTemplates() {
		this.excluded_downtime_template = new Template(`
			<tr data-row_index="#{row_index}">
				<td>
					#{start_time}
					<input type="hidden" name="excluded_downtimes[#{row_index}][name]" value="#{name}">
					<input type="hidden" name="excluded_downtimes[#{row_index}][period_from]" value="#{period_from}">
					<input type="hidden" name="excluded_downtimes[#{row_index}][period_to]" value="#{period_to}">
				</td>
				<td>#{duration}</td>
				<td class="wordwrap" style="max-width: <?= ZBX_TEXTAREA_BIG_WIDTH ?>px;">#{name}</td>
				<td>
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

	_update() {
		const schedule = document.getElementById('schedule');
		const schedule_mode = document.querySelector('#schedule_mode input:checked').value;

		schedule.style.display = schedule_mode == <?= CSlaHelper::SCHEDULE_MODE_CUSTOM ?> ? '' : 'none';

		for (const element of schedule.querySelectorAll('input[type="checkbox"]')) {
			schedule.querySelector(`input[name="schedule_periods[${element.value}]"]`).disabled = !element.checked;
		}
	}

	_editExcludedDowntime(row = null) {
		let popup_params;

		if (row !== null) {
			const row_index = row.dataset.row_index;

			popup_params = {
				edit: '1',
				row_index,
				name: row.querySelector(`[name="excluded_downtimes[${row_index}][name]"`).value,
				period_from: row.querySelector(`[name="excluded_downtimes[${row_index}][period_from]"`).value,
				period_to: row.querySelector(`[name="excluded_downtimes[${row_index}][period_to]"`).value
			};
		}
		else {
			let row_index = 0;

			while (document.querySelector(`#excluded-downtimes [data-row_index="${row_index}"]`) !== null) {
				row_index++;
			}

			popup_params = {row_index};
		}

		const overlay = PopUp('popup.sla.excludeddowntime.edit', popup_params, {
			dialogueid: 'sla_excluded_downtime_edit'
		});

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			if (row !== null) {
				this._updateExcludedDowntime(row, e.detail)
			}
			else {
				this._addExcludedDowntime(e.detail);
			}
		});
	}

	_addExcludedDowntime(excluded_downtime) {
		document
			.querySelector('#excluded-downtimes tbody')
			.insertAdjacentHTML('beforeend', this.excluded_downtime_template.evaluate(excluded_downtime));
	}

	_updateExcludedDowntime(row, excluded_downtime) {
		row.insertAdjacentHTML('afterend', this.excluded_downtime_template.evaluate(excluded_downtime));
		row.remove();
	}

	clone({title, buttons}) {
		this.slaid = null;

		this.overlay.unsetLoading();
		this.overlay.setProperties({title, buttons});
		this.overlay.recoverFocus();
		this.overlay.containFocus();
	}

	delete() {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'sla.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('sla')) ?>);

		this._post(curl.getUrl(), {slaids: [this.slaid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	submit() {
		const fields = getFormFields(this.form);

		if (this.slaid !== null) {
			fields.slaid = this.slaid;
		}

		fields.name = fields.name.trim();
		fields.slo = fields.slo.trim();

		if ('service_tags' in fields) {
			for (const service_tag of Object.values(fields.service_tags)) {
				service_tag.tag = service_tag.tag.trim();
				service_tag.value = service_tag.value.trim();
			}
		}

		this.overlay.setLoading();

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', this.slaid !== null ? 'sla.update' : 'sla.create');

		this._post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
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
};
