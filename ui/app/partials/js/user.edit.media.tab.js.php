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

?>

<script>
	const mediaView = new class {
		init({form_name}) {
			this.form_name = form_name;
			this._handleAction();
		}

		_handleAction() {
			document.querySelector('#mediaTab').addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this._addMedia();
				}
				else if (e.target.classList.contains('js-edit')) {
					this._editMedia(JSON.parse(e.target.dataset.parameters));
				}
				else if (e.target.classList.contains('js-remove')) {
					this._removeMedia(e.target);
				}
				else if (e.target.classList.contains('js-status')) {
					this._statusMedia(e.target);
				}
			});
		}

		_addMedia() {
			PopUp('popup.media', {'dstfrm': this.form_name}, {dialogue_class: 'modal-popup-generic'});
		}

		_editMedia(parameters) {
			PopUp('popup.media', parameters, {dialogue_class: 'modal-popup-generic'});
		}

		_removeMedia(target) {
			const index = target.closest('tr').id.replace('medias_', '');

			document.querySelector(`#medias_${index}`).remove();
			document.querySelectorAll(`[name^="medias[${index}]["]`).forEach((element) => element.remove());
		}

		_statusMedia(target) {
			const index = target.closest('tr').id.replace('medias_', '');

			create_var(this.form_name, target.dataset.status_type, index, true);
		}
	}
</script>
