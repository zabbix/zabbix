/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

package main

import (
	"errors"
	"flag"
	"fmt"
	"os"
	"path/filepath"
	"time"

	"golang.org/x/sys/windows/svc"
	"golang.org/x/sys/windows/svc/eventlog"
	"golang.org/x/sys/windows/svc/mgr"
	"zabbix.com/internal/agent"
	"zabbix.com/internal/agent/keyaccess"
	"zabbix.com/internal/agent/scheduler"
	"zabbix.com/internal/monitor"
	"zabbix.com/pkg/log"
)

var (
	serviceName = "Zabbix Agent 2"

	svcInstallFlag       bool
	svcUninstallFlag     bool
	svcStartFlag         bool
	svcStopFlag          bool
	svcMultipleAgentFlag bool

	isInteractive bool

	eLog *eventlog.Log
)

func loadAdditionalFlags() {
	const (
		svcInstallDefault     = false
		svcInstallDescription = "Install Zabbix agent 2 as service"
	)
	flag.BoolVar(&svcInstallFlag, "install", svcInstallDefault, svcInstallDescription)
	flag.BoolVar(&svcInstallFlag, "i", svcInstallDefault, svcInstallDescription+" (shorthand)")

	const (
		svcUninstallDefault     = false
		svcUninstallDescription = "Uninstall Zabbix agent 2 from service"
	)
	flag.BoolVar(&svcUninstallFlag, "uninstall", svcUninstallDefault, svcUninstallDescription)
	flag.BoolVar(&svcUninstallFlag, "d", svcUninstallDefault, svcUninstallDescription+" (shorthand)")

	const (
		svcStartDefault     = false
		svcStartDescription = "Start Zabbix agent 2 service"
	)
	flag.BoolVar(&svcStartFlag, "start", svcStartDefault, svcStartDescription)
	flag.BoolVar(&svcStartFlag, "s", svcStartDefault, svcStartDescription+" (shorthand)")

	const (
		svcStopDefault     = false
		svcStopDescription = "Stop Zabbix agent 2 service"
	)
	flag.BoolVar(&svcStopFlag, "stop", svcStopDefault, svcStopDescription)
	flag.BoolVar(&svcStopFlag, "x", svcStopDefault, svcStopDescription+" (shorthand)")

	const (
		svcMultipleDefault     = false
		svcMultipleDescription = "For -i -d -s -x functions service name will\ninclude Hostname parameter specified in\nconfiguration file"
	)
	flag.BoolVar(&svcMultipleAgentFlag, "multiple-agents", svcMultipleDefault, svcMultipleDescription)
	flag.BoolVar(&svcMultipleAgentFlag, "m", svcMultipleDefault, svcMultipleDescription+" (shorthand)")
}

func isWinService() bool {
	if svcInstallFlag || svcUninstallFlag || svcStartFlag || svcStopFlag || svcMultipleAgentFlag {
		return true
	}
	return false
}

func openEventLog() (err error) {
	if isWinService() {
		eLog, err = eventlog.Open(serviceName)
	}
	return
}

func eventLogInfo(msg string) (err error) {
	if isWinService() {
		return eLog.Info(1, msg)
	}
	return nil
}

func eventLogErr(err error) error {
	if isWinService() {
		return eLog.Error(3, err.Error())
	}
	return nil
}

func validateExclusiveFlags() error {
	/* check for mutually exclusive options */
	/* Allowed option combinations.		*/
	/* Option 'c' is always optional.	*/
	/*   p  t  v  i  d  s  x  m    	*/
	/* ------------------------    	*/
	/*   p  -  -  -  -  -  -  - 	*/
	/*   -  t  -  -  -  -  -  -		*/
	/*   -  -  v  -  -  -  -  -		*/
	/*   -  -  -  i  -  -  -  -		*/
	/*   -  -  -  -  d  -  -  -		*/
	/*   -  -  -  -  -  s  -  -		*/
	/*   -  -  -  -  -  -  x  -		*/
	/*   -  -  -  i  -  -  -  m		*/
	/*   -  -  -  -  d  -  -  m		*/
	/*   -  -  -  -  -  s  -  m		*/
	/*   -  -  -  -  -  -  x  m		*/
	/*   -  -  -  -  -  -  -  m	 special case required for starting as a service with '-m' option */
	defaultFlagSet := argTest || argPrint || argVerbose
	serviceFlagsSet := []bool{svcInstallFlag, svcUninstallFlag, svcStartFlag, svcStopFlag}
	var count int
	for _, serserviceFlagSet := range serviceFlagsSet {
		if serserviceFlagSet {
			count++
		}
		if count >= 2 || (serserviceFlagSet && defaultFlagSet) {
			return errors.New("mutually exclusive options used, use help '-help'('-h'), for additional information")
		}
	}

	if svcMultipleAgentFlag && count == 0 {
		return errors.New("multiple agents '-multiple-agents'('-m'), flag has to be used with another windows service flag, use help '-help'('-h'), for additional information")
	}

	return nil
}

