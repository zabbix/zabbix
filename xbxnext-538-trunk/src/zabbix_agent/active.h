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

#ifndef ZABBIX_ACTIVE_H
#define ZABBIX_ACTIVE_H

#include "threads.h"

extern char	*CONFIG_SOURCE_IP;
extern char	*CONFIG_HOSTNAME;
extern int	CONFIG_REFRESH_ACTIVE_CHECKS;
extern int	CONFIG_BUFFER_SEND;
extern int	CONFIG_BUFFER_SIZE;
extern int	CONFIG_MAX_LINES_PER_SECOND;

/*#define MAX_LINES_PER_SECOND	100*/	/*obsolete, configuration parameter must be used*/

/*define minimal and maximal values of lines to send by agent*/
/*per second for checks `log' and `eventlog', used to parse key parameters*/
#define	MIN_VALUE_LINES	1
#define	MAX_VALUE_LINES	1000

/*Windows event types for `eventlog' check*/
#if defined (_WINDOWS)
#ifndef INFORMATION_TYPE
#define INFORMATION_TYPE	"Information"
#endif
#ifndef WARNING_TYPE
#define WARNING_TYPE		"Warning"
#endif
#ifndef ERROR_TYPE
#define ERROR_TYPE		"Error"
#endif
#ifndef AUDIT_FAILURE
#define AUDIT_FAILURE		"Failure Audit"
#endif
#ifndef AUDIT_SUCCESS
#define AUDIT_SUCCESS		"Success Audit"
#endif
#endif	/*if defined (_WINDOWS)*/

typedef struct zbx_active_metric_type
{
	char	*key, *key_orig;
	int	refresh;
	int	nextcheck;
	int	status;
/* Must be long for fseek() */
	long	lastlogsize;
	int	mtime;
	unsigned char	skip_old_data;	/* for processing [event]log metrics */
} ZBX_ACTIVE_METRIC;

typedef struct active_check_args_type
{
	char		*host;
	unsigned short	port;
} ZBX_THREAD_ACTIVECHK_ARGS;

typedef struct zbx_active_buffer_element_type
{
	char	*host;
	char	*key;
	char	*value;
	int	timestamp;
	char	*source;
	int	severity;
	long	lastlogsize;
	zbx_timespec_t	ts;
	int	logeventid;
	int	mtime;
	unsigned char	persistent;
} ZBX_ACTIVE_BUFFER_ELEMENT;

typedef struct zbx_active_buffer_type
{
	ZBX_ACTIVE_BUFFER_ELEMENT	*data;
	int	count, pcount, lastsent;
} ZBX_ACTIVE_BUFFER;

ZBX_THREAD_ENTRY(active_checks_thread, args);

#endif /* ZABBIX_ACTIVE_H */
