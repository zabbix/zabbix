/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "config.h"

#include "common.h"
#include "sysinfo.h"


#if OFF
/*
 * hidden
 */
typedef struct busy_data
{
  hrtime_t clock;
  hrtime_t rtime;
} BUSY_DATA;

typedef struct svc_time_data
{
  hrtime_t rtime;
  uint_t reads;
  uint_t writes;
} SVC_TIME_DATA;

typedef struct read_ios_data
{
  hrtime_t clock;
  uint_t reads;
} READ_IOS_DATA;

typedef struct write_ios_data
{
  hrtime_t clock;
  uint_t writes;
} WRITE_IOS_DATA;

typedef struct rblocks_data
{
  hrtime_t clock;
  u_longlong_t nread;
} RBLOCKS_DATA;

typedef struct wblocks_data
{
  hrtime_t clock;
  u_longlong_t nwritten;
} WBLOCKS_DATA;

typedef struct disk_data
{
  struct disk_data *next;
  char name[MAX_STRING_LEN];
  BUSY_DATA busy;
  SVC_TIME_DATA svc;
  READ_IOS_DATA reads;
  WRITE_IOS_DATA writes;
  RBLOCKS_DATA rblocks;
  WBLOCKS_DATA wblocks;
} DISK_DATA;
#endif

#if OFF
/*
 * hidden
 */
static DISK_DATA *get_disk_data_record(const char *device)
{
  static DISK_DATA *disks;
  DISK_DATA *p;

  for(p = disks; (p) && (strncmp(p->name, device, MAX_STRING_LEN) != 0); p = p->next)

  if (p == (DISK_DATA *) NULL)
    {
      p = (DISK_DATA *) calloc(1, sizeof(DISK_DATA));

      if (p)
	{
	  zbx_strlcpy(p->name, device, MAX_STRING_LEN);

          if (p->name)
            {
	      p->next = disks;
              
              disks = p;
            }

          else
	    {
	      free(p);

              p = NULL;
            }
        }
    }

  return p;
}
#endif

#if OFF
/*
 * hidden
 */
static int get_disk_kstat_record(const char *name,
                                 hrtime_t *crtime,
                                 hrtime_t *snaptime, 
                                 kstat_io_t *returned_data)
{
  int result = SYSINFO_RET_FAIL;
  kstat_ctl_t *kc;

  kc = kstat_open();

  if (kc)
    {
      kstat_t *kt;

      kt = kstat_lookup(kc, NULL, -1, (char *) name);

      if (kt)
	{
	  if (    (kt->ks_type == KSTAT_TYPE_IO)
               && (kstat_read(kc, kt, returned_data) != -1)
	     )
            {
               *crtime = kt->ks_crtime;
	       *snaptime = kt->ks_snaptime;
	       result = SYSINFO_RET_OK;
            }
        }

      kstat_close(kc);
    }

  return result;
}
#endif 

#if OFF
/*
 * hidden
 */
int	DISKSVC(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
  int result = SYSINFO_RET_FAIL;
  DISK_DATA *p;

  p = get_disk_data_record(device);

  if (p)
    {
       hrtime_t crtime, snaptime;
       kstat_io_t kio;

       result = get_disk_kstat_record(device, &crtime, &snaptime, &kio);

       if (result == SYSINFO_RET_OK)
          {
             unsigned long ios;

             ios = (kio.reads - p->svc.reads) + (kio.writes - p->svc.writes);

             if (ios > 0)
	        *value = ((kio.rtime - p->svc.rtime)/ios)/1000000.0;

             else
   		*value = 0.0;

             p->svc.writes = kio.writes;
             p->svc.reads = kio.reads;
             p->svc.rtime = kio.rtime;
          }
    }

   return result;
}
#endif

#if OFF
/*
 * hidden
 */
int	DISKBUSY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
  int result = SYSINFO_RET_FAIL;
  DISK_DATA *p;

  p = get_disk_data_record(device);

  if (p)
    {
       hrtime_t crtime, snaptime;
       kstat_io_t kio;

       result = get_disk_kstat_record(device, &crtime, &snaptime, &kio);

       if (result == SYSINFO_RET_OK)
          {
             if (snaptime > p->busy.clock)
                *value = ((kio.rtime - p->busy.rtime) * 100.0) / (snaptime - p->busy.clock);

   	     else 
               *value = 0.0;

             p->busy.clock = snaptime;

             p->busy.rtime = kio.rtime;
          }
    }

   return result;
}
#endif

