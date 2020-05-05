/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

#ifndef ZABBIX_SYSINFO_COMMON_DIR_H
#define ZABBIX_SYSINFO_COMMON_DIR_H

#include "sysinfo.h"

#define DISK_BLOCK_SIZE			512	/* 512-byte blocks */

#define SIZE_MODE_APPARENT		0	/* bytes in file */
#define SIZE_MODE_DISK			1	/* size on disk */

#define TRAVERSAL_DEPTH_UNLIMITED	-1	/* directory traversal depth is not limited */

/* File Types */
#define FT_FILE		0x001
#define FT_DIR		0x002
#define FT_SYM		0x004
#define FT_SOCK		0x008
#define FT_BDEV		0x010
#define FT_CDEV		0x020
#define FT_FIFO		0x040
#define FT_ALL		0x080
#define FT_DEV		0x100
#define FT_OVERFLOW	0x200
#define FT_TEMPLATE	"file\0dir\0sym\0sock\0bdev\0cdev\0fifo\0all\0dev\0"
#define FT_ALLMASK	(FT_FILE | FT_DIR | FT_SYM | FT_SOCK | FT_BDEV | FT_CDEV | FT_FIFO)
#define FT_DEV2		(FT_BDEV | FT_CDEV)

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

int	VFS_DIR_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VFS_DIR_COUNT(AGENT_REQUEST *request, AGENT_RESULT *result);

int	zbx_etypes_to_mask(char *etypes, AGENT_RESULT *result);

#endif /* ZABBIX_SYSINFO_COMMON_DIR_H */
