release:
	@rm -f ../Wordpress/Bynder-Wordpress-Plugin.zip
	@rm -f ../Wordpress/Bynder-Wordpress-Plugin-with-source-code.zip
	@if [ ! -d "build" ]; then npm run build; fi
	zip ../Wordpress/Bynder-Wordpress-Plugin.zip bynder-wordpress.php src/init.php src/admin.js help.txt build/*