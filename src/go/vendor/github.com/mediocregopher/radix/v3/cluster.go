package radix

import (
	"reflect"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	errors "golang.org/x/xerrors"

	"github.com/mediocregopher/radix/v3/resp"
	"github.com/mediocregopher/radix/v3/resp/resp2"
	"github.com/mediocregopher/radix/v3/trace"
)

// dedupe is used to deduplicate a function invocation, so if multiple
// go-routines call it at the same time only the first will actually run it, and
// the others will block until that one is done.
type dedupe struct {
	l sync.Mutex
	s *sync.Once
}

func newDedupe() *dedupe {
	return &dedupe{s: new(sync.Once)}
}

func (d *dedupe) do(fn func()) {
	d.l.Lock()
	s := d.s
	d.l.Unlock()

	s.Do(func() {
		fn()
		d.l.Lock()
		d.s = new(sync.Once)
		d.l.Unlock()
	})
}

////////////////////////////////////////////////////////////////////////////////

// ClusterCanRetryAction is an Action which is aware of Cluster's retry behavior
// in the event of a slot migration. If an Action receives an error from a
// Cluster node which is either MOVED or ASK, and that Action implements
// ClusterCanRetryAction, and the ClusterCanRetry method returns true, then the
// Action will be retried on the correct node.
//
// NOTE that the Actions which are returned by Cmd, FlatCmd, and EvalScript.Cmd
// all implicitly implement this interface.
type ClusterCanRetryAction interface {
	Action
	ClusterCanRetry() bool
}

////////////////////////////////////////////////////////////////////////////////

type clusterOpts struct {
	pf              ClientFunc
	clusterDownWait time.Duration
	syncEvery       time.Duration
	ct              trace.ClusterTrace
}

// ClusterOpt is an optional behavior which can be applied to the NewCluster
// function to effect a Cluster's behavior
type ClusterOpt func(*clusterOpts)

// ClusterPoolFunc tells the Cluster to use the given ClientFunc when creating
// pools of connections to cluster members.
func ClusterPoolFunc(pf ClientFunc) ClusterOpt {
	return func(co *clusterOpts) {
		co.pf = pf
	}
}

// ClusterSyncEvery tells the Cluster to synchronize itself with the cluster's
// topology at the given interval. On every synchronization Cluster will ask the
// cluster for its topology and make/destroy its connections as necessary.
func ClusterSyncEvery(d time.Duration) ClusterOpt {
	return func(co *clusterOpts) {
		co.syncEvery = d
	}
}

// ClusterOnDownDelayActionsBy tells the Cluster to delay all commands by the given
// duration while the cluster is seen to be in the CLUSTERDOWN state. This
// allows fewer actions to be affected by brief outages, e.g. during a failover.
//
// If the given duration is 0 then Cluster will not delay actions during the
// CLUSTERDOWN state. Note that calls to Sync will not be delayed regardless
// of this option.
func ClusterOnDownDelayActionsBy(d time.Duration) ClusterOpt {
	return func(co *clusterOpts) {
		co.clusterDownWait = d
	}
}

// ClusterWithTrace tells the Cluster to trace itself with the given
// ClusterTrace. Note that ClusterTrace will block every point that you set to
// trace.
func ClusterWithTrace(ct trace.ClusterTrace) ClusterOpt {
	return func(co *clusterOpts) {
		co.ct = ct
	}
}

// Cluster contains all information about a redis cluster needed to interact
// with it, including a set of pools to each of its instances. All methods on
// Cluster are thread-safe
type Cluster struct {
	// Atomic fields must be at the beginning of the struct since they must be
	// correctly aligned or else access may cause panics on 32-bit architectures
	// See https://golang.org/pkg/sync/atomic/#pkg-note-BUG
	lastClusterdown int64 // unix timestamp in milliseconds, atomic

	co clusterOpts

	// used to deduplicate calls to sync
	syncDedupe *dedupe

	l              sync.RWMutex
	pools          map[string]Client
	primTopo, topo ClusterTopo

	closeCh   chan struct{}
	closeWG   sync.WaitGroup
	closeOnce sync.Once

	// Any errors encountered internally will be written to this channel. If
	// nothing is reading the channel the errors will be dropped. The channel
	// will be closed when the Close method is called.
	ErrCh chan error
}

