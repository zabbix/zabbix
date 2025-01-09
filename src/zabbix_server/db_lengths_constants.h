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

#ifndef ZABBIX_DB_LENGTHS_H
#define ZABBIX_DB_LENGTHS_H

#define TRIGGER_OPDATA_LEN		255
#define TRIGGER_URL_LEN			2048
#define TRIGGER_URL_NAME_LEN		64
#define TRIGGER_DESCRIPTION_LEN		255
#define TRIGGER_COMMENTS_LEN            65535
#define TRIGGER_EVENT_NAME_LEN		2048

#define ZBX_DB_TAG_NAME_LEN		255
#define ZBX_DB_TAG_VALUE_LEN		255

#define GROUP_NAME_LEN			255

#define ALERT_ERROR_LEN			2048
#define ALERT_ERROR_LEN_MAX		(ALERT_ERROR_LEN + 1)

#define EVENT_NAME_LEN			2048

#define REPORT_ERROR_LEN		2048
#endif /* ZABBIX_DB_LENGTHS_H */
