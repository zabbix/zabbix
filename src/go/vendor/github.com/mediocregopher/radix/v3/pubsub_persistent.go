package radix

import (
	"fmt"
	"sync"
	"time"
)

type persistentPubSubOpts struct {
	connFn     ConnFunc
	abortAfter int
}

// PersistentPubSubOpt is an optional parameter which can be passed into
// PersistentPubSub in order to affect its behavior.
type PersistentPubSubOpt func(*persistentPubSubOpts)

// PersistentPubSubConnFunc causes PersistentPubSub to use the given ConnFunc
// when connecting to its destination.
func PersistentPubSubConnFunc(connFn ConnFunc) PersistentPubSubOpt {
	return func(opts *persistentPubSubOpts) {
		opts.connFn = connFn
	}
}

// PersistentPubSubAbortAfter changes PersistentPubSub's reconnect behavior.
// Usually PersistentPubSub will try to reconnect forever upon a disconnect,
// blocking any methods which have been called until reconnect is successful.
//
// When PersistentPubSubAbortAfter is used, it will give up after that many
// attempts and return the error to the method which has been blocked the
// longest. Another method will need to be called in order for PersistentPubSub
// to resume trying to reconnect.
func PersistentPubSubAbortAfter(attempts int) PersistentPubSubOpt {
	return func(opts *persistentPubSubOpts) {
		opts.abortAfter = attempts
	}
}

type persistentPubSub struct {
	dial func() (Conn, error)
	opts persistentPubSubOpts

	l           sync.Mutex
	curr        PubSubConn
	subs, psubs chanSet
	closeCh     chan struct{}
}

// PersistentPubSubWithOpts is like PubSub, but instead of taking in an existing Conn to
// wrap it will create one on the fly. If the connection is ever terminated then
// a new one will be created and will be reset to the previous connection's
// state.
//
// This is effectively a way to have a permanent PubSubConn established which
// supports subscribing/unsubscribing but without the hassle of implementing
// reconnect/re-subscribe logic.
//
// With default options, none of the methods on the returned PubSubConn will
// ever return an error, they will instead block until a connection can be
// successfully reinstated.
//
// PersistentPubSubWithOpts takes in a number of options which can overwrite its
// default behavior. The default options PersistentPubSubWithOpts uses are:
//
//	PersistentPubSubConnFunc(DefaultConnFunc)
//
func PersistentPubSubWithOpts(
	network, addr string, options ...PersistentPubSubOpt,
) (
	PubSubConn, error,
) {
	opts := persistentPubSubOpts{
		connFn: DefaultConnFunc,
	}
	for _, opt := range options {
		opt(&opts)
	}

	p := &persistentPubSub{
		dial:    func() (Conn, error) { return opts.connFn(network, addr) },
		opts:    opts,
		subs:    chanSet{},
		psubs:   chanSet{},
		closeCh: make(chan struct{}),
	}
	err := p.refresh()
	return p, err
}

// PersistentPubSub is deprecated in favor of PersistentPubSubWithOpts instead.
func PersistentPubSub(network, addr string, connFn ConnFunc) PubSubConn {
	var opts []PersistentPubSubOpt
	if connFn != nil {
		opts = append(opts, PersistentPubSubConnFunc(connFn))
	}
	// since PersistentPubSubAbortAfter isn't used, this will never return an
	// error, panic if it does
	p, err := PersistentPubSubWithOpts(network, addr, opts...)
	if err != nil {
		panic(fmt.Sprintf("PersistentPubSubWithOpts impossibly returned an error: %v", err))
	}
	return p
}

func (p *persistentPubSub) refresh() error {
	if p.curr != nil {
		p.curr.Close()
	}

	attempt := func() (PubSubConn, error) {
		c, err := p.dial()
		if err != nil {
			return nil, err
		}
		errCh := make(chan error, 1)
		pc := newPubSub(c, errCh)

		for msgCh, channels := range p.subs.inverse() {
			if err := pc.Subscribe(msgCh, channels...); err != nil {
				pc.Close()
				return nil, err
			}
		}

		for msgCh, patterns := range p.psubs.inverse() {
			if err := pc.PSubscribe(msgCh, patterns...); err != nil {
				pc.Close()
				return nil, err
			}
		}

		go func() {
			select {
			case <-errCh:
				p.l.Lock()
				// It's possible that one of the methods (e.g. Subscribe)
				// already had the lock, saw the error, and called refresh. This
				// check prevents a double-refresh in that case.
				if p.curr == pc {
					p.refresh()
				}
				p.l.Unlock()
			case <-p.closeCh:
			}
		}()
		return pc, nil
	}

	var attempts int
	for {
		var err error
		if p.curr, err = attempt(); err == nil {
			return nil
		}
		attempts++
		if p.opts.abortAfter > 0 && attempts >= p.opts.abortAfter {
			return err
		}
		time.Sleep(200 * time.Millisecond)
	}
}

func (p *persistentPubSub) Subscribe(msgCh chan<- PubSubMessage, channels ...string) error {
	p.l.Lock()
	defer p.l.Unlock()

	// add first, so if the actual call fails then refresh will catch it
	for _, channel := range channels {
		p.subs.add(channel, msgCh)
	}

	if err := p.curr.Subscribe(msgCh, channels...); err != nil {
		if err := p.refresh(); err != nil {
			return err
		}
	}
	return nil
}

func (p *persistentPubSub) Unsubscribe(msgCh chan<- PubSubMessage, channels ...string) error {
	p.l.Lock()
	defer p.l.Unlock()

	// remove first, so if the actual call fails then refresh will catch it
	for _, channel := range channels {
		p.subs.del(channel, msgCh)
	}

	if err := p.curr.Unsubscribe(msgCh, channels...); err != nil {
		if err := p.refresh(); err != nil {
			return err
		}
	}
	return nil
}

func (p *persistentPubSub) PSubscribe(msgCh chan<- PubSubMessage, channels ...string) error {
	p.l.Lock()
	defer p.l.Unlock()

	// add first, so if the actual call fails then refresh will catch it
	for _, channel := range channels {
		p.psubs.add(channel, msgCh)
	}

	if err := p.curr.PSubscribe(msgCh, channels...); err != nil {
		if err := p.refresh(); err != nil {
			return err
		}
	}
	return nil
}

func (p *persistentPubSub) PUnsubscribe(msgCh chan<- PubSubMessage, channels ...string) error {
	p.l.Lock()
	defer p.l.Unlock()

	// remove first, so if the actual call fails then refresh will catch it
	for _, channel := range channels {
		p.psubs.del(channel, msgCh)
	}

	if err := p.curr.PUnsubscribe(msgCh, channels...); err != nil {
		if err := p.refresh(); err != nil {
			return err
		}
	}
	return nil
}

func (p *persistentPubSub) Ping() error {
	p.l.Lock()
	defer p.l.Unlock()

	for {
		if err := p.curr.Ping(); err == nil {
			break
		} else if err := p.refresh(); err != nil {
			return err
		}
	}
	return nil
}

func (p *persistentPubSub) Close() error {
	p.l.Lock()
	defer p.l.Unlock()
	close(p.closeCh)
	return p.curr.Close()
}