// NewCluster initializes and returns a Cluster instance. It will try every
// address given until it finds a usable one. From there it uses CLUSTER SLOTS
// to discover the cluster topology and make all the necessary connections.
//
// NewCluster takes in a number of options which can overwrite its default
// behavior. The default options NewCluster uses are:
//
//     ClusterPoolFunc(DefaultClientFunc)
//     ClusterSyncEvery(5 * time.Second)
//     ClusterOnDownDelayActionsBy(100 * time.Millisecond)
//
func NewCluster(clusterAddrs []string, opts ...ClusterOpt) (*Cluster, error) {
	c := &Cluster{
		syncDedupe: newDedupe(),
		pools:      map[string]Client{},
		closeCh:    make(chan struct{}),
		ErrCh:      make(chan error, 1),
	}

	defaultClusterOpts := []ClusterOpt{
		ClusterPoolFunc(DefaultClientFunc),
		ClusterSyncEvery(5 * time.Second),
		ClusterOnDownDelayActionsBy(100 * time.Millisecond),
	}

	for _, opt := range append(defaultClusterOpts, opts...) {
		// the other args to NewCluster used to be a ClientFunc, which someone
		// might have left as nil, in which case this now gives a weird panic.
		// Just handle it
		if opt != nil {
			opt(&(c.co))
		}
	}

	// make a pool to base the cluster on
	for _, addr := range clusterAddrs {
		p, err := c.co.pf("tcp", addr)
		if err != nil {
			continue
		}
		c.pools[addr] = p
		break
	}

	if err := c.Sync(); err != nil {
		for _, p := range c.pools {
			p.Close()
		}
		return nil, err
	}

	c.syncEvery(c.co.syncEvery)

	return c, nil
}

func (c *Cluster) err(err error) {
	select {
	case c.ErrCh <- err:
	default:
	}
}

func assertKeysSlot(keys []string) error {
	var ok bool
	var prevKey string
	var slot uint16
	for _, key := range keys {
		thisSlot := ClusterSlot([]byte(key))
		if !ok {
			ok = true
		} else if slot != thisSlot {
			return errors.Errorf("keys %q and %q do not belong to the same slot", prevKey, key)
		}
		prevKey = key
		slot = thisSlot
	}
	return nil
}

// may return nil, nil if no pool for the addr
func (c *Cluster) rpool(addr string) (Client, error) {
	c.l.RLock()
	defer c.l.RUnlock()
	if addr == "" {
		for _, p := range c.pools {
			return p, nil
		}
		return nil, errors.New("no pools available")
	} else if p, ok := c.pools[addr]; ok {
		return p, nil
	}
	return nil, nil
}

var errUnknownAddress = errors.New("unknown address")

// Client returns a Client for the given address, which could be either the
// primary or one of the secondaries (see Topo method for retrieving known
// addresses).
//
// NOTE that if there is a failover while a Client returned by this method is
// being used the Client may or may not continue to work as expected, depending
// on the nature of the failover.
//
// NOTE the Client should _not_ be closed.
func (c *Cluster) Client(addr string) (Client, error) {
	// rpool allows the address to be "", handle that case manually
	if addr == "" {
		return nil, errUnknownAddress
	}
	cl, err := c.rpool(addr)
	if err != nil {
		return nil, err
	} else if cl == nil {
		return nil, errUnknownAddress
	}
	return cl, nil
}

