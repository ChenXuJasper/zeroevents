<?php
namespace ZeroEvents;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;

class EventSocketTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $container = new Container;
        $container->bind('events', 'Illuminate\Events\Dispatcher');
        Facade::setFacadeApplication($container);
    }

    /**
     * @param int $type
     * @return EventSocket
     */
    public function socket($type = null)
    {
        $socket = new EventSocket(new \ZMQContext(1, false), $type ? : \ZMQ::SOCKET_DEALER);
        $socket->setSockOpt(\ZMQ::SOCKOPT_LINGER, 1000);
        $socket->setSockOpt(\ZMQ::SOCKOPT_SNDTIMEO, 1000);
        $socket->setSockOpt(\ZMQ::SOCKOPT_RCVTIMEO, 1000);

        return $socket;
    }

    public function testInstance()
    {
        $this->assertInstanceOf('ZMQSocket', $this->socket());
    }

    public function testEncode()
    {
        $this->assertSame(
            [
                'event',
                'null',
                '1',
                'true',
                '"string"',
                '[1,2,3]',
                '{"key":"value"}',
                '"при/вет"',
                '{}',
            ],
            $this->socket()->encode(
                'event',
                [null, 1, true, 'string', [1, 2, 3], ['key' => 'value'], 'при/вет', new \stdClass]
            )
        );
    }

    public function testDecode()
    {
        $this->assertSame(
            [
                'event' => 'event',
                'payload' => [null, 1, true, 'string', [1, 2, 3], ['key' => 'value'], 'при/вет'],
            ],
            $this->socket()->decode([
                'event',
                'null',
                '1',
                'true',
                '"string"',
                '[1,2,3]',
                '{"key":"value"}',
                '"при/вет"',
            ])
        );
    }

    public function testPushPull()
    {
        $dsn = 'ipc://' . sys_get_temp_dir() . '/test-push.ipc';

        if (!pcntl_fork()) {
            $socket = $this->socket();
            $socket->bind($dsn);
            $socket->push('response.event', [$socket->pull()]);
            exit;
        }

        $socket = $this->socket();
        $socket->connect($dsn);
        $socket->push('request.event', ['source', 'parent']);

        $this->assertSame(
            [
                'event' => 'response.event',
                'payload' => [
                    [
                        'event' => 'request.event',
                        'payload' => ['source', 'parent'],
                        'address' => null,
                    ],
                ],
                'address' => null,
            ],
            $socket->pull()
        );
    }

    public function testPullAndFire()
    {
        $dsn = 'ipc://' . sys_get_temp_dir() . '/test-push.ipc';

        if (!pcntl_fork()) {
            $socket = $this->socket();
            $socket->bind($dsn);
            $socket->push('response.event', [$socket->pull()]);
            exit;
        }

        $socket = $this->socket();
        $socket->connect($dsn);
        $socket->push('request.event', ['source', 'parent']);

        $event = null;
        Event::listen('response.event', function () use (&$event) {
            $event = ['event' => Event::firing(), 'payload' => func_get_args()];
            return true;
        });

        $this->assertTrue($socket->pullAndFire());

        $this->assertSame(
            [
                'event' => 'response.event',
                'payload' => [
                    [
                        'event' => 'request.event',
                        'payload' => ['source', 'parent'],
                        'address' => null,
                    ],
                ],
            ],
            $event
        );
    }

    public function testPushError()
    {
        $socket = $this->socket();

        Event::listen('zeroevents.push.error', function ($object, $event, $payload, $address) use ($socket) {
            $this->assertSame($socket, $object);
            $this->assertSame('request.event', $event);
            $this->assertSame([], $payload);
            $this->assertNull($address);
            return 'push error';
        });

        $this->assertSame('push error', $socket->push('request.event'));
    }

    public function testPullError()
    {
        $socket = $this->socket();
        $socket->connect('ipc://' . sys_get_temp_dir() . '/test-push.ipc');

        Event::listen('zeroevents.pull.error', function ($object) use ($socket) {
            $this->assertSame($socket, $object);
            return 'pull error';
        });

        $this->assertSame('pull error', $socket->pull());
    }
}
