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
 * @var CView $this
 */
?>


window.popup_import_compare = new class {

	/**
	 * @var {Overlay}
	 */
	#overlay;

	/**
	 * @var {HTMLFormElement}
	 */
	#form;

	init() {
		this.#overlay = overlays_stack.getById('popup_import_compare');
		this.#form = this.#overlay.$dialogue.$body[0].querySelector('form');

		this.#addEventListeners();
	}

	submitImportComparePopup(with_removed_entities) {
		if (with_removed_entities && window.popup_import.isDeleteMissingChecked()) {
			return window.popup_import.confirmSubmit(this.#overlay);
		}

		overlayDialogueDestroy(this.#overlay.dialogueid);
		return window.popup_import.submitImportPopup();
	}

	#addEventListeners() {
		this.#form.addEventListener('click', (e) => {
			if (e.target.classList.contains('<?= ZBX_STYLE_TOC_ARROW ?>')
					|| e.target.parentNode.classList.contains('<?= ZBX_STYLE_TOC_ARROW ?>')) {
				const btn = e.target.classList.contains('<?= ZBX_STYLE_TOC_ARROW ?>') ? e.target : e.target.parentNode;
				const arrow = btn.querySelector('span');
				const is_expanded = arrow.classList.contains('<?= ZBX_STYLE_ARROW_DOWN ?>');

				btn.parentNode.nextSibling.style.display = is_expanded ? 'none' : '';
				arrow.classList.toggle('<?= ZBX_STYLE_ARROW_DOWN ?>');
				arrow.classList.toggle('<?= ZBX_STYLE_ARROW_RIGHT ?>');
			}
		});
	}
}
