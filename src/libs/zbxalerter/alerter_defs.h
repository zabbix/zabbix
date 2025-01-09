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

#ifndef ZABBIX_ALERTER_DEFS_H
#define ZABBIX_ALERTER_DEFS_H

/* alerter -> manager */
#define ZBX_IPC_ALERTER_REGISTER	1000
#define ZBX_IPC_ALERTER_RESULT		1001
#define ZBX_IPC_ALERTER_MEDIATYPES	1002
#define ZBX_IPC_ALERTER_ALERTS		1003
#define ZBX_IPC_ALERTER_WATCHDOG	1004
#define ZBX_IPC_ALERTER_RESULTS		1005
#define ZBX_IPC_ALERTER_DROP_MEDIATYPES	1006
#define ZBX_IPC_ALERT_SYNCER_REGISTER	1007

/* manager -> alerter */
#define ZBX_IPC_ALERTER_EMAIL		1100
#define ZBX_IPC_ALERTER_SMS		1102
#define ZBX_IPC_ALERTER_EXEC		1104
#define ZBX_IPC_ALERTER_WEBHOOK		1105

/* process -> manager */
#define ZBX_IPC_ALERTER_DIAG_STATS		1200
#define ZBX_IPC_ALERTER_DIAG_TOP_MEDIATYPES	1201
#define ZBX_IPC_ALERTER_DIAG_TOP_SOURCES	1202
#define ZBX_IPC_ALERTER_SEND_ALERT		1203
#define ZBX_IPC_ALERTER_BEGIN_DISPATCH		1204
#define ZBX_IPC_ALERTER_SEND_DISPATCH		1205
#define ZBX_IPC_ALERTER_END_DISPATCH		1206

/* manager -> process */
#define ZBX_IPC_ALERTER_DIAG_STATS_RESULT		1300
#define ZBX_IPC_ALERTER_DIAG_TOP_MEDIATYPES_RESULT	1301
#define ZBX_IPC_ALERTER_DIAG_TOP_SOURCES_RESULT		1302
#define ZBX_IPC_ALERTER_ABORT_DISPATCH			1303

#endif
