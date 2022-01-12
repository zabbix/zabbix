<?php declare(strict_types=1);
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

<script>
	const view = {

		async init({load_images}) {
			document.getElementById('imagetype').addEventListener('change', (e) => {
				redirect(e.target.value);
			});

			if (load_images) {
				window.addEventListener('unhandledrejection', (e) => {
					e.preventDefault();
				});

				for (const image of document.querySelectorAll('.adm-img img[data-src]')) {
					await this.loadImage(image);
				}
			}
		},

		loadImage(image) {
			return new Promise((resolve, reject) => {
				image.onload = () => {
					image.removeAttribute('data-src');
					resolve(image);
				};
				image.onerror = reject;
				image.src = image.dataset.src;
				image.style = '';
			});
		}
	};
</script>
