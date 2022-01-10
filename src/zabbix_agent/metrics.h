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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_METRICS_H
#define ZABBIX_METRICS_H

#include "zbxtypes.h"

/* define minimal and maximal values of lines to send by agent */
/* per second for checks `log' and `eventlog', used to parse key parameters */
#define	MIN_VALUE_LINES			1
#define	MAX_VALUE_LINES			1000
#define	MAX_VALUE_LINES_MULTIPLIER	10

/* NB! Next list must fit in unsigned char (see ZBX_ACTIVE_METRIC "flags" field below). */
#define ZBX_METRIC_FLAG_PERSISTENT	0x01	/* do not overwrite old values when adding to the buffer */
#define ZBX_METRIC_FLAG_NEW		0x02	/* new metric, just added */
#define ZBX_METRIC_FLAG_LOG_LOG		0x04	/* log[ or log.count[, depending on ZBX_METRIC_FLAG_LOG_COUNT */
#define ZBX_METRIC_FLAG_LOG_LOGRT	0x08	/* logrt[ or logrt.count[, depending on ZBX_METRIC_FLAG_LOG_COUNT */
#define ZBX_METRIC_FLAG_LOG_EVENTLOG	0x10	/* eventlog[ */
#define ZBX_METRIC_FLAG_LOG_COUNT	0x20	/* log.count[ or logrt.count[ */
#define ZBX_METRIC_FLAG_LOG			/* item for log file monitoring, one of the above */	\
		(ZBX_METRIC_FLAG_LOG_LOG | ZBX_METRIC_FLAG_LOG_LOGRT | ZBX_METRIC_FLAG_LOG_EVENTLOG)

typedef struct
{
	char			*key;
	char			*key_orig;
	zbx_uint64_t		lastlogsize;
	int			refresh;
	int			nextcheck;
	int			mtime;
	unsigned char		skip_old_data;	/* for processing [event]log metrics */
	unsigned char		flags;
	unsigned char		state;
	int			big_rec;	/* for logfile reading: 0 - normal record, 1 - long unfinished record */
	int			use_ino;	/* 0 - do not use inodes (on FAT, FAT32) */
						/* 1 - use inodes (up to 64-bit) (various UNIX file systems, NTFS) */
						/* 2 - use 128-bit FileID (currently only on ReFS) to identify files */
						/* on a file system */
	int			error_count;	/* number of file reading errors in consecutive checks */
	int			logfiles_num;
	struct st_logfile	*logfiles;	/* for handling of logfile rotation for logrt[], logrt.count[] items */
	double			start_time;	/* Start time of check for log[], log.count[], logrt[], logrt.count[] */
						/* items. Used for measuring duration of checks. */
	zbx_uint64_t		processed_bytes;	/* number of processed bytes for log[], log.count[], logrt[], */
							/* logrt.count[] items */
	char			*persistent_file_name;	/* not used on Microsoft Windows */
}
ZBX_ACTIVE_METRIC;

#endif
