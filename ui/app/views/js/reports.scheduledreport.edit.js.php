<?php
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

<script>
	document.addEventListener('DOMContentLoaded', () => {
		const clone_btn = document.querySelector('#clone');

		if (clone_btn !== null) {
			clone_btn.addEventListener('click', () => {
				const update_btn = document.querySelector('#update');

				update_btn.setAttribute('id', 'add');
				update_btn.setAttribute('value', 'scheduledreport.create');
				update_btn.innerHTML = <?= json_encode(_('Add')) ?>;

				document.querySelectorAll('#reportid, #clone, #test, #delete').forEach((elem) => { elem.remove(); });
			});
		}

		const test_btn = document.querySelector('#test');

		if (test_btn !== null) {
			test_btn.addEventListener('click', (event) => {
				const form = event.target.closest('form');
				const popup_options = {
					period: form.elements['period'].value,
					now: Math.floor(Date.now() / 1000)
				};

				if (typeof form.elements['dashboardid'] !== 'undefined') {
					popup_options.dashboardid = form.elements['dashboardid'].value;
				}

				document.querySelectorAll('#name, #subject, #message').forEach((elem) => {
					popup_options[elem.id] = elem.value.trim();
				});

				PopUp('popup.scheduledreport.test', popup_options, null, event.target);
			});
		}
	});
</script>
