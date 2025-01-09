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

#include "zbxexpression.h"
#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxmutexs.h"
#include "dbconfig.h"
#include "configcache.h"
#include "configcache_mock.h"

void	mock_config_free_hosts(void);

void	mock_config_load_hosts(const char *path)
{
	zbx_mock_handle_t	hhosts, handle;
	zbx_mock_error_t	err;
	zbx_mock_config_t	*mock_config = get_mock_config();

	zbx_vector_ptr_create(&(mock_config->hosts));

	hhosts = zbx_mock_get_parameter_handle(path);
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hhosts, &handle))))
	{
		const char	*name;
		zbx_uint64_t	hostid;
		ZBX_DC_HOST	*host;
		int		i;

		if (ZBX_MOCK_SUCCESS != err)
		{
			fail_msg("Cannot read 'hosts' element #%d: %s", mock_config->hosts.values_num,
					zbx_mock_error_string(err));
		}

		hostid = zbx_mock_get_object_member_uint64(handle, "hostid");
		name =  zbx_mock_get_object_member_string(handle, "host");

		for (i = 0; i < mock_config->hosts.values_num; i++)
		{
			host = (ZBX_DC_HOST *)(mock_config->hosts.values[i]);

			if (host->hostid == hostid)
				fail_msg("Duplicate hostid " ZBX_FS_UI64 " detected", hostid);

			if (0 == strcmp(host->host, name))
				fail_msg("Duplicate host name " ZBX_FS_UI64 " detected", hostid);
		}

		host = (ZBX_DC_HOST *)zbx_malloc(NULL, sizeof(ZBX_DC_HOST));
		memset(host, 0, sizeof(ZBX_DC_HOST));
		host->hostid = hostid;
		host->host = name;
		zbx_vector_ptr_append(&(mock_config->hosts), host);
	}

	mock_config->initialized |= ZBX_MOCK_CONFIG_HOSTS;
}

void	mock_config_free_hosts(void)
{
	int	i;

	for (i = 0; i < get_mock_config()->hosts.values_num; i++)
	{
		ZBX_DC_HOST	*host = (ZBX_DC_HOST *)(get_mock_config()->hosts.values[i]);
		zbx_free(host);
	}
	zbx_vector_ptr_destroy(&(get_mock_config()->hosts));
}
