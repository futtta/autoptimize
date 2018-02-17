Autoptimize
===========

The [official Autoptimize repo on Github can be found here](https://github.com/futtta/autoptimize/).

## Installing/running the tests
* Install wp test suite by running `bin/install-wp-tests.sh`
* Run `composer install`
* Now you should be able to run either `composer test` or `phpunit`

Have a read through `tests/test-ao.php` and `tests/bootstrap.php` if you'd like to know more.

Ideally, this should be switched to a more modern setup using https://github.com/Brain-WP/BrainMonkey -- once the AO codebase allows for easier testing. One day, maybe.
