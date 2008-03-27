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

global $DB_TYPE, $DB_SERVER, $DB_PORT, $DB_DATABASE, $DB_USER, $DB_PASSWORD, $IMAGE_FORMAT_DEFAULT, $ZBX_SERVER, $ZBX_SERVER_PORT;


// MYSQL
//*
$DB_TYPE	= "MYSQL";
$DB_SERVER	= "192.168.3.4";
$DB_PORT	= "0";
$DB_DATABASE	= "aly";
$DB_USER	= "root";
$DB_PASSWORD	= "";
//*/

/*
$DB_TYPE	= "MYSQL";
$DB_SERVER	= "localhost";
$DB_PORT	= "0";
$DB_DATABASE	= "zabbix";
$DB_USER	= "admin";
$DB_PASSWORD	= "Xpoint1";
//*/


// POSTGERESQL
/*
$DB_TYPE	= "POSTGRESQL";
$DB_SERVER	= "127.0.0.1";
$DB_PORT	= "0";
$DB_DATABASE	= "zabbix";
$DB_USER	= "admin";
$DB_PASSWORD	= "Xpoint1";
//*/

/*
$DB_TYPE	= "POSTGRESQL";
$DB_SERVER	= "192.168.3.2";
$DB_PORT	= "0";
$DB_DATABASE	= "aly";
$DB_USER	= "artem";
$DB_PASSWORD	= "artem";
//*/

// ORACLE
/*
$DB_TYPE		= "ORACLE";
$DB_SERVER		= "localhost";
$DB_PORT		= "1521";
$DB_DATABASE		= "localhost";
$DB_USER		= "zabbix";
$DB_PASSWORD		= "zabbix";
$ZBX_SERVER		= "192.168.3.4";
$ZBX_SERVER_PORT	= "31055";
//*/


$IMAGE_FORMAT_DEFAULT	= IMAGE_FORMAT_PNG;
?>