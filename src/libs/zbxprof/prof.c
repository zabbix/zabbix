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

#include "zbxprof.h"
#include "zbxalgo.h"
#include "zbxtime.h"

#define PROF_LEVEL_MAX	10

typedef struct
{
	const char		*func_name;
	double			start;
	double			sec;
	double			sec_wait;
	unsigned int		locked;
	zbx_prof_scope_t	scope;
}
zbx_func_profile_t;

ZBX_PTR_VECTOR_DECL(func_profiles, zbx_func_profile_t*)
ZBX_PTR_VECTOR_IMPL(func_profiles, zbx_func_profile_t*)

static volatile int					zbx_prof_scope_requested;

static ZBX_THREAD_LOCAL zbx_vector_func_profiles_t	zbx_func_profiles;
static ZBX_THREAD_LOCAL zbx_prof_scope_t		zbx_prof_scope;
static ZBX_THREAD_LOCAL int				zbx_prof_initialized;

static ZBX_THREAD_LOCAL zbx_func_profile_t		*zbx_func_profile[PROF_LEVEL_MAX];
#undef PROF_LEVEL_MAX
static ZBX_THREAD_LOCAL int				zbx_func_profile_level;

static void	zbx_prof_init(void)
{
	if (0 == zbx_prof_initialized)
	{
		zbx_prof_initialized = 1;
		zbx_vector_func_profiles_create(&zbx_func_profiles);
	}
}

static int	compare_func_profile(const void *d1, const void *d2)
{
	const zbx_func_profile_t	*func_profile1 = *((const zbx_func_profile_t * const *)d1);
	const zbx_func_profile_t	*func_profile2 = *((const zbx_func_profile_t * const *)d2);

	ZBX_RETURN_IF_NOT_EQUAL(func_profile1->func_name, func_profile2->func_name);

	return 0;
}

static void	func_profile_free(zbx_func_profile_t *func_profile)
{
	zbx_free(func_profile);
}

void	zbx_prof_start(const char *func_name, zbx_prof_scope_t scope)
{
	if (0 != zbx_prof_scope)
	{
		int			i;
#if defined(__hpux)
		/* fix for compiling with HP-UX bundled cc compiler */
		zbx_func_profile_t	*func_profile, func_profile_local = {func_name, 0};
#else
		zbx_func_profile_t	*func_profile, func_profile_local = {.func_name = func_name};
#endif
		if (FAIL == (i = zbx_vector_func_profiles_bsearch(&zbx_func_profiles, &func_profile_local,
				compare_func_profile)))
		{
			func_profile = zbx_malloc(NULL, sizeof(zbx_func_profile_t));
			func_profile->func_name = func_name;
			func_profile->sec = 0;
			func_profile->locked = 0;
			func_profile->sec_wait = 0;
			func_profile->scope = scope;

			zbx_vector_func_profiles_append(&zbx_func_profiles, func_profile);
			zbx_vector_func_profiles_sort(&zbx_func_profiles, compare_func_profile);
		}
		else
			func_profile = zbx_func_profiles.values[i];

		func_profile->locked++;
		func_profile->start = zbx_time();

		zbx_func_profile[zbx_func_profile_level] = func_profile;
		zbx_func_profile_level++;
	}
}

void	zbx_prof_end_wait(void)
{
	if (0 != zbx_prof_scope)
	{
		zbx_func_profile_t	*func_profile;

		func_profile = zbx_func_profile[zbx_func_profile_level - 1];

		func_profile->sec_wait += zbx_time() - func_profile->start;
	}
}

void	zbx_prof_end(void)
{
	if (0 != zbx_prof_scope)
	{
		zbx_func_profile_t	*func_profile;

		func_profile = zbx_func_profile[zbx_func_profile_level - 1];
		func_profile->sec += zbx_time() - func_profile->start;
		zbx_func_profile_level--;
	}
}

static const char	*get_scope_string(zbx_prof_scope_t scope)
{
	switch (scope)
	{
		case ZBX_PROF_RWLOCK:
			return "rwlock";
		case ZBX_PROF_MUTEX:
			return "mutex";
		default:
			return "processing";
	}
}

