<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	class CTableInfo extends CTable
	{
/* public */
		function CTableInfo($message='...',$class='tableinfo')
		{
			parent::CTable($message,$class);

			$this->oddRowClass = 'odd_row';
			$this->evenRowClass = 'even_row';
			$this->options['cellpadding'] = 3;
			$this->options['cellspacing'] = 1;
			$this->headerClass = 'header';
			$this->footerClass = 'footer';
		}
	}
?>
