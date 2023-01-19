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

	init({maintenanceid, maintenance_tags}) {
		this.maintenanceid = maintenanceid;

		this.overlay = overlays_stack.getById('maintenance-edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.footer = this.overlay.$dialogue.$footer[0];

		// Update form field state according to the form data.

		document.getElementById('maintenance_type').addEventListener('change', () => {
			var maintenance_type = document.querySelector('input[name=maintenance_type]:checked').value;
			var tags_table_disabled = maintenance_type == <?= MAINTENANCE_TYPE_NODATA ?>;

			document.querySelectorAll('[id^="tags_"], [id^="maintenance_tags_"]').forEach((element) => {
				element.disabled = tags_table_disabled;
			});

			if(tags_table_disabled) {
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

		// Setup tags.

		const $maintenance_tags = jQuery(document.getElementById('maintenance-tags'));

		$maintenance_tags.dynamicRows({
			template: '#maintenance-tag-row-tmpl',
			rows: maintenance_tags
		});

		this._initActionButtons();


	}

	_initActionButtons() {
		this.dialogue.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-period-create')) {
				this._openPeriodPopup(this.maintenanceid);
			}
			else if (e.target.classList.contains('js-period-edit')) {
				this._openPeriodEditPopup(e, JSON.parse(e.target.dataset.period));
			}
			else if (e.target.classList.contains('js-period-remove')) {
				e.target.closest('tr').remove();
			}
		});
	}

	_openPeriodPopup() {

	}

	_openPeriodEditPopup() {

	}

	submit() {
		const fields = getFormFields(this.form);

		if (this.maintenanceid !== null) {
			fields.maintenanceid = this.maintenanceid;
		}

		fields.mname = fields.mname.trim();
		fields.description = fields.description.trim();

		if ('maintenance_tags' in fields) {
			for (const maintenance_tag of Object.values(fields.maintenance_tags)) {
				maintenance_tag.tag = maintenance_tag.tag.trim();
				maintenance_tag.value = maintenance_tag.value.trim();
			}
		}

		this.overlay.setLoading();

		const curl = new Curl('zabbix.php', false);
		curl.setArgument('action', this.maintenanceid !== 0 ? 'maintenance.update' : 'maintenance.create');

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
