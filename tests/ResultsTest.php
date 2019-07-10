<?php


namespace OpenPublicMedia\PbsMembershipVault\Test;

use Generator;
use OpenPublicMedia\PbsMembershipVault\Response\PagedResponse;

/**
 * Class ResultsTest
 *
 * @coversDefaultClass \OpenPublicMedia\PbsMembershipVault\Query\Results
 *
 * @package OpenPublicMedia\PbsTvSchedulesService\Test
 */
class ResultsTest extends TestCaseBase
{
    /**
     * @covers ::count
     */
    public function testCount()
    {
        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $result = $this->client->getAllMemberships();
        $this->assertCount(4, $result);
    }

    /**
     * @covers ::getResponse
     */
    public function testGetResponse()
    {
        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $result = $this->client->getAllMemberships();
        $response = $result->getResponse();
        $this->assertInstanceOf(PagedResponse::class, $response);
    }

    /**
     * @covers ::getIterator
     */
    public function testGetIterator()
    {
        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-2'));
        $result = $this->client->getAllMemberships();
        $iterator = $result->getIterator();
        $this->assertInstanceOf(Generator::class, $iterator);
        foreach ($result as $item) {
            $this->assertIsObject($item);
            continue;
        }
    }
}
