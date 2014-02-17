# PEAR Composer Fix

A script to add `composer.json` files to all packages within an organization.

The objective is to be able to run this script multiple times.

This is a _WIP_.

## Setup

Clone the repo and setup `config.php`.

Then:

```
$ ./composer.phar install
$ ./script.php
```

## Todo

 * branch when changes are found
 * commit changes
 * push branch
 * open PR (probably using hub)