// if addr is "" returns a random pool. If addr is given but there's no pool for
// it one will be created on-the-fly
func (c *Cluster) pool(addr string) (Client, error) {
	p, err := c.rpool(addr)
	if p != nil || err != nil {
		return p, err
	}

	// if the pool isn't available make it on-the-fly. This behavior isn't
	// _great_, but theoretically the syncEvery process should clean up any
	// extraneous pools which aren't really needed

	// it's important that the cluster pool set isn't locked while this is
	// happening, because this could block for a while
	if p, err = c.co.pf("tcp", addr); err != nil {
		return nil, err
	}

	// we've made a new pool, but we need to double-check someone else didn't
	// make one at the same time and add it in first. If they did, close this
	// one and return that one
	c.l.Lock()
	if p2, ok := c.pools[addr]; ok {
		c.l.Unlock()
		p.Close()
		return p2, nil
	}
	c.pools[addr] = p
	c.l.Unlock()
	return p, nil
}

// Topo returns the Cluster's topology as it currently knows it. See
// ClusterTopo's docs for more on its default order.
func (c *Cluster) Topo() ClusterTopo {
	c.l.RLock()
	defer c.l.RUnlock()
	return c.topo
}

func (c *Cluster) getTopo(p Client) (ClusterTopo, error) {
	var tt ClusterTopo
	err := p.Do(Cmd(&tt, "CLUSTER", "SLOTS"))
	return tt, err
}

// Sync will synchronize the Cluster with the actual cluster, making new pools
// to new instances and removing ones from instances no longer in the cluster.
// This will be called periodically automatically, but you can manually call it
// at any time as well
func (c *Cluster) Sync() error {
	p, err := c.pool("")
	if err != nil {
		return err
	}
	c.syncDedupe.do(func() {
		err = c.sync(p)
	})
	return err
}

func nodeInfoFromNode(node ClusterNode) trace.ClusterNodeInfo {
	return trace.ClusterNodeInfo{
		Addr:      node.Addr,
		Slots:     node.Slots,
		IsPrimary: node.SecondaryOfAddr == "",
	}
}

func (c *Cluster) traceTopoChanged(prevTopo ClusterTopo, newTopo ClusterTopo) {
	if c.co.ct.TopoChanged != nil {
		var addedNodes []trace.ClusterNodeInfo
		var removedNodes []trace.ClusterNodeInfo
		var changedNodes []trace.ClusterNodeInfo

		prevTopoMap := prevTopo.Map()
		newTopoMap := newTopo.Map()

		for addr, newNode := range newTopoMap {
			if prevNode, ok := prevTopoMap[addr]; ok {
				// Check whether two nodes which have the same address changed its value or not
				if !reflect.DeepEqual(prevNode, newNode) {
					changedNodes = append(changedNodes, nodeInfoFromNode(newNode))
				}
				// No need to handle this address for finding removed nodes
				delete(prevTopoMap, addr)
			} else {
				// The node's address not found from prevTopo is newly added node
				addedNodes = append(addedNodes, nodeInfoFromNode(newNode))
			}
		}

		// Find removed nodes, prevTopoMap has reduced
		for addr, prevNode := range prevTopoMap {
			if _, ok := newTopoMap[addr]; !ok {
				removedNodes = append(removedNodes, nodeInfoFromNode(prevNode))
			}
		}

		// Callback when any changes detected
		if len(addedNodes) != 0 || len(removedNodes) != 0 || len(changedNodes) != 0 {
			c.co.ct.TopoChanged(trace.ClusterTopoChanged{
				Added:   addedNodes,
				Removed: removedNodes,
				Changed: changedNodes,
			})
		}
	}
}

// while this method is normally deduplicated by the Sync method's use of
// dedupe it is perfectly thread-safe on its own and can be used whenever.
func (c *Cluster) sync(p Client) error {
	tt, err := c.getTopo(p)
	if err != nil {
		return err
	}

	for _, t := range tt {
		// call pool just to ensure one exists for this addr
		if _, err := c.pool(t.Addr); err != nil {
			return errors.Errorf("error connecting to %s: %w", t.Addr, err)
		}
	}

	c.traceTopoChanged(c.topo, tt)

	var toclose []Client
	func() {
		c.l.Lock()
		defer c.l.Unlock()
		c.topo = tt
		c.primTopo = tt.Primaries()

		tm := tt.Map()
		for addr, p := range c.pools {
			if _, ok := tm[addr]; !ok {
				toclose = append(toclose, p)
				delete(c.pools, addr)
			}
		}
	}()

	for _, p := range toclose {
		p.Close()
	}

	return nil
}

