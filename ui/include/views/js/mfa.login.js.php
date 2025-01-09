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
				correctLevel : QRCode.CorrectLevel.L
			});
		}
	}
</script>
