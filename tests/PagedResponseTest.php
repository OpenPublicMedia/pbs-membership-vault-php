<?php


namespace OpenPublicMedia\PbsMembershipVault\Test;

use Generator;
use OpenPublicMedia\PbsMembershipVault\Query\Results;
use OpenPublicMedia\PbsMembershipVault\Response\PagedResponse;

/**
 * Class PagedResponseTest
 *
 * @coversDefaultClass \OpenPublicMedia\PbsMembershipVault\Response\PagedResponse
 *
 * @package OpenPublicMedia\PbsTvSchedulesService\Test
 */
class PagedResponseTest extends TestCaseBase
{
    private function getAllMemberships()
    {
        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-2'));
        return $this->client->getAllMemberships();
    }

    public function testArrayResults()
    {
        $this->mockHandler->append($this->jsonFixtureResponse('genericMembershipsArray'));
        $result = $this->client->getMembershipsByUid('uid');
        $this->assertInstanceOf(Results::class, $result);
        $this->assertCount(1, $result);
    }

    public function testEmptyResults()
    {
        $this->mockHandler->append($this->apiJsonResponse(404));
        $results = $this->client->getAllMemberships();
        $this->assertCount(0, $results);
    }

    public function testResults()
    {
        $result = $this->getAllMemberships();
        foreach ($result as $item) {
            $this->assertIsObject($item);
        }
    }

    public function testNavigation()
    {
        $result = $this->getAllMemberships();
        $pagedResponse = $result->getResponse();
        $this->assertIsObject($pagedResponse->current());
        $this->assertEquals(1, $pagedResponse->key());
        $pagedResponse->next();
        $this->assertEquals(2, $pagedResponse->key());
        $pagedResponse->rewind();
        $this->assertEquals(1, $pagedResponse->key());
    }

    /**
     * @covers ::count
     */
    public function testCount()
    {
        $result = $this->getAllMemberships();
        $pagedResponse = $result->getResponse();
        $this->assertCount(2, $pagedResponse);
    }

    /**
     * @covers ::getTotalItemsCount
     */
    public function testGetTotalItemsCount()
    {
        $result = $this->getAllMemberships();
        $pagedResponse = $result->getResponse();
        $this->assertEquals(4, $pagedResponse->getTotalItemsCount());
    }
}
