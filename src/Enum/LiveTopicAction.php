<?php

namespace Wexample\SymfonyLive\Enum;

enum LiveTopicAction: string
{
    case CREATE = 'create';

    case UPDATE = 'update';

    case DELETE = 'delete';

    case EVENT = 'event';
}
