<?php

namespace PgAsync\Command;

use Rx\ObserverInterface;

trait CommandTrait
{
    /** @var ObserverInterface */
    protected $observer;

    protected $active = true;

    public function complete()
    {
        $this->active = false;
        if (!$this->observer instanceof ObserverInterface) {
            throw new \Exception('Observer not set on command.');
        }
        $this->observer->onCompleted();
    }

    public function error(\Throwable $exception = null)
    {
        $this->active = false;
        if (!$this->observer instanceof ObserverInterface) {
            throw new \Exception('Observer not set on command.');
        }
        $this->observer->onError($exception);
    }

    public function next($value) {
        if (!$this->active) {
            return;
        }
        if (!$this->observer instanceof ObserverInterface) {
            throw new \Exception('Observer not set on command.');
        }
        $this->observer->onNext($value);
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function cancel()
    {
        $this->active = false;
    }
}
