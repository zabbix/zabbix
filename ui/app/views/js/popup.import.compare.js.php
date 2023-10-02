<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

	submitImportComparePopup() {
		if (window.popup_import.isDeleteMissingChecked()) {
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
