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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
// include JS + templates
require_once('include/views/js/general.search.js.php');
?>
<?php
//	$hosts_wdgt->addItem();
	$searchDiv = new CDiv();
	$searchInputDiv = new CDiv();
	$searchButtonDiv = new CDiv();

	$search_form = new CForm('get','search.php');
	$searchBox = new CTextBox('search', get_request('search'));
	$searchBox->setAttribute('autocomplete', 'off');
	$searchBox->addClass('search');

	$search_form->addItem(new CDiv(array(_('Search').': ', $searchBox)));

	$search_div = new CDiv(SPACE, 'zbx_search nowrap', 'zbx_search');

return new CDiv(SPACE, 'zbx_search', 'zbx_search');
?>
