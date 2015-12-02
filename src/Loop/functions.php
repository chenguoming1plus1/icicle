<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop;

use Icicle\Loop\Watcher\{Immediate, Io, Signal, Timer};

if (!function_exists(__NAMESPACE__ . '\loop')) {
    /**
     * Returns the default event loop. Can be used to set the default event loop if an instance is provided.
     *
     * @param \Icicle\Loop\Loop|null $loop
     * 
     * @return \Icicle\Loop\Loop
     */
    function loop(Loop $loop = null): Loop
    {
        static $instance;

        if (null !== $loop) {
            $instance = $loop;
        } elseif (null === $instance) {
            $instance = create();
        }

        return $instance;
    }

    /**
     * @param bool $enableSignals True to enable signal handling, false to disable.
     *
     * @return \Icicle\Loop\Loop
     *
     * @codeCoverageIgnore
     */
    function create(bool $enableSignals = true): Loop
    {
        if (UvLoop::enabled()) {
            return new UvLoop($enableSignals);
        }

        if (EvLoop::enabled()) {
            return new EvLoop($enableSignals);
        }

        return new SelectLoop($enableSignals);
    }

    /**
     * Runs the tasks set up in the given function in a separate event loop from the default event loop. If the default
     * is running, the default event loop is blocked while the separate event loop is running.
     *
     * @param callable $worker
     * @param Loop|null $loop
     *
     * @return bool
     */
    function with(callable $worker, Loop $loop = null): bool
    {
        $previous = loop();

        try {
            return loop($loop ?: create())->run($worker);
        } finally {
            loop($previous);
        }
    }
    
    /**
     * Queues a function to be executed later. The function may be executed as soon as immediately after
     * the calling scope exits. Functions are guaranteed to be executed in the order queued.
     *
     * @param callable $callback
     * @param mixed ...$args
     */
    function queue(callable $callback, ...$args)
    {
        loop()->queue($callback, $args);
    }

    /**
     * Sets the maximum number of callbacks set with queue() that will be executed per tick.
     *
     * @param int $depth Maximum number of functions to execute each tick. Use 0 for unlimited.
     *
     * @return int Previous max depth.
     */
    function maxQueueDepth(int $depth): int
    {
        return loop()->maxQueueDepth($depth);
    }

    /**
     * Executes a single tick of the event loop.
     *
     * @param bool $blocking
     */
    function tick(bool $blocking = false)
    {
        loop()->tick($blocking);
    }

    /**
     * Starts the default event loop. If a function is provided, that function is executed immediately after starting
     * the event loop, passing the event loop as the first argument.
     *
     * @param callable<(Loop $loop): void>|null $initialize
     *
     * @return bool True if the loop was stopped, false if the loop exited because no events remained.
     *
     * @throws \Icicle\Loop\Exception\RunningError If the loop was already running.
     */
    function run(callable $initialize = null): bool
    {
        return loop()->run($initialize);
    }

    /**
     * Determines if the event loop is running.
     *
     * @return bool
     */
    function isRunning(): bool
    {
        return loop()->isRunning();
    }

    /**
     * Stops the event loop.
     */
    function stop()
    {
        loop()->stop();
    }

    /**
     * Determines if there are any pending events in the loop. Returns true if there are no pending events.
     *
     * @return bool
     */
    function isEmpty(): bool
    {
        return loop()->isEmpty();
    }

    /**
     * @param resource $socket Stream socket resource.
     * @param callable $callback Callback to be invoked when data is available on the socket.
     *
     * @return \Icicle\Loop\Watcher\Io
     */
    function poll($socket, callable $callback): Io
    {
        return loop()->poll($socket, $callback);
    }

    /**
     * @param resource $socket Stream socket resource.
     * @param callable $callback Callback to be invoked when the socket is available to write.
     *
     * @return \Icicle\Loop\Watcher\Io
     */
    function await($socket, callable $callback): Io
    {
        return loop()->await($socket, $callback);
    }

    /**
     * @param float|int $interval Number of seconds before the callback is invoked.
     * @param callable $callback Function to invoke when the timer expires.
     * @param mixed ...$args Arguments to pass to the callback function.
     *
     * @return \Icicle\Loop\Watcher\Timer
     */
    function timer(float $interval, callable $callback, ...$args): Timer
    {
        return loop()->timer($interval, false, $callback, $args);
    }

    /**
     * @param float|int $interval Number of seconds between invocations of the callback.
     * @param callable $callback Function to invoke when the timer expires.
     * @param mixed ...$args Arguments to pass to the callback function.
     *
     * @return \Icicle\Loop\Watcher\Timer
     */
    function periodic(float $interval, callable $callback, ...$args): Timer
    {
        return loop()->timer($interval, true, $callback, $args);
    }

    /**
     * @param callable $callback Function to invoke when no other active events are available.
     * @param mixed ...$args Arguments to pass to the callback function.
     *
     * @return \Icicle\Loop\Watcher\Immediate
     */
    function immediate(callable $callback, ...$args): Immediate
    {
        return loop()->immediate($callback, $args);
    }

    /**
     * @param int $signo Signal number. (Use constants such as SIGTERM, SIGCONT, etc.)
     * @param callable $callback Function to invoke when the given signal arrives.
     *
     * @return \Icicle\Loop\Watcher\Signal
     */
    function signal(int $signo, callable $callback): Signal
    {
        return loop()->signal($signo, $callback);
    }

    /**
     * Determines if signal handling is enabled.
     *
     * @return bool
     */
    function signalHandlingEnabled(): bool
    {
        return loop()->signalHandlingEnabled();
    }

    /**
     * Removes all events (I/O, timers, callbacks, signal handlers, etc.) from the loop.
     */
    function clear()
    {
        loop()->clear();
    }

    /**
     * Performs any reinitializing necessary after forking.
     */
    function reInit()
    {
        loop()->reInit();
    }
}
