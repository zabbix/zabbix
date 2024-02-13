<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

		init({qr_code_url}) {
			const qr_code_div = document.querySelector('.qr-code');
			const styles = getComputedStyle(qr_code_div);
			const size = qr_code_div.clientWidth;

			new QRCode(qr_code_div, {
				text: qr_code_url,
				width: size,
				height: size,
				colorDark : styles.color,
				colorLight : styles.backgroundColor,
				correctLevel : QRCode.CorrectLevel.H
			});
		}
	}
</script>
