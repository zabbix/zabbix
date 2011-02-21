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

#ifndef ZABBIX_ZLOG_H
#define ZABBIX_ZLOG_H

#include <stdarg.h>

extern int	CONFIG_ENABLE_LOG;

#ifdef HAVE___VA_ARGS__
#	define zabbix_syslog(fmt, ...) __zbx_zabbix_syslog(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define zabbix_syslog __zbx_zabbix_syslog
#endif /* HAVE___VA_ARGS__ */
void	__zbx_zabbix_syslog(const char *fmt, ...);

#endif
