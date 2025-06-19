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

package statuslistener

import (
	"context"
	"fmt"
	"net"
	"net/http"
	"strconv"
	"strings"
	"time"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/internal/agent/scheduler"
	"golang.zabbix.com/agent2/internal/monitor"
	"golang.zabbix.com/agent2/pkg/version"
	"golang.zabbix.com/sdk/log"
)

var srv http.Server

func getConf(confFilePath string) string {
	var listenPort string
	if agent.Options.Server != "" {
		listenPort = strconv.Itoa(agent.Options.ListenPort)
	}

	var sb strings.Builder

	sb.WriteString(fmt.Sprintf("Zabbix Agent 2 [%s]. (%s)\n", agent.Options.Hostname, version.Long()))
	sb.WriteString(fmt.Sprintf("using configuration file: %s\n", confFilePath))
	sb.WriteString(fmt.Sprintf("ServerActive: %s\n", agent.Options.ServerActive))
	sb.WriteString(fmt.Sprintf("ListenPort: %s\n", listenPort))
	sb.WriteString("\n")

	return sb.String()
}

func Start(taskManager scheduler.Scheduler, confFilePath string) (err error) {
	var l net.Listener

	if l, err = net.Listen("tcp", fmt.Sprintf(":%d", agent.Options.StatusPort)); err != nil {
		return err
	}

	log.Debugf("starting status listener")

	mux := http.NewServeMux()
	mux.Handle("/status", http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		log.Debugf("received status request from %s", r.RemoteAddr)
		_, _ = w.Write([]byte(getConf(confFilePath)))
		_, _ = w.Write([]byte(taskManager.Query("metrics")))
	}))

	srv = http.Server{Addr: fmt.Sprintf(":%d", agent.Options.StatusPort), Handler: mux}
	go func() {
		defer log.PanicHook()
		err = srv.Serve(l)
		log.Debugf("%s", err.Error())
	}()

	monitor.Register(monitor.Input)
	return nil
}

func Stop() {
	// shut down gracefully, but wait no longer than time defined in configuration parameter Timeout
	ctx, cancel := context.WithTimeout(context.Background(), time.Second*time.Duration(agent.Options.Timeout))
	defer cancel()

	if err := srv.Shutdown(ctx); err != nil {
		log.Errf("cannot gacefully stop status listener: %s", err.Error())
	} else {
		log.Debugf("status listener has been stopped")
	}

	monitor.Unregister(monitor.Input)
}
