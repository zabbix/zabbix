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

#ifndef ZABBIX_ZBX_DBVERSION_CONSTANTS_H
#define ZABBIX_ZBX_DBVERSION_CONSTANTS_H

#define ZBX_DBVERSION_UNDEFINED					0

/* ZBX_*_MIN_VERSION           - hard limit */
/* ZBX_*_MIN_SUPPORTED_VERSION - soft limit */
/* ZBX_*_MAX_VERSION           - soft limit */

#define ZBX_MYSQL_MIN_VERSION					50728
#define ZBX_MYSQL_MIN_VERSION_STR				"5.07.28"
#define ZBX_MYSQL_MIN_SUPPORTED_VERSION				80030
#define ZBX_MYSQL_MIN_SUPPORTED_VERSION_STR			"8.00.30"
#define ZBX_MYSQL_MAX_VERSION					90099
#define ZBX_MYSQL_MAX_VERSION_STR				"9.00.x"

#define ZBX_MARIADB_MIN_VERSION					100200
#define ZBX_MARIADB_MIN_VERSION_STR				"10.02.00"
#define ZBX_MARIADB_MIN_SUPPORTED_VERSION			100500
#define ZBX_MARIADB_MIN_SUPPORTED_VERSION_STR			"10.05.00"
#define ZBX_MARIADB_MAX_VERSION					110599
#define ZBX_MARIADB_MAX_VERSION_STR				"11.05.xx"

#define ZBX_POSTGRESQL_MIN_VERSION				100009
#define ZBX_POSTGRESQL_MIN_VERSION_STR				"10.9"
#define ZBX_POSTGRESQL_MIN_SUPPORTED_VERSION			130000
#define ZBX_POSTGRESQL_MIN_SUPPORTED_VERSION_STR		"13.0"
#define ZBX_POSTGRESQL_MAX_VERSION				179999
#define ZBX_POSTGRESQL_MAX_VERSION_STR				"17.x"

#define ZBX_ELASTIC_MIN_VERSION					70000
#define ZBX_ELASTIC_MIN_VERSION_STR				"7.x"
#define ZBX_ELASTIC_MAX_VERSION					79999
#define ZBX_ELASTIC_MAX_VERSION_STR				ZBX_ELASTIC_MIN_VERSION_STR

#define ZBX_TIMESCALE_MIN_VERSION				20001
#define ZBX_TIMESCALE_MIN_VERSION_STR				"2.0.1"
#define ZBX_TIMESCALE_MIN_SUPPORTED_VERSION 			21300
#define ZBX_TIMESCALE_MIN_SUPPORTED_VERSION_STR 		"2.13.0"
#define ZBX_TIMESCALE_MAX_VERSION				21799
#define ZBX_TIMESCALE_MAX_VERSION_STR				"2.17"
#define ZBX_TIMESCALE_LICENSE_APACHE_STR			"TimescaleDB Apache 2 Edition"
#define ZBX_TIMESCALE_LICENSE_COMMUNITY				"timescale"
#define ZBX_TIMESCALE_LICENSE_COMMUNITY_STR			"TimescaleDB Community Edition"

#endif /*ZABBIX_ZBX_DBVERSION_CONSTANTS_H*/
