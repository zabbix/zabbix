<?php
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
?>
<?php
/*********************** ITEM ARRAY ***********************
ITEM: array(name, url, param, css, submenu1, submenu2, ..., submenuN)
	name:		text
	url:		text (url for href parameter)
	param:		array(tw => t_val, sb => s_val)
		tw:			target parameter
		t_val:		one of '_blank', '_parent', '_self', '_top'
		sb:			text for statusbar
		s_val:		text
	css:		array(outer => cssarray, inner => cssarray)
		outer:		style for outer div element
		cssarray:	array(normal, mouseover, mousedown)
		inner:		style for inner link element with text
	submen1-N:	list of subitems
**********************************************************/
class CPUMenu {
	public function __construct($items = array(), $width = null) {
		$this->insertJavaScript();
		$this->items = $items;
		$this->width = $width;
	}

	public function getOnActionJS() {
		if (count($this->items) <= 0) {
			return null;
		}
		return 'return show_popup_menu(event,'.zbx_jsvalue($this->items).','.zbx_jsvalue($this->width).');';
	}

	public function insertJavaScript() {
		if (defined('CPUMENU_INSERTJAVASCRIPT_INSERTED')) {
			return null;
		}
		define('CPUMENU_INSERTJAVASCRIPT_INSERTED', 1);
		echo '<script language="JavaScript" type="text/javascript" src="js/menu.js"></script>';
	}
}
?>
