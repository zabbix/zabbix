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

#ifndef ZABBIX_LOGFILES_H
#define ZABBIX_LOGFILES_H

#include "../metrics/metrics.h"
#include "zbxalgo.h"
#include "zbxhash.h"
#include "zbxcomms.h"
#include "zbxregexp.h"
#include "zbxcfg.h"

#define ZBX_MD5_PRINT_BUF_LEN	((ZBX_MD5_DIGEST_SIZE) * 2 + 1)	/* for MD5 sum representation with hex-digits */

typedef struct
{
	zbx_uint64_t	itemid;
	char		*persistent_file_name;
	/* data for writing into persistent file */
	char		*filename;
	int		mtime;
	int		seq;
	int		incomplete;
	int		copy_of;
	zbx_uint64_t	dev;
	zbx_uint64_t	ino_lo;
	zbx_uint64_t	ino_hi;
	zbx_uint64_t	size;
	zbx_uint64_t	processed_size;
	int		md5_block_size;
	md5_byte_t	first_block_md5[ZBX_MD5_DIGEST_SIZE];
	zbx_uint64_t	last_block_offset;
	md5_byte_t	last_block_md5[ZBX_MD5_DIGEST_SIZE];
}
zbx_pre_persistent_t;

ZBX_VECTOR_DECL(pre_persistent, zbx_pre_persistent_t)

typedef struct
{
	zbx_uint64_t	itemid;
	time_t		not_received_time;	/* time the item was not received anymore */
						/* in the list of active checks           */
	char		*persistent_file_name;
}
zbx_persistent_inactive_t;

ZBX_VECTOR_DECL(persistent_inactive, zbx_persistent_inactive_t)

int	zbx_remove_persistent_file(const char *pathname, char **error);
void	zbx_write_persistent_files(zbx_vector_pre_persistent_t *prep_vec);
void	zbx_clean_pre_persistent_elements(zbx_vector_pre_persistent_t *prep_vec);
void	zbx_add_to_persistent_inactive_list(zbx_vector_persistent_inactive_t *inactive_vec, zbx_uint64_t itemid,
		const char *filename);
void	zbx_remove_from_persistent_inactive_list(zbx_vector_persistent_inactive_t *inactive_vec, zbx_uint64_t itemid);
void	zbx_remove_inactive_persistent_files(zbx_vector_persistent_inactive_t *inactive_vec);
int	zbx_find_or_create_prep_vec_element(zbx_vector_pre_persistent_t *prep_vec, zbx_uint64_t itemid,
		const char *persistent_file_name);
void	zbx_init_prep_vec_data(const struct st_logfile *logfile, zbx_pre_persistent_t *prep_vec_elem);
void	zbx_update_prep_vec_data(const struct st_logfile *logfile, zbx_uint64_t processed_size,
		zbx_pre_persistent_t *prep_vec_elem);

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
	md5_byte_t	first_block_md5[ZBX_MD5_DIGEST_SIZE];	/* md5 sum of the initial part of the file */
	zbx_uint64_t	last_block_offset;		/* position of the last block from the beginning of file */
	md5_byte_t	last_block_md5[ZBX_MD5_DIGEST_SIZE];	/* md5 sum of the last block */
};

typedef int	(*zbx_process_value_func_t)(zbx_vector_addr_ptr_t *addrs, zbx_vector_ptr_t *agent2_result,
		zbx_uint64_t itemid, const char *host, const char *key, const char *value, unsigned char state,
		zbx_uint64_t *lastlogsize, const int *mtime, const unsigned long *timestamp, const char *source,
		const unsigned short *severity, const unsigned long *logeventid, unsigned char flags,
		const zbx_config_tls_t *config_tls, int config_timeout, const char *config_source_ip,
		int config_buffer_send, int config_buffer_size);

int	process_log_check(zbx_vector_addr_ptr_t *addrs, zbx_vector_ptr_t *agent2_result,
		zbx_vector_expression_t *regexps, zbx_active_metric_t *metric, zbx_process_value_func_t process_value_cb,
		zbx_uint64_t *lastlogsize_sent, int *mtime_sent, char **error, zbx_vector_pre_persistent_t *prep_vec,
		const zbx_config_tls_t *config_tls, int config_timeout, const char *config_source_ip,
		const char *config_hostname, int config_buffer_send, int config_buffer_size,
		int config_max_lines_per_second);

struct st_logfile	*find_last_processed_file_in_logfiles_list(struct st_logfile *logfiles, int logfiles_num);
#endif
