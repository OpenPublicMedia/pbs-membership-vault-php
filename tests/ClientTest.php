<?php


namespace OpenPublicMedia\PbsMembershipVault\Test;

use DateTime;
use OpenPublicMedia\PbsMembershipVault\Exception\BadRequestException;
use OpenPublicMedia\PbsMembershipVault\Exception\MembershipNotFoundException;
use ReflectionException;
use RuntimeException;

/**
 * Class ClientTest
 *
 * @coversDefaultClass \OpenPublicMedia\PbsMembershipVault\Client
 *
 * @package OpenPublicMedia\PbsTvSchedulesService\Test
 */
class ClientTest extends TestCaseBase
{
    public function testRequestRuntimeException() {
        $this->mockHandler->append($this->apiJsonResponse(420));
        $this->expectException(RuntimeException::class);
        $this->client->request('get', 'memberships');
    }

    /**
     * @covers ::getNextPage
     */
    public function testGetNextPage()
    {
        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-2'));
        $result = $this->client->getAllMemberships();
        $this->assertCount(4, $result);
        // Iterate the result to trigger a ::getNextPage call.
        foreach ($result as $item) {
            $this->assertIsObject($item);
            continue;
        }
    }

    /**
     * @covers ::notEmptyOrNull
     *
     * @dataProvider notEmptyOrNullProvider
     *
     * @param $value
     *   Value to test with.
     * @param bool $expectation
     *   Expected result.
     *
     * @throws ReflectionException
     */
    public function testNotEmptyOrNull($value, bool $expectation) {
        $result = $this->invokeMethod($this->client, 'notEmptyOrNull', [$value]);
        $this->assertEquals($result, $expectation);
    }

    /**
     * @return array
     *   Test values and expectations.
     *
     * @see testNotEmptyOrNull()
     */
    public function notEmptyOrNullProvider() {
        return [
            ['', false],
            [null, false],
            [0, true],
            [1, true],
            ['string', true],
        ];
    }

    /**
     * @covers ::dateTimesToStrings
     */
    public function testDateTimesToStrings()
    {
        $data = ['no' => 'datetime', 'in' => 'this', 'array' => '.'];
        $initial = $data;
        $this->invokeMethod($this->client, 'dateTimesToStrings', [&$data]);
        $this->assertEquals($initial, $data);

        $dt = new DateTime();
        $data['dt'] = $dt;
        $initial = $data;
        $this->invokeMethod($this->client, 'dateTimesToStrings', [&$data]);
        $this->assertNotEquals($initial, $data);
        $this->assertEquals($data['dt'], $dt->format($this->client::DATETIME_FORMAT));
    }

    public function testAddMembership()
    {
        $this->mockHandler->append($this->apiJsonResponse(404));
        $this->mockHandler->append($this->jsonFixtureResponse('genericMembership'));
        $result = $this->client->addMembership(
            'id',
            'first_name',
            'last_name',
            'offer',
            new DateTime(),
            new DateTime()
        );
        $this->assertIsObject($result);
    }

    public function testAddMembershipBadRequestException()
    {
        $this->mockHandler->append($this->jsonFixtureResponse('genericMembership'));
        $this->expectException(BadRequestException::class);
        $this->client->addMembership(
            'id',
            'first_name',
            'last_name',
            'offer',
            new DateTime(),
            new DateTime()
        );
    }

    public function testUpdateMembership()
    {
        $this->mockHandler->append(self::apiJsonResponse(200));
        $result = $this->client->updateMembership('id', []);
        $this->assertTrue($result);
    }

    public function testUpdateMembershipMembershipNotFoundException()
    {
        $this->mockHandler->append(self::apiJsonResponse(404));
        $this->expectException(MembershipNotFoundException::class);
        $this->client->updateMembership('id', []);
    }

    public function testUpdateMembershipBadRequestException()
    {
        $this->mockHandler->append(self::apiJsonResponse(400));
        $this->expectException(BadRequestException::class);
        $this->client->updateMembership('id', []);
    }

