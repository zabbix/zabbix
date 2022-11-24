#include "zbxalgo.h"
#include "log.h"
#include "zbxtime.h"
#include "zbxprof.h"

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
ZBX_FUNC_PROFILE;

static volatile int	zbx_prof_enable_requested;

static zbx_vector_ptr_t	zbx_func_profiles;
static int		zbx_prof_enabled;
static ZBX_FUNC_PROFILE	*zbx_func_profile[PROF_LEVEL_MAX];
static int		zbx_level;

static int	zbx_default_ptr_ptr_compare_func(const void *d1, const void *d2)
{
	const char	*p1 = *(const char **)d1;
	const char	*p2 = *(const char **)d2;

	return zbx_default_ptr_compare_func(p1, p2);
}

void	zbx_prof_start(const char *func_name, zbx_prof_scope_t scope)
{
	if (1 == zbx_prof_enabled)
	{
		int			i;
		ZBX_FUNC_PROFILE	*func_profile;

		if (0 == zbx_func_profiles.values_alloc)
			zbx_vector_ptr_create(&zbx_func_profiles);

		if (FAIL == (i = zbx_vector_ptr_bsearch(&zbx_func_profiles, &func_name,
			zbx_default_ptr_ptr_compare_func)))
		{
			func_profile = zbx_malloc(NULL, sizeof(ZBX_FUNC_PROFILE));
			func_profile->func_name = func_name;
			func_profile->sec = 0;
			func_profile->locked = 0;
			func_profile->sec_wait = 0;
			func_profile->scope = scope;

			zbx_vector_ptr_append(&zbx_func_profiles, func_profile);
			zbx_vector_ptr_sort(&zbx_func_profiles, zbx_default_ptr_ptr_compare_func);
		}
		else
			func_profile = zbx_func_profiles.values[i];

		func_profile->locked++;
		func_profile->start = zbx_time();

		zbx_func_profile[zbx_level] = func_profile;
		zbx_level++;
	}
}

void	zbx_prof_end(void)
{
	if (1 == zbx_prof_enabled)
	{
		ZBX_FUNC_PROFILE	*func_profile;

		func_profile = zbx_func_profile[zbx_level - 1];
		func_profile->sec += zbx_time() - func_profile->start;
		zbx_level--;
	}
}

void	zbx_prof_end_wait(void)
{
	if (1 == zbx_prof_enabled)
	{
		ZBX_FUNC_PROFILE	*func_profile;

		func_profile = zbx_func_profile[zbx_level - 1];

		func_profile->sec_wait += zbx_time() - func_profile->start;
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

static void	zbx_reset_prof(void)
{
	if (0 != zbx_func_profiles.values_alloc)
	{
		zbx_vector_ptr_clear_ext(&zbx_func_profiles, zbx_ptr_free);
		zbx_vector_ptr_destroy(&zbx_func_profiles);
	}
}

static void	zbx_print_prof(void)
{
	if (1 == zbx_prof_enabled)
	{
		int			i;
		ZBX_FUNC_PROFILE	*func_profile;
		static char		*sql = NULL;
		static size_t		sql_alloc;
		size_t			sql_offset = 0;
		double			total_wait_lock = 0, total_busy_lock = 0;

		for (i = 0; i < zbx_func_profiles.values_num; i++)
		{
			func_profile = zbx_func_profiles.values[i];

			if (ZBX_PROF_PROCESSING == func_profile->scope)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "processing %s() busy:" ZBX_FS_DBL
						"\n", func_profile->func_name, func_profile->sec);
			}
			else
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s %s() locked:%u busy:" ZBX_FS_DBL
						" wait:"ZBX_FS_DBL "\n", get_scope_string(func_profile->scope),
						func_profile->func_name, func_profile->locked,
						func_profile->sec - func_profile->sec_wait, func_profile->sec_wait);

				total_wait_lock += func_profile->sec_wait;
				total_busy_lock += func_profile->sec - func_profile->sec_wait;
			}
		}

		if (0 != sql_offset)
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "Profiling information: %s", sql);
			zabbix_log(LOG_LEVEL_INFORMATION, "Time spent holding locks:" ZBX_FS_DBL
					" Time spent waiting for locks:" ZBX_FS_DBL "\n", total_busy_lock, total_wait_lock);
		}
	}
	else
		zbx_reset_prof();
}

void	zbx_prof_update(void)
{
	static double	last_update, time_now;

	if (1 == zbx_prof_enable_requested)
		zbx_prof_enabled = 1;
	else
		zbx_prof_enabled = 0;

	if (30 < (time_now = zbx_time()) - last_update)
	{
		last_update = time_now;

		if (1 == zbx_prof_enabled)
			zbx_print_prof();
		else
			zbx_reset_prof();
	}
}

void	zbx_prof_enable(void)
{
	zbx_prof_enable_requested = 1;
}

void	zbx_prof_disable(void)
{
	zbx_prof_enable_requested = 0;
}
