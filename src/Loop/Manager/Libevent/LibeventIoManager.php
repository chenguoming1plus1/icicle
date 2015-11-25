<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Libevent;

use Event;
use EventBase;
use Icicle\Loop\Exception\FreedError;
use Icicle\Loop\Exception\ResourceBusyError;
use Icicle\Loop\LibeventLoop;
use Icicle\Loop\Manager\IoManager;
use Icicle\Loop\Watcher\Io;

class LibeventIoManager implements IoManager
{
    const MIN_TIMEOUT = 0.001;
    const MICROSEC_PER_SEC = 1e6;

    /**
     * @var \Icicle\Loop\LibeventLoop
     */
    private $loop;

    /**
     * @var resource
     */
    private $base;

    /**
     * @var resource[]
     */
    private $events = [];
    
    /**
     * @var \Icicle\Loop\Watcher\Io[]
     */
    private $sockets = [];
    
    /**
     * @var bool[]
     */
    private $pending = [];

    /**
     * @var \Icicle\Loop\Watcher\Io[]
     */
    private $unreferenced = [];
    
    /**
     * @var callable
     */
    private $callback;

    /**
     * @var int
     */
    private $type;
    
    /**
     * @param \Icicle\Loop\LibeventLoop $loop
     * @param int $eventType
     */
    public function __construct(LibeventLoop $loop, $eventType)
    {
        $this->loop = $loop;
        $this->base = $this->loop->getEventBase();
        $this->type = $eventType;
        
        $this->callback = function ($resource, $what, Io $io) {
            $this->pending[(int) $resource] = false;
            $io->call(0 !== (EV_TIMEOUT & $what));
        };
    }
    
    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        foreach ($this->events as $event) {
            event_free($event);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        foreach ($this->pending as $id => $pending) {
            if ($pending && !isset($this->unreferenced[$id])) {
                return false;
            }
        }

        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function create($resource, callable $callback)
    {
        $id = (int) $resource;
        
        if (isset($this->sockets[$id])) {
            throw new ResourceBusyError();
        }
        
        $this->sockets[$id] = new Io($this, $resource, $callback);
        $this->pending[$id] = false;
        
        return $this->sockets[$id];
    }
    
    /**
     * {@inheritdoc}
     */
    public function listen(Io $io, $timeout = 0)
    {
        $id = (int) $io->getResource();
        
        if (!isset($this->sockets[$id]) || $io !== $this->sockets[$id]) {
            throw new FreedError();
        }
        
        if (!isset($this->events[$id])) {
            $event = event_new();
            event_set($event, $io->getResource(), $this->type, $this->callback, $io);
            event_base_set($event, $this->base);

            $this->events[$id] = $event;
        }

        $this->pending[$id] = true;

        if (0 === $timeout) {
            event_add($this->events[$id]);
            return;
        }

        $timeout = (float) $timeout;
        if (self::MIN_TIMEOUT > $timeout) {
            $timeout = self::MIN_TIMEOUT;
        }

        event_add($this->events[$id], $timeout * self::MICROSEC_PER_SEC);
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel(Io $io)
    {
        $id = (int) $io->getResource();
        
        if (isset($this->sockets[$id], $this->events[$id]) && $io === $this->sockets[$id]) {
            event_del($this->events[$id]);
            $this->pending[$id] = false;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(Io $io)
    {
        $id = (int) $io->getResource();
        
        return isset($this->sockets[$id], $this->pending[$id])
            && $io === $this->sockets[$id]
            && $this->pending[$id];
    }
    
    /**
     * {@inheritdoc}
     */
    public function free(Io $io)
    {
        $id = (int) $io->getResource();
        
        if (isset($this->sockets[$id]) && $io === $this->sockets[$id]) {
            unset($this->sockets[$id], $this->pending[$id], $this->unreferenced[$id]);
            
            if (isset($this->events[$id])) {
                event_free($this->events[$id]);
                unset($this->events[$id]);
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFreed(Io $io)
    {
        $id = (int) $io->getResource();
        
        return !isset($this->sockets[$id]) || $io !== $this->sockets[$id];
    }

    /**
     * {@inheritdoc}
     */
    public function reference(Io $io)
    {
        unset($this->unreferenced[(int) $io->getResource()]);
    }

    /**
     * {@inheritdoc}
     */
    public function unreference(Io $io)
    {
        $id = (int) $io->getResource();

        if (isset($this->events[$id]) && $io === $this->sockets[$id]) {
            $this->unreferenced[$id] = $io;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        foreach ($this->events as $event) {
            event_free($event);
        }
        
        $this->events = [];
        $this->sockets = [];
        $this->pending = [];
    }
}