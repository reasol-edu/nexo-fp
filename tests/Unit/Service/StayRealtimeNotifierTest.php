<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Stay;
use App\Service\StayRealtimeNotifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Uid\Uuid;

class StayRealtimeNotifierTest extends TestCase
{
    public function testTopicForStringIsPrefixed(): void
    {
        $notifier = new StayRealtimeNotifier($this->hub(static fn () => 'ok'));

        self::assertSame('stay/abc', $notifier->topicForStay('abc'));
    }

    public function testTopicForStayUsesRfc4122(): void
    {
        $id   = Uuid::v4();
        $stay = $this->stayWithId($id);

        $notifier = new StayRealtimeNotifier($this->hub(static fn () => 'ok'));

        self::assertSame('stay/' . $id->toRfc4122(), $notifier->topicForStay($stay));
    }

    public function testPublishStayChangedSendsPrivateUpdateToStayTopic(): void
    {
        $id        = Uuid::v4();
        $stay      = $this->stayWithId($id);
        $published = [];

        $notifier = new StayRealtimeNotifier($this->hub(function (Update $update) use (&$published): string {
            $published[] = $update;

            return 'ok';
        }));

        $notifier->publishStayChanged($stay);

        self::assertCount(1, $published);
        $update = $published[0];
        self::assertSame(['stay/' . $id->toRfc4122()], $update->getTopics());
        self::assertTrue($update->isPrivate());
        self::assertSame(['ts'], array_keys((array) json_decode($update->getData(), true)));
    }

    public function testPublishStayChangedSwallowsHubFailures(): void
    {
        $notifier = new StayRealtimeNotifier($this->hub(static function (): string {
            throw new \RuntimeException('hub down');
        }));

        $notifier->publishStayChanged($this->stayWithId(Uuid::v4()));

        $this->expectNotToPerformAssertions();
    }

    private function hub(callable $publisher): MockHub
    {
        return new MockHub(
            'https://example.com/.well-known/mercure',
            new StaticTokenProvider('jwt'),
            $publisher,
        );
    }

    private function stayWithId(Uuid $id): Stay
    {
        $stay = new Stay();
        $ref  = new \ReflectionProperty(Stay::class, 'id');
        $ref->setValue($stay, $id);

        return $stay;
    }
}
