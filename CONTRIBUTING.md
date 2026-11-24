# Contributing

1. `composer update`
2. Make your change with a test.
3. Run the checks CI runs:

   ```sh
   composer test   # PHPUnit
   composer stan   # PHPStan, level max
   composer cs     # php-cs-fixer (composer cs-fix to apply)
   ```

Unit tests must not touch the network. `tests/LiveTest.php` runs against the real API only when
`TRACKIFIED_API_KEY` is set; it creates one tracking and deletes it.

Releases: bump `TrackifiedClient::VERSION` and `CHANGELOG.md`, then push a `vX.Y.Z` tag.
