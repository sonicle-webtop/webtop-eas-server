# WebTop EAS Server (Exchange Active Sync)

[![License](https://img.shields.io/badge/license-AGPLv3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0.txt)

This package adds EAS capabilities to WebTop platform allowing synchronization with ActiveSync compatible devices such as mobile phones, tablets and Outlook 2013 and above.
You can find an indicative compatibility table [here](https://wiki.z-hub.io/display/ZP/Compatibility).

 &#9888; Version `2.4.5.20` has its own logging module, instead of rely on ZPush internal implementation. So, from this version onward, no dedicated "errors log" (`server_error.log`) file will be produced anymore.

## Requirements

This backend is built on top of [Z-Push](http://z-push.org/), so you need to so you need to satisfy at least z-push [requirements](https://wiki.z-hub.io/display/ZP/Installation+from+source).
Then, summing:

* PHP >= 5.5.X (with php-imap and php-mbstring extensions installed)
* Apache with mod_php
* WebTop instance supporting EAS REST API (core >= v.5.6.0, calendar >= v.5.6.0, contacts >= v.5.6.0, tasks >= v.5.3.0)

## Installation

The standard installation is to create a dedicated folder `webtop-eas` into your Apache's document-root, copy [server sources](./src) into it and then configure your VirtualHost in the right way.

```xml
<VirtualHost *:*>
  #...
  <IfModule mod_alias.c>
    Alias /Microsoft-Server-ActiveSync "/path/to/your/htdocs/webtop-eas/index.php"
  </IfModule>
  #...
  <directory "/path/to/your/htdocs/webtop-eas">
    AllowOverride All
    <IfModule !mod_authz_core.c>
      Order allow,deny
      allow from all
    </IfModule>
    <IfModule mod_authz_core.c>
      Require all granted
    </IfModule>
  </directory>
  #...
</VirtualHost>
```

### Service AutoDiscovery (&#9888; not supported yet)

AutoDiscover is the service used to simplify the configuration of collaboration accounts for clients, especially for mobile phones.

If AutoDiscover configuration is turned-on, the user is only required to fill in his email address and the password on his phone.
You can find a detailed explanation [here](https://wiki.z-hub.io/display/ZP/Configuring+Z-Push+Autodiscover/).

If you followed the standard installation (subfolder under your Apache's document-root), you can update you virtual-host configuration simply adding the following lines:

```xml
  #...
  <IfModule mod_alias.c>
    Alias /AutoDiscover/AutoDiscover.xml "/path/to/your/htdocs/webtop-eas/autodiscover.php"
    Alias /Autodiscover/Autodiscover.xml "/path/to/your/htdocs/webtop-eas/autodiscover.php"
    Alias /autodiscover/autodiscover.xml "/path/to/your/htdocs/webtop-eas/autodiscover.php"
  </IfModule>
  #...
```

### Authentication

This EAS server (as stated [below](#eas-support)) uses HTTP Basic authentication.
Remember that in some cases Apache needs to be configured allowing pass headers to PHP like in this way:

```xml
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
```

Always enable SSL in production environments, Basic authentication is secure only when used over an encrypted connection.

## Configuration

EAS server configuration is done via `config.json` file placed inside your installation root folder.
You can start by copying the example [example.config.json](./src/example.config.json) configuration file, that carries all basic options needed for start-up:

```shell
 $ cp example.config.json config.json
 $ vi config.json
```

This setup relies on some internal defaults that you do not need to change and are suitable for most common situations.
You can instead find a fully-featured example in [example-full.config.json](./src/example-full.config.json) file.

### Options

At the bare minimum, you need to set values to the following options: *log.dir*, *state.dir* and *webtop.apiBaseUrl*. (you can find them below marked with &#9888;)

* `timezone` \[string]
  The default server timezone. It must be one of the [supported timezones](http://www.php.net/manual/en/timezones.php), excluding those that do not start with the following prefixes: Africa, America, Asia, Atlantic, Australia, Europe, Indian, Pacific. *(Defaults to: Europe/Rome)*
* `log.level` \[string]
  The actual logging level. Allowed values are: OFF, ERROR, WARN, INFO, DEBUG, TRACE. *(Defaults to: `ERROR`)*
  These levels are mapped back tp z-push own levels: ERROR displays also fatal entries, and TRACE will produces WBXML output. If you need more control over z-push levels you can edit [zpush.config.php](./src/inc/zpush.config.php) file manually.
* &#9888; `log.dir` \[string]
  A path that points to the directory where the log files (see below) will be written. (without trailling separator)
* `log.file` [string]
  This can be used for specifying the log full file-name directly, instead of using `log.dir` (and `log.name`). This takes precedence over the previous ones. NB: If you want to redirect all logging output to stdout you can simply point this property to `php://stdout`.
* `log.name` \[string]
  The log filename. *(Defaults to: `server.log`)*
* `log.specialUsers` \[string[]]
  Comma separated list of users for which generate a dedicated log file in order to simplify debugging.
  Files will be produced in `log.dir` at `TRACE` loggin level.
* &#9888; `state.dir` \[string]
  A path that points to the directory where the state files/directories will be written. (without trailling separator)
  Note that the configured webserver (or php processor) needs to have full read and write permissions on this directory.
* `state.useLegacyFolderIds` \[boolean]
  True to activate backward compatibility to old `z-push-webtop` folderIDs, this prevents the device from performing a full resync of data after transitioning from `z-push-webtop` to `webtop-eas-server`. *(Defaults to: `false`)*
* &#9888; `webtop.apiBaseUrl` \[string]
  This server relies on REST APIs in order to gather all the information for serving clients. This URL reflects the address at which the current WebTop installation responds to. Note that since this is basically a server-to-server configuration, you could use local addresses; this will speed-up HTTP requests. Eg. `http://localhost:8080/webtop`.
* `webtop.apiUrlPath` \[string]
  Path, added to the base, to target the REST endpoint for core related calls. This should not be changed. *(Defaults to: `/api/com.sonicle.webtop.core/v1`)*
* `mail.enabled` \[boolean]
  False to disable email support. *(Defaults to: `true`)*
* `mail.imapServer` \[string]
  The IMAP server address. *(Defaults to: `localhost`)*
* `mail.imapPort` \[int]
  The IMAP server port. *(Defaults to: `143`)*
* `calendar.enabled` \[boolean]
  False to disable events/appointmens support. *(Defaults to: `true`)*
* `calendar.apiUrlPath` \[string]
  Path, added to the base, to target the REST endpoint for calendar related calls. This should not be changed. *(Defaults to: `/api/com.sonicle.webtop.calendar/v1`)*
* `contacts.enabled` \[boolean]
  False to disable contacts support. *(Defaults to: `true`)*
* `contacts.apiUrlPath` \[string]
  Path, added to the base, to target the REST endpoint for calendar related calls. This should not be changed. *(Defaults to: `/api/com.sonicle.webtop.contacts/v1`)*
* `tasks.enabled` \[boolean]
  False to disable tasks support. *(Defaults to: `true`)*
* `tasks.apiUrlPath` \[string]
  Path, added to the base, to target the REST endpoint for tasks related calls. This should not be changed. *(Defaults to: `/api/com.sonicle.webtop.tasks/v1`)*

#### Example

```json
{
	"timezone": "Europe/Rome",
	"log": {
		"level": "ERROR",
		"dir": "/var/log/webtop-eas"
	},
	"state": {
		"dir": "/var/lib/webtop-eas/state"
	},
	"webtop": {
		"apiBaseUrl": "http://localhost:8080/webtop"
	}
}
```

## Build

### Client REST API

The implemented backends relies on a set of REST API endpoints in order to get all the data needed to satisfy ActiveSync requests. Client API code, that dialogues with remote endpoint, is generated through swagger-codegen against a OpenAPI-Specification file that can be found in the related WebTop service project repository.

Core REST client implementation can be re-generated in this way:
```shell
 $ ./bin/make-core-client.sh
```
Calendar client like so:
```shell
 $ ./bin/make-calendar-client.sh
```
And again, contacts using:
```shell
 $ ./bin/make-contacts-client.sh
```
Terminating with tasks client:
```shell
 $ ./bin/make-tasks-client.sh
```

## License

This is Open Source software released under [AGPLv3](./LICENSE)