    public function testActivateMembership()
    {
        $this->mockHandler->append(self::apiJsonResponse(200));
        $result = $this->client->activateMembership('id', 'uid');
        $this->assertTrue($result);
    }

    public function testDeleteMembership()
    {
        $this->mockHandler->append(self::apiJsonResponse(204));
        $result = $this->client->deleteMembership('id');
        $this->assertTrue($result);
    }

    public function testDeleteMembershipBadRequestException()
    {
        $this->mockHandler->append(self::apiJsonResponse(400));
        $this->expectException(BadRequestException::class);
        $this->client->deleteMembership('id');
    }

    public function testDeleteMembershipMembershipNotFoundException()
    {
        $this->mockHandler->append(self::apiJsonResponse(404));
        $this->expectException(MembershipNotFoundException::class);
        $this->client->deleteMembership('id');
    }

    public function testGetMembershipById()
    {
        $this->mockHandler->append($this->jsonFixtureResponse('genericMembership'));
        $result = $this->client->getMembershipById('id');
        $this->assertIsObject($result);
    }

    public function testGetMembershipByIdMembershipNotFoundException()
    {
        $this->mockHandler->append(self::apiJsonResponse(404));
        $this->expectException(MembershipNotFoundException::class);
        $this->client->getMembershipById('id');
    }

    public function testGetMembershipByToken()
    {
        $this->mockHandler->append($this->jsonFixtureResponse('genericMembership'));
        $result = $this->client->getMembershipByToken('token');
        $this->assertIsObject($result);
    }

    public function testGetMembershipByTokenMembershipNotFoundException()
    {
        $this->mockHandler->append(self::apiJsonResponse(404));
        $this->expectException(MembershipNotFoundException::class);
        $this->client->getMembershipByToken('token');
    }

    public function testGetAllMemberships()
    {
        $since = new DateTime();
        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $result = $this->client->getAllMemberships();
        $this->verifyGenerator($result, 4);

        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $result = $this->client->getAllMemberships($since);
        $this->verifyGenerator($result, 4);
    }

    public function testGetActiveMemberships() {
        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $result = $this->client->getActiveMemberships();
        $this->verifyGenerator($result, 4);

        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $result = $this->client->getActiveMemberships('email');
        $this->verifyGenerator($result, 4);

        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $datetime = new DateTime();
        $result = $this->client->getActiveMemberships(null, $datetime, $datetime);
        $this->verifyGenerator($result, 4);
    }

    public function testGetMembershipsByEmail()
    {
        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $result = $this->client->getMembershipsByEmail('email');
        $this->verifyGenerator($result, 4);
    }

    public function testGetMembershipsByUid()
    {
        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $result = $this->client->getMembershipsByUid('uid');
        $this->verifyGenerator($result, 4);
    }

    public function testGetActivatedMemberships()
    {
        $since = new DateTime();
        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $result = $this->client->getActivatedMemberships();
        $this->verifyGenerator($result, 4);

        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $result = $this->client->getActivatedMemberships($since);
        $this->verifyGenerator($result, 4);
    }

    public function testGetProvisionalMemberships()
    {
        $since = new DateTime();
        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $result = $this->client->getProvisionalMemberships();
        $this->verifyGenerator($result, 4);

        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $result = $this->client->getProvisionalMemberships($since);
        $this->verifyGenerator($result, 4);
    }

    public function testGetGracePeriodMemberships()
    {
        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $result = $this->client->getGracePeriodMemberships();
        $this->verifyGenerator($result, 4);
    }

    public function testGetDeletedMemberships()
    {
        $since = new DateTime();
        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $result = $this->client->getDeletedMemberships();
        $this->verifyGenerator($result, 4);

        $this->mockHandler->append($this->jsonFixtureResponse('genericMemberships-1'));
        $result = $this->client->getDeletedMemberships($since);
        $this->verifyGenerator($result, 4);
    }
}
