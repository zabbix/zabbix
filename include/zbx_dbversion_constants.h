/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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

#ifndef ZABBIX_ZBX_DBVERSION_CONSTANTS_H
#define ZABBIX_ZBX_DBVERSION_CONSTANTS_H

#define ZBX_DBVERSION_UNDEFINED					0

/* ZBX_*_MIN_VERSION           - hard limit */
/* ZBX_*_MIN_SUPPORTED_VERSION - soft limit */
/* ZBX_*_MAX_VERSION           - soft limit */

#define ZBX_MYSQL_MIN_VERSION					50728
#define ZBX_MYSQL_MIN_VERSION_STR				"5.07.28"
#define ZBX_MYSQL_MIN_SUPPORTED_VERSION				80000
#define ZBX_MYSQL_MIN_SUPPORTED_VERSION_STR			"8.00.0"
#define ZBX_MYSQL_MAX_VERSION					90099
#define ZBX_MYSQL_MAX_VERSION_STR				"9.00.x"

#define ZBX_MARIADB_MIN_VERSION					100200
#define ZBX_MARIADB_MIN_VERSION_STR				"10.02.00"
#define ZBX_MARIADB_MIN_SUPPORTED_VERSION			100500
#define ZBX_MARIADB_MIN_SUPPORTED_VERSION_STR			"10.05.00"
#define ZBX_MARIADB_MAX_VERSION					120099
#define ZBX_MARIADB_MAX_VERSION_STR				"12.00.xx"

#define ZBX_POSTGRESQL_MIN_VERSION				100009
#define ZBX_POSTGRESQL_MIN_VERSION_STR				"10.9"
#define ZBX_POSTGRESQL_MIN_SUPPORTED_VERSION			130000
#define ZBX_POSTGRESQL_MIN_SUPPORTED_VERSION_STR		"13.0"
#define ZBX_POSTGRESQL_MAX_VERSION				179999
#define ZBX_POSTGRESQL_MAX_VERSION_STR				"17.x"

#define ZBX_ORACLE_MIN_VERSION					1201000200
#define ZBX_ORACLE_MIN_VERSION_STR				"Database 12c Release 12.01.00.02.x"
#define ZBX_ORACLE_MIN_SUPPORTED_VERSION			1900000000
#define ZBX_ORACLE_MIN_SUPPORTED_VERSION_STR			"Database 19c Release 19.x.x"
#define ZBX_ORACLE_MAX_VERSION					2199000000
#define ZBX_ORACLE_MAX_VERSION_STR				"Database 21c Release 21.x.x"

#define ZBX_ELASTIC_MIN_VERSION					70000
#define ZBX_ELASTIC_MIN_VERSION_STR				"7.x"
#define ZBX_ELASTIC_MAX_VERSION					79999
#define ZBX_ELASTIC_MAX_VERSION_STR				ZBX_ELASTIC_MIN_VERSION_STR

#define ZBX_TIMESCALE_MIN_VERSION				10500
#define ZBX_TIMESCALE_MIN_VERSION_STR				"1.5.0"
#define ZBX_TIMESCALE_MIN_SUPPORTED_VERSION 			20001
#define ZBX_TIMESCALE_MIN_SUPPORTED_VERSION_STR 		"2.0.1"
#define ZBX_TIMESCALE_MIN_VERSION_WITH_LICENSE_PARAM_SUPPORT	20000
#define ZBX_TIMESCALE_MAX_VERSION				22299
#define ZBX_TIMESCALE_MAX_VERSION_STR				"2.22"
#define ZBX_TIMESCALE_LICENSE_APACHE				"apache"
#define ZBX_TIMESCALE_LICENSE_APACHE_STR			"TimescaleDB Apache 2 Edition"
#define ZBX_TIMESCALE_LICENSE_COMMUNITY				"timescale"
#define ZBX_TIMESCALE_LICENSE_COMMUNITY_STR			"TimescaleDB Community Edition"
#define ZBX_TIMESCALE_COMPRESSED_CHUNKS_HISTORY			"compressed_chunks_history"
#define ZBX_TIMESCALE_COMPRESSED_CHUNKS_TRENDS			"compressed_chunks_trends"

#endif /*ZABBIX_ZBX_DBVERSION_CONSTANTS_H*/
