/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#ifndef ZABBIX_DB_LENGTHS_H
#define ZABBIX_DB_LENGTHS_H

#define TRIGGER_OPDATA_LEN		255
#define TRIGGER_URL_LEN			255
#define TRIGGER_DESCRIPTION_LEN		255
#if defined(HAVE_ORACLE)
#	define TRIGGER_COMMENTS_LEN	2048
#else
#	define TRIGGER_COMMENTS_LEN	65535
#endif
#define TRIGGER_EVENT_NAME_LEN		2048

#define ZBX_DB_TAG_NAME_LEN			255
#define ZBX_DB_TAG_VALUE_LEN			255

#define GROUP_NAME_LEN			255

#define ALERT_ERROR_LEN			2048
#define ALERT_ERROR_LEN_MAX		(ALERT_ERROR_LEN + 1)

#define ITEM_PREPROC_PARAMS_LEN		65535

#define EVENT_NAME_LEN			2048

#define REPORT_ERROR_LEN		2048
#endif /* ZABBIX_DB_LENGTHS_H */