func (c *Cluster) syncEvery(d time.Duration) {
	c.closeWG.Add(1)
	go func() {
		defer c.closeWG.Done()
		t := time.NewTicker(d)
		defer t.Stop()

		for {
			select {
			case <-t.C:
				if err := c.Sync(); err != nil {
					c.err(err)
				}
			case <-c.closeCh:
				return
			}
		}
	}()
}

func (c *Cluster) addrForKey(key string) string {
	s := ClusterSlot([]byte(key))
	c.l.RLock()
	defer c.l.RUnlock()
	for _, t := range c.primTopo {
		for _, slot := range t.Slots {
			if s >= slot[0] && s < slot[1] {
				return t.Addr
			}
		}
	}
	return ""
}

type askConn struct {
	Conn
}

func (ac askConn) Encode(m resp.Marshaler) error {
	if err := ac.Conn.Encode(Cmd(nil, "ASKING")); err != nil {
		return err
	}
	return ac.Conn.Encode(m)
}

func (ac askConn) Decode(um resp.Unmarshaler) error {
	if err := ac.Conn.Decode(resp2.Any{}); err != nil {
		return err
	}
	return ac.Conn.Decode(um)
}

func (ac askConn) Do(a Action) error {
	return a.Run(ac)
}

const doAttempts = 5

// Do performs an Action on a redis instance in the cluster, with the instance
// being determeined by the key returned from the Action's Key() method.
//
// This method handles MOVED and ASK errors automatically in most cases, see
// ClusterCanRetryAction's docs for more.
func (c *Cluster) Do(a Action) error {
	var addr, key string
	keys := a.Keys()
	if len(keys) == 0 {
		// that's ok, key will then just be ""
	} else if err := assertKeysSlot(keys); err != nil {
		return err
	} else {
		key = keys[0]
		addr = c.addrForKey(key)
	}

	return c.doInner(a, addr, key, false, doAttempts)
}

func (c *Cluster) getClusterDownSince() int64 {
	return atomic.LoadInt64(&c.lastClusterdown)
}

func (c *Cluster) setClusterDown(down bool) (changed bool) {
	// There is a race when calling this method concurrently when the cluster
	// healed after being down.
	//
	// If we have 2 goroutines, one that sends a command before the cluster
	// heals and once that sends a command after the cluster healed, both
	// goroutines will call this method, but with different values
	// (down == true and down == false).
	//
	// Since there is bi ordering between the two goroutines, it can happen
	// that the call to setClusterDown in the second goroutine runs before
	// the call in the first goroutine. In that case the state would be
	// changed from down to up by the second goroutine, as it should, only
	// for the first goroutine to set it back to down a few microseconds later.
	//
	// If this happens other commands will be needlessly delayed until
	// another goroutine sets the state to up again and we will trace two
	// unnecessary state transitions.
	//
	// We can not reliably avoid this race without more complex tracking of
	// previous states, which would be rather complex and possibly expensive.

	// Swapping values is expensive (on amd64, an uncontended swap can be 10x
	// slower than a load) and can easily become quite contended when we have
	// many goroutines trying to update the value concurrently, which would
	// slow it down even more.
	//
	// We avoid the overhead of swapping when not necessary by loading the
	// value first and checking if the value is already what we want it to be.
	//
	// Since atomic loads are fast (on amd64 an atomic load can be as fast as
	// a non-atomic load, and is perfectly scalable as long as there are no
	// writes to the same cache line), we can safely do this without adding
	// unnecessary extra latency.
	prevVal := atomic.LoadInt64(&c.lastClusterdown)

	var newVal int64
	if down {
		newVal = time.Now().UnixNano() / 1000 / 1000
		// Since the exact value is only used for delaying commands small
		// differences don't matter much and we can avoid many updates by
		// ignoring small differences (<5ms).
		if prevVal != 0 && newVal-prevVal < 5 {
			return false
		}
	} else {
		if prevVal == 0 {
			return false
		}
	}

	prevVal = atomic.SwapInt64(&c.lastClusterdown, newVal)

	changed = (prevVal == 0 && newVal != 0) || (prevVal != 0 && newVal == 0)

	if changed && c.co.ct.StateChange != nil {
		c.co.ct.StateChange(trace.ClusterStateChange{IsDown: down})
	}

	return changed
}

