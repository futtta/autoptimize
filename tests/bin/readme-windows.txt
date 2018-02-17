C:\github\autoptimize>C:\PortableGit\bin\bash.exe bin/install-wp-tests.sh wordpress_test webodjel webber localhost 4.3.1

C:\PortableGit\bin\bash.exe install-wp-tests.sh

Open `C:\Windows\Temp\wordpress-tests-lib\wp-tests-config.php` file and:

		# TODO/FIXME: 
		# In msys/mingw case $WP_TESTS_DIR has the "wrong" style
		# of path written in the config file, i.e.: 
		#
		# ```php
		# define( 'ABSPATH', '/c/Windows/Temp/wordpress/' );
		# ```
		#
		# If that's happening to you, edit that file manually once after 
		# running this script and change it so it becomes:
		#
		# ```php
		# define( 'ABSPATH', 'C:/Windows/Temp/wordpress/' );
		# ```
		#
		# Running `php phpunit.phar` within your plugin directory should 
		# work as expected after that.
