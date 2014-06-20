/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#ifndef ZABBIX_LOG_H
#define ZABBIX_LOG_H

#define LOG_LEVEL_EMPTY		0	/* printing nothing (if not LOG_LEVEL_INFORMATION set) */
#define LOG_LEVEL_CRIT		1
#define LOG_LEVEL_ERR		2
#define LOG_LEVEL_WARNING	3
#define LOG_LEVEL_DEBUG		4

#define LOG_LEVEL_INFORMATION	127	/* printing in any case no matter what level set */

#define LOG_TYPE_UNDEFINED	0
#define LOG_TYPE_SYSLOG		1
#define LOG_TYPE_FILE		2

typedef enum
{
	ERR_Z3001 = 3001,
	ERR_Z3002,
	ERR_Z3003,
	ERR_Z3004,
	ERR_Z3005,
	ERR_Z3006,
	ERR_Z3007
}
zbx_err_codes_t;

extern int	CONFIG_LOG_FILE_SIZE;

/* Type - 1 (syslog), 2 - file */
int zabbix_open_log(int type,int level, const char *filename);

#ifdef HAVE___VA_ARGS__
#	define zabbix_log(level, fmt, ...) __zbx_zabbix_log(level, ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define zabbix_log __zbx_zabbix_log
#endif /* HAVE___VA_ARGS__ */
void zabbix_errlog(zbx_err_codes_t err, ...);
void __zbx_zabbix_log(int level, const char *fmt, ...);

void zabbix_close_log();
void zabbix_set_log_level(int level);

char *zbx_strerror(int errnum);
char *strerror_from_system(unsigned long error);
#ifdef _WINDOWS
char *strerror_from_module(unsigned long error, LPCTSTR module);
#endif

#if defined(_WINDOWS)
#	define redirect_std(filename)
#else
	void redirect_std(const char *filename);
#endif

#endif
