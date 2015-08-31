<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="X-UA-Compatible" content="IE=Edge"/>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="Author" content="Zabbix SIA" />
		<title>You are using an outdated browser.</title>
		<link rel="icon" href="favicon.ico">
		<link rel="apple-touch-icon-precomposed" sizes="76x76" href="apple-touch-icon-76x76-precomposed.png">
		<link rel="apple-touch-icon-precomposed" sizes="120x120" href="apple-touch-icon-120x120-precomposed.png">
		<link rel="apple-touch-icon-precomposed" sizes="152x152" href="apple-touch-icon-152x152-precomposed.png">
		<link rel="apple-touch-icon-precomposed" sizes="180x180" href="apple-touch-icon-180x180-precomposed.png">
		<link rel="icon" sizes="192x192" href="touch-icon-192x192.png">
		<meta name="msapplication-TileImage" content="ms-tile-144x144.png">
		<meta name="msapplication-TileColor" content="#d40000">
		<link rel="stylesheet" type="text/css" href="styles/<?= ZBX_DEFAULT_THEME ?>.css" />
	</head>
	<body>
		<div class="<?= ZBX_STYLE_ARTICLE ?>">
			<div class="browser-warning-container">
				<h2 class="<?= ZBX_STYLE_RED ?>">You are using an outdated browser.</h2>
				<p>Zabbix frontend is built on advanced, modern technologies and does not support old browsers. It is highly recommended that you choose and install a modern browser. It is free of charge and only takes a couple of minutes.</p>
				<p>New browsers usually come with support for new technologies, increasing web page speed, better privacy settings and so on. They also resolve security and functional issues.</p>
				<ul>
					<li>
						<a target="_blank" href="http://www.google.com/chrome"><div class="browser-logo-chrome"></div></a>
						<a target="_blank" href="http://www.google.com/chrome">Google Chrome</a>
					</li>
					<li>
						<a target="_blank" href="http://www.mozilla.org/firefox"><div class="browser-logo-ff"></div></a>
						<a target="_blank" href="http://www.mozilla.org/firefox">Mozilla Firefox</a>
					</li>
					<li>
						<a target="_blank" href="http://windows.microsoft.com/en-US/internet-explorer/downloads/ie"><div class="browser-logo-ie"></div></a>
						<a target="_blank" href="http://windows.microsoft.com/en-US/internet-explorer/downloads/ie">Internet Explorer</a>
					</li>
					<li>
						<a target="_blank" href="http://www.opera.com/download"><div class="browser-logo-opera"></div></a>
						<a target="_blank" href="http://www.opera.com/download">Opera browser</a>
					</li>
					<li>
						<a target="_blank" href="http://www.apple.com/safari/download"><div class="browser-logo-safari"></div></a>
						<a target="_blank" href="http://www.apple.com/safari/download">Apple Safari</a>
					</li>
				</ul>
				<div class="browser-warning-footer">
					<a href="index.php" onClick="javascript: document.cookie='browserwarning_ignore=yes';">Continue despite this warning</a>
				</div>
			</div>
		</div>
		<div class="<?= ZBX_STYLE_FOOTER ?>">
			<a class="logo" target="_blank" href="http://www.zabbix.com"></a>
			&copy; 2001&ndash;2015, <a class="<?= ZBX_STYLE_GREY ?> <?= ZBX_STYLE_LINK_ALT ?>" target="_blank" href="http://www.zabbix.com/">Zabbix SIA</a>
		</div>
	</body>
</html>
