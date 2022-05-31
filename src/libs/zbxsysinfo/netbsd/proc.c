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

#include "common.h"
#include "sysinfo.h"
#include "zbxregexp.h"
#include "log.h"
#include "zbxjson.h"

#include <sys/sysctl.h>

static kvm_t	*kd = NULL;

typedef struct
{
	int		pid;
	int		ppid;

	char		*name;
	char		*cmdline;
	char		*state;
	zbx_uint64_t	processes;

	char		*user;
	char		*group;
	zbx_uint64_t	uid;
	zbx_uint64_t	gid;

	zbx_uint64_t	cputime_user;
	zbx_uint64_t	cputime_system;
	zbx_uint64_t	ctx_switches;
	zbx_uint64_t	page_faults;
	zbx_uint64_t	io_read_op;
	zbx_uint64_t	io_write_op;

	zbx_uint64_t	vsize;
	zbx_uint64_t	rss;
	zbx_uint64_t	size;
	zbx_uint64_t	tsize;
	zbx_uint64_t	dsize;
	zbx_uint64_t	ssize;
	zbx_uint64_t	swap;
}
proc_data_t;

ZBX_PTR_VECTOR_DECL(proc_data_ptr, proc_data_t *)
ZBX_PTR_VECTOR_IMPL(proc_data_ptr, proc_data_t *)

/******************************************************************************
 *                                                                            *
 * Purpose: frees process data structure                                      *
 *                                                                            *
 ******************************************************************************/
static void	proc_data_free(proc_data_t *proc_data)
{
	zbx_free(proc_data->name);
	zbx_free(proc_data->cmdline);
	zbx_free(proc_data->state);
	zbx_free(proc_data->user);
	zbx_free(proc_data->group);

	zbx_free(proc_data);
}

static char	*proc_argv(pid_t pid)
{
	size_t		sz = 0;
	int		mib[4], i;
	static char	*argv = NULL;
	static size_t	argv_alloc = 0;

	mib[0] = CTL_KERN;
	mib[1] = KERN_PROC_ARGS;
	mib[2] = (int)pid;
	mib[3] = KERN_PROC_ARGV;

	if (0 != sysctl(mib, 4, NULL, &sz, NULL, 0))
		return NULL;

	if (argv_alloc < sz)
	{
		argv_alloc = sz;
		if (NULL == argv)
			argv = zbx_malloc(argv, argv_alloc);
		else
			argv = zbx_realloc(argv, argv_alloc);
	}

	sz = argv_alloc;
	if (0 != sysctl(mib, 4, argv, &sz, NULL, 0))
		return NULL;

	for (i = 0; i < (int)(sz - 1); i++ )
		if (argv[i] == '\0')
			argv[i] = ' ';

	return argv;
}

