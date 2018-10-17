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

#include "zbxmocktest.h"
#include "zbxmockdata.h"

#include "common.h"

int	__wrap_stat(const char *pathname, struct stat *buf)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	handle;

	if (ZBX_MOCK_SUCCESS == (error = zbx_mock_file(pathname, &handle)))
	{
		buf->st_mode = S_IFMT & S_IFREG;
		return 0;
	}

	if (ZBX_MOCK_NO_PARAMETER != error)
	{
		fail_msg("Error during path \"%s\" lookup among files: %s", pathname,
				zbx_mock_error_string(error));
	}

	if (0)	/* directory lookup is not implemented */
	{
		buf->st_mode = S_IFMT & S_IFDIR;
		return 0;
	}

	errno = ENOENT;	/* No such file or directory */
	return -1;
}

int	__wrap___xstat(int ver, const char *pathname, struct stat *buf)
{
	ZBX_UNUSED(ver);

	return __wrap_stat(pathname, buf);
}
