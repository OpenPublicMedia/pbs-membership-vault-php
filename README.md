# PBS Membership Vault PHP Library

This library abstracts interactions with the 
[PBS Membership Vault API](https://docs.pbs.org/display/MV/Membership+Vault+API).

## Installation

Install via composer:

```bash
composer require openpublicmedia/pbs-membership-vault-php
```

## Use

The primary class provided by this library is `OpenPublicMedia\PbsMembershipVault\Client`. 
A `Client` instance can be used to interact with membership data in various ways.

### Response data structures

Responses from the `Client` class will return a Generator when querying for 
multiple memberships (e.g. with `getMemberships`) or a single `stdClass` object
when querying for a specific membership record (e.g. with `getMembershipById($id)`).

### Examples

#### Creating a client

```php
use OpenPublicMedia\PbsMembershipVault\Client;

$api_key = 'xxxxxxxxxxxxxx';
$api_secret = 'xxxxxxxxxxx';
$station_id = 'xxxxxxxxxxx';

$client = new Client($api_key, $api_secret, $station_id);
```

#### Getting a Membership by ID

```php
$id = 'xxxxxxxxxxx';
$membership = $client->getMembershipById($id);
var_dump($membership);
class stdClass#34 (18) {
  public $grace_period => string
  public $update_date => string
  public $first_name => string
  public $last_name => string
  public $create_date => string
  public $offer => string
  public $notes => string
  public $current_state =>
  class stdClass#28 (2) {
    public $explanation =>
    class stdClass#35 (3) {
      public $status => string
      public $timing => string
      public $token_activated bool
    }
    public $has_access => bool
  }
  public $membership_id => string
  public $start_date => string
  public $status => string
  public $token => string
  public $additional_metadata => string
  public $activation_date => string
  public $provisional => bool
  public $expire_date => string
  public $pbs_profile =>
  class stdClass#24 (7) {
    public $first_name => string
    public $last_name => string
    public $UID => string
    public $birth_date => string
    public $retrieval_status =>
    class stdClass#23 (2) {
      public $status => int
      public $message => string
    }
    public $email => string
    public $login_provider => string
  }
  public $email => string
}
```

#### Getting all activated Memberships

```php
$memberships = $client->getActivatedMemberships();
var_dump(count($memberships));
int(51763)
foreach ($memberships as $membership) {
    var_dump($membership);
    class stdClass#29 (18) {...}
}
```

#### Adding a new Membership

```php
$start_date = new DateTime();
$expire_date = clone($start_date);
$expire_date->add(new DateInterval('P1Y'));

$membership = $client->addMembership(
    'membership_id',
    'first_name',
    'last_name',
    'offer',
    $start_date
    $expire_date
);

var_dump($membership);
class stdClass#29 (18) {...}
```

#### Updating an existing Membership

```php
$membership_id = 'xxxxxxxx';

$membership = $client->getMembershipById($membership_id);
var_dump($membership->notes);
string(0) ""

$result = $client->updateMembership($membership_id, ['notes' => 'Updated notes']);
var_dump($result);
bool(true)

$membership = $client->getMembershipById($membership_id);
var_dump($membership->notes);
string(13) "Updated notes"
```

#### Handling exceptions

Most `Client` methods can throw `OpenPublicMedia\PbsMembershipVault\Exception\BadRequestException`.
This exception will include a JSON encoded message that can be used to determine
follow-up actions.

```php
$start_date = new DateTime();
$expire_date = clone($start_date);
$expire_date->add(new DateInterval('P1Y'));

try {
    $membership = $client->addMembership(
        'membership_id', 
        '', 
        '', 
        'offer', 
        $start_date, 
        $end_date
    );
} catch (BadRequestException $e) {
    $errors = json_decode($e->getMessage());
    var_dump($errors);
    class stdClass#31 (2) {
      public $first_name =>
      array(1) {
        [0] =>
        string(23) "This field is required."
      }
      public $last_name =>
      array(1) {
        [0] =>
        string(23) "This field is required."
      }
    }
}
```

The exception `OpenPublicMedia\PbsMembershipVault\Exception\MembershipNotFoundException`
is used to indicate that a Membership has not been found for a provided ID.

```php
$membership_id = 'does_not_exist';
try {
    $membership = $client->getMembershipById($membership_id);
} catch (MembershipNotFoundException $e) {
    $response = json_decode($e->getMessage());
    var_dump($response);
    class stdClass#29 (2) {
      public $type =>
      string(2) "id"
      public $value =>
      string(17) "does_not_exist"
    }
}
```

## Development goals

See [CONTRIBUTING](CONTRIBUTING.md) for information about contributing to
this project.

### v1

- [x] API authentication (`OpenPublicMedia\PbsMembershipVault\Client`)
- [x] API direct querying (`$client->request()`)
- [x] Result/error handling
- [x] Transparent paged response handling (`OpenPublicMedia\PbsMembershipVault\Response\PagedResponse`)
- [x] Membership
    - [x] Membership:get
    - [x] Membership:list_token (this "list" endpoint returns a single result)
    - [x] Membership:put
    - [x] Membership:update (PATCH)
    - [x] Membership:delete 
- [x] Memberships
    - [x] Membership:list
    - [x] Memberships:last_updated_since
    - [x] Memberships:active?
    - [x] Memberships:email
    - [x] Membership:deleted
    - [x] Membership:deleted?since
    - [x] Membership:list_activated
    - [x] Membership:list_activated/?since
    - [x] Membership:list_provisional
    - [x] Membership:list_provisional/?since
    - [x] Membership:list_grace_period
    - [x] Membership:list_uid
- [x] Examples/detailed documentation

### v2.x

 - [ ] Membership entity (to replace stdClass)
 - [ ] Improved handling of API error responses
 - [ ] Improved handling of activation specifically
