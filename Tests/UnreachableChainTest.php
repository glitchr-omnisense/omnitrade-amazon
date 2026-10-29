<?php

namespace Omnitrade\Amazon\Tests;

use Omnitrade\Amazon\Api;
use Omnitrade\Exception\ProviderException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Nothing of Amazon's Creators API's credentials anywhere in what a log of a failed call writes: the messages,
 * the previous exception's (a copy of the HTTP client's, the secrets masked) and the traces'
 * arguments, which PHP keeps unless zend.exception_ignore_args is on.
 */
final class UnreachableChainTest extends TestCase
{
    private const SECRETS = ['amzn-creators-secret-2c8e41', 'issued-token-creators-7b1d06'];

    private string|false $ignoreArgs = false;

    protected function setUp(): void
    {
        $this->ignoreArgs = ini_set('zend.exception_ignore_args', '0');
    }

    protected function tearDown(): void
    {
        ini_set('zend.exception_ignore_args', false !== $this->ignoreArgs ? $this->ignoreArgs : '1');
    }

    /** @return iterable<string, array{\Closure(Api): mixed}> */
    public static function calls(): iterable
    {
        yield 'items' => [static fn (Api $api): mixed => $api->getItems(['B000000001'])];
        yield 'search' => [static fn (Api $api): mixed => $api->searchItems('stylo')];
    }

    #[DataProvider('calls')]
    public function testNoPartOfAnUnreachableChainHoldsTheSecrets(\Closure $call): void
    {
        self::assertClean($call, static function (string $method, string $url): never {
            throw new TransportException(\sprintf('Could not resolve host: x for "%s".', $url), 6);
        });
    }

    #[DataProvider('calls')]
    public function testAnUnreachableCallAfterTheTokenHoldsNoSecret(\Closure $call): void
    {
        self::assertClean($call, static function (string $method, string $url): MockResponse {
            if (str_contains($url, '/auth/o2/token')) {
                return new MockResponse(json_encode(['access_token' => 'issued-token-creators-7b1d06', 'expires_in' => 3600]));
            }

            throw new TransportException(\sprintf('Could not resolve host: x for "%s".', $url), 6);
        });
    }

    #[DataProvider('calls')]
    public function testAnErrorAnswerKeepsNoSecretInTheTraces(\Closure $call): void
    {
        self::assertClean($call, static fn (string $method, string $url): MockResponse => str_contains($url, '/auth/o2/token') ? new MockResponse(json_encode(['access_token' => 'issued-token-creators-7b1d06', 'expires_in' => 3600])) : new MockResponse('{"message":"down"}', ['http_code' => 500]));
    }

    private static function assertClean(\Closure $call, \Closure $responses): void
    {
        $api = new Api(new MockHttpClient($responses), 'amzn-creators-id', 'amzn-creators-secret-2c8e41', 'tag-21', 'www.amazon.fr', 'https://api.amazon.co.uk/auth/o2/token');
        try {
            $call($api);
            self::fail('no exception');
        } catch (ProviderException $e) {
            self::assertNotEmpty($e->getTrace());
            for ($one = $e; null !== $one; $one = $one->getPrevious()) {
                // A boolean, not assertStringNotContainsString(): a trace with its arguments is too large to export.
                $trace = print_r($one->getTrace(), true).$one->getTraceAsString();
                foreach (self::SECRETS as $secret) {
                    self::assertStringNotContainsString($secret, $one->getMessage());
                    self::assertFalse(str_contains($trace, $secret), \sprintf('The trace of %s holds "%s".', $one::class, $secret));
                }
            }
        }
    }
}
