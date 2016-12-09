/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#include "common.h"

/******************************************************************************
 *									    *
 * Function: get_cluster_size						 *
 *									    *
 * Purpose: gets file system cluster size (sector count * bytes per sector)   *
 *	  from specified path (for situations when file system is mounted   *
 *	  to empty NTFS directory)					  *
 *									    *
 * Parameters: path       - [IN] file system path			     *
 *									    *
 * Return value: On success, nonzero cluster size returned		    *
 *	       On error, 0 is returned.				     *
 *									    *
 ******************************************************************************/
zbx_uint64_t	get_cluster_size(const char *path)
{
	wchar_t *disk = NULL, *wpath;
	unsigned long sectors_per_cluster, bytes_per_sector, path_length;

	wpath = zbx_utf8_to_unicode(path);

	path_length = GetFullPathName(wpath, 0, NULL, NULL) + 1;
	disk = (wchar_t *)zbx_malloc(NULL, path_length * sizeof(wchar_t));

	if (0 == GetVolumePathName(wpath, disk, path_length) ||
		0 == GetDiskFreeSpace(disk, &sectors_per_cluster, &bytes_per_sector, NULL, NULL))
	{
		sectors_per_cluster = 0;
	}

	zbx_free(wpath);
	zbx_free(disk);
	return (zbx_uint64_t)sectors_per_cluster * bytes_per_sector;
}
