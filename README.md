slimdump
========

`slimdump` is a little tool to help you creating configurable dumps of large MySQL-databases. It works off one or several configuration files. For every table you specify, it can dump only the schema (`CREATE TABLE ...` statement), full table data, data without blobs and more.

## Why?
First off - if you just need a full dump of your database you will be better off with mysqldump. But if you regularly need complex dumps like e. g. full dumps of all your content tables, only the schema of log tables or tables containing user details, and your media tables without BLOBs (just in case you're storing BLOBs in the database): welcome to slimdump!

## Installation
To install slimdump systemwide, just run `composer global require webfactory/slimdump`. To use slimdump as a regular Unix command, just add it to the PATH in your `~/.profile`:
`export PATH=~/.composer/vendor/bin:$PATH`

## Usage
`slimdump` needs the DSN for the database to dump and one or more config files:
`slimdump {DSN} {config-file} [...more config files...]`

`slimdump` writes to STDOUT. If you want your dump written to a file, just redirect the output:
`slimdump {DSN} {config-file} > dump.sql`

## Configuration
Configuration is stored in XML format somewhere in your filesystem. As a benefit, you could add the configuration to your repository to share a quickstart to your database dump with your coworkers.

Example:
```xml
<?xml version="1.0" ?>
<slimdump>
  <!-- Create a full dump (schema + data) of "some_table" -->
  <table name="some_table" dump="full" />
  
  <!-- Dump the "media" table, omit BLOB fields. -->
  <table name="media" dump="noblob" />
  
  <!-- Dump the "users" table, hide names and email addresses. -->
  <table name="user" dump="full">
      <column name="username" dump="masked" />
      <column name="email" dump="masked" />
  </table>
  
</slimdump>
```

### Dump modes

The following modes are supported for the `dump` attribute:
* `none` - Table is not dumped at all. Makes sense if you use broad wildcards (see below) and then want to exclude a specific table.
* `schema` - Only the table schema will be dumped
* `noblob` - Will dump a `NULL` value for BLOB fields
* `full` - Whole table will be dumped
* `masked` - Replaces all chars with "x". Mostly makes sense when applied on the column level, for example for email addresses or user names.

### Wildcards
Of course you can use wildcards for table names (* for multiple characters, ? for a single character).

Example:
```xml
<?xml version="1.0" ?>
<slimdump>
  <!-- Default: dump all tables -->
  <table name="*" dump="full" />
  
  <!-- Dump all tables beginning with "a_" as schema -->
  <table name="a_*" dump="schema" />
  
  <!-- Dump "big_blob_table" without blobs -->
  <table name="big_blob_table" dump="noblob" />
  
  <!-- Do not dump any tables ending with "_test" -->
  <table name="*_test" dump="none" />
</slimdump>
```
This is a valid configuration. If more than one instruction matches a specific table name, the most specific one will be used. E. g. if you have definitions for blog_* and blog_author, the latter will be used for your author table, independent of their sequence order in the config.

## Other databases
Currently only MySQL is supported. But feel free to port it to the database of your needs.

## Development

### Building the Phar

Download [Box](https://github.com/box-project/box2) into the project root:

    curl -LSs https://box-project.github.io/box2/installer.php | php
    
Install dependencies via Composer:

    php composer.phar install
    
Start the Phar build:

    ./box.phar build -v
   
Use slimdump as Phar:

    ./slimdump.phar {DSN} {config-file}
    
### Tests

You can execute the phpunit-tests by calling `vendor/bin/phpunit`. 

## Credits, Copyright and License
This tool was started at webfactory GmbH in Bonn by [mpdude](https://github.com/mpdude).

- <http://www.webfactory.de>
- <http://twitter.com/webfactory>

Copyright 2014-2015 webfactory GmbH, Bonn. Code released under [the MIT license](LICENSE).
