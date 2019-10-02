<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


?>
<script type="text/javascript">
	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(data) {
		if (!isset('object', data) || data.object !== 'hostid') {
			return false;
		}

		for (var i = 0, len = data.values.length; i < len; i++) {
			create_var(data.parentId, 'add_templates[' + data.values[i].id + ']', data.values[i].id, false);
		}

		submitFormWithParam(data.parentId, "add_template", "1");
	}
</script>