int     PROC_MEM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*procname, *proccomm, *param, *args;
	int			do_task, pagesize, count, i, proccount = 0, invalid_user = 0, proc_ok, comm_ok, op, arg;
	double			value = 0.0, memsize = 0;
	struct kinfo_proc2	*proc, *pproc;
	struct passwd		*usrinfo;

	if (4 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	procname = get_rparam(request, 0);
	param = get_rparam(request, 1);

	if (NULL != param && '\0' != *param)
	{
		errno = 0;

		if (NULL == (usrinfo = getpwnam(param)))
		{
			if (0 != errno)
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain user information: %s",
						zbx_strerror(errno)));
				return SYSINFO_RET_FAIL;
			}

			invalid_user = 1;
		}
	}
	else
		usrinfo = NULL;

	param = get_rparam(request, 2);

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "sum"))
		do_task = ZBX_DO_SUM;
	else if (0 == strcmp(param, "avg"))
		do_task = ZBX_DO_AVG;
	else if (0 == strcmp(param, "max"))
		do_task = ZBX_DO_MAX;
	else if (0 == strcmp(param, "min"))
		do_task = ZBX_DO_MIN;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	proccomm = get_rparam(request, 3);

	if (1 == invalid_user)	/* handle 0 for non-existent user after all parameters have been parsed and validated */
		goto out;

	pagesize = getpagesize();

	if (NULL == kd && NULL == (kd = kvm_open(NULL, NULL, NULL, KVM_NO_FILES, NULL)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain a descriptor to access kernel virtual memory."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL != usrinfo)
	{
		op = KERN_PROC_UID;
		arg = (int)usrinfo->pw_uid;
	}
	else
	{
		op = KERN_PROC_ALL;
		arg = 0;
	}

	if (NULL == (proc = kvm_getproc2(kd, op, arg, sizeof(struct kinfo_proc2), &count)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain process information."));
		return SYSINFO_RET_FAIL;
	}

	for (pproc = proc, i = 0; i < count; pproc++, i++)
	{
		proc_ok = 0;
		comm_ok = 0;

		if (NULL == procname || '\0' == *procname || 0 == strcmp(procname, pproc->p_comm))
			proc_ok = 1;

		if (NULL != proccomm && '\0' != *proccomm)
		{
			if (NULL != (args = proc_argv(pproc->p_pid)))
			{
				if (NULL != zbx_regexp_match(args, proccomm, NULL))
					comm_ok = 1;
			}
		}
		else
			comm_ok = 1;

		if (proc_ok && comm_ok)
		{
			value = pproc->p_vm_tsize + pproc->p_vm_dsize + pproc->p_vm_ssize;
			value *= pagesize;

			if (0 == proccount++)
				memsize = value;
			else
			{
				if (ZBX_DO_MAX == do_task)
					memsize = MAX(memsize, value);
				else if (ZBX_DO_MIN == do_task)
					memsize = MIN(memsize, value);
				else
					memsize += value;
			}
		}
	}
out:
	if (ZBX_DO_AVG == do_task)
		SET_DBL_RESULT(result, 0 == proccount ? 0 : memsize / proccount);
	else
		SET_UI64_RESULT(result, memsize);

	return SYSINFO_RET_OK;
}

int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*procname, *proccomm, *param, *args;
	int			proccount = 0, invalid_user = 0, zbx_proc_stat;
	int			count, i, proc_ok, stat_ok, comm_ok, op, arg;
	struct kinfo_proc2	*proc, *pproc;
	struct passwd		*usrinfo;

	if (4 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	procname = get_rparam(request, 0);
	param = get_rparam(request, 1);

	if (NULL != param && '\0' != *param)
	{
		errno = 0;

		if (NULL == (usrinfo = getpwnam(param)))
		{
			if (0 != errno)
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain user information: %s",
						zbx_strerror(errno)));
				return SYSINFO_RET_FAIL;
			}

			invalid_user = 1;
		}
	}
	else
		usrinfo = NULL;

	param = get_rparam(request, 2);

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "all"))
		zbx_proc_stat = ZBX_PROC_STAT_ALL;
	else if (0 == strcmp(param, "run"))
		zbx_proc_stat = ZBX_PROC_STAT_RUN;
	else if (0 == strcmp(param, "sleep"))
		zbx_proc_stat = ZBX_PROC_STAT_SLEEP;
	else if (0 == strcmp(param, "zomb"))
		zbx_proc_stat = ZBX_PROC_STAT_ZOMB;
	else if (0 == strcmp(param, "disk"))
		zbx_proc_stat = ZBX_PROC_STAT_DISK;
	else if (0 == strcmp(param, "trace"))
		zbx_proc_stat = ZBX_PROC_STAT_TRACE;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	proccomm = get_rparam(request, 3);

	if (1 == invalid_user)	/* handle 0 for non-existent user after all parameters have been parsed and validated */
		goto out;

	if (NULL == kd && NULL == (kd = kvm_open(NULL, NULL, NULL, KVM_NO_FILES, NULL)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain a descriptor to access kernel virtual memory."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL != usrinfo)
	{
		op = KERN_PROC_UID;
		arg = (int)usrinfo->pw_uid;
	}
	else
	{
		op = KERN_PROC_ALL;
		arg = 0;
	}

	if (NULL == (proc = kvm_getproc2(kd, op, arg, sizeof(struct kinfo_proc2), &count)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain process information."));
		return SYSINFO_RET_FAIL;
	}

	for (pproc = proc, i = 0; i < count; pproc++, i++)
	{
		proc_ok = 0;
		stat_ok = 0;
		comm_ok = 0;

		if (NULL == procname || '\0' == *procname || 0 == strcmp(procname, pproc->p_comm))
			proc_ok = 1;

		if (ZBX_PROC_STAT_ALL != zbx_proc_stat)
		{
			switch (zbx_proc_stat)
			{
				case ZBX_PROC_STAT_RUN:
					if (LSRUN == pproc->p_stat || LSONPROC == pproc->p_stat)
						stat_ok = 1;
					break;
				case ZBX_PROC_STAT_SLEEP:
					if (LSSLEEP == pproc->p_stat && 0 != (pproc->p_flag & L_SINTR))
						stat_ok = 1;
					break;
				case ZBX_PROC_STAT_ZOMB:
					if (0 != P_ZOMBIE(pproc))
						stat_ok = 1;
					break;
				case ZBX_PROC_STAT_DISK:
					if (LSSLEEP == pproc->p_stat && 0 == (pproc->p_flag & L_SINTR))
						stat_ok = 1;
					break;
				case ZBX_PROC_STAT_TRACE:
					if (LSSTOP == pproc->p_stat)
						stat_ok = 1;
					break;
			}
		}
		else
			stat_ok = 1;

		if (NULL != proccomm && '\0' != *proccomm)
		{
			if (NULL != (args = proc_argv(pproc->p_pid)))
			{
				if (NULL != zbx_regexp_match(args, proccomm, NULL))
					comm_ok = 1;
			}
		}
		else
			comm_ok = 1;

		if (proc_ok && stat_ok && comm_ok)
			proccount++;
	}
out:
	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}

static char	*get_state(struct kinfo_proc2 *proc)
{
	char	*state;

	if (LSRUN == proc->p_stat || LSONPROC == proc->p_stat)
		state = zbx_strdup(NULL, "running");
	else if (LSSLEEP == proc->p_stat && 0 != (proc->p_flag & L_SINTR))
		state = zbx_strdup(NULL, "sleeping");
	else if (0 != P_ZOMBIE(proc))
		state = zbx_strdup(NULL, "zombie");
	else if (LSSLEEP == proc->p_stat && 0 == (proc->p_flag & L_SINTR))
		state = zbx_strdup(NULL, "disk sleep");
	else if (LSSTOP == proc->p_stat)
		state = zbx_strdup(NULL, "tracing stop");
	else
		state = zbx_strdup(NULL, "other");

	return state;
}

int	PROC_GET(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char				*procname, *proccomm, *param, *args;
	int				invalid_user = 0, count, i, k, zbx_proc_mode, pagesize, op, arg;
	struct passwd			*usrinfo;
	zbx_vector_proc_data_ptr_t	proc_data_ctx;
	struct zbx_json			j;
	struct kinfo_proc2		*proc = NULL;

	if (4 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	procname = get_rparam(request, 0);
	param = get_rparam(request, 1);

	if (NULL != param && '\0' != *param)
	{
		errno = 0;

		if (NULL == (usrinfo = getpwnam(param)))
		{
			if (0 != errno)
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain user information: %s",
						zbx_strerror(errno)));
				return SYSINFO_RET_FAIL;
			}

			invalid_user = 1;
		}
	}
	else
		usrinfo = NULL;

	proccomm = get_rparam(request, 2);
	param = get_rparam(request, 3);

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "process"))
	{
		zbx_proc_mode = ZBX_PROC_MODE_PROCESS;
	}
	else if (0 == strcmp(param, "summary") && (NULL == proccomm || '\0' == *proccomm))
	{
		zbx_proc_mode = ZBX_PROC_MODE_SUMMARY;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (1 == invalid_user)
	{
		zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);
		goto out;
	}

	pagesize = getpagesize();

	if (NULL == kd && NULL == (kd = kvm_open(NULL, NULL, NULL, KVM_NO_FILES, NULL)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain a descriptor to access kernel virtual memory."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL != usrinfo)
	{
		op = KERN_PROC_UID;
		arg = (int)usrinfo->pw_uid;
	}
	else
	{
		op = KERN_PROC_ALL;
		arg = 0;
	}

	if (NULL == (proc = kvm_getproc2(kd, op, arg, sizeof(struct kinfo_proc2), &count)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain process information."));
		return SYSINFO_RET_FAIL;
	}

	zbx_vector_proc_data_ptr_create(&proc_data_ctx);

	for (i = 0; i < count; i++)
	{
		proc_data_t	*proc_data;
		struct passwd	*pw;
		struct group	*gr;

		if (NULL != procname && '\0' != *procname && 0 != strcmp(procname, proc[i].p_comm))
			continue;

		args = proc_argv(proc[i].p_pid);

		if (NULL != proccomm && '\0' != *proccomm && NULL == zbx_regexp_match(args, proccomm, NULL))
			continue;

		proc_data = (proc_data_t *)zbx_malloc(NULL, sizeof(proc_data_t));

		if (ZBX_PROC_MODE_PROCESS == zbx_proc_mode)
		{
			pw = getpwuid(proc[i].p_ruid);
			gr = getgrgid(proc[i].p_rgid);

			proc_data->pid = proc[i].p_pid;
			proc_data->ppid = proc[i].p_ppid;
			proc_data->cmdline = zbx_strdup(NULL, ZBX_NULL2EMPTY_STR(args));
			proc_data->state = get_state(&proc[i]);
			proc_data->uid = proc[i].p_ruid;
			proc_data->gid = proc[i].p_rgid;
			proc_data->user = NULL != pw ? zbx_strdup(NULL, pw->pw_name) :
					zbx_dsprintf(NULL, ZBX_FS_UI64, proc_data->uid);
			proc_data->group = NULL != gr ? zbx_strdup(NULL, gr->gr_name) :
					zbx_dsprintf(NULL, ZBX_FS_UI64, proc_data->gid);
		}
		else
		{
			proc_data->cmdline = NULL;
			proc_data->state = NULL;
			proc_data->user = NULL;
			proc_data->group = NULL;
		}

		proc_data->name = zbx_strdup(NULL, ZBX_NULL2EMPTY_STR(proc[i].p_comm));
		proc_data->size = (proc[i].p_vm_tsize + proc[i].p_vm_dsize + proc[i].p_vm_ssize) * pagesize;
		proc_data->rss = proc[i].p_vm_rssize * pagesize;
		proc_data->vsize = proc[i].p_vm_vsize;
		proc_data->tsize = proc[i].p_vm_tsize * pagesize;
		proc_data->dsize = proc[i].p_vm_dsize * pagesize;
		proc_data->ssize = proc[i].p_vm_ssize * pagesize;
		proc_data->cputime_user = proc[i].p_uutime_sec;
		proc_data->cputime_system = proc[i].p_ustime_sec;
		proc_data->ctx_switches = proc[i].p_uru_nvcsw + proc[i].p_uru_nivcsw;
		proc_data->page_faults = proc[i].p_uru_majflt;
		proc_data->io_read_op = proc[i].p_uru_oublock;
		proc_data->io_write_op = proc[i].p_uru_inblock;
		proc_data->swap = proc[i].p_uru_nswap;

		zbx_vector_proc_data_ptr_append(&proc_data_ctx, proc_data);
	}

	if (ZBX_PROC_MODE_SUMMARY == zbx_proc_mode)
	{
		for (i = 0; i < proc_data_ctx.values_num; i++)
		{
			proc_data_t	*pdata = proc_data_ctx.values[i];

			pdata->processes = 1;

			for (k = i + 1; k < proc_data_ctx.values_num; k++)
			{
				proc_data_t	*pdata_cmp = proc_data_ctx.values[k];

				if (0 == strcmp(pdata->name, pdata_cmp->name))
				{
					pdata->processes++;
					pdata->rss += pdata_cmp->rss;
					pdata->vsize += pdata_cmp->vsize;
					pdata->tsize += pdata_cmp->tsize;
					pdata->dsize += pdata_cmp->dsize;
					pdata->ssize += pdata_cmp->ssize;
					pdata->size += pdata_cmp->size;
					pdata->swap += pdata_cmp->swap;
					pdata->cputime_user += pdata_cmp->cputime_user;
					pdata->cputime_system += pdata_cmp->cputime_system;
					pdata->ctx_switches += pdata_cmp->ctx_switches;
					pdata->page_faults += pdata_cmp->page_faults;
					pdata->io_read_op += pdata_cmp->io_read_op;
					pdata->io_write_op += pdata_cmp->io_write_op;

					proc_data_free(pdata_cmp);
					zbx_vector_proc_data_ptr_remove(&proc_data_ctx, k--);
				}
			}
		}
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	for (i = 0; i < proc_data_ctx.values_num; i++)
	{
		proc_data_t	*pdata;

		pdata = proc_data_ctx.values[i];

		zbx_json_addobject(&j, NULL);

		if (ZBX_PROC_MODE_PROCESS == zbx_proc_mode)
		{
			zbx_json_addint64(&j, "pid", pdata->pid);
			zbx_json_addint64(&j, "ppid", pdata->ppid);
			zbx_json_addstring(&j, "name", pdata->name, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "cmdline", pdata->cmdline, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "user", pdata->user, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "group", pdata->group, ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&j, "uid", pdata->uid);
			zbx_json_adduint64(&j, "gid", pdata->gid);
		}
		else
		{
			zbx_json_addstring(&j, "name", pdata->name, ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&j, "processes", pdata->processes);
		}

		zbx_json_adduint64(&j, "vsize", pdata->vsize);
		zbx_json_adduint64(&j, "rss", pdata->rss);
		zbx_json_adduint64(&j, "size", pdata->size);
		zbx_json_adduint64(&j, "tsize", pdata->tsize);
		zbx_json_adduint64(&j, "dsize", pdata->dsize);
		zbx_json_adduint64(&j, "ssize", pdata->ssize);
		zbx_json_adduint64(&j, "cputime_user", pdata->cputime_user);
		zbx_json_adduint64(&j, "cputime_system", pdata->cputime_system);

		if (ZBX_PROC_MODE_PROCESS == zbx_proc_mode)
			zbx_json_addstring(&j, "state", pdata->state, ZBX_JSON_TYPE_STRING);

		zbx_json_adduint64(&j, "ctx_switches", pdata->ctx_switches);
		zbx_json_adduint64(&j, "page_faults", pdata->page_faults);
		zbx_json_adduint64(&j, "swap", pdata->swap);
		zbx_json_adduint64(&j, "io_read_op", pdata->io_read_op);
		zbx_json_adduint64(&j, "io_write_op", pdata->io_write_op);

		zbx_json_close(&j);
	}

	zbx_vector_proc_data_ptr_clear_ext(&proc_data_ctx, proc_data_free);
	zbx_vector_proc_data_ptr_destroy(&proc_data_ctx);
out:
	zbx_json_close(&j);
	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));
	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}
