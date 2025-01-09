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

#include "dbhigh_test.h"

#include "zbxmocktest.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

#include "zbxcommon.h"
#include "zbxdbhigh.h"

static zbx_uint64_t	flags_get(zbx_mock_handle_t htag)
{
	zbx_mock_handle_t	hflags, hflag;
	int			flags_num = 1;
	zbx_uint64_t		flags = ZBX_FLAG_DB_TAG_UNSET;
	zbx_mock_error_t	err;

	err = zbx_mock_object_member(htag, "flags", &hflags);

	if (ZBX_MOCK_NO_SUCH_MEMBER == err)
		goto out;

	if (ZBX_MOCK_SUCCESS != err)
		fail_msg("Cannot read flags '%s'", zbx_mock_error_string(err));

	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hflags, &hflag))))
	{
		const char	*flag;

		if (ZBX_MOCK_SUCCESS != err || ZBX_MOCK_SUCCESS != (err = zbx_mock_string(hflag, &flag)))
			fail_msg("Cannot read flag #%d: %s", flags_num, zbx_mock_error_string(err));
		else if (0 == strcmp(flag, "ZBX_FLAG_DB_TAG_UNSET"))
			flags |= ZBX_FLAG_DB_TAG_UNSET;
		else if (0 == strcmp(flag, "ZBX_FLAG_DB_TAG_UPDATE_AUTOMATIC"))
			flags |= ZBX_FLAG_DB_TAG_UPDATE_AUTOMATIC;
		else if (0 == strcmp(flag, "ZBX_FLAG_DB_TAG_UPDATE_VALUE"))
			flags |= ZBX_FLAG_DB_TAG_UPDATE_VALUE;
		else if (0 == strcmp(flag, "ZBX_FLAG_DB_TAG_UPDATE_TAG"))
			flags |= ZBX_FLAG_DB_TAG_UPDATE_TAG;
		else if (0 == strcmp(flag, "ZBX_FLAG_DB_TAG_REMOVE"))
			flags |= ZBX_FLAG_DB_TAG_REMOVE;
		else if (0 == strcmp(flag, "ZBX_FLAG_DB_TAG_UPDATE"))
			flags |= ZBX_FLAG_DB_TAG_UPDATE;
		else
			fail_msg("Unknown flag #%d: %s", flags_num, zbx_mock_error_string(err));

		flags_num++;
	}
out:
	return flags;
}

static int	automatic_str_to_int(const char *str)
{
	int	out = -1;

	if (0 == strcmp(str, "ZBX_DB_TAG_NORMAL"))
		out = ZBX_DB_TAG_NORMAL;
	else if (0 == strcmp(str, "ZBX_DB_TAG_AUTOMATIC"))
		out = ZBX_DB_TAG_AUTOMATIC;
	else
		fail_msg("Unknown value of 'automatic' \"%s\"", str);

	return out;
}

int	db_tags_and_values_compare(const void *d1, const void *d2)
{
	int ret;

	const zbx_db_tag_t *tag1 = *(const zbx_db_tag_t * const *)d1;
	const zbx_db_tag_t *tag2 = *(const zbx_db_tag_t * const *)d2;

	if (0 == (ret = strcmp(tag1->tag, tag2->tag)))
		ret = strcmp(tag1->value, tag2->value);

	return ret;
}

void	tags_read(const char *path, zbx_vector_db_tag_ptr_t *tags)
{
	int			i = 0;
	zbx_mock_handle_t	htag_vector, htag;
	zbx_mock_error_t	mock_err;

	htag_vector = zbx_mock_get_parameter_handle(path);

	while (ZBX_MOCK_END_OF_VECTOR != (mock_err = (zbx_mock_vector_element(htag_vector, &htag))))
	{
		zbx_mock_handle_t	hid, hauto;
		zbx_db_tag_t		*db_tag;
		const char		*tag_str, *value_str, *auto_str;

		if (ZBX_MOCK_SUCCESS != mock_err)
			fail_msg("Cannot read '%s' %s", path, zbx_mock_error_string(mock_err));

		/* tag */
		tag_str = zbx_mock_get_object_member_string(htag, "tag");

		/* value */
		value_str = zbx_mock_get_object_member_string(htag, "value");

		db_tag = zbx_db_tag_create(tag_str, value_str);

		/* tagid */
		mock_err = zbx_mock_object_member(htag, "tagid", &hid);

		if (ZBX_MOCK_SUCCESS == mock_err)
		{
			if (ZBX_MOCK_SUCCESS != (mock_err = zbx_mock_uint64(hid, &db_tag->tagid)))
				fail_msg("Cannot read '%s[%d].tagid': %s", path, i, zbx_mock_error_string(mock_err));
		}
		else if (ZBX_MOCK_NO_SUCH_MEMBER != mock_err)
		{
			fail_msg("Cannot read '%s[%d].tagid': %s", path, i, zbx_mock_error_string(mock_err));
		}

		/* automatic */
		mock_err = zbx_mock_object_member(htag, "automatic", &hauto);

		if (ZBX_MOCK_SUCCESS == mock_err)
		{
			if (ZBX_MOCK_SUCCESS != (mock_err = zbx_mock_string(hauto, &auto_str)))
			{
				fail_msg("Cannot read '%s[%d].automatic': %s", path, i,
						zbx_mock_error_string(mock_err));
			}
			else
				db_tag->automatic = automatic_str_to_int(auto_str);
		}
		else if (ZBX_MOCK_NO_SUCH_MEMBER != mock_err)
			fail_msg("Cannot read '%s[%d].automatic': %s", path, i, zbx_mock_error_string(mock_err));

		/* flags */
		db_tag->flags = flags_get(htag);

		zbx_vector_db_tag_ptr_append(tags, db_tag);
		i++;
	}
}