static int get_kstat_io(
    const char *name,
    kstat_io_t *returned_data
    )
{
    int result = SYSINFO_RET_FAIL;
    kstat_ctl_t *kc;
    kstat_t *kt;

    kc = kstat_open();
    if (kc)
    {
	kt = kstat_lookup(kc, NULL, -1, (char *) name);
	if (kt)
	{
	    if (kt->ks_type == KSTAT_TYPE_IO)
	    {	
		if(kstat_read(kc, kt, returned_data) != -1)
		{
		    result = SYSINFO_RET_OK;
		}
            }
        }
	kstat_close(kc);
    }
    return result;
}

static int	VFS_DEV_READ_BYTES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_io_t kio;
    int	ret;

    ret = get_kstat_io(param, &kio);

    if(ret == SYSINFO_RET_OK)
    {
	/* u_longlong_t nread;	number of bytes read */
	SET_UI64_RESULT(result, kio.nread);
    }

    return ret;
}

static int	VFS_DEV_READ_OPERATIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_io_t kio;
    int	ret;

    ret = get_kstat_io(param, &kio);

    if(ret == SYSINFO_RET_OK)
    {
	/* uint_t reads;    number of read operations */
	SET_UI64_RESULT(result, kio.reads);
    }

    return ret;
}

static int	VFS_DEV_WRITE_BYTES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_io_t kio;
    int	ret;

    ret = get_kstat_io(param, &kio);

    if(ret == SYSINFO_RET_OK)
    {
	/* u_longlong_t nwritten;   number of bytes written */
	SET_UI64_RESULT(result, kio.nwritten);
    }

    return ret;
}

static int	VFS_DEV_WRITE_OPERATIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_io_t kio;
    int	ret;

    ret = get_kstat_io(param, &kio);

    if(ret == SYSINFO_RET_OK)
    {
	/* uint_t   writes;    number of write operations */
	SET_UI64_RESULT(result, kio.writes);
    }

    return ret;
}

int	VFS_DEV_WRITE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{

#define DEV_FNCLIST struct dev_fnclist_s
DEV_FNCLIST
{
	char *mode;
	int (*function)();
};

	DEV_FNCLIST fl[] = 
	{
		{"bytes", 	VFS_DEV_WRITE_BYTES},
		{"operations", 	VFS_DEV_WRITE_OPERATIONS},
		{0,		0}
	};

	char devname[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;
	
        assert(result);

        init_result(result);
	
        if(num_param(param) > 2)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, devname, sizeof(mode)) != 0)
        {
                return SYSINFO_RET_FAIL;
        }
	
	if(get_param(param, 2, mode, sizeof(mofe)) != 0)
        {
                mode[0] = '\0';
        }
        if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "bytes");
	}
	
	for(i=0; fl[i].mode!=0; i++)
	{
		if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
		{
			return (fl[i].function)(cmd, devname, flags, result);
		}
	}
	
	return SYSINFO_RET_FAIL;
}

int	VFS_DEV_READ(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{

#define DEV_FNCLIST struct dev_fnclist_s
DEV_FNCLIST
{
	char *mode;
	int (*function)();
};

	DEV_FNCLIST fl[] = 
	{
		{"bytes",	VFS_DEV_READ_BYTES},
		{"operations",	VFS_DEV_READ_OPERATIONS},
		{0,		0}
	};

	char devname[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;
	
        assert(result);

        init_result(result);
	
        if(num_param(param) > 2)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, devname, sizeof(devname)) != 0)
        {
                return SYSINFO_RET_FAIL;
        }
	
	if(get_param(param, 2, mode, sizeof(mode)) != 0)
        {
                mode[0] = '\0';
        }
        if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "bytes");
	}
	for(i=0; fl[i].mode!=0; i++)
	{
		if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
		{
			return (fl[i].function)(cmd, devname, flags, result);
		}
	}
	return SYSINFO_RET_FAIL;
}

int	OLD_IO(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	assert(result);

        init_result(result);

        return SYSINFO_RET_FAIL;
}

