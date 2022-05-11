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
 * @var CView $this
 */
?>

jQuery(function($) {
	$(document).on('click', '.import-compare .<?= ZBX_STYLE_TOC_ARROW ?>', function() {
		$(this).parent().siblings('.<?= ZBX_STYLE_TOC_SUBLIST ?>').toggle();
		$('span', $(this)).toggleClass('<?= ZBX_STYLE_ARROW_DOWN ?> <?= ZBX_STYLE_ARROW_UP ?>');

		return false;
	});
});

function submitImportComparePopup(compare_overlay) {
	const form = document.querySelector('.import-compare');
	const import_overlayid = form.querySelector('#import_overlayid').value;
	const import_overlay = overlays_stack.getById(import_overlayid);

	if (isDeleteMissingChecked(import_overlay)) {
		return confirmSubmit(import_overlay, compare_overlay);
	}

	overlayDialogueDestroy(compare_overlay.dialogueid);
	return submitImportPopup(import_overlay);
}
