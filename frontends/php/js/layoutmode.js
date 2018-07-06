/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


jQuery(function($) {
	var $norm_mode_btn = $('.btn-dashbrd-normal'),
		$layout_mode = $('.layout-mode');

	if ($norm_mode_btn.length) {
		$(window).on('mousemove keyup scroll', function() {
			clearTimeout($norm_mode_btn.data('timer'));
			$norm_mode_btn
				.removeClass('hidden')
				.data('timer', setTimeout(function() {
					$norm_mode_btn.addClass('hidden');
				}, 2000));
		}).trigger('mousemove');
	}
	if ($layout_mode.length) {
        $layout_mode.on('click', function(e) {
			e.stopPropagation();
			var xhr = updateUserProfile('web.layout.mode',$(this).data('layoutMode'), []);

			xhr.always(function(){
				location.reload();
			});
		});
	}
});
