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

#include "configcache.h"
#include "configcache_mock.h"

zbx_mock_config_t	mock_config;

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
		if (hs == &mock_config.dc.hmacros_hm)
		{
			const ZBX_DC_HMACRO_HM	*query = (const ZBX_DC_HMACRO_HM *)data;

			for (i = 0; i < mock_config.host_macros.values_num; i++)
			{
				ZBX_DC_HMACRO_HM	*hm = (ZBX_DC_HMACRO_HM *)mock_config.host_macros.values[i];

				if (query->hostid == hm->hostid && 0 == strcmp(hm->macro, query->macro))
					return hm;
			}
			return NULL;
		}
		else if (hs == &mock_config.dc.gmacros_m)
		{
			const ZBX_DC_GMACRO_M	*query = (const ZBX_DC_GMACRO_M *)data;

			for (i = 0; i < mock_config.global_macros.values_num; i++)
			{
				ZBX_DC_GMACRO_M	*gm = (ZBX_DC_GMACRO_M *)mock_config.global_macros.values[i];
				if (0 == strcmp(gm->macro, query->macro))
					return gm;
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
	config = &mock_config.dc;
}

void	mock_config_free(void)
{
	if (0 != (mock_config.initialized & ZBX_MOCK_CONFIG_USERMACROS))
		mock_config_free_user_macros();

	if (0 != (mock_config.initialized & ZBX_MOCK_CONFIG_HOSTS))
		mock_config_free_hosts();

}