static void	zbx_print_prof(const char *info)
{
	if (0 != zbx_prof_scope)
	{
		int			i;
		zbx_func_profile_t	*func_profile;
		static ZBX_THREAD_LOCAL char	*str = NULL;
		static ZBX_THREAD_LOCAL size_t	str_alloc;
		size_t			str_offset = 0;
		double			total_wait_lock = 0, total_busy_lock = 0, total_mutex_wait_lock = 0,
					total_mutex_busy_lock = 0;
		unsigned int		total_locked_mutex = 0, total_locked_rwlock = 0;

		for (i = 0; i < zbx_func_profiles.values_num; i++)
		{
			func_profile = zbx_func_profiles.values[i];

			if (0 == (zbx_prof_scope & func_profile->scope))
				continue;

			if (ZBX_PROF_PROCESSING == func_profile->scope)
			{
				zbx_snprintf_alloc(&str, &str_alloc, &str_offset, "\n%s() processing : busy:" ZBX_FS_DBL
						" sec", func_profile->func_name, func_profile->sec);
			}
			else
			{
				zbx_snprintf_alloc(&str, &str_alloc, &str_offset, "\n%s() %s : locked:%u holding:"
						ZBX_FS_DBL " sec waiting:"ZBX_FS_DBL " sec",
						func_profile->func_name, get_scope_string(func_profile->scope),
						func_profile->locked, func_profile->sec - func_profile->sec_wait,
						func_profile->sec_wait);

				if (ZBX_PROF_RWLOCK == func_profile->scope)
				{
					total_wait_lock += func_profile->sec_wait;
					total_busy_lock += func_profile->sec - func_profile->sec_wait;
					total_locked_rwlock += func_profile->locked;
				}
				else
				{
					total_mutex_wait_lock += func_profile->sec_wait;
					total_mutex_busy_lock += func_profile->sec - func_profile->sec_wait;
					total_locked_mutex += func_profile->locked;
				}
			}
		}

		if (0 != str_offset)
		{
			if (0 != (ZBX_PROF_RWLOCK & zbx_prof_scope))
			{
				zbx_snprintf_alloc(&str, &str_alloc, &str_offset, "\nrwlocks : locked:%u holding:"
						ZBX_FS_DBL " sec waiting:" ZBX_FS_DBL " sec", total_locked_rwlock,
						total_busy_lock, total_wait_lock);
			}
			if (0 != (ZBX_PROF_MUTEX & zbx_prof_scope))
			{
				zbx_snprintf_alloc(&str, &str_alloc, &str_offset, "\nmutexes : locked:%u holding:"
						ZBX_FS_DBL " sec waiting:" ZBX_FS_DBL " sec", total_locked_mutex,
						total_mutex_busy_lock, total_mutex_wait_lock);
			}

			if (ZBX_PROF_ALL == zbx_prof_scope)
			{
				zbx_snprintf_alloc(&str, &str_alloc, &str_offset, "\nlocking total : locked:%u holding:"
						ZBX_FS_DBL " sec waiting:" ZBX_FS_DBL " sec",
						total_locked_rwlock + total_locked_mutex,
						total_busy_lock + total_mutex_busy_lock,
						total_wait_lock + total_mutex_wait_lock);
			}

			zabbix_log(LOG_LEVEL_INFORMATION, "=== Profiling statistics for %s === %s", info, str);
		}
	}
}

void	zbx_prof_enable(zbx_prof_scope_t scope)
{
	if (0 == scope)
		scope = ZBX_PROF_ALL;

	zbx_prof_scope_requested = (int)scope;
}

void	zbx_prof_disable(void)
{
	zbx_prof_scope_requested = 0;
}

static void	zbx_reset_prof(void)
{
	if (0 != zbx_prof_initialized)
		zbx_vector_func_profiles_clear_ext(&zbx_func_profiles, func_profile_free);
}

void	zbx_prof_update(const char *info, double time_now)
{
#define PROF_UPDATE_INTERVAL	30
	static ZBX_THREAD_LOCAL double	last_update;

	if (0 != zbx_prof_scope_requested)
	{
		zbx_prof_init();
		zbx_prof_scope = zbx_prof_scope_requested;
	}
	else
		zbx_prof_scope = 0;

	if (PROF_UPDATE_INTERVAL < time_now - last_update)
	{
		last_update = time_now;

		if (0 != zbx_prof_scope)
			zbx_print_prof(info);
		else
			zbx_reset_prof();
	}
#undef PROF_UPDATE_INTERVAL
}
