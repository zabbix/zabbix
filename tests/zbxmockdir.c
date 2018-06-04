/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

/* make sure that __wrap_*() prototypes match unwrapped counterparts */

#define opendir	__wrap_opendir
#define readdir	__wrap_readdir
#include <dirent.h>
#undef opendir
#undef readdir

#include "zbxmocktest.h"
#include "zbxmockdata.h"

#include "common.h"

DIR	*__wrap_opendir(const char *name)
{
	ZBX_UNUSED(name);

	errno = ENOENT;
	return NULL;
}

struct dirent	*__wrap_readdir(DIR *dirp)
{
	ZBX_UNUSED(dirp);

	errno = EBADF;
	return NULL;
}
