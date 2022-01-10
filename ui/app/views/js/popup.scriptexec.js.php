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

$(document).ready(function() {
	$('#script_execution_log').on('click', function() {
		if ($(this).hasClass('<?= ZBX_STYLE_DISABLED ?>')) {
			return;
		}

		let debug = JSON.parse($('#debug').val()),
			$content = $('<div>'),
			$logitems = $('<div>', {class: 'logitems'}),
			$footer = $('<div>', {class: 'logtotalms'});

		debug.logs.forEach(function (entry) {
			$('<pre>')
				.text(entry.ms + ' ' + entry.level + ' ' + entry.message)
				.appendTo($logitems);
		});
		$content.append($logitems);
		$footer.text(<?= json_encode(_('Time elapsed:')) ?> + " " + debug.ms + 'ms');

		overlayDialogue({
			'title': <?= json_encode(_('Script execution log')) ?>,
			'content': $content,
			'class': 'modal-popup modal-popup-generic debug-modal position-middle',
			'footer': $footer,
			'buttons': [
				{
					'title': <?= json_encode(_('Ok')) ?>,
					'cancel': true,
					'focused': true,
					'action': () => {}
				}
			]
		}, opener);
	});
});
