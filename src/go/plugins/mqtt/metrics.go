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

package mqtt

import (
	"golang.zabbix.com/agent2/pkg/watch"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/uri"
)

const (
	keyGet = "mqtt.get"
)

var uriDefaults = &uri.Defaults{Scheme: "tcp", Port: "1883"}

var (
	paramURI = metric.NewConnParam("URL", "URL to connect or session name.").
			WithDefault(uriDefaults.Scheme + "://localhost:" + uriDefaults.Port).WithSession().
			WithValidator(uri.URIValidator{Defaults: uriDefaults, AllowedSchemes: []string{"tcp", "tls", "ws"}})
	paramTopic       = metric.NewConnParam("Topic", "MQTT topic.")
	paramUsername    = metric.NewConnParam("User", "MQTT user.").WithDefault("")
	paramPassword    = metric.NewConnParam("Password", "User's password.").WithDefault("")
	paramTLSCaFile   = metric.NewSessionOnlyParam("TLSCAFile", "TLS ca file path.").WithDefault("")
	paramTLSCertFile = metric.NewSessionOnlyParam("TLSCertFile", "TLS cert file path.").WithDefault("")
	paramTLSKeyFile  = metric.NewSessionOnlyParam("TLSKeyFile", "TLS key file path.").WithDefault("")
)

var metrics = metric.MetricSet{
	keyGet: metric.New(
		"Subscribe to MQTT topics for published messages.",
		[]*metric.Param{
			paramURI, paramTopic, paramUsername, paramPassword, paramTLSCaFile, paramTLSCertFile, paramTLSKeyFile,
		},
		false,
	),
}

func init() {
	impl.manager = watch.NewManager(&impl)
	impl.mqttClients = make(map[broker]*mqttClient)

	err := plugin.RegisterMetrics(&impl, pluginName, metrics.List()...)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}
