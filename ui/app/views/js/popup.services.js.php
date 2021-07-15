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

window.services_popup = {
	overlay: null,
	dialogue: null,
	form: null,

	init() {
		this.overlay = overlays_stack.getById('services');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		const filter_form = this.overlay.$dialogue.$controls[0].querySelector('form');

		filter_form.addEventListener('submit', (e) => {
			e.preventDefault();

			PopUp('popup.services', getFormFields(filter_form), 'services', e.target);
		}, {passive: false});

		filter_form.addEventListener('reset', (e) => {
			e.preventDefault();

			filter_form.elements.filter_name.value = '';

			PopUp('popup.services', getFormFields(filter_form), 'services', e.target);
		}, {passive: false});

		this.form.addEventListener('click', (e) => {
			if (e.target.matches('input[name="serviceid_all"]')) {
				for (const checkbox of this.form.querySelectorAll('input[name="serviceid"]')) {
					checkbox.checked = e.target.checked;
					checkbox.closest('tr').classList.toggle('row-selected', e.target.checked);
				}
			}
			else if (e.target.matches('input[name="serviceid"]')) {
				e.target.closest('tr').classList.toggle('row-selected', e.target.checked);

				const has_all_checked = this.form.querySelector('input[name="serviceid"]:not(:checked)') === null;

				this.form.querySelector('input[name="serviceid_all"]').checked = has_all_checked;
			}
			else if (e.target.classList.contains('js-name')) {
				this.submit(e.target.closest('tr').querySelector('input[name="serviceid"]').value);
			}
		});
	},

	submit(serviceid = null) {
		const services = [];

		const checkboxes = serviceid === null
			? this.form.querySelectorAll(`input[name="serviceid"]:checked`)
			: this.form.querySelectorAll(`input[name="serviceid"][value="${serviceid}"]`);

		for (const checkbox of checkboxes) {
			const service = {serviceid: checkbox.value};

			for (const input of checkbox.parentElement.querySelectorAll('input[type="hidden"]')) {
				service[input.name] = input.value;
			}

			services.push(service);
		}

		this.overlay.unsetLoading();

		overlayDialogueDestroy('services');

		this.dialogue.dispatchEvent(new CustomEvent('services-submit', {detail: services}));
	}
};

services_popup.init();
