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
)

const serviceName = "Zabbix Agent 2"

var (
	svcInstallFlag   bool
	svcUninstallFlag bool
	svcStartFlag     bool
	svcStopFlag      bool
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
}

func validateExclusiveFlags() error {
	return nil
}

func isInteractive() (bool, error) {
	return svc.IsAnInteractiveSession()
}

func handleWindowsService(conf string) error {
	switch true {
	case svcInstallFlag:
		if err := svcInstall(conf); err != nil {
			return fmt.Errorf("failed to install %s as service: %s", serviceName, err)
		}
		fmt.Printf("service '%s' installed succesfully\n", serviceName)
	case svcUninstallFlag:
		if err := svcUninstall(); err != nil {
			return fmt.Errorf("failed to uninstall %s as service: %s", serviceName, err)
		}
		fmt.Printf("service '%s' uninstalled succesfully\n", serviceName)
	case svcStartFlag:
		if err := svcStart(conf); err != nil {
			return fmt.Errorf("failed to start %s service: %s", serviceName, err)
		}
		fmt.Printf("service '%s' started succesfully\n", serviceName)
	case svcStopFlag:
		if err := svcStop(); err != nil {
			return fmt.Errorf("failed to stop %s service: %s", serviceName, err)
		}
		fmt.Printf("service '%s' stopped succesfully\n", serviceName)
	}
	return nil
}

//TODO: add unit test
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

//TODO: maybe grupe connect and open service into a single func, so they are not coded multiple times
func svcInstall(conf string) error {
	exepath, err := getAgentPath()
	if err != nil {
		return err
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
		//TODO: manage error
		s.Delete()
		return fmt.Errorf("failed to report service into the event log: %s", err.Error())
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
	closeWg.Done()
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
			//TODO log to eventlog
		}
	}

	closeChan <- true
	closeWg.Wait()
	changes <- svc.Status{State: svc.StopPending}

	return
}
