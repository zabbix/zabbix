<?php
/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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
	define("HOST_STATUS_MONITORED",		0);
	define("HOST_STATUS_NOT_MONITORED",	1);
	define("HOST_STATUS_UNREACHABLE",	2);
	define("HOST_STATUS_TEMPLATE",		3);
	define("HOST_STATUS_DELETED",		4);

	define("GRAPH_DRAW_TYPE_LINE",0);
	define("GRAPH_DRAW_TYPE_FILL",1);
	define("GRAPH_DRAW_TYPE_BOLDLINE",2);
	define("GRAPH_DRAW_TYPE_DOT",3);
	define("GRAPH_DRAW_TYPE_DASHEDLINE",4);

	define("ITEM_VALUE_TYPE_FLOAT",0);
	define("ITEM_VALUE_TYPE_STR",1);

	define("ITEM_STATUS_ACTIVE",0);
	define("ITEM_STATUS_DISABLED",1);
	define("ITEM_STATUS_NOTSUPPORTED",3);

	define("SERVICE_ALGORITHM_NONE",0);
	define("SERVICE_ALGORITHM_MAX",1);
	define("SERVICE_ALGORITHM_MIN",2);

	define("TRIGGER_VALUE_FALSE",0);
	define("TRIGGER_VALUE_TRUE",1);
	define("TRIGGER_VALUE_UNKNOWN",2);

	define("RECIPIENT_TYPE_USER",0);
	define("RECIPIENT_TYPE_GROUP",1);
