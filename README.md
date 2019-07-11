# PBS Membership Vault PHP Library

This library abstracts interactions with the 
[PBS Membership Vault API](https://docs.pbs.org/display/MV/Membership+Vault+API).

## Use

The primary class provided by this library is 
`OpenPublicMedia\PbsMembershipVault\Client`. A `Client` instance can be used to 
interact with membership data in various ways.

### Response data structures

Responses from the `Client` class will return a Generator when querying for 
multiple memberships (e.g. with `getMemberships`) or a single object when 
querying for a specific membership record (e.g. with `getMembership($id)`).

### Examples

TK

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
- [ ] Examples/detailed documentation

### v2.x

 - [ ] Membership entity
 - [ ] Improved handling of API error responses
 - [ ] Improved handling of activation specifically
