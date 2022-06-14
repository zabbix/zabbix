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

#ifndef ZABBIX_LOGFILES_H
#define ZABBIX_LOGFILES_H

#include "../metrics.h"
#include "persistent_state.h"

#define ZBX_MD5_PRINT_BUF_LEN	((MD5_DIGEST_SIZE) * 2 + 1)	/* for MD5 sum representation with hex-digits */

typedef enum
{
	ZBX_LOG_ROTATION_LOGRT = 0,	/* pure rotation model */
	ZBX_LOG_ROTATION_LOGCPT,	/* copy-truncate rotation model */
	ZBX_LOG_ROTATION_REREAD,	/* reread if modification time changes but size does not */
	ZBX_LOG_ROTATION_NO_REREAD	/* don't reread if modification time changes but size does not */
}
zbx_log_rotation_options_t;

struct	st_logfile
{
	char		*filename;
	int		mtime;		/* st_mtime from stat() */
	int		seq;		/* number in processing order */
	int		retry;
	int		incomplete;	/* 0 - the last record ends with a newline, 1 - the last record contains */
					/* no newline at the end */
	int		copy_of;	/* '-1' - the file is not a copy. '0 <= copy_of' - this file is a copy of */
					/* the file with index 'copy_of' in the old log file list. */
	zbx_uint64_t	dev;		/* ID of device containing file */
	zbx_uint64_t	ino_lo;		/* UNIX: inode number. Microsoft Windows: nFileIndexLow or FileId.LowPart */
	zbx_uint64_t	ino_hi;		/* Microsoft Windows: nFileIndexHigh or FileId.HighPart */
	zbx_uint64_t	size;		/* st_size from stat() */
	zbx_uint64_t	processed_size;	/* how far the Zabbix agent has analyzed the file */
	int		md5_block_size;	/* size of the first and last blocks of file for which the md5 sum is */
					/* calculated (in 'first_block_md5') */
	md5_byte_t	first_block_md5[MD5_DIGEST_SIZE];	/* md5 sum of the initial part of the file */
	zbx_uint64_t	last_block_offset;		/* position of the last block from the beginning of file */
	md5_byte_t	last_block_md5[MD5_DIGEST_SIZE];	/* md5 sum of the last block */
};

typedef int 	(*zbx_process_value_func_t)(zbx_vector_ptr_t *addrs, zbx_vector_ptr_t *agent2_result, const char *host,
		const char *key, const char *value, unsigned char state, zbx_uint64_t *lastlogsize, const int *mtime,
		const unsigned long *timestamp, const char *source, const unsigned short *severity,
		const unsigned long *logeventid, unsigned char flags);

void	destroy_logfile_list(struct st_logfile **logfiles, int *logfiles_alloc, int *logfiles_num);

int	process_log_check(zbx_vector_ptr_t *addrs, zbx_vector_ptr_t *agent2_result, zbx_vector_ptr_t *regexps,
		ZBX_ACTIVE_METRIC *metric, zbx_process_value_func_t process_value_cb, zbx_uint64_t *lastlogsize_sent,
		int *mtime_sent, char **error, zbx_vector_pre_persistent_t *prep_vec);

struct st_logfile	*find_last_processed_file_in_logfiles_list(struct st_logfile *logfiles, int logfiles_num);
#endif
