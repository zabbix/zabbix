<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
<?php if (false): ?><script><?php endif; ?>
window.sla_edit = {
	id: null,
	downtime_template: null,

	overlay: null,
	dialogue: null,
	form: null,
	header: null,
	footer: null,

	init({service_tags, excluded_downtimes}) {
		this.overlay = overlays_stack.getById('sla_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.header = this.overlay.$dialogue.$header[0];
		this.footer = this.overlay.$dialogue.$footer[0];

		this.downtime_template = new Template(this.form.querySelector('#downtimes-row-tmpl').innerHTML);

		const $tags = jQuery(this.form.querySelector('#service_tags'));

		$tags.dynamicRows({
			template: '#tag-row-tmpl',
			rows: service_tags
		});

		$tags.on('click', '.element-table-add', () => {
			$tags
				.find('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>')
				.textareaFlexible();

			$tags.find('z-select:last').val(<?= json_encode(ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL) ?>);
		});

		if ($tags.find('z-select:last').length == 0) {
			$tags.find('.element-table-add').click();
		}

		this.form.querySelector('#excluded_downtimes')
			.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this.editDowntime();
				}
				else if (e.target.classList.contains('js-edit')) {
					this.editDowntime(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					e.target.closest('tr').remove();
				}
			});

		for (const td of this.form.querySelectorAll('#excluded_downtimes td:empty')) {
			td.remove();
		}

		for (const downtime of excluded_downtimes) {
			this.addDowntime(downtime);
		}

		for (const schedule_mode_switch of this.form.querySelectorAll('[name="schedule_mode"]')) {
			schedule_mode_switch.addEventListener('click', (e) => {
				for (const shedules_element of this.form.querySelectorAll('.js-schedules')) {
					shedules_element.classList.toggle(
						'display-none',
						schedule_mode_switch.value == <?= CSlaHelper::SCHEDULE_MODE_NONSTOP ?>
					);
				}
			});
		}

		this.form.querySelector('#schedules')
			.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-toggle-schedule')) {
					this.form.querySelector('[name="schedule['+e.target.value+']"]').disabled = !e.target.checked;
				}
			});

		this.downtime = {
			init({update_url}) {
				this.update_url = update_url;

				this.overlay = overlays_stack.getById('sla_downtime_edit');
				this.dialogue = this.overlay.$dialogue[0];
				this.form = this.overlay.$dialogue.$body[0].querySelector('form');
			},

			submit() {
				sla_edit.submitHandler({
					dialogue: this.dialogue,
					form: this.form,
					overlay: this.overlay,
					submit_url: this.update_url
				});
			}
		}
	},

	editDowntime(row = null) {
		let popup_params;

		if (row !== null) {
			const row_index = row.dataset.row_index;

			popup_params = {
				row_index,
				name: row.querySelector(`[name="excluded_downtimes[${row_index}][name]"`).value,
				start_time: row.querySelector(`[name="js[${row_index}][start_time]"`).value,
				duration_days: row.querySelector(`[name="js[${row_index}][duration_days]"`).value,
				duration_hours: row.querySelector(`[name="js[${row_index}][duration_hours]"`).value,
				duration_minutes: row.querySelector(`[name="js[${row_index}][duration_minutes]"`).value
			};
		}
		else {
			let row_index = 0;

			while (this.form.querySelector(`#excluded_downtimes [data-row_index="${row_index}"]`) !== null) {
				row_index++;
			}

			popup_params = {row_index};
		}

		const overlay = PopUp('popup.sla.downtime.edit', popup_params, 'sla_downtime_edit', document.activeElement);

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			if (row !== null) {
				this.updateDowntime(row, e.detail.form)
			}
			else {
				this.addDowntime(e.detail.form);
			}
		});
	},

	addDowntime(downtime) {
		this.form
			.querySelector('#excluded_downtimes tbody')
			.insertAdjacentHTML('beforeend', this.downtime_template.evaluate(downtime));
	},

	updateDowntime(row, downtime) {
		row.insertAdjacentHTML('afterend', this.downtime_template.evaluate(downtime));
		row.remove();
	},

	clone(dialog_title) {
		this.id = null;
		this.header.textContent = dialog_title;

		for (const element of this.footer.querySelectorAll('.js-update, .js-clone')) {
			element.classList.add('<?= ZBX_STYLE_DISPLAY_NONE ?>');
		}

		for (const element of this.footer.querySelectorAll('.js-add')) {
			element.classList.remove('<?= ZBX_STYLE_DISPLAY_NONE ?>');
		}
	},

	submit() {
		// Clean helper inputs for less data to post.
		for (const element of this.form.querySelectorAll('[name^="js["]')) {
			element.remove();
		}

		sla_edit.submitHandler({
			dialogue: this.dialogue,
			form: this.form,
			overlay: this.overlay,
			submit_url: this.form.action
		});
	},

	submitHandler({dialogue, form, overlay, submit_url}) {
		const fields = getFormFields(form);

		for (const [field, value] of Object.entries(fields)) {
			if (typeof value === 'string') {
				fields[field] = value.trim();
			}
		}

		for (const el of form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}

		overlay.setLoading();

		fetch(submit_url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(fields)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('errors' in response) {
					throw {html_string: response.errors};
				}

				overlayDialogueDestroy(overlay.dialogueid);

				dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
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

				form.parentNode.insertBefore(message_box, form);
			})
			.finally(() => {
				overlay.unsetLoading();
			});
	}
};
