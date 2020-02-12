/*
 ** Zabbix
 ** Copyright (C) 2001-2020 Zabbix SIA
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

	const Scrollable = {

		$draggable: null,
		position: {x: 0, y: 0},
		scroller: {left: 0, top: 0},

		init: function($container) {
			if (typeof $container === 'undefined') {
				let _this = this;

				$('.scrollable').each(function() {
					_this.init($(this));
				});

				$(window)
					.on('resize', this.updateAll.bind(this))
					.on('mouseup', this.stopDrag.bind(this))
					.on('mousemove', this.scroll.bind(this));
			}
			else {
				this.create($container);
			}
		},

		activate: function(e) {
			if (this.$draggable === null) {
				let $container = $(e.currentTarget);

				$('.scrollbar-track', $container)
					.toggleClass('is-active', (e.type == 'mouseenter' && $container.data('sh') < 1));
			}
		},

		create: function($container) {
			let $thumb = $('<div>', {class: 'scrollbar-thumb'}),
				$track = $('<div>', {class: 'scrollbar-track'}).append($thumb);

			$container
				.append($track)
				.on('scroll', this.scroll.bind(this))
				.on('mouseenter mouseleave', this.activate.bind(this));

			$thumb.on('mousedown', this.startDrag.bind(this));

			this.update($container);
		},

		updateAll: function() {
			let _this = this;

			$('.scrollable').each(function() {
				_this.update($(this));
			});
		},

		update: function ($container) {
			$container.data('sh', $container.height() / $container[0].scrollHeight);

			$('.scrollbar-track', $container).css({
				top: $container.scrollTop()
			});

			$('.scrollbar-thumb', $container).css({
				top: Math.floor($container.scrollTop() * $container.data('sh')),
				height: Math.ceil($container.height() * $container.data('sh'))
			});
		},

		scroll: function(e) {
			if (e.type == 'scroll') {
				this.update($(e.target));
			}
			else if (this.$draggable !== null) {
				this.$draggable.scrollTop(this.scroller.top + (e.pageY - this.position.y) / this.$draggable.data('sh'));
			}
		},

		startDrag: function(e) {
			let $draggable = $(e.target).closest('.scrollable');

			if ($draggable && $draggable.data('sh') < 1) {
				this.$draggable = $draggable;
				this.position = {x: e.pageX, y: e.pageY};
				this.scroller = {left: $draggable.scrollLeft(), top: $draggable.scrollTop()};
			}

			return false;
		},

		stopDrag: function() {
			this.$draggable = null;
		}
	};

	Scrollable.init();
});
