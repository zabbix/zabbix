/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxsysinfo.h"
#include "../sysinfo.h"

#if defined(HAVE_LIBODM)
#	include <odmi.h>
#	include <sys/cfgodm.h>
#endif

#include "zbxstr.h"
#include "zbxjson.h"
#include "zbxregexp.h"

#define ZBX_MODE_DISKS		0
#define ZBX_MODE_DISK_STATS	1

typedef struct
{
	zbx_uint64_t	nread;
	zbx_uint64_t	nwritten;
	zbx_uint64_t	reads;
	zbx_uint64_t	writes;
}
zbx_perfstat_t;

int	zbx_get_diskstat(const char *devname, zbx_uint64_t *dstat)
{
	ZBX_UNUSED(devname);
	ZBX_UNUSED(dstat);

	return FAIL;
}

#if defined(HAVE_LIBODM)
typedef struct
{
	char	*name;
	char	*unique_id;
}
zbx_disk_t;

ZBX_PTR_VECTOR_DECL(disk_ptr, zbx_disk_t*)
ZBX_PTR_VECTOR_IMPL(disk_ptr, zbx_disk_t*)

static void	disk_free(zbx_disk_t *disk)
{
	zbx_free(disk->name);
	zbx_free(disk->unique_id);
	zbx_free(disk);
}

static int	disk_compare(const void *d1, const void *d2)
{
	const zbx_disk_t	*p1 = *(zbx_disk_t * const *)d1;
	const zbx_disk_t	*p2 = *(zbx_disk_t * const *)d2;

	return strcmp(p1->name, p2->name);
}
#endif /* HAVE_LIBODM */

static int	get_perfstat_io(const char *devname, zbx_perfstat_t *zp, char **error)
{
#if defined(HAVE_LIBPERFSTAT)
	int	err;

	if ('\0' != *devname)
	{
		perfstat_id_t	name;
		perfstat_disk_t	data;

		zbx_strscpy(name.name, devname);

		if (0 < (err = perfstat_disk(&name, &data, sizeof(data), 1)))
		{
			zp->nread = data.rblks * data.bsize;
			zp->nwritten = data.wblks * data.bsize;
			zp->reads = data.xrate;
			zp->writes = data.xfers - data.xrate;

			return SYSINFO_RET_OK;
		}
	}
	else
	{
		perfstat_disk_total_t	data;

		if (0 < (err = perfstat_disk_total(NULL, &data, sizeof(data), 1)))
		{
			zp->nread = data.rblks * 512;
			zp->nwritten = data.wblks * 512;
			zp->reads = data.xrate;
			zp->writes = data.xfers - data.xrate;

			return SYSINFO_RET_OK;
		}
	}

	if (0 == err)
		*error = zbx_strdup(NULL, "Cannot obtain system information.");
	else
		*error = zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno));

	return SYSINFO_RET_FAIL;
#else
	*error = zbx_strdup(NULL, "Agent was compiled without support for Perfstat API.");

	return SYSINFO_RET_FAIL;
#endif
}

