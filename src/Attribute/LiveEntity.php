<?php

namespace Wexample\SymfonyLive\Attribute;

use Attribute;
use Wexample\SymfonyLive\Enum\LiveTopicAction;

/**
 * Opens an entity to live subscription: the subscribe-info endpoint then answers on its
 * kebab name, and signs a token for the actions listed here. Being open to subscription
 * says nothing about who may subscribe, which stays the app's voter decision.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class LiveEntity
{
    /**
     * @param LiveTopicAction[] $actions empty grants every action
     */
    public function __construct(
        public readonly array $actions = [],
    ) {
    }
}
