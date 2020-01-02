![webfactory Logo](https://www.webfactory.de/bundles/webfactorytwiglayout/img/logo.png) slimdump
========

[![Build Status](https://travis-ci.org/webfactory/slimdump.svg?branch=master)](https://travis-ci.org/webfactory/slimdump)
[![Coverage Status](https://coveralls.io/repos/webfactory/slimdump/badge.svg?branch=master&service=github)](https://coveralls.io/github/webfactory/slimdump?branch=master)

`slimdump` is a little tool to help you creating configurable dumps of large MySQL-databases. It works off one or several configuration files. For every table you specify, it can dump only the schema (`CREATE TABLE ...` statement), full table data, data without blobs and more.

## Why?

We created `slimdump` because we often need to dump parts of MySQL databases in a convenient and reproducible way. Also, when you need to analyze problems with data from your production databases, you might want to pull only relevant parts of data and also hide personal data (user names, for example).

`mysqldump` is a great tool, probably much more proven when it comes to edge cases and with a lot of switches. But there is no easy way to create a simple configuration file that describes a particular type of dump (e. g. a subset of your tables) and share it with your co-workers. Let alone dumping tables and omitting BLOB type columns. 

## Installation

When PHP is your everyday programming language, you probably have [Composer](https://getcomposer.org) installed. You can then easily install `slimdump` as a [global package](https://getcomposer.org/doc/03-cli.md#global). Just run `composer global require webfactory/slimdump`. In order to use it like any other Unix command, make sure `$COMPOSER_HOME/vendor/bin` is in your `$PATH`.

Of course, you can also add `slimdump` as a local (per-project) Composer dependency.

We're also working on providing a `.phar` package of `slimdump` for those not using PHP regularly. With that solution, all you need is to have the PHP interpreter installed and to download a single archive file to use `slimdump`. You can help us and open a pull request for that :-)!

## Usage
`slimdump` needs the DSN for the database to dump and one or more config files:

`slimdump {DSN} {config-file} [...more config files...]`

`slimdump` writes to STDOUT. If you want your dump written to a file, just redirect the output:

`slimdump {DSN} {config-file} > dump.sql`


If you want to use an environment variable for the DSN, replace the first parameter with `-`:

`MYSQL_DSN={DSN} slimdump - {config file(s)}`

The DSN has to be in the following format:

`mysql://[user[:password]@]host[:port]/dbname`

For further explanations have a look at the [Doctrine documentation](http://doctrine-orm.readthedocs.org/projects/doctrine-dbal/en/latest/reference/configuration.html#connecting-using-a-url).

You can also specify the buffer size, which can be useful on shared environments where your `max_allowed_packet` is low.
Do this by using the optional cli-option `buffer-size`. Add a suffix (KB, MB or GB) to the value for better readability.

Example:
`slimdump --buffer-size=16MB {DSN} {config-file}`

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
  
  <!-- Dump the "user" table, hide names and email addresses. -->
  <table name="user" dump="full">
      <column name="username" dump="masked" />
      <column name="email" dump="masked" />
      <column name="password" dump="replace" replacement="test" />
  </table>
  
  <!-- Dump the "document" table but do not pass the "AUTO_INCREMENT" parameter to the SQL query.
       Instead start to increment from the beginning -->
  <table name="document" dump="full" keep-auto-increment="false" />
  
  <!-- 
    Trigger handling: 
    
    By default, CREATE TRIGGER statements will be dumped for all tables, but the "DEFINER=..."
    clause will be removed to make it easier to re-import the database e. g. in development
    environments. 
    
    You can change this by setting 'dump-triggers' to one of:
        - 'false' or 'none': Do not dump triggers at all
        - 'true' or 'no-definer': Dump trigger creation statement but remove DEFINER=... clause
        - 'keep-definer': Keep the DEFINER=... clause
  -->
  <table name="events" dump="schema" dump-triggers="false" />
</slimdump>
```

### Conditions

You may want to select only some rows. In that case you can define a condition on a table.

```xml
<?xml version="1.0" ?>
<slimdump>
  <!-- Dump all users whose usernames begin with foo -->
  <table name="user" dump="full" condition="`username` LIKE 'foo%'" />
</slimdump>
```

In this example, only users with a username starting with 'foo' are exported:
A simple way to export roughly a percentage of the users is this:

```xml
<?xml version="1.0" ?>
<slimdump>
  <!-- Dump all users whose usernames begin with foo -->
  <table name="user" dump="full" condition="id % 10 = 0" />
</slimdump>
```

This will export only the users with a id divisible by ten without remainder, e.g. about 1/10th of the user rows (given
the ids are evenly distributed).

If you want to keep referential integrity, you might have to configure a more complex condition like this:

```xml
<?xml version="1.0" ?>
<slimdump>
  <!-- Dump all users whose usernames begin with foo -->
  <table name="user" dump="full" condition="id IN (SELECT author_id FROM blog_posts UNION SELECT author_id from comments)" />
</slimdump>
```

In this case, we export only users that are referenced in other tables, e.g. that are authors of blog posts or comments.


### Dump modes

The following modes are supported for the `dump` attribute:

* `none` - Table is not dumped at all. Makes sense if you use broad wildcards (see below) and then want to exclude a specific table.
* `schema` - Only the table schema will be dumped
* `noblob` - Will dump a `NULL` value for BLOB fields
* `full` - Whole table will be dumped
* `masked` - Replaces all chars with "x". Mostly makes sense when applied on the column level, for example for email addresses or user names.
* `replace` - When applied on a <column> element, it replaces the values in this column with either a static value or a nice dummy value generated by [Faker](https://github.com/fzaninotto/Faker/). Useful e.g. to replace passwords with a static ones or to replace personal data like the first and last name with realistically sounding dummy data.

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

### Replacements

You probably don't want to use any personal data from your database. Therefore, slimdump allows you to replace data on
column level - a great instrument not only for General Data Protection Regulation (GDPR) compliance. 

The most simple replacement is a static one:

```xml
<?xml version="1.0" ?>
<slimdump>
    <table name="users" dump="full">
        <column name="password" dump="replace" replacement="test" />
    </table>
</slimdump>
```

This replaces the password values of all users with "test" (in clear text - but for sure you have [some sort of hashing in place](https://secure.php.net/manual/en/faq.passwords.php), do you?).

To achieve realistically sounding dummy data, slimdump also allows [basic Faker formatters](https://github.com/fzaninotto/Faker/#formatters). 
You can use every Faker formatter which needs no arguments and modifiers such as `unique` (just seperate the modifier
with an object operator (`->`), as you would do in PHP). This is especially useful if your table has a unique constraint
on a column containing personal information, like the email address. 

```xml
<?xml version="1.0" ?>
<slimdump>
    <table name="users" dump="full">
        <column name="username" dump="replace" replacement="FAKER_word" />
        <column name="password" dump="replace" replacement="test" />
        <column name="firstname" dump="replace" replacement="FAKER_firstName" />
        <column name="lastname" dump="replace" replacement="FAKER_lastName" />
        <column name="email" dump="replace" replacement="FAKER_unique->safeEmail" />
    </table>
</slimdump>
```

## Other databases
Currently only MySQL is supported. But feel free to port it to the database of your needs.

## Development

### Building the Phar

* Make sure [Phive](https://phar.io/) is installed
* Run `phive install` to install tools, including [Box](https://github.com/humbug/box)
* Run `composer install --no-dev` to make sure the `vendor/` folder is up to date
* Run `tools/box compile` to build `slimdump.phar`.

### Tests

You can execute the phpunit-tests by calling `vendor/bin/phpunit`. 

## Credits, Copyright and License

This tool was started at webfactory GmbH in Bonn by [mpdude](https://github.com/mpdude).

- <https://www.webfactory.de>
- <https://twitter.com/webfactory>

Copyright 2014-2017 webfactory GmbH, Bonn. Code released under [the MIT license](LICENSE).
