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
 * @var CPartial $this
 */
?>

<script>
	document.querySelectorAll('#subscriptions-table .js-add-user')
		.forEach((elm) => elm.addEventListener('click',
			(event) => PopUp("popup.scheduledreport.subscription.edit",
				{recipient_type: <?= ZBX_REPORT_RECIPIENT_TYPE_USER ?>}, null, event.target
			)
		));
	document.querySelectorAll('#subscriptions-table .js-add-user-group')
		.forEach((elm) => elm.addEventListener('click',
			(event) => PopUp("popup.scheduledreport.subscription.edit",
				{recipient_type: <?= ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP ?>}, null, event.target
			)
		));
</script>
<script>
	const users = <?= json_encode($data['users']) ?>;
	const user_groups = <?= json_encode($data['user_groups']) ?>;

	class ReportSubscription {

		constructor(data, edit = null) {
			this.data = data;

			this.row = document.createElement('tr');
			console.log(this.data);
		}
	}
</script>
