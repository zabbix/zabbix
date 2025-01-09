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

#ifndef ZABBIX_PERSISTENT_STATE_H
#define ZABBIX_PERSISTENT_STATE_H

#include "logfiles.h"

char	*zbx_create_persistent_server_directory(const char *base_path, const char *host, unsigned short port,
		char **error);
char	*zbx_make_persistent_file_name(const char *persistent_server_dir, const char *item_key);
int	zbx_read_persistent_file(const char *filename, char *buf, size_t buf_size, char **err_msg);

int	zbx_restore_file_details(const char *str, struct st_logfile **logfiles, int *logfiles_num,
		zbx_uint64_t *processed_size, int *mtime, char **err_msg);
#endif
