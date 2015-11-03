/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#ifndef ZABBIX_SETPROCTITLE_H
#define ZABBIX_SETPROCTITLE_H

#if defined(__linux__)				/* Linux */
#       define PS_OVERWRITE_ARGV
#elif defined(_AIX)				/* AIX */
#       define PS_OVERWRITE_ARGV
#       define PS_CONCAT_ARGV
#elif defined(__sun) && defined(__SVR4)		/* Solaris */
#       define PS_OVERWRITE_ARGV
#       define PS_APPEND_ARGV
#elif defined(HAVE_SYS_PSTAT_H)			/* HP-UX */
#       define PS_PSTAT_ARGV
#elif defined(__APPLE__) && defined(__MACH__)	/* OS X */
#	include <TargetConditionals.h>
#	if TARGET_OS_MAC == 1 && TARGET_OS_EMBEDDED == 0 && TARGET_OS_IPHONE == 0 && TARGET_IPHONE_SIMULATOR == 0
#		define PS_OVERWRITE_ARGV
#		define PS_DARWIN_ARGV
#	endif
#endif

char	**setproctitle_save_env(int argc, char **argv);
void	setproctitle_set_status(const char *status);
void	setproctitle_free_env(void);

#endif	/* ZABBIX_SETPROCTITLE_H */
