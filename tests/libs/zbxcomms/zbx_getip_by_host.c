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

static int get_ip_from_etc_hosts(const char *hostname, char *ip, size_t iplen)
{
	int fd = openat(AT_FDCWD, "/etc/hosts", O_RDONLY);

	if (fd == -1)
		return FAIL;

	struct stat st;

	if (fstat(fd, &st) == -1)
	{
		close(fd);
		return FAIL;
	}

	char *data = mmap(NULL, st.st_size, PROT_READ, MAP_PRIVATE, fd, 0);

	if (data == MAP_FAILED)
	{
		close(fd);
		return FAIL;
	}

	char line[ZBX_MAX_HOSTNAME_LEN];
	size_t i = 0;

	for (off_t j = 0; j < st.st_size; j++)
	{
		if (data[j] == '\n' || i >= sizeof(line) - 1)
		{
			line[i] = '\0';
			i = 0;

			if (line[0] == '#' || line[0] == '\0')
				continue;

			char file_ip[ZBX_MAX_HOSTNAME_LEN], name[ZBX_MAX_HOSTNAME_LEN];

			if (sscanf(line, "%s %s", file_ip, name) == 2)
			{
				if (strcmp(hostname, name) == 0)
				{
					zbx_strlcpy(ip, file_ip, iplen);
					munmap(data, st.st_size);
					close(fd);
					return SUCCEED;
				}
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

void	zbx_mock_test_entry(void **state)
{
	const char	*host = zbx_mock_get_parameter_string("in.host");
	char		*ip = zbx_malloc(NULL, ZBX_MAX_HOSTNAME_LEN),
			*exp_ip = zbx_malloc(NULL, ZBX_MAX_HOSTNAME_LEN);

	ZBX_UNUSED(state);

	zbx_getip_by_host(host, ip, ZBX_MAX_HOSTNAME_LEN);

	if (SUCCEED == get_ip_from_etc_hosts(host, exp_ip, ZBX_MAX_HOSTNAME_LEN))
		zbx_mock_assert_str_eq("return value", exp_ip, ip);
	else
		zbx_mock_assert_str_eq("host not found, expect empty string", "", ip);

	zbx_free(ip);
	zbx_free(exp_ip);
}
