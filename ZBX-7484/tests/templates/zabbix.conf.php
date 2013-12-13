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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

global $DB;

$DB["TYPE"]		= "{DBTYPE}";
$DB["SERVER"]		= "{DBHOST}";
$DB["PORT"]		= "0";
$DB["DATABASE"]		= "{DBNAME}";
$DB["USER"]		= "{DBUSER}";
$DB["PASSWORD"]		= "{DBPASSWORD}";

$ZBX_SERVER		= "hudson";
$ZBX_SERVER_PORT	= "10051";
$ZBX_SERVER_NAME	= "TEST_SERVER_NAME";

$IMAGE_FORMAT_DEFAULT	= IMAGE_FORMAT_PNG;
?>
