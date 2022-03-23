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

#ifndef ZABBIX_PERSISTENT_STATE_H
#define ZABBIX_PERSISTENT_STATE_H

#include "common.h"	/* for SEC_PER_DAY */
#include "md5.h"	/* for MD5_DIGEST_SIZE, md5_byte_t */
#include "zbxtypes.h"	/* for zbx_uint64_t struct st_logfile; */
#include "zbxalgo.h"

struct st_logfile;

#define ZBX_PERSIST_INACTIVITY_PERIOD	SEC_PER_DAY	/* the time period after which persistent files used by log */
							/* items which are not received in active check list can be */
							/* removed */
typedef struct
{
	char		*key_orig;
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
	md5_byte_t	first_block_md5[MD5_DIGEST_SIZE];
	zbx_uint64_t	last_block_offset;
	md5_byte_t	last_block_md5[MD5_DIGEST_SIZE];
}
zbx_pre_persistent_t;

ZBX_VECTOR_DECL(pre_persistent, zbx_pre_persistent_t)

typedef struct
{
	char	*key_orig;
	time_t	not_received_time;	/* time the item was not received anymore in the list of active checks */
	char	*persistent_file_name;
}
zbx_persistent_inactive_t;

ZBX_VECTOR_DECL(persistent_inactive, zbx_persistent_inactive_t)

char	*zbx_create_persistent_server_directory(const char *base_path, const char *host, unsigned short port,
		char **error);
char	*zbx_make_persistent_file_name(const char *persistent_server_dir, const char *item_key);
int	zbx_read_persistent_file(const char *filename, char *buf, size_t buf_size, char **err_msg);
int	zbx_remove_persistent_file(const char *pathname, char **error);
void	zbx_write_persistent_files(zbx_vector_pre_persistent_t *prep_vec);
void	zbx_clean_pre_persistent_elements(zbx_vector_pre_persistent_t *prep_vec);

void	zbx_add_to_persistent_inactive_list(zbx_vector_persistent_inactive_t *inactive_vec, char *key,
		const char *filename);
void	zbx_remove_from_persistent_inactive_list(zbx_vector_persistent_inactive_t *inactive_vec, char *key);
void	zbx_remove_inactive_persistent_files(zbx_vector_persistent_inactive_t *inactive_vec);

int	zbx_find_or_create_prep_vec_element(zbx_vector_pre_persistent_t *prep_vec, const char *key,
		const char *persistent_file_name);
void	zbx_init_prep_vec_data(const struct st_logfile *logfile, zbx_pre_persistent_t *prep_vec_elem);
void	zbx_update_prep_vec_data(const struct st_logfile *logfile, zbx_uint64_t processed_size,
		zbx_pre_persistent_t *prep_vec_elem);
int	zbx_restore_file_details(const char *str, struct st_logfile **logfiles, int *logfiles_num,
		zbx_uint64_t *processed_size, int *mtime, char **err_msg);
#endif
