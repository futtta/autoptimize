Issues
==
If you are a plugin user who has problems configuring Autoptimize (your site breaking in some way when activating AO) **the place to get help is [the Autoptimize support forum at wordpress.org](https://wordpress.org/support/plugin/autoptimize/)**.

If you are a developer and you think you discovered a bug in the code or if you have ideas for improvements, create an issue here on Github with a clear technical description.

If you are both developer & plugin user the choice is yours (it really depends on what type of support you need) ;-)

Pull requests
==
PR's are enthousiastically welcomed!

For optimal efficiency please take the following things into account:
1. use the **beta** branch as master is frozen to be at the same level as the current version on wordpress.org.
2. test your changes not only functionally, but also using the **unit tests** available (running `composer test` after having done `composer install` and running `bin/install-wp-tests.sh`)
3. (new) code is supposed to follow the WordPress code guidelines, test using **phpcs** (install with `composer install` and then run `vendor/squizlabs/bin/phpcs` against your changed/ added file

When in doubt or in need of guidance/ help create an issue describing what you would like to change and we'll figure it out! :-)

Frank
