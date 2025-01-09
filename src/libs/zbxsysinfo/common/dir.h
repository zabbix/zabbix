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

#ifndef ZABBIX_SYSINFO_COMMON_DIR_H
#define ZABBIX_SYSINFO_COMMON_DIR_H

#include "module.h"
#include "zbxjson.h"

#define DISK_BLOCK_SIZE			512	/* 512-byte blocks */

#define SIZE_MODE_APPARENT		0	/* bytes in file */
#define SIZE_MODE_DISK			1	/* size on disk */

#define TRAVERSAL_DEPTH_UNLIMITED	-1	/* directory traversal depth is not limited */

/* File Types */
#define ZBX_FT_FILE	0x001
#define ZBX_FT_DIR	0x002
#define ZBX_FT_SYM	0x004
#define ZBX_FT_SOCK	0x008
#define ZBX_FT_BDEV	0x010
#define ZBX_FT_CDEV	0x020
#define ZBX_FT_FIFO	0x040
#define ZBX_FT_ALL	0x080
#define ZBX_FT_DEV	0x100
#define ZBX_FT_OVERFLOW	0x200
#define ZBX_FT_ALLMASK	(ZBX_FT_FILE | ZBX_FT_DIR | ZBX_FT_SYM | ZBX_FT_SOCK | ZBX_FT_BDEV | ZBX_FT_CDEV | ZBX_FT_FIFO)
#define ZBX_FT_DEV2	(ZBX_FT_BDEV | ZBX_FT_CDEV)

#define ZBX_FT_FILE_STR		"file"
#define ZBX_FT_DIR_STR		"dir"
#define ZBX_FT_SYM_STR		"sym"
#define ZBX_FT_SOCK_STR		"sock"
#define ZBX_FT_BDEV_STR		"bdev"
#define ZBX_FT_CDEV_STR		"cdev"
#define ZBX_FT_FIFO_STR		"fifo"
#define ZBX_FT_ALL_STR		"all"
#define ZBX_FT_DEV_STR		"dev"

typedef struct
{
	int depth;
	char *path;
} zbx_directory_item_t;

typedef struct
{
	zbx_uint64_t st_dev;			/* device */
	zbx_uint64_t st_ino;			/* file serial number */
} zbx_file_descriptor_t;

int	vfs_dir_size(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_dir_count(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_dir_get(AGENT_REQUEST *request, AGENT_RESULT *result);

int	zbx_etypes_to_mask(const char *etypes, AGENT_RESULT *result);
int	zbx_vfs_file_info(const char *filename, struct zbx_json *j, int array, char **error);

#endif /* ZABBIX_SYSINFO_COMMON_DIR_H */
