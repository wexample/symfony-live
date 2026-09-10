<?php

namespace Wexample\SymfonyLive\Helper;

use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyLive\Enum\LiveTopicAction;

class LiveTopicHelper
{
    final public const SEPARATOR = '/';
    final public const PREFIX_ENTITY = 'entity';

    /**
     * Builds `entity/<kebab-entity-name>/<action>/<identifier>`, the topic grammar the
     * browser rebuilds segment by segment in symfony-loader LiveUpdatesService.topic():
     * a change here is a change on both sides.
     *
     * @param string|int $identifier what the subscriber knows the entity by, which is a
     *                               secure id as often as it is a primary key
     */
    public static function entity(
        object|string $entity,
        LiveTopicAction|string $action,
        string|int $identifier
    ): string {
        return static::join(
            static::PREFIX_ENTITY,
            ClassHelper::getKebabName($entity),
            $action instanceof LiveTopicAction ? $action->value : $action,
            $identifier
        );
    }

    public static function join(string|int ...$segments): string
    {
        return implode(static::SEPARATOR, $segments);
    }
}
