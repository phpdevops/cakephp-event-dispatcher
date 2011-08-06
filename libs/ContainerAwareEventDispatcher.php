<?php

namespace event_dispatcher\libs;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;

/**
 * Lazily loads listeners and subscribers from the dependency injection
 * container
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class ContainerAwareEventDispatcher extends EventDispatcher
{
    /**
     * The container from where services are loaded
     * @var ContainerInterface
     */
    private $container;

    /**
     * The service IDs of the event listeners and subscribers
     * @var array
     */
    private $listenerIds = array();

    /**
     * The services registered as listeners
     * @var array
     */
    private $listeners = array();

    /**
     * Constructor.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Adds a service as event listener
     *
     * @param string|array $eventNames One or more events for which the listener is added
     * @param string       $serviceId  The ID of the listener service
     * @param integer      $priority   The higher this value, the earlier an event listener
     *                                 will be triggered in the chain.
     *                                 Defaults to 0.
     */
    public function addListenerService($eventNames, $serviceId, $priority = 0)
    {
        if (!is_string($serviceId)) {
            throw new \InvalidArgumentException('Expected a string argument');
        }

        foreach ((array) $eventNames as $eventName) {
            // Prevent duplicate entries
            $this->listenerIds[$eventName][$serviceId] = $priority;
        }
    }

    /**
     * {@inheritDoc}
     *
     * Lazily loads listeners for this event from the dependency injection
     * container.
     *
     * @throws \InvalidArgumentException if the service is not defined
     */
    public function dispatch($eventName, Event $event = null)
    {
        if (isset($this->listenerIds[$eventName])) {
            foreach ($this->listenerIds[$eventName] as $serviceId => $priority) {
                $listener = $this->container->get($serviceId);

                if (!isset($this->listeners[$eventName][$serviceId])) {
                    $this->addListener($eventName, $listener, $priority);
                } elseif ($listener !== $this->listeners[$eventName][$serviceId]) {
                    $this->removeListener($eventName, $this->listeners[$eventName][$serviceId]);
                    $this->addListener($eventName, $listener, $priority);
                }

                $this->listeners[$eventName][$serviceId] = $listener;
            }
        }

        parent::dispatch($eventName, $event);
    }
}
