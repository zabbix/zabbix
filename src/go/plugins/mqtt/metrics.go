/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

package mqtt

import (
	"git.zabbix.com/ap/plugin-support/errs"
	"git.zabbix.com/ap/plugin-support/metric"
	"git.zabbix.com/ap/plugin-support/plugin"
	"git.zabbix.com/ap/plugin-support/uri"
	"zabbix.com/pkg/watch"
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
