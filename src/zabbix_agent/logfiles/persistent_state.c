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

#include "persistent_state.h"
#include "logfiles.h"

#include "zbxalgo.h"
#include "zbxhash.h"
#include "zbxnum.h"
#include "zbxjson.h"
#include "zbxcrypto.h"

/* tags for agent persistent storage files */
#define ZBX_PERSIST_TAG_FILENAME		"filename"
#define ZBX_PERSIST_TAG_MTIME			"mtime"
#define ZBX_PERSIST_TAG_SEQ			"seq"
#define ZBX_PERSIST_TAG_INCOMPLETE		"incomplete"
#define ZBX_PERSIST_TAG_COPY_OF			"copy_of"
#define ZBX_PERSIST_TAG_DEVICE			"dev"
#define ZBX_PERSIST_TAG_INODE_LO		"ino_lo"
#define ZBX_PERSIST_TAG_INODE_HI		"ino_hi"
#define ZBX_PERSIST_TAG_SIZE			"size"
#define ZBX_PERSIST_TAG_PROCESSED_SIZE		"processed_size"
#define ZBX_PERSIST_TAG_MD5_BLOCK_SIZE		"md5_block_size"
#define ZBX_PERSIST_TAG_FIRST_BLOCK_MD5		"first_block_md5"
#define ZBX_PERSIST_TAG_LAST_BLOCK_OFFSET	"last_block_offset"
#define ZBX_PERSIST_TAG_LAST_BLOCK_MD5		"last_block_md5"

ZBX_VECTOR_IMPL(pre_persistent, zbx_pre_persistent_t)
ZBX_VECTOR_IMPL(persistent_inactive, zbx_persistent_inactive_t)

