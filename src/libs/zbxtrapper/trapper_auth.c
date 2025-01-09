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

#include "zbxtrapper.h"

#include "zbxjson.h"
#include "zbxdbhigh.h"
#include "zbxhash.h"

#define	ZBX_SID_AUTH_TOKEN_LENGTH	64

/******************************************************************************
 *                                                                            *
 * Purpose: Takes a string token, hashes it with sha-512 and then formats the *
 *          resulting binary into the printable hex string.                   *
 *                                                                            *
 * Parameters: auth_token           - [IN] string auth token                  *
 *             hash_res_stringhexes - [OUT] hashed and formatted auth token   *
 *                                                                            *
 ******************************************************************************/
static void	format_auth_token_hash(const char *auth_token, char *hash_res_stringhexes)
{
	char	hash_res[ZBX_SID_AUTH_TOKEN_LENGTH];

	zbx_sha512_hash(auth_token, hash_res);

	for (int i = 0 ; i < ZBX_SID_AUTH_TOKEN_LENGTH; i++)
	{
		char z[3];

		zbx_snprintf(z, 3, "%02x", (unsigned char)hash_res[i]);
		hash_res_stringhexes[i * 2] = z[0];
		hash_res_stringhexes[i * 2 + 1] = z[1];
	}

	hash_res_stringhexes[ZBX_SID_AUTH_TOKEN_LENGTH * 2] = '\0';
}

/******************************************************************************
 *                                                                            *
 * Purpose: authenticates and initializes user data from supplied json        *
 *                                                                            *
 * Parameters: jp     - [IN] request                                          *
 *             user   - [OUT] user data                                       *
 *             result - [OUT] error logging                                   *
 *                                                                            *
 * Return value: SUCCEED - managed to find and authenticate user              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_user_from_json(const struct zbx_json_parse *jp, zbx_user_t *user, char **result)
{
	char	buffer[MAX_STRING_LEN];
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SID, buffer, sizeof(buffer), NULL))
	{
		size_t	buf_len = strlen(buffer);
#define	SID_SESSION_LENGTH	32
		if (SID_SESSION_LENGTH == buf_len)
		{
			ret = zbx_db_get_user_by_active_session(buffer, user);
		}
		else if (ZBX_SID_AUTH_TOKEN_LENGTH == buf_len)
		{
			char	hash_res_stringhexes[ZBX_SID_AUTH_TOKEN_LENGTH * 2 + 1];

			format_auth_token_hash(buffer, hash_res_stringhexes);
			ret = zbx_db_get_user_by_auth_token(hash_res_stringhexes, user);
		}
		else
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Failed to parse %s token, invalid length: %lu",
					ZBX_PROTO_TAG_SID, (unsigned long) buf_len);
			ret = FAIL;
		}
#undef SID_SESSION_LENGTH
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Failed to parse %s tag", ZBX_PROTO_TAG_SID);

		if (NULL != result)
			*result = zbx_dsprintf(*result, "Failed to parse %s tag", ZBX_PROTO_TAG_SID);

		ret = FAIL;
		goto out;
	}

	if (FAIL == ret && NULL != result)
		*result = zbx_dsprintf(*result, "Permission denied.");
out:
	if (FAIL == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "Permission denied");
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Permission granted");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
#undef	ZBX_SID_AUTH_TOKEN_LENGTH
