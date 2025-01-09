<?php
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