#if !defined(_WINDOWS) && !defined(__MINGW32__)
static int	zbx_persistent_inactive_compare_func(const void *d1, const void *d2)
{
	const zbx_persistent_inactive_t	*p1 = (const zbx_persistent_inactive_t *)d1;
	const zbx_persistent_inactive_t	*p2 = (const zbx_persistent_inactive_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1->itemid, p2->itemid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets part of persistent storage path for specified string         *
 *                                                                            *
 * Parameters:                                                                *
 *          str - [IN] string (e.g. host, item key)                           *
 *                                                                            *
 * Return value: dynamically allocated string with part of path               *
 *               corresponding to input string                                *
 *                                                                            *
 * Comments: The part of persistent storage path is built of md5 sum of the   *
 *           input string with the length of input string appended.           *
 *                                                                            *
 * Example: for input string                                                  *
 *           "logrt[/home/zabbix/test.log,,,,,,,,/home/zabbix/agent_private]" *
 *          the function returns                                              *
 *           "147bd30c08995022fbe78de3c26c082962"                             *
 *                                            \/ - length of input string     *
 *            \------------------------------/   - md5 sum of input string    *
 *                                                                            *
 ******************************************************************************/
static char	*str2file_name_part(const char *str)
{
	md5_state_t	state;
	md5_byte_t	md5[ZBX_MD5_DIGEST_SIZE];
	char		size_buf[21];		/* 20 - max size of printed 'size_t' value, 1 - '\0' */
	char		*md5_text = NULL;
	size_t		str_sz, md5_text_sz, size_buf_len;

	str_sz = strlen(str);

	zbx_md5_init(&state);
	zbx_md5_append(&state, (const md5_byte_t *)str, (int)str_sz);
	zbx_md5_finish(&state, md5);

	size_buf_len = zbx_snprintf(size_buf, sizeof(size_buf), ZBX_FS_SIZE_T, (zbx_fs_size_t)str_sz);

	md5_text_sz = ZBX_MD5_DIGEST_SIZE * 2 + size_buf_len + 1;
	md5_text = (char *)zbx_malloc(NULL, md5_text_sz);

	zbx_md5buf2str(md5, md5_text);
	memcpy(md5_text + ZBX_MD5_DIGEST_SIZE * 2, size_buf, size_buf_len + 1);

	return md5_text;
}

/******************************************************************************
 *                                                                            *
 * Purpose: CalculateS the part of persistent storage path for the specified  *
 *          server/port pair where the agent is sending active check data.    *
 *                                                                            *
 * Parameters:                                                                *
 *          server - [IN] server string                                       *
 *          port   - [IN] port number                                         *
 *                                                                            *
 * Return value: dynamically allocated string with part of path               *
 *                                                                            *
 * Comments: The part of persistent storage path is built of md5 sum of the   *
 *           input string with the length of input string appended.           *
 *                                                                            *
 * Example: for server "127.0.0.1" and port 10091 function returns            *
 *         "8392b6ea687188e70feac24517d2f9d715"                               *
 *                                          \/ - length  of "127.0.0.1:10091" *
 *          \------------------------------/   - md5 sum of "127.0.0.1:10091" *
 *                                                                            *
 ******************************************************************************/
static char	*active_server2dir_name_part(const char *server, unsigned short port)
{
	char	*endpoint, *dir_name;

	endpoint = zbx_dsprintf(NULL, "%s:%hu", server, port);
	dir_name = str2file_name_part(endpoint);
	zbx_free(endpoint);

	return dir_name;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Makes the name of persistent storage directory for the specified  *
 *          server/proxy and port.                                            *
 *                                                                            *
 * Parameters:                                                                *
 *          base_path - [IN] initial part of pathname                         *
 *          server    - [IN] server string                                    *
 *          port      - [IN] port number                                      *
 *                                                                            *
 * Return value: dynamically allocated string with name of directory          *
 *                                                                            *
 * Example: for base_path "/var/tmp/", server "127.0.0.1", port 10091 the     *
 *          function returns "/var/tmp/8392b6ea687188e70feac24517d2f9d715"    *
 *                                                                            *
 ******************************************************************************/
static char	*make_persistent_server_directory_name(const char *base_path, const char *server, unsigned short port)
{
	char	*server_part, *file_name;

	server_part = active_server2dir_name_part(server, port);
	file_name = zbx_dsprintf(NULL, "%s/%s", base_path, server_part);
	zbx_free(server_part);

	return file_name;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the directory exists                                    *
 *                                                                            *
 * Parameters:                                                                *
 *          pathname  - [IN] full pathname of directory                       *
 *          error     - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED or FAIL (with dynamically allocated 'error')         *
 *                                                                            *
 ******************************************************************************/
static int	check_persistent_directory_exists(const char *pathname, char **error)
{
	zbx_stat_t	status;

	if (0 != zbx_stat(pathname, &status))
	{
		*error = zbx_dsprintf(*error, "cannot obtain persistent directory information: %s",
				zbx_strerror(errno));
		return FAIL;
	}

	if (0 == S_ISDIR(status.st_mode))
	{
		*error = zbx_dsprintf(*error, "cannot obtain persistent directory: file exists but is not a directory");
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates directory if it does not exist or checks access if it     *
 *          exists                                                            *
 *                                                                            *
 * Parameters:                                                                *
 *          pathname  - [IN] full pathname of directory                       *
 *          error     - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - directory created or already exists                *
 *               FAIL    - cannot create directory or it has insufficient     *
 *                         access rights (see dynamically allocated 'error')  *
 *                                                                            *
 ******************************************************************************/
static int	create_persistent_directory(const char *pathname, char **error)
{
	if (0 != mkdir(pathname, S_IRWXU))
	{
		if (EEXIST == errno)
			return check_persistent_directory_exists(pathname, error);

		*error = zbx_dsprintf(*error, "%s(): cannot create directory \"%s\": %s", __func__, pathname,
				zbx_strerror(errno));
		return FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): created directory:[%s]", __func__, pathname);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates all subdirectories in the pathname if they do not exist   *
 *                                                                            *
 * Parameters:                                                                *
 *          pathname - [IN] full pathname of directory, must consist of only  *
 *                          ASCII characters                                  *
 *          error    - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - directories created or already exist               *
 *               FAIL    - cannot create directory or it has insufficient     *
 *                         access rights (see dynamically allocated 'error')  *
 *                                                                            *
 ******************************************************************************/
static int	create_base_path_directories(const char *pathname, char **error)
{
	char	*path, *p;

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): pathname:[%s]", __func__, pathname);

	if ('/' != pathname[0])
	{
		*error = zbx_dsprintf(*error, "persistent directory name is not an absolute path,"
				" it does not start with '/'");
		return FAIL;
	}

	path = zbx_strdup(NULL, pathname);	/* mutable copy */

	for (p = path + 1; ; ++p)
	{
		if ('/' == *p)
		{
			*p = '\0';

			zabbix_log(LOG_LEVEL_DEBUG, "%s(): checking directory:[%s]", __func__, path);

			if (SUCCEED != create_persistent_directory(path, error))
			{
				zbx_free(path);
				return FAIL;
			}

			*p = '/';
		}
		else if ('\0' == *p)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): checking directory:[%s]", __func__, path);

			if (SUCCEED != create_persistent_directory(path, error))
			{
				zbx_free(path);
				return FAIL;
			}

			break;
		}
	}

	zbx_free(path);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Creates directory if it does not exist or check access if it      *
 *          exists. Directory name is derived from host and port.             *
 *                                                                            *
 * Parameters:                                                                *
 *          base_path - [IN] initial part of pathname, must consist of only   *
 *                           ASCII characters                                 *
 *          host      - [IN] host string                                      *
 *          port      - [IN] port number                                      *
 *          error     - [OUT] error message                                   *
 *                                                                            *
 * Return value: on success - dynamically allocated string with name of       *
 *                            directory,                                      *
 *               on error   - NULL (with dynamically allocated 'error')       *
 *                                                                            *
 ******************************************************************************/
char	*zbx_create_persistent_server_directory(const char *base_path, const char *host, unsigned short port,
		char **error)
{
	char	*server_dir_name, *err_msg = NULL;

	/* persistent storage top-level directory */
	if (SUCCEED != create_base_path_directories(base_path, error))
		return NULL;

	/* directory for server/proxy where the current active check process will be sending data */
	server_dir_name = make_persistent_server_directory_name(base_path, host, port);

	if (SUCCEED != create_persistent_directory(server_dir_name, &err_msg))
	{
		*error = zbx_dsprintf(*error, "cannot create directory \"%s\": %s", server_dir_name, err_msg);
		zbx_free(server_dir_name);
		zbx_free(err_msg);
		return NULL;
	}

	if (0 != access(server_dir_name, R_OK | W_OK | X_OK))
	{
		if (NULL != server_dir_name)	/* only to silence GCC warning "directive argument is null" */
		{
			*error = zbx_dsprintf(*error, "insufficient access rights to directory \"%s\"",
					server_dir_name);
		}
		else
			THIS_SHOULD_NEVER_HAPPEN;

		zbx_free(server_dir_name);
		return NULL;
	}

	return server_dir_name;
}

/******************************************************************************
 *                                                                            *
 * Purpose: makes name of persistent storage directory or file                *
 *                                                                            *
 * Parameters:                                                                *
 *          persistent_server_dir - [IN] initial part of pathname             *
 *          item_key              - [IN] item key (not NULL)                  *
 *                                                                            *
 * Return value: dynamically allocated string with name of file               *
 *                                                                            *
 ******************************************************************************/
char	*zbx_make_persistent_file_name(const char *persistent_server_dir, const char *item_key)
{
	char	*file_name, *item_part;

	item_part = str2file_name_part(item_key);
	file_name = zbx_dsprintf(NULL, "%s/%s", persistent_server_dir, item_part);
	zbx_free(item_part);

	return file_name;
}

/******************************************************************************
 *                                                                            *
 * Purpose: writes metric info into persistent file                           *
 *                                                                            *
 * Parameters:                                                                *
 *          filename  - [IN]                                                  *
 *          data      - [IN] file content                                     *
 *          error     - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - file was successfully written                      *
 *               FAIL    - cannot write file (see dynamically allocated       *
 *                         'error' message)                                   *
 *                                                                            *
 ******************************************************************************/
static int	zbx_write_persistent_file(const char *filename, const char *data, char **error)
{
	FILE	*fp;
	size_t	sz, alloc_bytes = 0, offset = 0;
	int	ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): filename:[%s] data:[%s]", __func__, filename, data);

	if (NULL == (fp = fopen(filename, "w")))
	{
		zbx_snprintf_alloc(error, &alloc_bytes, &offset, "cannot open file: %s", zbx_strerror(errno));
		return FAIL;
	}

	sz = strlen(data);

	if (sz != fwrite(data, 1, sz, fp))
	{
		zbx_snprintf_alloc(error, &alloc_bytes, &offset, "cannot write to file: %s", zbx_strerror(errno));
		ret = FAIL;
	}

	if (0 != fclose(fp))
	{
		zbx_snprintf_alloc(error, &alloc_bytes, &offset, "%scannot close file: %s",
				(FAIL == ret) ? "; ": "" , zbx_strerror(errno));
		ret = FAIL;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Reads metric info from persistent file. One line is read.         *
 *                                                                            *
 * Parameters:                                                                *
 *          filename  - [IN]                                                  *
 *          buf       - [OUT] buffer to read file into                        *
 *          buf_size  - [IN]                                                  *
 *          err_msg   - [OUT]                                                 *
 *                                                                            *
 * Return value: SUCCEED - file was successfully read                         *
 *               FAIL    - cannot read file (see dynamically allocated        *
 *                         'err_msg' message)                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_read_persistent_file(const char *filename, char *buf, size_t buf_size, char **err_msg)
{
	int	ret = FAIL;
	FILE	*f;

	if (NULL == (f = fopen(filename, "r")))
	{
		*err_msg = zbx_dsprintf(*err_msg, "cannot open file \"%s\": %s", filename, zbx_strerror(errno));
		return FAIL;
	}

	if (NULL == fgets(buf, (int)buf_size, f))
	{
		*err_msg = zbx_dsprintf(*err_msg, "cannot read from file \"%s\" or file empty", filename);
		goto out;
	}

	buf[strcspn(buf, "\r\n")] = '\0';	/* discard newline at the end of string */

	ret = SUCCEED;
out:
	if (0 != fclose(f))
	{	/* only log, cannot do anything in case of error */
		zabbix_log(LOG_LEVEL_CRIT, "cannot close file \"%s\": %s", filename, zbx_strerror(errno));
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: removes specified file                                            *
 *                                                                            *
 * Parameters:                                                                *
 *          pathname  - [IN] file name                                        *
 *          error     - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - file was removed or does not exist                 *
 *               FAIL    - cannot remove specified file (see dynamically      *
 *                         allocated 'error' message)                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_remove_persistent_file(const char *pathname, char **error)
{
	zabbix_log(LOG_LEVEL_DEBUG, "%s(): removing persistent file '%s'", __func__, pathname);

	if (0 == unlink(pathname) || ENOENT == errno)
		return SUCCEED;

	*error = zbx_strdup(*error, zbx_strerror(errno));

	return FAIL;
}

void	zbx_write_persistent_files(zbx_vector_pre_persistent_t *prep_vec)
{
	int	i;

	for (i = 0; i < prep_vec->values_num; i++)
	{
		char		*error = NULL;
		struct zbx_json	json;

		/* prepare JSON */
		zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

		if (NULL != prep_vec->values[i].filename)
		{
			zbx_json_addstring(&json, ZBX_PERSIST_TAG_FILENAME, prep_vec->values[i].filename,
					ZBX_JSON_TYPE_STRING);
		}

		zbx_json_adduint64(&json, ZBX_PERSIST_TAG_MTIME, (zbx_uint64_t)prep_vec->values[i].mtime);

		zbx_json_adduint64(&json, ZBX_PERSIST_TAG_PROCESSED_SIZE, prep_vec->values[i].processed_size);

		if (NULL != prep_vec->values[i].filename)
		{
			char	buf[ZBX_MD5_PRINT_BUF_LEN];

			zbx_json_adduint64(&json, ZBX_PERSIST_TAG_SEQ, (zbx_uint64_t)prep_vec->values[i].seq);
			zbx_json_adduint64(&json, ZBX_PERSIST_TAG_INCOMPLETE,
					(zbx_uint64_t)prep_vec->values[i].incomplete);

			zbx_json_addint64(&json, ZBX_PERSIST_TAG_COPY_OF, prep_vec->values[i].copy_of);
			zbx_json_adduint64(&json, ZBX_PERSIST_TAG_DEVICE, prep_vec->values[i].dev);
			zbx_json_adduint64(&json, ZBX_PERSIST_TAG_INODE_LO, prep_vec->values[i].ino_lo);
			zbx_json_adduint64(&json, ZBX_PERSIST_TAG_INODE_HI, prep_vec->values[i].ino_hi);
			zbx_json_adduint64(&json, ZBX_PERSIST_TAG_SIZE, prep_vec->values[i].size);

			zbx_json_adduint64(&json, ZBX_PERSIST_TAG_MD5_BLOCK_SIZE,
					(zbx_uint64_t)prep_vec->values[i].md5_block_size);

			zbx_md5buf2str(prep_vec->values[i].first_block_md5, buf);
			zbx_json_addstring(&json, ZBX_PERSIST_TAG_FIRST_BLOCK_MD5, buf, ZBX_JSON_TYPE_STRING);

			zbx_json_adduint64(&json, ZBX_PERSIST_TAG_LAST_BLOCK_OFFSET,
					prep_vec->values[i].last_block_offset);

			zbx_md5buf2str(prep_vec->values[i].last_block_md5, buf);
			zbx_json_addstring(&json, ZBX_PERSIST_TAG_LAST_BLOCK_MD5, buf, ZBX_JSON_TYPE_STRING);
		}

		zbx_json_close(&json);

		if (SUCCEED != zbx_write_persistent_file(prep_vec->values[i].persistent_file_name, json.buffer, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot write persistent file \"%s\": %s",
					prep_vec->values[i].persistent_file_name, error);
			zbx_free(error);
		}

		zbx_json_free(&json);
	}
}

void	zbx_clean_pre_persistent_elements(zbx_vector_pre_persistent_t *prep_vec)
{
	int	i;

	/* clean only element data and number of elements, do not reduce vector size */

	for (i = 0; i < prep_vec->values_num; i++)
	{
		zbx_free(prep_vec->values[i].persistent_file_name);
		zbx_free(prep_vec->values[i].filename);
	}

	zbx_vector_pre_persistent_clear(prep_vec);
}

void	zbx_add_to_persistent_inactive_list(zbx_vector_persistent_inactive_t *inactive_vec, zbx_uint64_t itemid,
		const char *filename)
{
	zbx_persistent_inactive_t	el;

	el.itemid = itemid;

	if (FAIL == zbx_vector_persistent_inactive_search(inactive_vec, el,
			zbx_persistent_inactive_compare_func))
	{
		/* create and initialize a new vector element */

		el.not_received_time = time(NULL);
		el.persistent_file_name = zbx_strdup(NULL, filename);

		zbx_vector_persistent_inactive_append(inactive_vec, el);

		zabbix_log(LOG_LEVEL_DEBUG, "%s(): added element %d with itemid '" ZBX_FS_UI64 "' for file '%s'",
				__func__, inactive_vec->values_num - 1, itemid, filename);
	}
}

void	zbx_remove_from_persistent_inactive_list(zbx_vector_persistent_inactive_t *inactive_vec, zbx_uint64_t itemid)
{
	zbx_persistent_inactive_t	el;
	int				idx;

	el.itemid = itemid;

	if (FAIL == (idx = zbx_vector_persistent_inactive_search(inactive_vec, el,
			zbx_persistent_inactive_compare_func)))
	{
		return;
	}

	zbx_free(inactive_vec->values[idx].persistent_file_name);
	zbx_vector_persistent_inactive_remove(inactive_vec, idx);

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): removed element %d with itemid " ZBX_FS_UI64 , __func__, idx, itemid);
}

void	zbx_remove_inactive_persistent_files(zbx_vector_persistent_inactive_t *inactive_vec)
{
#define ZBX_PERSIST_INACTIVITY_PERIOD	SEC_PER_DAY	/* the time period after which persistent files used by log */
							/* items which are not received in active check list can be */
							/* removed */
	int	i;
	time_t	now;

	now = time(NULL);

	for (i = 0; i < inactive_vec->values_num; i++)
	{
		zbx_persistent_inactive_t	*el = inactive_vec->values + i;

		if (ZBX_PERSIST_INACTIVITY_PERIOD <= now - el->not_received_time)
		{
			char	*err_msg = NULL;

			zabbix_log(LOG_LEVEL_DEBUG, "%s(): removing element %d with itemid '" ZBX_FS_UI64 "'",
					__func__, i, el->itemid);

			if (SUCCEED != zbx_remove_persistent_file(el->persistent_file_name, &err_msg))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot remove persistent file \"%s\": %s",
						el->persistent_file_name, err_msg);
				zbx_free(err_msg);
			}

			zbx_free(el->persistent_file_name);
			zbx_vector_persistent_inactive_remove(inactive_vec, i);
		}
	}
#undef ZBX_PERSIST_INACTIVITY_PERIOD
}

static int	zbx_pre_persistent_compare_func(const void *d1, const void *d2)
{
	const zbx_pre_persistent_t	*p1 = (const zbx_pre_persistent_t *)d1;
	const zbx_pre_persistent_t	*p2 = (const zbx_pre_persistent_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1->itemid, p2->itemid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Searches preparation vector to find element with the specified    *
 *          itemid. If not found then creates the element.                    *
 *                                                                            *
 * Parameters:                                                                *
 *    prep_vec             - [IN/OUT] preparation vector for persistent data  *
 *                                files                                       *
 *    itemid               - [IN]                                             *
 *    persistent_file_name - [IN] file name for copying into new element      *
 *                                                                            *
 ******************************************************************************/
int	zbx_find_or_create_prep_vec_element(zbx_vector_pre_persistent_t *prep_vec, zbx_uint64_t itemid,
		const char *persistent_file_name)
{
	zbx_pre_persistent_t	prep_element;
	int			prep_vec_idx;

	prep_element.itemid = itemid;

	if (FAIL == (prep_vec_idx = zbx_vector_pre_persistent_search(prep_vec, prep_element,
			zbx_pre_persistent_compare_func)))
	{
		/* create and initialize a new vector element */
		memset(&prep_element, 0, sizeof(prep_element));

		zbx_vector_pre_persistent_append(prep_vec, prep_element);
		prep_vec_idx = prep_vec->values_num - 1;

		/* fill in 'itemid' and 'persistent_file_name' values - they never change for the specified */
		/* log*[] item (otherwise it is not the same item anymore) */
		prep_vec->values[prep_vec_idx].itemid = itemid;
		prep_vec->values[prep_vec_idx].persistent_file_name = zbx_strdup(NULL, persistent_file_name);

		zabbix_log(LOG_LEVEL_DEBUG, "%s(): itemid:" ZBX_FS_UI64 " created element %d",
				__func__, itemid, prep_vec_idx);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): itemid:" ZBX_FS_UI64 " found element %d",
				__func__, itemid, prep_vec_idx);
	}

	return prep_vec_idx;
}

void	zbx_init_prep_vec_data(const struct st_logfile *logfile, zbx_pre_persistent_t *prep_vec_elem)
{
	/* copy attributes which are stable within one check of the specified log file but */
	/* may change in the next check */

	if (NULL == prep_vec_elem->filename || 0 != strcmp(prep_vec_elem->filename, logfile->filename))
		prep_vec_elem->filename = zbx_strdup(prep_vec_elem->filename, logfile->filename);

	prep_vec_elem->mtime = logfile->mtime;
	prep_vec_elem->seq = logfile->seq;
	prep_vec_elem->copy_of = logfile->copy_of;
	prep_vec_elem->dev = logfile->dev;
	prep_vec_elem->ino_lo = logfile->ino_lo;
	prep_vec_elem->ino_hi = logfile->ino_hi;
	prep_vec_elem->size = logfile->size;

	prep_vec_elem->md5_block_size = logfile->md5_block_size;
	memcpy(prep_vec_elem->first_block_md5, logfile->first_block_md5, sizeof(logfile->first_block_md5));

	prep_vec_elem->last_block_offset = logfile->last_block_offset;
	memcpy(prep_vec_elem->last_block_md5, logfile->last_block_md5, sizeof(logfile->last_block_md5));
}

void	zbx_update_prep_vec_data(const struct st_logfile *logfile, zbx_uint64_t processed_size,
		zbx_pre_persistent_t *prep_vec_elem)
{
	/* copy attributes which (may) change with every log file record */
	prep_vec_elem->processed_size = processed_size;
	prep_vec_elem->incomplete = logfile->incomplete;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Create the 'old log file list' and restore log file attributes    *
 *          from JSON string which was read from persistent file.             *
 *                                                                            *
 * Parameters:                                                                *
 *     str            - [IN] JSON string                                      *
 *     logfiles       - [OUT] log file list (vector)                          *
 *     logfiles_num   - [OUT] number of elements in the log file list         *
 *     processed_size - [OUT] processed size attribute                        *
 *     mtime          - [OUT]                                                 *
 *     err_msg        - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 * Examples of valid JSON 'str' (one text line but here it is split for       *
 * readability):                                                              *
 *     {"mtime":1623174047,                                                   *
 *      "processed_size":0}                                                   *
 * or                                                                         *
 *     {"filename":"/home/zabbix/test.log",                                   *
 *      "mtime":0,                                                            *
 *      "processed_size":324,                                                 *
 *      "seq":0,                                                              *
 *      "incomplete":0,                                                       *
 *      "copy_of":-1,                                                         *
 *      "dev":65027,                                                          *
 *      "ino_lo":17043636,                                                    *
 *      "ino_hi":0,                                                           *
 *      "size":635,                                                           *
 *      "md5_block_size":512,                                                 *
 *      "first_block_md5":"7f2d0cf871384671c51359ce8c90e475",                 *
 *      "last_block_offset":123,                                              *
 *      "last_block_md5":"86985e105f79b95d6bc918fb45ec7727"}                  *
 ******************************************************************************/
int	zbx_restore_file_details(const char *str, struct st_logfile **logfiles, int *logfiles_num,
		zbx_uint64_t *processed_size, int *mtime, char **err_msg)
{
	struct zbx_json_parse	jp;
	/* temporary variables before filling in 'st_logfile' elements */
	char		filename[MAX_STRING_LEN];
	int		mtime_tmp = 0;
	int		seq = 0;
	int		incomplete = 0;
	int		copy_of = 0;
	zbx_uint64_t	dev;
	zbx_uint64_t	ino_lo;
	zbx_uint64_t	ino_hi;
	zbx_uint64_t	size;
	zbx_uint64_t	processed_size_tmp = 0;
	int		md5_block_size = 0;
	md5_byte_t	first_block_md5[ZBX_MD5_DIGEST_SIZE];
	zbx_uint64_t	last_block_offset = 0;
	md5_byte_t	last_block_md5[ZBX_MD5_DIGEST_SIZE];
	/* flags to check missing attributes */
	int		got_filename = 0, got_mtime = 0, got_seq = 0, got_incomplete = 0, got_copy_of = 0, got_dev = 0,
			got_ino_lo = 0, got_ino_hi = 0, got_size = 0, got_processed_size = 0, got_md5_block_size = 0,
			got_first_block_md5 = 0, got_last_block_offset = 0, got_last_block_md5 = 0, sum;
	char		tmp[MAX_STRING_LEN];

	if (0 != *logfiles_num || NULL != *logfiles)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		*err_msg = zbx_dsprintf(*err_msg, "%s(): non-empty log file list, logfiles_num:%d",
				__func__, *logfiles_num);
		zabbix_log(LOG_LEVEL_WARNING, "%s(): non-empty log file list, logfiles_num:%d",
				__func__, *logfiles_num);
		return FAIL;
	}

	if (SUCCEED != zbx_json_open(str, &jp))
	{
		*err_msg = zbx_dsprintf(*err_msg, "cannot parse persistent data: %s", zbx_json_strerror());
		return FAIL;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PERSIST_TAG_FILENAME, filename, sizeof(filename), NULL))
		got_filename = 1;

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PERSIST_TAG_MTIME, tmp, sizeof(tmp), NULL))
	{
		mtime_tmp = atoi(tmp);
		got_mtime = 1;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PERSIST_TAG_SEQ, tmp, sizeof(tmp), NULL))
	{
		seq = atoi(tmp);
		got_seq = 1;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PERSIST_TAG_INCOMPLETE, tmp, sizeof(tmp), NULL))
	{
		incomplete = atoi(tmp);
		got_incomplete = 1;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PERSIST_TAG_COPY_OF, tmp, sizeof(tmp), NULL))
	{
		copy_of = atoi(tmp);
		got_copy_of = 1;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PERSIST_TAG_DEVICE, tmp, sizeof(tmp), NULL))
	{
		if (SUCCEED == zbx_is_uint64(tmp, &dev))
			got_dev = 1;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PERSIST_TAG_INODE_LO, tmp, sizeof(tmp), NULL))
	{
		if (SUCCEED == zbx_is_uint64(tmp, &ino_lo))
			got_ino_lo = 1;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PERSIST_TAG_INODE_HI, tmp, sizeof(tmp), NULL))
	{
		if (SUCCEED == zbx_is_uint64(tmp, &ino_hi))
			got_ino_hi = 1;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PERSIST_TAG_SIZE, tmp, sizeof(tmp), NULL))
	{
		if (SUCCEED == zbx_is_uint64(tmp, &size))
			got_size = 1;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PERSIST_TAG_PROCESSED_SIZE, tmp, sizeof(tmp), NULL))
	{
		if (SUCCEED == zbx_is_uint64(tmp, &processed_size_tmp))
			got_processed_size = 1;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PERSIST_TAG_MD5_BLOCK_SIZE, tmp, sizeof(tmp), NULL))
	{
		md5_block_size = atoi(tmp);
		got_md5_block_size = 1;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PERSIST_TAG_FIRST_BLOCK_MD5, tmp, sizeof(tmp), NULL))
	{
		if (sizeof(first_block_md5) == zbx_hex2bin((const unsigned char *)tmp, first_block_md5,
				sizeof(first_block_md5)))
		{
			got_first_block_md5 = 1;
		}
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PERSIST_TAG_LAST_BLOCK_OFFSET, tmp, sizeof(tmp), NULL))
	{
		if (SUCCEED == zbx_is_uint64(tmp, &last_block_offset))
			got_last_block_offset = 1;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PERSIST_TAG_LAST_BLOCK_MD5, tmp, sizeof(tmp), NULL))
	{
		if (sizeof(last_block_md5) == zbx_hex2bin((const unsigned char *)tmp, last_block_md5,
				sizeof(last_block_md5)))
		{
			got_last_block_md5 = 1;
		}
	}

	/* 'mtime' and 'processed_size' should always be present */
	if (0 == got_mtime || 0 == got_processed_size)
	{
		*err_msg = zbx_dsprintf(*err_msg, "corrupted data: 'mtime' or 'processed_size' attribute missing");
		return FAIL;
	}

	/* all other 12 variables should be present or none of them */
	sum = got_filename + got_seq + got_incomplete + got_copy_of + got_dev + got_ino_lo + got_ino_hi + got_size +
			got_md5_block_size + got_first_block_md5 + got_last_block_offset + got_last_block_md5;

	if (0 == sum)	/* got only metadata 'mtime' and 'processed_size' */
	{
		*mtime = mtime_tmp;
		*processed_size = processed_size_tmp;
		return SUCCEED;
	}

	if (12 != sum)
	{
		*err_msg = zbx_dsprintf(*err_msg, "present/missing attributes: filename:%d seq:%d incomplete:%d"
				" copy_of:%d dev:%d ino_lo:%d ino_hi:%d size:%d md5_block_size:%d first_block_md5:%d"
				" last_block_offset:%d last_block_md5:%d", got_filename, got_seq,
				got_incomplete, got_copy_of, got_dev, got_ino_lo, got_ino_hi, got_size,
				got_md5_block_size, got_first_block_md5, got_last_block_offset, got_last_block_md5);
		return FAIL;
	}

	/* All attributes present. Create log file list with one element. It will be used as the 'old log file list', */
	/* it does not need to be resizable. */
	*logfiles = (struct st_logfile *)zbx_malloc(NULL, sizeof(struct st_logfile));
	*logfiles_num = 1;

	(*logfiles)[0].filename = zbx_strdup(NULL, filename);
	(*logfiles)[0].mtime = mtime_tmp;
	(*logfiles)[0].seq = seq;
	(*logfiles)[0].retry = 0;
	(*logfiles)[0].incomplete = incomplete;
	(*logfiles)[0].copy_of = copy_of;
	(*logfiles)[0].dev = dev;
	(*logfiles)[0].ino_lo = ino_lo;
	(*logfiles)[0].ino_hi = ino_hi;
	(*logfiles)[0].size = size;
	(*logfiles)[0].processed_size = processed_size_tmp;

	(*logfiles)[0].md5_block_size = md5_block_size;
	memcpy((*logfiles)[0].first_block_md5, first_block_md5, sizeof(first_block_md5));

	(*logfiles)[0].last_block_offset = last_block_offset;
	memcpy((*logfiles)[0].last_block_md5, last_block_md5, sizeof(last_block_md5));

	*mtime = mtime_tmp;
	*processed_size = processed_size_tmp;

	return SUCCEED;
}
#endif	/* not WINDOWS */
