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

#include "configcache.h"
#include "configcache_mock.h"

static zbx_mock_config_t	mock_config;

zbx_mock_config_t	*get_mock_config(void)
{
	return &mock_config;
}

void	*__wrap_zbx_hashset_search(zbx_hashset_t *hs, const void *data);
void	*__real_zbx_hashset_search(zbx_hashset_t *hs, const void *data);

void	mock_config_free_user_macros(void);
void	mock_config_free_hosts(void);

void	*__wrap_zbx_hashset_search(zbx_hashset_t *hs, const void *data)
{
	int	i;

	if (&mock_config.dc.items == hs)
	{
		static ZBX_DC_ITEM	item = {.hostid = 1};

		return &item;
	}

	if (0 != (mock_config.initialized & ZBX_MOCK_CONFIG_USERMACROS))
	{
		if (hs == &mock_config.dc.um_cache->hosts)
		{
			zbx_uint64_t	hostid = **(zbx_uint64_t **)data;

			for (i = 0; i < mock_config.um_hosts.values_num; i++)
			{
				if (mock_config.um_hosts.values[i]->hostid == hostid)
					return &mock_config.um_hosts.values[i];
			}

			return NULL;
		}
	}

	if (0 != (mock_config.initialized & ZBX_MOCK_CONFIG_HOSTS))
	{
		if (hs == &mock_config.dc.hosts)
		{
			zbx_uint64_t	hostid = *(zbx_uint64_t *)data;

			for (i = 0; i < mock_config.hosts.values_num; i++)
			{
				ZBX_DC_HOST	*host = (ZBX_DC_HOST *)mock_config.hosts.values[i];
				if (host->hostid == hostid)
					return host;
			}
			return NULL;
		}
		if (hs == &mock_config.dc.hosts_h)
		{
			ZBX_DC_HOST_H	*hh = (ZBX_DC_HOST_H *)data;

			for (i = 0; i < mock_config.hosts.values_num; i++)
			{
				ZBX_DC_HOST	*host = (ZBX_DC_HOST *)mock_config.hosts.values[i];
				if (0 == strcmp(hh->host, host->host))
				{
					static ZBX_DC_HOST_H host_index;

					host_index.host = host->host;
					host_index.host_ptr = host;
					return &host_index;
				}
			}
			return NULL;
		}
	}

	/* return NULL from searches in non mocked configuration cache hashsets */
	if ((char *)hs >= (char *)&mock_config.dc && (char *)hs < (char *)(&mock_config.dc + 1))
		return NULL;

	/* perform normal hashset lookup for non configuration cache hashsets */
	return __real_zbx_hashset_search(hs, data);
}

void	free_string(const char *str)
{
	char	*ptr = (char *)str;
	zbx_free(ptr);
}

void	mock_config_init(void)
{
	memset(&mock_config, 0, sizeof(mock_config));
	set_dc_config(&mock_config.dc);
}

void	mock_config_free(void)
{
	if (0 != (mock_config.initialized & ZBX_MOCK_CONFIG_USERMACROS))
		mock_config_free_user_macros();

	if (0 != (mock_config.initialized & ZBX_MOCK_CONFIG_HOSTS))
		mock_config_free_hosts();

	zbx_free(mock_config.dc.um_cache);
}


