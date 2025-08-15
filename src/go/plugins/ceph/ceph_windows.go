package ceph

import (
	"crypto/tls"
	"net/http"
	"time"

	"golang.zabbix.com/agent2/plugins/ceph/handlers"
	"golang.zabbix.com/agent2/plugins/ceph/requests"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/uri"
)

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base

	options pluginOptions
	client  *http.Client
}

// Start implements the Runner interface and performs initialization when plugin is activated.
func (p *Plugin) Start() {
	p.client = &http.Client{
		Timeout: time.Duration(p.options.Timeout) * time.Second,
	}

	p.client.Transport = &http.Transport{
		DisableKeepAlives: false,
		IdleConnTimeout:   time.Duration(p.options.KeepAlive) * time.Second,
		TLSClientConfig:   &tls.Config{InsecureSkipVerify: p.options.InsecureSkipVerify}, //nolint:gosec // user defined
	}
}

// Stop implements the Runner interface and frees resources when plugin is deactivated.
func (p *Plugin) Stop() {
	p.client.CloseIdleConnections()
	p.client = nil
}

// handleNativeMode for Windows returns an error.
func (*Plugin) handleNativeMode(_ *uri.URI, _ *handlers.MetricMeta) (<-chan *requests.Response, error) {
	return nil, errs.New("native mode is only supported on linux")
}
