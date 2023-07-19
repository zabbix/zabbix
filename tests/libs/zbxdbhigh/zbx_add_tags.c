/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
#include "zbxmockutil.h"
#include "zbxmockassert.h"

#include "zbxcommon.h"
#include "zbxdbhigh.h"

static zbx_uint64_t	get_flags(zbx_mock_handle_t	htag)
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

static void	read_tags(const char *path, zbx_vector_db_tag_ptr_t *tags)
{
	int			i = 0;
	zbx_mock_handle_t	htag_vector, htag;
	zbx_mock_error_t	mock_err;

	htag_vector = zbx_mock_get_parameter_handle(path);

	while (ZBX_MOCK_END_OF_VECTOR != (mock_err = (zbx_mock_vector_element(htag_vector, &htag))))
	{
		zbx_mock_handle_t	hid, hname, hvalue, hauto;
		zbx_db_tag_t		*db_tag;
		const char		*tag_str, *value_str, *auto_str;

		if (ZBX_MOCK_SUCCESS != mock_err)
			fail_msg("Cannot read '%s' %s", path, zbx_mock_error_string(mock_err));

		/* tag */
		if (ZBX_MOCK_SUCCESS != (mock_err = zbx_mock_object_member(htag, "tag", &hname)))
			fail_msg("Cannot read '%s[%d].tag': %s", path, i, zbx_mock_error_string(mock_err));
		if (ZBX_MOCK_SUCCESS != (mock_err = zbx_mock_string(hname, &tag_str)))
			fail_msg("Cannot read '%s[%d].tag': %s", path, i, zbx_mock_error_string(mock_err));

		/* value */
		if (ZBX_MOCK_SUCCESS != (mock_err = zbx_mock_object_member(htag, "value", &hvalue)))
			fail_msg("Cannot read '%s[%d].value': %s", path, i, zbx_mock_error_string(mock_err));
		if (ZBX_MOCK_SUCCESS != (mock_err = zbx_mock_string(hvalue, &value_str)))
			fail_msg("Cannot read '%s[%d].value': %s", path, i, zbx_mock_error_string(mock_err));

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
		db_tag->flags = get_flags(htag);

		zbx_vector_db_tag_ptr_append(tags, db_tag);
		i++;
	}
}

void	zbx_mock_test_entry(void **state)
{
	int			i;
	zbx_vector_db_tag_ptr_t	host_tags, add_tags, out_host_tags;

	ZBX_UNUSED(state);

	zbx_vector_db_tag_ptr_create(&host_tags);
	zbx_vector_db_tag_ptr_create(&add_tags);
	zbx_vector_db_tag_ptr_create(&out_host_tags);

	read_tags("in.host_tags", &host_tags);
	read_tags("in.add_tags", &add_tags);
	read_tags("out.host_tags", &out_host_tags);

	zbx_add_tags(&host_tags, &add_tags);

	zbx_mock_assert_int_eq("Unexpected host tag count", out_host_tags.values_num, host_tags.values_num);

	for (i = 0; i < host_tags.values_num; i++)
	{
		zbx_mock_assert_str_eq("Unexpected tag name", out_host_tags.values[i]->tag,
				host_tags.values[i]->tag);
		zbx_mock_assert_str_eq("Unexpected tag value", out_host_tags.values[i]->value,
				host_tags.values[i]->value);
		zbx_mock_assert_int_eq("Unexpected automatic", out_host_tags.values[i]->automatic,
				host_tags.values[i]->automatic);
		zbx_mock_assert_uint64_eq("Unexpected flags", out_host_tags.values[i]->flags,
				host_tags.values[i]->flags);
	}

	zbx_vector_db_tag_ptr_clear_ext(&host_tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&host_tags);

	zbx_vector_db_tag_ptr_clear_ext(&add_tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&add_tags);

	zbx_vector_db_tag_ptr_clear_ext(&out_host_tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&out_host_tags);
}
