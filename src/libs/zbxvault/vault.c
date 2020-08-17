/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "zbxjson.h"
#include "zbxvault.h"
#include "zbxhttp.h"

extern char	*CONFIG_VAULTTOKEN;
extern char	*CONFIG_VAULTURL;
extern char	*CONFIG_VAULTDBPATH;

extern char	*CONFIG_DBUSER;
extern char	*CONFIG_DBPASSWORD;

int	init_database_credentials_from_vault(char **error)
{
	char			*out = NULL, tmp[MAX_STRING_LEN], tmp1[MAX_STRING_LEN];
	struct zbx_json_parse	jp, jp_data;
	int			ret = FAIL;

	if (NULL == CONFIG_VAULTDBPATH)
		return SUCCEED;

	if (NULL == CONFIG_VAULTTOKEN)
	{
		*error = zbx_dsprintf(*error, "cannot retrieve database credentials from vault,"
				" VaultToken must be defined");
		return FAIL;
	}

	zbx_snprintf(tmp, sizeof(tmp), "%s%s", CONFIG_VAULTURL, CONFIG_VAULTDBPATH);
	zbx_snprintf(tmp1, sizeof(tmp1), "X-Vault-Token: %s", CONFIG_VAULTTOKEN);

	ret = zbx_http_get(tmp, tmp1, &out, error);

	zbx_guaranteed_memset(tmp, 0, strlen(tmp));
	zbx_guaranteed_memset(tmp1, 0, strlen(tmp1));

	if (SUCCEED != ret)
		goto fail;

	if (SUCCEED != zbx_json_open(out, &jp))
	{
		*error = zbx_dsprintf(*error, "cannot parse credentials from vault: %s", zbx_json_strerror());
		goto fail;
	}

	if (SUCCEED != zbx_json_brackets_by_name(&jp, "data", &jp_data))
	{
		*error = zbx_dsprintf(*error, "cannot find the \"%s\" array in the received JSON object.",
				ZBX_PROTO_TAG_DATA);
		goto fail;
	}

	if (SUCCEED != zbx_json_value_by_name(&jp_data, ZBX_PROTO_TAG_USERNAME, tmp, sizeof(tmp), NULL))
	{
		*error = zbx_dsprintf(*error, "cannot retrieve value of tag \"%s\"", ZBX_PROTO_TAG_USERNAME);
		goto fail;
	}

	if (SUCCEED != zbx_json_value_by_name(&jp_data, ZBX_PROTO_TAG_PASSWORD, tmp1, sizeof(tmp1), NULL))
	{
		*error = zbx_dsprintf(*error, "cannot retrieve value of tag \"%s\"", ZBX_PROTO_TAG_PASSWORD);
		goto fail;
	}

	if (NULL != CONFIG_DBUSER)
	{
		*error = zbx_dsprintf(*error,
				"cannot retrieve database user name, both DBName and VaultDBPath are defined");
		goto fail;
	}

	if (NULL != CONFIG_DBPASSWORD)
	{
		*error = zbx_dsprintf(*error,
				"cannot retrieve database password, both DBPassword and VaultDBPath are defined");
		goto fail;
	}

	CONFIG_DBUSER = zbx_strdup(NULL, tmp);		/* TODO encrypt */
	CONFIG_DBPASSWORD = zbx_strdup(NULL, tmp1);	/* TODO encrypt */

	ret = SUCCEED;
fail:
	zbx_guaranteed_memset(tmp, 0, strlen(tmp));
	zbx_guaranteed_memset(tmp1, 0, strlen(tmp1));

	if (NULL != out)
		zbx_guaranteed_memset(out, 0, strlen(out));
	zbx_free(out);

	return ret;
}
