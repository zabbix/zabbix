<?php
/*
** Zabbix
** Copyright (C) 2001-2011 Zabbix SIA
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
$screenWidget = new CWidget();
$screenWidget->addPageHeader(_('CONFIGURATION OF SCREEN'));
$screenWidget->addHeader($this->data['screen']['name']);
$screenWidget->addItem(BR());
if (!empty($this->data['screen']['templateid'])) {
	$screenWidget->addItem(get_header_host_table('screens', $this->data['screen']['templateid']));
}

$screenTable = get_screen($this->data['screen'], 1);
zbx_add_post_js('init_screen("'.$this->data['screenid'].'", "iframe", "'.$this->data['screenid'].'");');
zbx_add_post_js('timeControl.processObjects();');

// append form to widget
$screenWidget->addItem($screenTable);
return $screenWidget;
?>
