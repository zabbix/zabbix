<?php declare(strict_types=1);
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

<script>
	$(() => {
		$('#imagetype').on('change', (e) => redirect(e.target.value));

		lazy_load.load(document.querySelectorAll('.lazyload-image img'));
	});

	class lazyLoadImages {

		constructor(root) {
			this.options = {
				root: root,
				rootMargin: '0px 0px 0px 0px',
				threshold: 1
			}
			const self = this;

			new Promise((resolve) => {
				self.observer = new IntersectionObserver(async (entries) => {
					for (let i = 0; i < entries.length; i++) {
						const entry = entries[i];

						if (entry.isIntersecting) {
							continue;
						}

						self.observer.unobserve(entry.target);

						await self.imageLoadHandler(entry.target);
					}

					resolve();
				}, this.options);
			});
		}

		load(elems) {
			if (elems.length == 0) {
				return;
			}

			for (let i = 0; i < elems.length; i++) {
				this.observer.observe(elems[i]);
			}
		}

		async imageLoadHandler(elem) {
			return new Promise((resolve, reject) => {
				elem.onload = () => {
					elem.removeAttribute('data-src');
					resolve(elem);
				};
				elem.onerror = reject;
				elem.src = elem.dataset.src;
			});
		}
	}

	const lazy_load = new lazyLoadImages(document.querySelector('.adm-img'));
</script>
