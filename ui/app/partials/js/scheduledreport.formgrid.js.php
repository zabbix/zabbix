<?php
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
 * @var CPartial $this
 */
?>

<script>
	jQuery(document).ready(function() {
		<?= $data['user_multiselect'] ?>
		<?= $data['dashboard_multiselect'] ?>
	});
</script>

<script>
	(() => {
		document
			.querySelector('#cycle')
			.addEventListener('change', (event) => {
				const show_weekdays = (event.target.value == <?= ZBX_REPORT_CYCLE_WEEKLY ?>);

				document
					.querySelectorAll('#weekdays-label, #weekdays')
					.forEach(
						(elem) => elem
							.classList
							.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !show_weekdays)
					);
			});

		document
			.querySelector('#scheduledreport-form')
			.addEventListener('submit', () => {
				document.querySelectorAll('#name, #subject, #message, #description').forEach((elem) => {
					elem.value = elem.value.trim();
				});
			});
	})();
</script>
