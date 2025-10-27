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

/*
** We use the library Eclipse Paho (eclipse/paho.mqtt.golang), which is
** distributed under the terms of the Eclipse Distribution License 1.0 (The 3-Clause BSD License)
** available at https://www.eclipse.org/org/documents/edl-v10.php
**/

package mqtt

import (
	"crypto/tls"
	"encoding/json"
	"fmt"
	"math/rand"
	"net/url"
	"strings"
	"sync"
	"time"

	mqtt "github.com/eclipse/paho.mqtt.golang"
	"golang.zabbix.com/agent2/pkg/watch"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/plugin/itemutil"
	"golang.zabbix.com/sdk/tlsconfig"
	"golang.zabbix.com/sdk/zbxerr"
	"golang.zabbix.com/sdk/zbxsync"
)

const (
	pluginName = "MQTT"
)

const (
	// The backoff goroutine is not running.
	stateIdle backoffState = iota
	// The backoff goroutine is active and trying to connect.
	stateRetrying
	// The Release method has been called, and the goroutine is shutting down.
	stateStopping
)

var _ plugin.Watcher = (*Plugin)(nil)
var _ watch.EventProvider = (*Plugin)(nil)

var _ watch.EventSource = (*mqttSub)(nil)
var _ watch.EventFilter = (*respFilter)(nil)

var impl Plugin //nolint:gochecknoglobals // legacy implementation

// Plugin inherits plugin.Base and store MQTT plugin-specific data and methods.
type Plugin struct {
	plugin.Base

	options     options
	manager     *watch.Manager
	mqttClients zbxsync.SyncMap[broker, *mqttClient]
}

type mqttClient struct {
	client    mqtt.Client
	broker    broker
	subs      map[string]*mqttSub
	opts      *mqtt.ClientOptions
	connected bool
}

type mqttSub struct {
	broker   broker
	topic    string
	wildCard bool

	stateLock    sync.Mutex
	backoffState backoffState
	stopCh       chan struct{}
}

type broker struct {
	url      string
	username string
	password string
}

type respFilter struct {
	wildcard bool
}

type backoffState int

// Watch implements plugin.Watcher interface.
func (p *Plugin) Watch(items []*plugin.Item, ctx plugin.ContextProvider) {
	p.manager.Lock()
	p.manager.Update(ctx.ClientID(), ctx.Output(), items)
	p.manager.Unlock()
}

// EventSourceByKey implements watch.EventProvider interface.
//
//nolint:ireturn // returning interface is a part of implementation requirements.
func (p *Plugin) EventSourceByKey(rawKey string) (watch.EventSource, error) {
	key, raw, err := itemutil.ParseKey(rawKey)
	if err != nil {
		return nil, errs.Wrap(err, "cannot parse key")
	}

	params, _, hc, err := metrics[key].EvalParams(raw, p.options.Sessions)
	if err != nil {
		return nil, errs.Wrap(err, "cannot evaluate params")
	}

	err = metric.SetDefaults(params, hc, p.options.Default)
	if err != nil {
		return nil, errs.Wrap(err, "cannot set defaults")
	}

	topic := params["Topic"]
	username := params["User"]
	password := params["Password"]

	u, err := parseURL(params["URL"])
	if err != nil {
		return nil, err
	}

	if topic == "" {
		return nil, errs.WrapConst(errs.New("second parameter \"Topic\" is required"), zbxerr.ErrorTooFewParameters)
	}

	var (
		client *mqttClient
		ok     bool
		b      = broker{
			url:      u.String(),
			username: username,
			password: password,
		}
	)

	opt, err := p.createOptions(
		getClientID(rand.NewSource(time.Now().UnixNano())),
		username,
		password,
		b,
		&tlsconfig.Details{
			TLSCaFile:   params["TLSCAFile"],
			TLSCertFile: params["TLSCertFile"],
			TLSKeyFile:  params["TLSKeyFile"],
			RawURI:      u.String(),
		},
	)
	if err != nil {
		return nil, err
	}

	client, ok = p.mqttClients.Load(b)
	if !ok {
		impl.Debugf("creating client for [%s]", b.url)
		client = &mqttClient{
			client:    nil,
			broker:    b,
			subs:      make(map[string]*mqttSub),
			opts:      opt,
			connected: false,
		}
		p.mqttClients.Store(b, client)
	}

	sub, ok := client.subs[topic]
	if !ok {
		impl.Debugf("creating new subscriber on topic '%s' for [%s]", topic, b.url)

		sub = &mqttSub{
			broker:   b,
			topic:    topic,
			wildCard: hasWildCards(topic),
		}
		client.subs[topic] = sub
	}

	return sub, nil
}

