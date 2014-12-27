zeroevents
==========

Events between processes. Built on top of Illuminate\Events and ZeroMQ

## Installation

* 1. Install package using [composer](https://getcomposer.org/)
```
composer require ptrofimov/zeroevents:1.*
```
* 2. Copy config from vendor directory to your project. Or use [laravel publish](http://laravel.com/docs/4.2/packages#package-configuration)
```
cp vendor/ptrofimov/zeroevents/config/zeroevents.php ./app/config/
```
* 3. Define addresses of required sockets in the config. More information about types and abilities of sockets you could find [here](http://zguide.zeromq.org/page:all#toc11)

## Usage

* 1. **In the first process:** subscribe EventRouter to desired events. For each define socket where events will be transferred to.

```php
Event::subscribe(
    new EventRouter([
        'something.*' => Socket::get('service')
    ])
);
```

* 2. **In the second process:** define listeners for events.

```php
Event::listen('*', function () {
    dd(['event' => Event::firing(), 'payload' => func_get_args()]);
});
```

* 3. **In the second process:** run event service that will listen to specified sockets and fire events.

```php
(new EventService([
    Socket::get('service.listen')
]))->run();
```

That's it. Each time the event will be fired in first process, that will be transferred to the second process and be fired there as well.

You could see also an example for [laravel](example/laravel.php) and [non-laravel](example/non-laravel.php) based projects.

## Running tests

* 1. Install [phpunit](https://phpunit.de/getting-started.html)
* 2. Run tests with phpunit
```
phpunit --bootstrap vendor/autoload.php tests
```

## EventSocket class

EventSocket class is inherited from ZMQSocket. Its supports all native methods like connect and send,
plus it adds methods for sending and receiving events.

### Methods

* **encode**(string *event*, array *payload*) - serialize event and payload into array of frames before sending
 * event goes as string in the first frame of message
 * payload is serialized in JSON and goes in the following frames of message
 * encode method is used in **push** method
* **decode**(array *frames*) - unserialize event and payload from array of frames after receiving
 * return array [event, payload]
 * event is supposed to go as string in the first frame of message
 * payload is supposed to go as serialized in JSON in the following frames of message
 * decode method is used in **pull** method

### Connecting socket

You can use usual way to connect to socket via calling the constructor of ZMQSocket
or, better, use **EventListener** that gives you connected socket on the base of options from config.

```php
use ZeroEvents\EventSocket;

$socket = new EventSocket(new ZMQContext, ZMQ::SOCKET_PUSH);
$socket->connect('ipc:///var/tmp/test.ipc');
```

### Pushing events

It is recommended to listen to events that occur during sending of the message (ttl expired, out of connection)
to handle them.

```php
Event::listen('zeroevents.push.error', function () {
    // logging or something else
});

$socket->push('event', ['payload']);
```

## License

Copyright (c) 2014 Petr Trofimov

MIT License

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
