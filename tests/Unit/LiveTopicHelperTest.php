<?php

namespace Wexample\SymfonyLive\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wexample\SymfonyLive\Enum\LiveTopicAction;
use Wexample\SymfonyLive\Helper\LiveTopicHelper;
use Wexample\SymfonyLive\Service\LiveEntityRegistryService;
use Wexample\SymfonyLive\Tests\Fixtures\Entity\MeetingRoom;
use Wexample\SymfonyLive\Tests\Fixtures\Entity\Room;

/**
 * A topic names the hierarchy, not the kind: every kind of room is published
 * on `entity/room/…`, which is the one name a subscriber knows.
 */
class LiveTopicHelperTest extends TestCase
{
    public function testASubclassIsPublishedUnderItsRootsName(): void
    {
        $room = new MeetingRoom();

        $this->assertSame(
            'entity/room/update/' . $room->getId(),
            LiveTopicHelper::entity($room, LiveTopicAction::UPDATE)
        );
        $this->assertSame('room', LiveEntityRegistryService::nameOf(MeetingRoom::class));
        $this->assertSame('room', LiveEntityRegistryService::nameOf(Room::class));
    }

    public function testTheTopicReadsBackAsItWasBuilt(): void
    {
        $room = new MeetingRoom();
        $topic = LiveTopicHelper::entity($room, LiveTopicAction::EVENT);

        $this->assertSame(
            ['name' => 'room', 'action' => 'event', 'id' => (string) $room->getId()],
            LiveTopicHelper::parseEntity($topic)
        );
    }
}
