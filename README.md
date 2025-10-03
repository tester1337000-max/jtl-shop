# JTL-Shop

|**JTL-Shop** is an open source shop software designed for use with JTL-Wawi. |
|:-----------------:|

## System Requirements

**Webserver**
* Apache Version 2.2 or 2.4
	* mod_rewrite module activated
	* .htaccess support (allowed to override options)
* nginx
  
**Database** 
* MySQL 5 >= 5.6
* MariaDB >= 10.1

**PHP**
* PHP 8.2 or greater
* PHP-Modules: 
	* [SimpleXML](https://php.net/manual/en/book.simplexml.php)
	* [ImageMagick + Imagick](https://php.net/manual/en/book.imagick.php)
	* [Curl](https://php.net/manual/en/book.curl.php)
	* [Iconv](https://php.net/manual/en/book.iconv.php)
	* [MBString](https://php.net/manual/en/book.mbstring.php)
	* [Tokenizer](https://php.net/manual/en/book.tokenizer.php)
	* [Intl](https://www.php.net/manual/de/book.intl.php)
	* [PDO (MySQL)](https://php.net/manual/en/book.pdo.php)
	* Optional: [IonCube Loader](https://www.ioncube.com/loaders.php) for some third-party plug-ins
* PHP Settings
	* `max_execution_time` >= 120s
	* `memory_limit` >= 128MB
	* `upload_max_filesize` >= 8MB
	* `allow_url_fopen` activated

## Software boundaries
* See [Software boundaries and limits](https://jtl-url.de/limits) for details

## License 
* MIT License - see [LICENSE.md](LICENSE.md)

## Changelog
* See [issues.jtl-software.de](https://issues.jtl-software.de/issues?project=JTL-Shop) or review commits in [Gitlab](https://gitlab.com/jtl-software/jtl-shop/core) for the latest changes

## Third party libraries
* [Smarty](https://www.smarty.net/) - LGPL
* Guzzle - MIT
* Intervention Image - MIT
* elFinder - BSD
* CodeMirror - MIT
* Minify
* PCLZip - LGPL
* PHPMailer - LGPL
* phpQuery - MIT

### Frontend libraries
* jQuery + jQuery UI + various jQuery Scripts - MIT
* Bootstrap + Bootstrap-Scripts - MIT
* FileInput - BSD
* typeAhead - MIT
* LESS Leaner CSS - Apache v2 License
* [slick](https://github.com/kenwheeler/slick/) - MIT

## Contributing

If you want to participate in the development of JTL-Shop,  
you can do it in the following way:

* first create your own *fork* of this repository
* then create a *branch* in your own *fork*
* finally make a merge request for that *branch* in the *upstream* of your *fork*

Please DO NOT create a merge request of your own master branch!

A short description of the procedure can be found here: 
[contributing](https://docs.jtl-shop.de/de/latest/shop_programming_tips/contributing.html)

## Related Links

* [JTL](https://www.jtl-software.de/) - JTL-Software Homepage
* [JTL Userguide](https://guide.jtl-software.de/) - Userguide
* [JTL Developer Documentation](https://docs.jtl-shop.de/) - Developer Docs
* [JTL Community](https://forum.jtl-software.de/) - JTL-Forum 
* [JTL Shop-Entwicklung](https://gitlab.com/jtl-software/jtl-shop/core) - GitLab Repository
* [JTL Shop-Builds](https://build.jtl-shop.de/) - Ready-to-use zip archives 
