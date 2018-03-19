<?php

namespace hamburgscleanest\GuzzleAdvancedThrottle\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use hamburgscleanest\GuzzleAdvancedThrottle\Middleware\ThrottleMiddleware;
use hamburgscleanest\GuzzleAdvancedThrottle\RequestLimitRuleset;
use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Class MemcachedTest
 * @package hamburgscleanest\GuzzleAdvancedThrottle\Tests
 */
class MemcachedTest extends TestCase
{

    /** @test
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function requests_are_cached() : void
    {
        $host = 'www.test.de';
        $ruleset = new RequestLimitRuleset([
            $host => [
                [
                    'max_requests' => 2
                ]
            ]
        ],
            'cache',
            'laravel',
            new Repository([
                'cache' => [
                    'driver'  => 'memcached',
                    'options' => [
                        'servers' => [
                            [
                                'host'   => '127.0.0.1',
                                'port'   => 11211,
                                'weight' => 100,
                            ],
                        ]
                    ]
                ]
            ]));
        $throttle = new ThrottleMiddleware($ruleset);
        $stack = new MockHandler([new Response(200, [], null, '1'), new Response(200, [], null, '2'), new Response(200, [], null, '3')]);
        $client = new Client(['base_uri' => $host, 'handler' => $throttle->handle()($stack)]);

        $responseOne = $client->request('GET', '/')->getProtocolVersion();
        $responseTwo = $client->request('GET', '/')->getProtocolVersion();
        $responseThree = $client->request('GET', '/')->getProtocolVersion();

        $this->assertNotEquals($responseOne, $responseTwo);
        $this->assertEquals($responseTwo, $responseThree);
    }

    /** @test
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function throw_too_many_requests_when_nothing_in_cache() : void
    {
        $host = 'www.test.de';
        $ruleset = new RequestLimitRuleset([
            $host => [
                [
                    'max_requests' => 0
                ]
            ]
        ],
            'cache',
            'laravel',
            new Repository([
                'cache' => [
                    'driver'  => 'memcached',
                    'options' => [
                        'servers' => [
                            [
                                'host'   => '127.0.0.1',
                                'port'   => 11211,
                                'weight' => 100,
                            ],
                        ]
                    ]
                ]
            ]));
        $throttle = new ThrottleMiddleware($ruleset);
        $stack = new MockHandler([new Response()]);
        $client = new Client(['base_uri' => $host, 'handler' => $throttle->handle()($stack)]);

        $this->expectException(TooManyRequestsHttpException::class);
        $client->request('GET', '/');
    }

}