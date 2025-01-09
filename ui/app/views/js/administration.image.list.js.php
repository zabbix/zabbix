<?php declare(strict_types = 0);
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
