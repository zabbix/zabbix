<?php declare(strict_types = 0);
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


/**
 * @var CView $this
 */
?>

<script>
	const view = new class {

		init({checkbox_hash}) {
			this.checkbox_hash = checkbox_hash;
		}

		editTemplate(e, templateid) {
			e.preventDefault();
			const template_data = {templateid};

			this.openTemplatePopup(template_data);
		}

		openTemplatePopup(template_data) {
			const overlay =  PopUp('template.edit', template_data, {
				dialogueid: 'templates-form',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => this.submitTemplate(e.detail));
		}

		submitTemplate(data) {
			let curl = null;

			if ('success' in data) {
				postMessageOk(data.success.title);

				if ('messages' in data.success) {
					postMessageDetails('success', data.success.messages);
				}

				if ('action' in data.success && data.success.action === 'delete') {
					curl = new Curl('zabbix.php');
					curl.setArgument('action', 'template.list');
				}
			}

			uncheckTableRows(this.checkbox_hash);

			if (curl == null) {
				location.href = location.href;
			}
			else {
				location.href = curl.getUrl();
			}
		}
	}
</script>
