/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


var agt = navigator.userAgent.toLowerCase(),
	OP = (agt.indexOf('opera') != -1) && window.opera,
	IE = (agt.indexOf('msie') != -1) && document.all && !OP,
	IE10 = (agt.indexOf('msie 10.0') != -1) && document.all && !OP,
	IE9 = (agt.indexOf('msie 9.0') != -1) && document.all && !OP,
	IE8 = (agt.indexOf('msie 8.0') != -1) && document.all && !OP,
	IE7 = (agt.indexOf('msie 7.0') != -1) && document.all && !OP,
	IE6 = (agt.indexOf('msie 6.0') != -1) && document.all && !OP,
	CR = (agt.indexOf('chrome') != -1),
	SF = (agt.indexOf('safari') != -1) && !CR,
	WK = (agt.indexOf('applewebkit') != -1),
	KQ = (agt.indexOf('khtml') != -1) && !WK,
	GK = (agt.indexOf('gecko') != -1) && !KQ && !WK,
	MC = (agt.indexOf('mac') != -1);

// redirect outdated browser to warning page
if (document.cookie.indexOf('browserwarning_ignore') < 0) {
	if (IE6 || IE7 || KQ) {
		window.location.replace('browserwarning.php');
	}
}
