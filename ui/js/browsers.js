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


var agt = navigator.userAgent.toLowerCase(),
	IE6 = (agt.indexOf('msie 6.0') != -1),
	IE7 = (agt.indexOf('msie 7.0') != -1),
	IE8 = (agt.indexOf('msie 8.0') != -1),
	IE9 = (agt.indexOf('msie 9.0') != -1),
	IE10 = (agt.indexOf('msie 10.0') != -1),
	IE11 = !!agt.match(/trident\/.*rv:11/),
	IE = (IE6 || IE7 || IE8 || IE9 || IE10 || IE11),
	ED = (agt.indexOf('edge') != -1),
	CR = (agt.indexOf('chrome') != -1 && !ED),
	SF = (agt.indexOf('safari') != -1 && !CR && !ED),
	KQ = (agt.indexOf('konqueror') && agt.indexOf('khtml') != -1 && agt.indexOf('applewebkit') == -1),
	GK = (agt.indexOf('gecko') != -1);

// redirect outdated browser to warning page
if (document.cookie.indexOf('browserwarning_ignore') < 0) {
	if (IE || KQ) {
		window.location.replace('browserwarning.php');
	}
}
