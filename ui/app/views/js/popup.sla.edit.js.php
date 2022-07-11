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


/**
 * @var CView $this
 */
?>

window.sla_edit_popup = {
	excluded_downtime_template: null,

	slaid: null,

	create_url: null,
	update_url: null,
	delete_url: null,

	overlay: null,
	dialogue: null,
	form: null,
	footer: null,

	init({slaid, service_tags, excluded_downtimes, create_url, update_url, delete_url}) {
		this.initTemplates();

		this.slaid = slaid;

		this.create_url = create_url;
		this.update_url = update_url;
		this.delete_url = delete_url;

		this.overlay = overlays_stack.getById('sla_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.footer = this.overlay.$dialogue.$footer[0];

		for (const excluded_downtime of excluded_downtimes) {
			this.addExcludedDowntime(excluded_downtime);
		}

		// Update form field state according to the form data.

		for (const element of document.querySelectorAll('#schedule_mode input[type="radio"')) {
			element.addEventListener('change', () => this.update());
		}

		for (const element of document.querySelectorAll('#schedule input[type="checkbox"]')) {
			element.addEventListener('change', () => this.update());
		}

		// Setup Problem tags.

		const $service_tags = jQuery(document.getElementById('service-tags'));

		$service_tags.dynamicRows({
			template: '#service-tag-row-tmpl',
			rows: service_tags
		});

		// Setup Excluded downtimes.
		document
			.getElementById('excluded-downtimes')
			.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this.editExcludedDowntime();
				}
				else if (e.target.classList.contains('js-edit')) {
					this.editExcludedDowntime(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					e.target.closest('tr').remove();
				}
			});

		this.update();
	},

	initTemplates() {
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
	},

	update() {
		const schedule = document.getElementById('schedule');
		const schedule_mode = document.querySelector('#schedule_mode input:checked').value;

		schedule.style.display = schedule_mode == <?= CSlaHelper::SCHEDULE_MODE_CUSTOM ?> ? '' : 'none';

		for (const element of schedule.querySelectorAll('input[type="checkbox"]')) {
			schedule.querySelector(`input[name="schedule_periods[${element.value}]"]`).disabled = !element.checked;
		}
	},

	editExcludedDowntime(row = null) {
		let popup_params;

		if (row !== null) {
			const row_index = row.dataset.row_index;

			popup_params = {
				edit: '1',
				row_index,
				name: row.querySelector(`[name="excluded_downtimes[${row_index}][name]"`).value,
				period_from: row.querySelector(`[name="excluded_downtimes[${row_index}][period_from]"`).value,
				period_to: row.querySelector(`[name="excluded_downtimes[${row_index}][period_to]"`).value,
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
				this.updateExcludedDowntime(row, e.detail)
			}
			else {
				this.addExcludedDowntime(e.detail);
			}
		});
	},

	addExcludedDowntime(excluded_downtime) {
		document
			.querySelector('#excluded-downtimes tbody')
			.insertAdjacentHTML('beforeend', this.excluded_downtime_template.evaluate(excluded_downtime));
	},

	updateExcludedDowntime(row, excluded_downtime) {
		row.insertAdjacentHTML('afterend', this.excluded_downtime_template.evaluate(excluded_downtime));
		row.remove();
	},

	clone({title, buttons}) {
		this.slaid = null;

		this.overlay.unsetLoading();
		this.overlay.setProperties({title, buttons});
	},

	delete() {
		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}

		this.overlay.setLoading();

		const curl = new Curl(this.delete_url);

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({slaids: [this.slaid]})
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {response_error: response.error};
				}

				if ('success' in response) {
					postMessageOk(response.success.title);

					if ('messages' in response.success) {
						postMessageDetails('success', response.success.messages);
					}

					uncheckTableRows('sla');
				}

				location.href = location.href;
			})
			.catch((error) => {
				this.overlay.unsetLoading();

				let title, messages;

				if (typeof error === 'object' && 'response_error' in error) {
					title = error.response_error.title;
					messages = error.response_error.messages;
				}
				else {
					title = <?= json_encode(_('Unexpected server error.')) ?>;
					messages = [];
				}

				const message_box = makeMessageBox('bad', messages, title, true, false)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
	},

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

		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}

		this.overlay.setLoading();

		const curl = new Curl(this.slaid !== null ? this.update_url : this.create_url);

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(fields)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('errors' in response) {
					throw {html_string: response.errors};
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {
					detail: {
						title: response.title,
						messages: ('messages' in response) ? response.messages : null
					}
				}));
			})
			.catch((error) => {
				let message_box;

				if (typeof error === 'object' && 'html_string' in error) {
					message_box =
						new DOMParser().parseFromString(error.html_string, 'text/html').body.firstElementChild;
				}
				else {
					const error = <?= json_encode(_('Unexpected server error.')) ?>;

					message_box = makeMessageBox('bad', [], error, true, false)[0];
				}

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}
};
