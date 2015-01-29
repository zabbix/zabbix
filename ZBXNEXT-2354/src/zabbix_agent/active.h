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

#ifndef ZABBIX_ACTIVE_H
#define ZABBIX_ACTIVE_H

#include "threads.h"

extern char	*CONFIG_SOURCE_IP;
extern char	*CONFIG_HOSTNAME;
extern char	*CONFIG_HOST_METADATA;
extern char	*CONFIG_HOST_METADATA_ITEM;
extern int	CONFIG_REFRESH_ACTIVE_CHECKS;
extern int	CONFIG_BUFFER_SEND;
extern int	CONFIG_BUFFER_SIZE;
extern int	CONFIG_MAX_LINES_PER_SECOND;
extern char	*CONFIG_LISTEN_IP;
extern int	CONFIG_LISTEN_PORT;

/* define minimal and maximal values of lines to send by agent */
/* per second for checks `log' and `eventlog', used to parse key parameters */
#define	MIN_VALUE_LINES	1
#define	MAX_VALUE_LINES	1000

#define HOST_METADATA_LEN	255	/* UTF-8 characters, not bytes */

/* Windows event types for `eventlog' check */
#ifdef _WINDOWS
#	ifndef INFORMATION_TYPE
#		define INFORMATION_TYPE	"Information"
#	endif
#	ifndef WARNING_TYPE
#		define WARNING_TYPE	"Warning"
#	endif
#	ifndef ERROR_TYPE
#		define ERROR_TYPE	"Error"
#	endif
#	ifndef AUDIT_FAILURE
#		define AUDIT_FAILURE	"Failure Audit"
#	endif
#	ifndef AUDIT_SUCCESS
#		define AUDIT_SUCCESS	"Success Audit"
#	endif
#	ifndef CRITICAL_TYPE
#		define CRITICAL_TYPE	"Critical"
#	endif
#	ifndef VERBOSE_TYPE
#		define VERBOSE_TYPE	"Verbose"
#	endif
#endif	/* _WINDOWS */

typedef struct
{
	char			*key, *key_orig;
	zbx_uint64_t		lastlogsize;
	int			refresh;
	int			nextcheck;
/* must be long for fseek() */
	int			mtime;
	unsigned char		skip_old_data;	/* for processing [event]log metrics */
	unsigned char		state;
	int			big_rec;	/* for logfile reading: 0 - normal record, 1 - long unfinished record */
	int			use_ino;	/* 0 - do not use inodes (on FAT, FAT32) */
						/* 1 - use inodes (up to 64-bit) (various UNIX file systems, NTFS) */
						/* 2 - use 128-bit FileID (currently only on ReFS) to identify files */
						/* on a file system */
	int			error_count;	/* number of file reading errors in consecutive checks */
	int			logfiles_num;
	struct st_logfile	*logfiles;	/* for handling of logfile rotation for logrt[] items */
}
ZBX_ACTIVE_METRIC;

typedef struct
{
	char		*host;
	unsigned short	port;
}
ZBX_THREAD_ACTIVECHK_ARGS;

typedef struct
{
	char		*host;
	char		*key;
	char		*value;
	zbx_uint64_t	lastlogsize;
	int		timestamp;
	char		*source;
	int		severity;
	zbx_timespec_t	ts;
	int		logeventid;
	int		mtime;
	unsigned char	persistent;
}
ZBX_ACTIVE_BUFFER_ELEMENT;

typedef struct
{
	ZBX_ACTIVE_BUFFER_ELEMENT	*data;
	int	count, pcount, lastsent;
	int	first_error;
}
ZBX_ACTIVE_BUFFER;

ZBX_THREAD_ENTRY(active_checks_thread, args);

#endif	/* ZABBIX_ACTIVE_H */
