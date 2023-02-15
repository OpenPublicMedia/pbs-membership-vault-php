<?php


namespace OpenPublicMedia\PbsMembershipVault\Test;

use Generator;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use OpenPublicMedia\PbsMembershipVault\Client;
use OpenPublicMedia\PbsMembershipVault\Query\Results;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

class TestCaseBase extends TestCase
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var MockHandler
     */
    protected $mockHandler;

    /**
     * Create client with mock handler.
     */
    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $this->client = new Client(
            'api_key',
            'secret',
            'station_id',
            Client::LIVE,
            ['handler' => $this->mockHandler]
        );
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object
     *   Instantiated object that we will run method on.
     * @param string $methodName
     *   Method name to call
     * @param array $parameters
     *   Array of parameters to pass into method.
     *
     * @return mixed
     *   Method return.
     *
     * @throws ReflectionException
     *
     * @url https://jtreminio.com/blog/unit-testing-tutorial-part-iii-testing-protected-private-methods-coverage-reports-and-crap/#targeting-private-protected-methods-directly
     */
    public function invokeMethod(&$object, string $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    /**
     * @param string $name
     *   Base file name for a JSON fixture file.
     * @param int $code
     *   Response status code.
     *
     * @return Response
     *   Guzzle 200 response with JSON body content.
     */
    protected static function jsonFixtureResponse(string $name, int $code = 200): Response
    {
        return self::apiJsonResponse(
            $code,
            file_get_contents(__DIR__ . '/fixtures/' . $name . '.json')
        );
    }

    protected static function apiJsonResponse(int $code, string $json = '[]'): Response
    {
        return new Response($code, ['Content-Type' => 'application/json'], $json);
    }

    /**
     * Gets and verifies contents of a Results Generator.
     *
     * @param Results $result
     *   Results from an API query.
     * @param int $count
     *   Expected size of Results.
     */
    protected function verifyGenerator(Results $result, int $count): void
    {
        $generator = $result->getIterator();
        $this->assertInstanceOf(Generator::class, $generator);
        $this->assertCount($count, $result);
    }
}