// Initialize implements watch.EventSource interface methods.
func (ms *mqttSub) Initialize() error {
	mc, ok := impl.mqttClients.Load(ms.broker)
	if !ok || mc == nil {
		return errs.New(fmt.Sprintf("Cannot connect to [%s]: broker could not be initialized", ms.broker.url))
	}

	if mc.client == nil {
		var err error

		impl.Debugf("establishing connection to [%s]", ms.broker.url)

		mc.client, err = newClient(mc.opts)
		if err != nil {
			impl.Warningf("cannot establish connection to [%s]: %s", ms.broker.url, err)

			ms.stateLock.Lock()
			// Only start a new backoff goroutine if we are in the idle state.
			if ms.backoffState == stateIdle {
				ms.backoffState = stateRetrying
				ms.stopCh = make(chan struct{})
				// Pass the channel as an argument to avoid a data race on ms.stopCh
				go ms.startAsyncEstablishingConnectionBackoff()
			}

			ms.stateLock.Unlock()

			// the backoff system will try to make connection so the manager system would know that connection is
			//  being created
			return nil
		}

		impl.Debugf("established connection to [%s]", ms.broker.url)

		return nil
	}

	if mc.connected {
		return ms.subscribe(mc)
	}

	return nil
}

// Release implements watch.EventSource interface methods.
func (ms *mqttSub) Release() {
	ms.stateLock.Lock()
	// Only close the channel if the goroutine is currently running.
	if ms.backoffState == stateRetrying {
		ms.backoffState = stateStopping
		close(ms.stopCh)
	}

	ms.stateLock.Unlock()

	mc, ok := impl.mqttClients.Load(ms.broker)
	if !ok || mc == nil {
		impl.Errf("cannot release [%s]: broker was not initialized", ms.broker.url)

		return
	}

	if mc.client != nil && !mc.client.IsConnected() {
		impl.Tracef("unsubscribing topic '%s' from [%s]", ms.topic, ms.broker.url)

		token := mc.client.Unsubscribe(ms.topic)
		if !token.WaitTimeout(time.Duration(impl.options.Timeout) * time.Second) {
			impl.Errf("cannot unsubscribe topic '%s' from [%s]: timed out", ms.topic, ms.broker.url)
		}

		if token.Error() != nil {
			impl.Errf("cannot unsubscribe topic '%s' from [%s]: %s", ms.topic, ms.broker.url, token.Error())
		}
	}

	delete(mc.subs, ms.topic)
	impl.Tracef("unsubscribed topic '%s' from [%s]", ms.topic, ms.broker.url)

	if len(mc.subs) == 0 {
		impl.Debugf("disconnecting from [%s]", ms.broker.url)

		if mc.client != nil {
			mc.client.Disconnect(200)
		}

		impl.mqttClients.Delete(ms.broker)
	}
}

// NewFilter implements watch.EventSource interface methods.
//
//nolint:ireturn // returning interface is a part of implementation requirements.
func (ms *mqttSub) NewFilter(_ string) (watch.EventFilter, error) {
	return &respFilter{ms.wildCard}, nil
}

// Process implements watch.EventFilter interface methods.
func (f *respFilter) Process(v any) (*string, error) {
	m, ok := v.(mqtt.Message)
	if !ok {
		err, ok := v.(error)
		if !ok {
			err = errs.New(fmt.Sprintf("unexpected input type %T", v))
		}

		return nil, err
	}

	var value string

	if f.wildcard {
		j, err := json.Marshal(map[string]string{m.Topic(): string(m.Payload())})
		if err != nil {
			return nil, errs.Wrap(err, "cannot marshal filter response")
		}

		value = string(j)
	} else {
		value = string(m.Payload())
	}

	return &value, nil
}

func (ms *mqttSub) startAsyncEstablishingConnectionBackoff() {
	const (
		baseBackoff = 1 * time.Second
		maxBackoff  = 10 * time.Minute
		factor      = 2
	)

	//nolint:gosec // This use of math/rand is not for a security-sensitive context.
	wait := baseBackoff + time.Duration(rand.Intn(1000))*time.Millisecond
	ticker := time.NewTicker(wait)

	go func() {
		defer ticker.Stop()

		for {
			select {
			case <-ms.stopCh:
				// Exit signal received from Release.
				return
			case <-ticker.C:
				// This check ensures the routine stops if the parent object is destroyed.
				if ms == nil {
					return
				}

				mc, ok := impl.mqttClients.Load(ms.broker)
				if !ok || mc == nil {
					impl.Warningf("finished attempting to establish connection to [%s]", ms.broker.url)

					return
				}

				if mc.connected {
					return
				}

				var err error

				mc.client, err = newClient(mc.opts)
				if err != nil {
					impl.Debugf("cannot establish connection to [%s]: %s", ms.broker.url, err)

					wait *= factor
					if wait > maxBackoff {
						wait = maxBackoff
					}

					// Reset the ticker with the new, longer backoff duration plus jitter.
					//nolint:gosec // This use of math/rand is not for a security-sensitive context.
					newDuration := wait + time.Duration(rand.Intn(1000))*time.Millisecond
					ticker.Reset(newDuration)

					continue
				}

				ms.stateLock.Lock()
				ms.backoffState = stateIdle
				ms.stopCh = nil
				ms.stateLock.Unlock()

				impl.Debugf("established connection to [%s]", ms.broker.url)

				return
			}
		}
	}()
}

