# converse/converse.js-dist package builder

## Overview

This repository helps build and update converse/converse.js-dist package versions that mirrors converse/converse.js repository release versions.

## Installation

### Install composer dependencies

```
composer install
```

### Setup .env

```
cp .env.dist .env
```

Edit the `SOURCE_FOLDER` and `TARGET_FOLDER` to the directories of your choice.

The SOURCE_FOLDER will be the extracted path for converse.js assets.
The TARGET_FOLDER can be an existing git repositories, avoiding the need to checkout them again.

## Execution

```
php index.php
```

## Packagist

https://packagist.org/packages/jcbrand/converse.js-dist
