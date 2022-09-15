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

#ifndef ZABBIX_SYSINFO_H
#define ZABBIX_SYSINFO_H

#define ZBX_PROC_STAT_ALL	0
#define ZBX_PROC_STAT_RUN	1
#define ZBX_PROC_STAT_SLEEP	2
#define ZBX_PROC_STAT_ZOMB	3
#define ZBX_PROC_STAT_DISK	4
#define ZBX_PROC_STAT_TRACE	5

#define ZBX_PROC_MODE_PROCESS	0
#define ZBX_PROC_MODE_THREAD	1
#define ZBX_PROC_MODE_SUMMARY	2

#define ZBX_DO_SUM		0
#define ZBX_DO_MAX		1
#define ZBX_DO_MIN		2
#define ZBX_DO_AVG		3
#define ZBX_DO_ONE		4


#define ZBX_LLD_MACRO_FSNAME		"{#FSNAME}"
#define ZBX_LLD_MACRO_FSTYPE		"{#FSTYPE}"
#define ZBX_LLD_MACRO_FSLABEL		"{#FSLABEL}"
#define ZBX_LLD_MACRO_FSDRIVETYPE	"{#FSDRIVETYPE}"
#define ZBX_LLD_MACRO_FSOPTIONS		"{#FSOPTIONS}"

#define ZBX_SYSINFO_TAG_FSNAME			"fsname"
#define ZBX_SYSINFO_TAG_FSTYPE			"fstype"
#define ZBX_SYSINFO_TAG_FSLABEL			"fslabel"
#define ZBX_SYSINFO_TAG_FSDRIVETYPE		"fsdrivetype"
#define ZBX_SYSINFO_TAG_BYTES			"bytes"
#define ZBX_SYSINFO_TAG_INODES			"inodes"
#define ZBX_SYSINFO_TAG_TOTAL			"total"
#define ZBX_SYSINFO_TAG_FREE			"free"
#define ZBX_SYSINFO_TAG_USED			"used"
#define ZBX_SYSINFO_TAG_PFREE			"pfree"
#define ZBX_SYSINFO_TAG_PUSED			"pused"
#define ZBX_SYSINFO_TAG_FSOPTIONS		"options"

#define ZBX_SYSINFO_FILE_TAG_TYPE		"type"
#define ZBX_SYSINFO_FILE_TAG_BASENAME		"basename"
#define ZBX_SYSINFO_FILE_TAG_PATHNAME		"pathname"
#define ZBX_SYSINFO_FILE_TAG_DIRNAME		"dirname"
#define ZBX_SYSINFO_FILE_TAG_USER		"user"
#define ZBX_SYSINFO_FILE_TAG_GROUP		"group"
#define ZBX_SYSINFO_FILE_TAG_PERMISSIONS	"permissions"
#define ZBX_SYSINFO_FILE_TAG_SID		"SID"
#define ZBX_SYSINFO_FILE_TAG_UID		"uid"
#define ZBX_SYSINFO_FILE_TAG_GID		"gid"
#define ZBX_SYSINFO_FILE_TAG_SIZE		"size"
#define ZBX_SYSINFO_FILE_TAG_TIME		"time"
#define ZBX_SYSINFO_FILE_TAG_TIMESTAMP		"timestamp"
#define ZBX_SYSINFO_FILE_TAG_TIME_ACCESS	"access"
#define ZBX_SYSINFO_FILE_TAG_TIME_MODIFY	"modify"
#define ZBX_SYSINFO_FILE_TAG_TIME_CHANGE	"change"

#endif /* ZABBIX_SYSINFO_H */
