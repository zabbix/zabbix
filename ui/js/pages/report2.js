/*
** Copyright (C) 2001-2024 Zabbix SIA
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


$(() => {
	const $form = $(document.forms['report2']),
		$filter_form = $(document.forms['zbx_filter']);

	$form.find('[name="mode"]').on('change', (e) => {
		$form.submit();
	})

	$filter_form
		.find('[name="filter_groups"],[name="filter_templateid"],[name="tpl_triggerid"],[name="hostgroupid"]')
		.on('change', (e) => {
			$filter_form.submit();
		})
});