func isInteractiveSession() (bool, error) {
	return svc.IsAnInteractiveSession()
}

func setHostname() error {
	if err := log.Open(log.Console, log.None, "", 0); err != nil {
		return fmt.Errorf("cannot initialize logger: %s", err)
	}

	if err := keyaccess.LoadRules(agent.Options.AllowKey, agent.Options.DenyKey); err != nil {
		return fmt.Errorf("failed to load key access rules: %s", err)
	}

	var m *scheduler.Manager
	var err error
	if m, err = scheduler.NewManager(&agent.Options); err != nil {
		return fmt.Errorf("cannot create scheduling manager: %s", err)
	}
	m.Start()
	if err = configUpdateItemParameters(m, &agent.Options); err != nil {
		return fmt.Errorf("cannot process configuration: %s", err)
	}
	m.Stop()
	monitor.Wait(monitor.Scheduler)
	return nil
}

func handleWindowsService(conf string) error {
	var err error
	if svcInstallFlag || svcUninstallFlag || svcStartFlag || svcStopFlag {
		err = resolveWindowsService(conf)
		if err != nil {
			return err
		}

		os.Exit(0)
	}

	isInteractive, err = isInteractiveSession()
	if err != nil {
		return fmt.Errorf("can not determine if is interactive session: %s", err)
	}

	if !isInteractive {
		go runService()
	}

	return nil
}

func resolveWindowsService(conf string) error {
	if svcMultipleAgentFlag {
		if len(agent.Options.Hostname) == 0 {
			err := setHostname()
			if err != nil {
				return err
			}
		}
		serviceName = fmt.Sprintf("%s [%s]", serviceName, agent.Options.Hostname)
	}

	var msg string
	switch true {
	case svcInstallFlag:
		if err := svcInstall(conf); err != nil {
			return fmt.Errorf("failed to install %s as service: %s", serviceName, err)
		}
		msg = fmt.Sprintf("'%s' installed succesfully", serviceName)
	case svcUninstallFlag:
		if err := svcUninstall(); err != nil {
			return fmt.Errorf("failed to uninstall %s as service: %s", serviceName, err)
		}
		msg = fmt.Sprintf("'%s' uninstalled succesfully", serviceName)
	case svcStartFlag:
		if err := svcStart(conf); err != nil {
			return fmt.Errorf("failed to start %s service: %s", serviceName, err)
		}
		msg = fmt.Sprintf("'%s' started succesfully", serviceName)
	case svcStopFlag:
		if err := svcStop(); err != nil {
			return fmt.Errorf("failed to stop %s service: %s", serviceName, err)
		}
		msg = fmt.Sprintf("'%s' stopped succesfully", serviceName)
	}

	msg = fmt.Sprintf("zabbix_agent2 [%d]: %s\n", os.Getpid(), msg)
	fmt.Fprintf(os.Stdout, msg)
	if err := eventLogInfo(msg); err != nil {
		return fmt.Errorf("failed to log to event log: %s", err)
	}
	return nil
}

func getAgentPath() (p string, err error) {
	p, err = filepath.Abs(os.Args[0])
	if err != nil {
		return
	}

	var i os.FileInfo
	i, err = os.Stat(p)
	if err != nil {
		if filepath.Ext(p) == "" {
			p += ".exe"
			i, err = os.Stat(p)
			if err != nil {
				return
			}
		}
	}

	if i.Mode().IsDir() {
		return p, fmt.Errorf("incorrect path to executable '%s'", p)

	}

	return
}