func (c *Cluster) traceRedirected(addr, key string, moved, ask bool, count int, final bool) {
	if c.co.ct.Redirected != nil {
		c.co.ct.Redirected(trace.ClusterRedirected{
			Addr:          addr,
			Key:           key,
			Moved:         moved,
			Ask:           ask,
			RedirectCount: count,
			Final:         final,
		})
	}
}

func (c *Cluster) doInner(a Action, addr, key string, ask bool, attempts int) error {
	if downSince := c.getClusterDownSince(); downSince > 0 && c.co.clusterDownWait > 0 {
		// only wait when the last command was not too long, because
		// otherwise the chance it high that the cluster already healed
		elapsed := (time.Now().UnixNano() / 1000 / 1000) - downSince
		if elapsed < int64(c.co.clusterDownWait/time.Millisecond) {
			time.Sleep(c.co.clusterDownWait)
		}
	}

	p, err := c.pool(addr)
	if err != nil {
		return err
	}

	// We only need to use WithConn if we want to send an ASKING command before
	// our Action a. If ask is false we can thus skip the WithConn call, which
	// avoids a few allocations, and execute our Action directly on p. This
	// helps with most calls since ask will only be true when a key gets
	// migrated between nodes.
	thisA := a
	if ask {
		thisA = WithConn(key, func(conn Conn) error {
			return askConn{conn}.Do(a)
		})
	}

	err = p.Do(thisA)
	if err == nil {
		c.setClusterDown(false)
		return nil
	}

	if !errors.As(err, new(resp2.Error)) {
		return err
	}
	msg := err.Error()

	clusterDown := strings.HasPrefix(msg, "CLUSTERDOWN ")
	clusterDownChanged := c.setClusterDown(clusterDown)
	if clusterDown && c.co.clusterDownWait > 0 && clusterDownChanged {
		return c.doInner(a, addr, key, ask, 1)
	}

	// if the error was a MOVED or ASK we can potentially retry
	moved := strings.HasPrefix(msg, "MOVED ")
	ask = strings.HasPrefix(msg, "ASK ")
	if !moved && !ask {
		return err
	}

	// if we get an ASK there's no need to do a sync quite yet, we can continue
	// normally. But MOVED always prompts a sync. In the section after this one
	// we figure out what address to use based on the returned error so the sync
	// isn't used _immediately_, but it still needs to happen.
	//
	// Also, even if the Action isn't a ClusterCanRetryAction we want a MOVED to
	// prompt a Sync
	if moved {
		if serr := c.Sync(); serr != nil {
			return serr
		}
	}

	if ccra, ok := a.(ClusterCanRetryAction); !ok || !ccra.ClusterCanRetry() {
		return err
	}

	msgParts := strings.Split(msg, " ")
	if len(msgParts) < 3 {
		return errors.Errorf("malformed MOVED/ASK error %q", msg)
	}
	ogAddr, addr := addr, msgParts[2]

	c.traceRedirected(ogAddr, key, moved, ask, doAttempts-attempts+1, attempts <= 1)
	if attempts--; attempts <= 0 {
		return errors.New("cluster action redirected too many times")
	}

	return c.doInner(a, addr, key, ask, attempts)
}

// Close cleans up all goroutines spawned by Cluster and closes all of its
// Pools.
func (c *Cluster) Close() error {
	closeErr := errClientClosed
	c.closeOnce.Do(func() {
		close(c.closeCh)
		c.closeWG.Wait()
		close(c.ErrCh)

		c.l.Lock()
		defer c.l.Unlock()
		var pErr error
		for _, p := range c.pools {
			if err := p.Close(); pErr == nil && err != nil {
				pErr = err
			}
		}
		closeErr = pErr
	})
	return closeErr
}
