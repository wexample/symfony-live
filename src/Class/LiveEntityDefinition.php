<?php

namespace Wexample\SymfonyLive\Class;

use Wexample\SymfonyLive\Enum\LiveTopicAction;

class LiveEntityDefinition
{
    /**
     * @param LiveTopicAction[] $actions
     */
    public function __construct(
        public readonly string $className,
        public readonly string $name,
        public readonly array $actions,
    ) {
    }
}
