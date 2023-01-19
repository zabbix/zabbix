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
		fields.mname = fields.mname.trim();

		const curl = new Curl('zabbix.php', false);
		curl.setArgument('action', this.maintenanceid !== 0 ? 'maintenance.update' : 'maintenance.create');

		this._post(curl.getUrl(), fields);
	}

	_post(url, data) {}

	clone() {}

	delete() {}

}
