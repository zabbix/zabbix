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
 * @var CView $this
 */
?>

jQuery(function($) {
	$(document).on('click', '.import-compare .toc .arrow', function() {
		$(this).closest('li').children('.sublist').toggle();
		$(this).children('span')
			.toggleClass('arrow-down arrow-up');

		return false;
	});
});

function submitImportComparePopup(overlay) {
	const form = document.querySelector('.import-compare');
	const parent_overlayid = form.querySelector('#parent_overlayid').value;
	const parent_overlay = overlays_stack.getById(parent_overlayid);

	parent_overlay.setLoading();
	submitImportPopup(parent_overlay);
}
