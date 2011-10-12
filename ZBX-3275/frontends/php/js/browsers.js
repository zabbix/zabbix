/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
var agt = navigator.userAgent.toLowerCase();
var OP = (agt.indexOf('opera') != -1) && window.opera;
var IE = (agt.indexOf('msie') != -1) && document.all && !OP;
var IE9 = (agt.indexOf('msie 9.0') != -1) && document.all && !OP;
var IE8 = (agt.indexOf('msie 8.0') != -1) && document.all && !OP;
var IE7 = (agt.indexOf('msie 7.0') != -1) && document.all && !OP;
var IE6 = (agt.indexOf('msie 6.0') != -1) && document.all && !OP;
var CR = (agt.indexOf('chrome') != -1);
var SF = (agt.indexOf('safari') != -1) && !CR;
var WK = (agt.indexOf('applewebkit') != -1);
var KQ = (agt.indexOf('khtml') != -1) && !WK;
var GK = (agt.indexOf('gecko') != -1) && !KQ && !WK;
var MC = (agt.indexOf('mac') != -1);

function checkBrowser() {
	if (OP) alert('Opera');
	if (IE) alert('IE');
	if (IE6) alert('IE6');
	if (IE7) alert('IE7');
	if (IE8) alert('IE8');
	if (IE9) alert('IE9');
	if (CR) alert('Chrome');
	if (SF) alert('Safari');
	if (WK) alert('Apple Webkit');
	if (KQ) alert('Konqueror');
	if (MC) alert('Mac');
	if (GK) alert('Firefox');
	return 0;
}

/*
 * Redirect outdated browser to warning page
 */
if (document.cookie.indexOf('browserwarning_ignore') < 0) {
	if (IE6 || IE7) {
		window.location.replace('browserwarning.php');
	}
}
