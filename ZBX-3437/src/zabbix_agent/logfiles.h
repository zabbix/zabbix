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

#ifndef ZABBIX_LOGFILES_H
#define ZABBIX_LOGFILES_H

#include "zbxregexp.h"
#include "md5.h"

struct	st_logfile
{
	char		*filename;
	int		mtime;		/* st_mtime from stat() */
	int		md5size;	/* size of the initial part for which the md5 sum is calculated */
	int		seq;		/* number in processing order */
	int		incomplete;	/* 0 - the last record ends with a newline, 1 - the last record contains */
					/* no newline at the end */
	zbx_uint64_t	dev;		/* ID of device containing file */
	zbx_uint64_t	ino_lo;		/* UNIX: inode number. Microsoft Windows: nFileIndexLow or FileId.LowPart */
	zbx_uint64_t	ino_hi;		/* Microsoft Windows: nFileIndexHigh or FileId.HighPart */
	zbx_uint64_t	size;		/* st_size from stat() */
	zbx_uint64_t	processed_size;	/* how far the Zabbix agent has analyzed the file */
	md5_byte_t	md5buf[MD5_DIGEST_SIZE];	/* md5 sum of the initial part of the file */
};

int	process_log(const char *filename, zbx_uint64_t *lastlogsize, int *mtime, unsigned char *skip_old_data,
		int *big_rec, int *incomplete, char **err_msg, const char *encoding, zbx_vector_ptr_t *regexps,
		const char *pattern, const char *output_template, int *p_count, int *s_count,
		zbx_process_value_func_t process_value, const char *server, unsigned short port, const char *hostname,
		const char *key);

int	process_logrt(int is_logrt, const char *filename, zbx_uint64_t *lastlogsize, int *mtime,
		unsigned char *skip_old_data, int *big_rec, int *use_ino, int *error_count, char **err_msg,
		struct st_logfile **logfiles_old, int *logfiles_num_old, const char *encoding,
		zbx_vector_ptr_t *regexps, const char *pattern, const char *output_template, int *p_count, int *s_count,
		zbx_process_value_func_t process_value, const char *server, unsigned short port, const char *hostname,
		const char *key);

#endif
