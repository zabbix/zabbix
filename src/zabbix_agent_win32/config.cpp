/* 
** ZabbixW32 - Win32 agent for Zabbix
** Copyright (C) 2002 Victor Kirhenshtein
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
**
** $module: config.cpp
**
**/

#include "zabbixw32.h"


//
// Parse PerfCounter=... parameter and add new performance counter
// Argument is a config file parameter value which should have the following syntax:
//    <key>,"<counter path>",<time interval>
// Returns TRUE on success and FALSE otherwise
//

static BOOL AddPerformanceCounter(char *args)
{
   char *ptr1,*ptr2,*eptr,buffer[MAX_ALIAS_NAME];
   USER_COUNTER *counter;
   int i;

   ptr1=strchr(args,',');
   if (ptr1==NULL)
      return FALSE;     // Invalid syntax

   *ptr1=0;
   ptr1++;
   StrStrip(args);
   StrStrip(ptr1);
   if (*ptr1!='"')
      return FALSE;     // Invalid syntax
   ptr1++;
   ptr2=strchr(ptr1,'"');
   if (ptr2==NULL)
      return FALSE;     // Invalid syntax
   *ptr2=0;
   ptr2++;
   StrStrip(ptr2);
   if (*ptr2!=',')
      return FALSE;     // Invalid syntax
   ptr2++;
   StrStrip(ptr2);

   i=strtol(ptr2,&eptr,10);
   if ((*eptr!=0)||     // Not a decimal number
       (i<1)||(i>1800)) // Interval value out of range
      return FALSE;     // Invalid syntax

   // Add internal alias
   sprintf(buffer,"__usercnt{%s}",args);
   if (!AddAlias(args,buffer))
      return FALSE;

   counter=(USER_COUNTER *)malloc(sizeof(USER_COUNTER));
   memset(counter,0,sizeof(USER_COUNTER));

   strncpy(counter->name,args,MAX_COUNTER_NAME-1);
   strncpy(counter->counterPath,ptr1,MAX_PATH-1);
   counter->interval=i;
   counter->rawValueArray=(PDH_RAW_COUNTER *)malloc(sizeof(PDH_RAW_COUNTER)*counter->interval);

   // Add to the list
   counter->next=userCounterList;
   userCounterList=counter;
   return TRUE;
}


//
// Read configuration
//

BOOL ReadConfig(void)
{
   FILE *cfg;
   char *ptr,buffer[4096];
   int sourceLine=0,errors=0;

   if (optStandalone)
      printf("Using configuration file \"%s\"\n",confFile);

   cfg=fopen(confFile,"r");
   if (cfg==NULL)
   {
      if (optStandalone)
         printf("Unable to open configuration file: %s\n",strerror(errno));
      return FALSE;
   }

   while(!feof(cfg))
   {
      buffer[0]=0;
      fgets(buffer,4095,cfg);
      sourceLine++;
      ptr=strchr(buffer,'\n');
      if (ptr!=NULL)
         *ptr=0;
      ptr=strchr(buffer,'#');
      if (ptr!=NULL)
         *ptr=0;

      StrStrip(buffer);
      if (buffer[0]==0)
         continue;

      ptr=strchr(buffer,'=');
      if (ptr==NULL)
      {
         errors++;
         if (optStandalone)
            printf("Syntax error in configuration file, line %d\n",sourceLine);
         continue;
      }
      *ptr=0;
      ptr++;
      StrStrip(buffer);
      StrStrip(ptr);

      if (!stricmp(buffer,"LogFile"))
      {
         memset(logFile,0,MAX_PATH);
         strncpy(logFile,ptr,MAX_PATH-1);
      }
      else if (!stricmp(buffer,"Server"))
      {
         confServerAddr=inet_addr(ptr);
         if (confServerAddr==INADDR_NONE)
         {
            errors++;
            if (optStandalone)  
               printf("Error in configuration file, line %d: invalid server's IP address (%s)\n",sourceLine,ptr);
         }
      }
      else if (!stricmp(buffer,"ListenPort"))
      {
         int n;

         n=atoi(ptr);
         if ((n<1)||(n>65535))
         {
            confListenPort=10000;
            errors++;
            if (optStandalone)
               printf("Error in configuration file, line %d: invalid port number (%s)\n",sourceLine,ptr);
         }
         else
         {
            confListenPort=(WORD)n;
         }
      }
      else if (!stricmp(buffer,"Alias"))
      {
         char *sep;

         sep=strchr(ptr,':');
         if (sep==NULL)
         {
            errors++;
            if (optStandalone)
               printf("Error in configuration file, line %d: invalid alias syntax\n",sourceLine);
         }
         else
         {
            *sep=0;
            sep++;
            StrStrip(ptr);
            StrStrip(sep);
            AddAlias(ptr,sep);
         }
      }
      else if (!stricmp(buffer,"Timeout"))
      {
         int tm;

         tm=atoi(ptr);
         if ((tm>0)&&(tm<=30))
         {
            confTimeout=tm*1000;    // Convert to milliseconds
         }
         else
         {
            errors++;
            if (optStandalone)
               printf("Error in configuration file, line %d: invalid timeout value (%d seconds)\n",
                      sourceLine,tm);
         }
      }
      else if (!stricmp(buffer,"PerfCounter"))
      {
         if (!AddPerformanceCounter(ptr))
         {
            errors++;
            if (optStandalone)
               printf("Error in configuration file, line %d: invalid performance counter specification\n",
                      sourceLine);
         }
      }
      else if ((!stricmp(buffer,"PidFile"))||(!stricmp(buffer,"NoTimeWait"))||
               (!stricmp(buffer,"StartAgents"))||(!stricmp(buffer,"DebugLevel")))
      {
         // Ignore these parameters, they are for compatibility with UNIX agent only
      }
      else
      {
         errors++;
         if (optStandalone)
            printf("Error in configuration file, line %d: unknown option \"%s\"\n",sourceLine,buffer);
      }
   }

   if ((optStandalone)&&(!errors))
      printf("Configuration file OK\n");

   fclose(cfg);
   return TRUE;
}