static int	vfs_dev_read_bytes(const char *devname, AGENT_RESULT *result)
{
	zbx_perfstat_t	zp;
	char		*error;

	if (SYSINFO_RET_OK != get_perfstat_io(devname, &zp, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, zp.nread);

	return SYSINFO_RET_OK;
}

static int	vfs_dev_read_operations(const char *devname, AGENT_RESULT *result)
{
	zbx_perfstat_t	zp;
	char		*error;

	if (SYSINFO_RET_OK != get_perfstat_io(devname, &zp, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, zp.reads);

	return SYSINFO_RET_OK;
}

static int	vfs_dev_write_bytes(const char *devname, AGENT_RESULT *result)
{
	zbx_perfstat_t	zp;
	char		*error;

	if (SYSINFO_RET_OK != get_perfstat_io(devname, &zp, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, zp.nwritten);

	return SYSINFO_RET_OK;
}

static int	vfs_dev_write_operations(const char *devname, AGENT_RESULT *result)
{
	zbx_perfstat_t	zp;
	char		*error;

	if (SYSINFO_RET_OK != get_perfstat_io(devname, &zp, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, zp.writes);

	return SYSINFO_RET_OK;
}

#define ZBX_DEV_PFX	"/dev/"

int	vfs_dev_read(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*devname, *type;
	int		ret;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	devname = get_rparam(request, 0);

	if (NULL == devname || 0 == strcmp("all", devname))
		devname = "";
	else if (0 == strncmp(ZBX_DEV_PFX, devname, ZBX_CONST_STRLEN(ZBX_DEV_PFX)))
		devname += ZBX_CONST_STRLEN(ZBX_DEV_PFX);

	type = get_rparam(request, 1);

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "operations"))
		ret = vfs_dev_read_operations(devname, result);
	else if (0 == strcmp(type, "bytes"))
		ret = vfs_dev_read_bytes(devname, result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}

int	vfs_dev_write(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*devname, *type;
	int		ret;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	devname = get_rparam(request, 0);

	if (NULL == devname || 0 == strcmp("all", devname))
		devname = "";
	else if (0 == strncmp(ZBX_DEV_PFX, devname, ZBX_CONST_STRLEN(ZBX_DEV_PFX)))
		devname += ZBX_CONST_STRLEN(ZBX_DEV_PFX);

	type = get_rparam(request, 1);

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "operations"))
		ret = vfs_dev_write_operations(devname, result);
	else if (0 == strcmp(type, "bytes"))
		ret = vfs_dev_write_bytes(devname, result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}

#if defined(HAVE_LIBODM)
static char	*get_unique_id(char *hdisk, zbx_vector_disk_ptr_t *devices)
{
	int		index;
	zbx_disk_t	disk_local;

	disk_local.name = hdisk;
	index = zbx_vector_disk_ptr_bsearch(devices, &disk_local, disk_compare);

	if (FAIL != index)
		return devices->values[index]->unique_id;
	else
		return "";
}

static void	log_odm_err(const char *err_prefix)
{
	char	*errmsg;

	if (0 > odm_err_msg(odmerrno, (char **)&errmsg))
		zabbix_log(LOG_LEVEL_WARNING, "%s", err_prefix);
	else
		zabbix_log(LOG_LEVEL_WARNING, "%s, %s", err_prefix, errmsg);
}

static void	get_unique_ids(const zbx_regexp_t *disknames_rxp, zbx_vector_disk_ptr_t *disks)
{
	char		crit[MAX_STRING_LEN];
	CLASS_SYMBOL	cuat_cls;
	struct CuAt	cuat;
	struct CuAt	*p;

	zbx_vector_disk_ptr_create(disks);

	if (-1 == odm_initialize())
	{
		log_odm_err("cannot get device IDs, odm_initialize() failed");

		return;
	}

	cuat_cls = odm_open_class(CuAt_CLASS);

	if ((CLASS_SYMBOL)-1 == cuat_cls)
	{
		log_odm_err("cannot get device IDs, odm_open_class() failed");
		goto out2;
	}

	zbx_snprintf(crit, sizeof(crit), "attribute='unique_id'");

	p = (struct CuAt *)odm_get_first(cuat_cls, crit, &cuat);

	if ((struct CuAt *)-1 == p)
	{
		log_odm_err("cannot get device IDs, odm_get_first() failed");
		goto out1;
	}

	while (NULL != p && (struct CuAt *)-1 != p)
	{
		if (NULL == disknames_rxp || 0 == zbx_regexp_match_precompiled(cuat.name, disknames_rxp))
		{
			zbx_disk_t	*disk = zbx_malloc(NULL, sizeof(zbx_disk_t));

			disk->name = zbx_strdup(NULL, cuat.name);
			disk->unique_id = zbx_strdup(NULL, cuat.value);
			zbx_vector_disk_ptr_append(disks, disk);
		}

		p = (struct CuAt *)odm_get_next(cuat_cls, &cuat);
	}

	if ((struct CuAt *)-1 == p)
		log_odm_err("cannot get device IDs, odm_get_next() failed");
out1:
	odm_close_class(cuat_cls);
out2:
	odm_terminate();

	if (0 < disks->values_num)
		zbx_vector_disk_ptr_sort(disks, disk_compare);
}
#endif /* HAVE_LIBODM */

static void	vfs_dev_get_process_entries(int mode, const zbx_regexp_t *disknames_rxp, struct zbx_json *cfg,
		struct zbx_json *val)
{
	int			i, num_avail, num_filled;
	perfstat_disk_t		*statp;
	perfstat_id_t		first;
#if defined(HAVE_LIBODM)
	zbx_vector_disk_ptr_t	disks;
#endif

	num_avail = perfstat_disk(NULL, NULL, sizeof(perfstat_disk_t), 0);

	if (-1 == num_avail)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve number of available sets of disk statistics, %s",
				zbx_strerror(errno));

		return;
	}

	if (0 == num_avail)
		return;

	statp = (perfstat_disk_t *)zbx_calloc(NULL, num_avail, sizeof(perfstat_disk_t));

	zbx_strlcpy(first.name, FIRST_DISK, sizeof(first.name));

	num_filled = perfstat_disk(&first, statp, sizeof(perfstat_disk_t), num_avail);

	if (-1 == num_filled)
	{
		zabbix_log(LOG_LEVEL_WARNING, "error while executing retrieving individual disk usage statistics, %s",
				zbx_strerror(errno));

		goto out;
	}
#if defined(HAVE_LIBODM)
	get_unique_ids(disknames_rxp, &disks);
#endif
	for (i = 0; i < num_filled; i++)
	{
		char		buf[MAX_STRING_LEN];
		perfstat_disk_t	*dsk = &statp[i];

		if (NULL != disknames_rxp && 0 != zbx_regexp_match_precompiled(dsk->name, disknames_rxp))
			continue;

		zbx_json_addobject(cfg, NULL);
		zbx_json_addstring(cfg, "name", dsk->name, ZBX_JSON_TYPE_STRING);
#if defined(HAVE_LIBODM)
		zbx_json_addstring(cfg, "devid", get_unique_id(dsk->name, &disks), ZBX_JSON_TYPE_STRING);
#else
		zbx_json_addstring(cfg, "devid", "unknown (agent compiled without ODM support)", ZBX_JSON_TYPE_STRING);
#endif
		zbx_json_addstring(cfg, "type", "disk", ZBX_JSON_TYPE_STRING);

		if (ZBX_MODE_DISKS == mode)
		{
			zbx_snprintf(buf, sizeof(buf), ZBX_DEV_PFX "%s", dsk->name);
			zbx_json_addstring(cfg, "path", buf, ZBX_JSON_TYPE_STRING);
		}

		zbx_json_adduint64(cfg, "size_bytes", (zbx_uint64_t)dsk->size * 1000000);

		if (ZBX_MODE_DISKS == mode)
		{
			zbx_json_adduint64(cfg, "logical_block_size", (zbx_uint64_t)dsk->bsize);
		}

		zbx_json_close(cfg);

		if (ZBX_MODE_DISK_STATS == mode)
		{
			zbx_json_addobject(val, NULL);
			zbx_json_addstring(val, "name", statp[i].name, ZBX_JSON_TYPE_STRING);
			zbx_json_addobject(val, "stats");
			zbx_json_adduint64(val, "reads_completed", (zbx_uint64_t)dsk->__rxfers);
			zbx_json_adduint64(val, "writes_completed", (zbx_uint64_t)(dsk->xfers - dsk->__rxfers));
			zbx_json_adduint64(val, "bytes_read", (zbx_uint64_t)dsk->rblks * (zbx_uint64_t)dsk->bsize);
			zbx_json_adduint64(val, "bytes_written", (zbx_uint64_t)dsk->wblks * (zbx_uint64_t)dsk->bsize);
			zbx_json_adduint64(val, "io_time_ms", (zbx_uint64_t)dsk->time);
			zbx_json_close(val);
			zbx_json_close(val);
		}
	}
#if defined(HAVE_LIBODM)
	zbx_vector_disk_ptr_clear_ext(&disks, disk_free);
	zbx_vector_disk_ptr_destroy(&disks);
#endif
out:
	zbx_free(statp);
}

