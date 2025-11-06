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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxcommon.h"
#include "zbxcomms.h"

#include <sys/mman.h>
#include <sys/stat.h>

#ifndef _WINDOWS
static int get_host_from_etc_hosts(const char *ip, char *hostname, size_t hostnamelen)
{
	int	fd = openat(AT_FDCWD, "/etc/hosts", O_RDONLY);

	if (-1 == fd)
		return FAIL;

	struct	stat st;

	if (-1 == fstat(fd, &st))
	{
		close(fd);

		return FAIL;
	}

	char *data = mmap(NULL, st.st_size, PROT_READ, MAP_PRIVATE, fd, 0);

	if (MAP_FAILED == data)
	{
		close(fd);

		return FAIL;
	}

	char	line[ZBX_MAX_HOSTNAME_LEN];
	size_t	i = 0;

	for (off_t j = 0; j < st.st_size; j++)
	{
		if (data[j] == '\n' || i >= sizeof(line) - 1)
		{
			line[i] = '\0';
			i = 0;

			if (line[0] == '#' || line[0] == '\0')
				continue;

			char	file_ip[ZBX_MAX_HOSTNAME_LEN], name[ZBX_MAX_HOSTNAME_LEN];

			if (2 != sscanf(line, "%s %s", file_ip, name))
				continue;

			if (0 == strcmp(ip, file_ip))
			{
				zbx_strlcpy(hostname, name, hostnamelen);
				munmap(data, st.st_size);
				close(fd);

				return SUCCEED;
			}
		}
		else
		{
			line[i++] = data[j];
		}
	}

	munmap(data, st.st_size);
	close(fd);

	return FAIL;
}
#endif

void	zbx_mock_test_entry(void **state)
{
	const char	*ip = zbx_mock_get_parameter_string("in.ip");
	char		host[ZBX_MAX_HOSTNAME_LEN],
			exp_host[ZBX_MAX_HOSTNAME_LEN];

	ZBX_UNUSED(state);

	zbx_gethost_by_ip(ip, host, ZBX_MAX_HOSTNAME_LEN);

	if (SUCCEED == get_host_from_etc_hosts(ip, exp_host, ZBX_MAX_HOSTNAME_LEN))
		zbx_mock_assert_str_eq("return value", exp_host, host);
	else
		zbx_mock_assert_str_eq("ip not found, expect empty string", "", host);
}
