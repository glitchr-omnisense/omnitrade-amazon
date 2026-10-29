# Changelog

## Unreleased (1.x)

### Fixed
- A `ProviderException` no longer carries the Creators API's client secret and the token. The
  message quoted the HTTP client's (the whole URL), the previous was the client's own exception, and
  every trace of the chain kept the arguments of the calls under it (the request's options, the
  headers with the credentials) when `zend.exception_ignore_args` is off: a log of the whole chain
  wrote them. The message masks them as `***`, the previous is a copy of the client's exception (the
  same class and code), and no exception `Api` throws keeps its trace's arguments
  (`Omnitrade\Http\Redaction`).
- `Product::$description` is HTML, as the family's contract has it: the item's features as a list
  (`<ul><li>`, each escaped), where they came as plain text lines.