/******************************************************************************
 *                                                                            *
 * Purpose: discovers disks and partitions                                    *
 *                                                                            *
 * Parameters: request - [IN]                                                 *
 *             result  - [OUT]                                                *
 *                                                                            *
 * Return value: SYSINFO_RET_OK or SYSINFO_RET_FAIL.                          *
 *                                                                            *
 ******************************************************************************/
int	vfs_dev_get(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#if defined(HAVE_LIBPERFSTAT)
	char		*devnames, *mode, *rxp_error = NULL;
	int		has_vals, imode, ret = SYSINFO_RET_OK;
	struct zbx_json	j, cfg, val;
	zbx_regexp_t	*devnames_rxp = NULL;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	mode = get_rparam(request, 0);
	devnames = get_rparam(request, 1);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "disks"))	/* default parameter */
	{
		imode = ZBX_MODE_DISKS;
		has_vals = 0;
	}
	else if (0 == strcmp(mode, "disk_stats"))
	{
		imode = ZBX_MODE_DISK_STATS;
		has_vals = 1;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	if (NULL != devnames && '\0' != *devnames && SUCCEED != zbx_regexp_compile(devnames, &devnames_rxp, &rxp_error))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Invalid regular expression in second parameter: %s",
				rxp_error));

		zbx_free(rxp_error);
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_initarray(&cfg, ZBX_JSON_STAT_BUF_LEN);
	if (1 == has_vals)
		zbx_json_initarray(&val, ZBX_JSON_STAT_BUF_LEN);

	vfs_dev_get_process_entries(imode, devnames_rxp, &cfg, &val);

	zbx_json_addraw(&j, "config", cfg.buffer);
	if (1 == has_vals)
		zbx_json_addraw(&j, "values", val.buffer);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&cfg);
	if (1 == has_vals)
		zbx_json_free(&val);
	zbx_json_free(&j);
clean:
	if (NULL != devnames_rxp)
		zbx_regexp_free(devnames_rxp);

	return ret;
#else
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Agent was compiled without support for Perfstat API."));

	return SYSINFO_RET_FAIL;
#endif /* HAVE_LIBPERFSTAT */
}
#undef ZBX_DEV_PFX