func (p *Plugin) createOptions(
	clientid string,
	username string,
	password string,
	b broker,
	details *tlsconfig.Details,
) (*mqtt.ClientOptions, error) {
	opts := mqtt.NewClientOptions().
		AddBroker(b.url).
		SetClientID(clientid).
		SetCleanSession(true).
		SetConnectTimeout(time.Duration(impl.options.Timeout) * time.Second)

	if username != "" {
		opts.SetUsername(username)

		if password != "" {
			opts.SetPassword(password)
		}
	}

	opts.OnConnectionLost = func(_ mqtt.Client, reason error) {
		p.Warningf("connection lost to [%s]: %s", b.url, reason.Error())
	}

	var connectionEstablished bool

	opts.OnConnect = func(_ mqtt.Client) {
		if !connectionEstablished {
			p.Warningf("connected to [%s]", b.url)

			connectionEstablished = true
		} else {
			p.Warningf("reconnected to [%s]", b.url)
		}

		p.manager.Lock()
		defer p.manager.Unlock()

		mc, ok := p.mqttClients.Load(b)
		if !ok || mc == nil || mc.client == nil {
			p.Warningf("cannot subscribe to [%s]: broker is not connected", b.url)

			return
		}

		mc.connected = true
		for _, ms := range mc.subs {
			err := ms.subscribe(mc)
			if err != nil {
				p.Warningf("cannot subscribe topic '%s' to [%s]: %s", ms.topic, b.url, err)
				p.manager.Notify(ms, err)
			}
		}
	}

	t, err := getTLSConfig(details)
	if err != nil {
		return nil, err
	}

	opts.SetTLSConfig(t)

	return opts, nil
}

//nolint:ireturn // legacy implementation.
func newClient(options *mqtt.ClientOptions) (mqtt.Client, error) {
	c := mqtt.NewClient(options)

	token := c.Connect()
	if !token.WaitTimeout(time.Duration(impl.options.Timeout) * time.Second) {
		c.Disconnect(200)

		return nil, errs.New("timed out while connecting")
	}

	if token.Error() != nil {
		return nil, errs.Wrap(token.Error(), "cannot connect to mqtt")
	}

	return c, nil
}

func getTLSConfig(d *tlsconfig.Details) (*tls.Config, error) {
	if d.TLSCaFile == "" && d.TLSCertFile == "" && d.TLSKeyFile == "" {
		return nil, nil //nolint:nilnil // this case makes sense for such behavior because no tls should be created.
	}

	config, err := tlsconfig.CreateConfig(
		tlsconfig.Details{
			TLSCaFile:   d.TLSCaFile,
			TLSCertFile: d.TLSCertFile,
			TLSKeyFile:  d.TLSKeyFile,
			RawURI:      d.RawURI,
		},
		false,
	)
	if err != nil {
		return nil, errs.Wrap(err, "cannot create tls config")
	}

	return config, nil
}

func (ms *mqttSub) handler(_ mqtt.Client, msg mqtt.Message) {
	go func(message mqtt.Message, sub *mqttSub) {
		impl.manager.Lock()
		defer impl.manager.Unlock()

		impl.Tracef("received publish from [%s] on topic '%s' got: %s",
			ms.broker.url, msg.Topic(),
			string(msg.Payload()),
		)

		impl.manager.Notify(sub, message)
	}(msg, ms)
}

func (ms *mqttSub) subscribe(mc *mqttClient) error {
	impl.Tracef("subscribing '%s' to [%s]", ms.topic, ms.broker.url)

	token := mc.client.Subscribe(ms.topic, 0, ms.handler)
	if !token.WaitTimeout(time.Duration(impl.options.Timeout) * time.Second) {
		return errs.New("timed out while subscribing")
	}

	if token.Error() != nil {
		return errs.Wrap(token.Error(), "cannot subscribe to topic")
	}

	impl.Tracef("subscribed '%s' to [%s]", ms.topic, ms.broker.url)

	return nil
}

func getClientID(src rand.Source) string {
	const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"

	var result = make([]byte, 8)

	//nolint:gosec
	// we are okay with using a weaker random number generator as this is not intended to be a secure token
	r := rand.New(src)

	for i := range result {
		result[i] = charset[r.Intn(len(charset))]
	}

	return "ZabbixAgent2" + string(result)
}

func hasWildCards(topic string) bool {
	return strings.HasSuffix(topic, "#") || strings.Contains(topic, "+")
}

func parseURL(rawURL string) (*url.URL, error) {
	if !strings.Contains(rawURL, "://") {
		rawURL = "tcp://" + rawURL
	}

	out, err := url.Parse(rawURL)
	if err != nil {
		return nil, errs.Wrap(err, "cannot parse URL")
	}

	if out.Port() != "" && out.Hostname() == "" {
		return nil, errs.New("host is required.")
	}

	if out.Port() == "" {
		out.Host += ":1883"
	}

	if len(out.Query()) > 0 {
		return nil, errs.New("URL should not contain query parameters.")
	}

	return out, nil
}
