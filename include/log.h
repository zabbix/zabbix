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

#ifndef ZABBIX_LOG_H
#define ZABBIX_LOG_H

#define LOG_LEVEL_EMPTY		0
#define LOG_LEVEL_CRIT		1
#define LOG_LEVEL_ERR		2
#define LOG_LEVEL_WARNING	3
#define LOG_LEVEL_INFORMATION	4
#define LOG_LEVEL_DEBUG		5

#define LOG_TYPE_UNDEFINED	0
#define LOG_TYPE_SYSLOG		1
#define LOG_TYPE_FILE		2

/* Type - 0 (syslog), 1 - file */
int zabbix_open_log(int type,int level, const char *filename);
void zabbix_log(int level, const char *fmt, ...);
void zabbix_close_log(void);
void zabbix_set_log_level(int level);

char *strerror_from_system(unsigned long error);
char *strerror_from_module(unsigned long error, const char *module);

#endif
