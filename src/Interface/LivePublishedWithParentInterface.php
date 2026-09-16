<?php

namespace Wexample\SymfonyLive\Interface;

use Wexample\SymfonyHelpers\Entity\Interfaces\AbstractEntityInterface;

/**
 * An entity whose changes are also published on the topic of what holds it.
 *
 * A collection cannot listen to its own rows: a row that does not exist yet has
 * no topic, and it is precisely the row that appears which the reader is waiting
 * for. So a run publishes on its process, a message on its session, and whoever
 * watches the collection subscribes once to the thing it is the collection of.
 *
 * The parent carries the child's own action and the child's own payload — what
 * travels is unchanged, only the address it travels to is added to.
 */
interface LivePublishedWithParentInterface
{
    /**
     * @return AbstractEntityInterface[] the entities whose topics carry this one too
     */
    public function getLiveParents(): array;
}
