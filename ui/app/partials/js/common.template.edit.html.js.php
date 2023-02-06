<?php declare(strict_types = 1);
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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
	'use strict';

	window.common_template_edit = new class {

		init({form_name}) {
			this.form = document.getElementById(form_name);
		}

		getAllTemplates() {
			return [... this.form.querySelectorAll('[name^="add_templates["], [name^="templates["]')]
				.map((input) => input.value);
		}

		templatesChanged(data_templateids, templateids) {
			if (data_templateids.length !== templateids.length) {
				return true;
			}

			for (const templateid of data_templateids) {
				if (templateids.indexOf(templateid) === -1) {
					return true;
				}
			}

			return false;
		}
	}
</script>