func svcInstall(conf string) error {
	exepath, err := getAgentPath()
	if err != nil {
		return fmt.Errorf("failed to get Zabbix Agent 2 exeutable path: %s", err.Error())
	}

	m, err := mgr.Connect()
	if err != nil {
		return fmt.Errorf("failed to connect to service manager: %s", err.Error())
	}
	defer m.Disconnect()

	s, err := m.OpenService(serviceName)
	if err == nil {
		s.Close()
		return errors.New("service already exists")
	}

	s, err = m.CreateService(serviceName, exepath, mgr.Config{StartType: mgr.StartAutomatic, DisplayName: serviceName,
		Description: "Provides system monitoring", BinaryPathName: fmt.Sprintf("%s -c %s", exepath, conf)}, "-c", conf)
	if err != nil {
		return fmt.Errorf("failed to create service: %s", err.Error())
	}
	defer s.Close()
	err = eventlog.InstallAsEventCreate(serviceName, eventlog.Error|eventlog.Warning|eventlog.Info)
	if err != nil {
		err = fmt.Errorf("failed to report service into the event log: %s", err.Error())
		derr := s.Delete()
		if derr != nil {
			return fmt.Errorf("%s and %s", err.Error(), derr.Error())
		}

		return err
	}
	return nil
}

func svcUninstall() error {
	m, err := mgr.Connect()
	if err != nil {
		return fmt.Errorf("failed to connect to service manager: %s", err.Error())
	}
	defer m.Disconnect()
	s, err := m.OpenService(serviceName)
	if err != nil {
		return fmt.Errorf("failed to open service: %s", err.Error())
	}
	defer s.Close()
	err = s.Delete()
	if err != nil {
		return fmt.Errorf("failed to delete service: %s", err.Error())
	}

	err = eventlog.Remove(serviceName)
	if err != nil {
		return fmt.Errorf("failed to remove service from the event log: %s", err.Error())
	}

	return nil
}

func svcStart(conf string) error {
	m, err := mgr.Connect()
	if err != nil {
		return fmt.Errorf("failed to connect to service manager: %s", err.Error())
	}
	defer m.Disconnect()
	s, err := m.OpenService(serviceName)
	if err != nil {
		return fmt.Errorf("failed to open service: %s", err.Error())
	}
	defer s.Close()

	err = s.Start("-c", conf)
	if err != nil {
		return fmt.Errorf("failed to start service: %s", err.Error())
	}

	return nil
}

func svcStop() error {
	m, err := mgr.Connect()
	if err != nil {
		return fmt.Errorf("failed to connect to service manager: %s", err.Error())
	}
	defer m.Disconnect()
	s, err := m.OpenService(serviceName)
	if err != nil {
		return fmt.Errorf("failed to open service: %s", err.Error())
	}
	defer s.Close()
	status, err := s.Control(svc.Stop)
	if err != nil {
		return fmt.Errorf("failed to send stop request to service: %s", err.Error())
	}
	timeout := time.Now().Add(10 * time.Minute)
	for status.State != svc.Stopped {
		if timeout.Before(time.Now()) {
			return fmt.Errorf("failed to stop '%s' service", serviceName)
		}
		time.Sleep(300 * time.Millisecond)
		status, err = s.Query()
		if err != nil {
			return fmt.Errorf("failed to get service status: %s", err.Error())
		}
	}

	return nil
}

func closeWinService() {
	if !isInteractive {
		closeWg.Done()
	}
}

func runService() {
	err := svc.Run(serviceName, &winService{})
	if err != nil {
		fatalExit("", err)
	}
}

type winService struct{}

func (ws *winService) Execute(args []string, r <-chan svc.ChangeRequest, changes chan<- svc.Status) (ssec bool, errno uint32) {
	changes <- svc.Status{State: svc.StartPending}
	changes <- svc.Status{State: svc.Running, Accepts: svc.AcceptStop}
loop:
	for {
		c := <-r
		switch c.Cmd {
		case svc.Stop:
			closeWg.Add(1)
			break loop
		default:
			log.Warningf("unsupported windows service command recieved")
		}
	}

	closeChan <- true
	closeWg.Wait()
	changes <- svc.Status{State: svc.StopPending}

	return
}
