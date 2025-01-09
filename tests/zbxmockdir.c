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

/* make sure that __wrap_*() prototypes match unwrapped counterparts */

#define opendir	__wrap_opendir
#define readdir	__wrap_readdir
#include <dirent.h>
#undef opendir
#undef readdir

#include "zbxmocktest.h"
#include "zbxmockdata.h"

#include "zbxcommon.h"

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
