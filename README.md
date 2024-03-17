# Simple implementation of Yandex Disk API


Here are some examples how you can use it:

```php
use ImpressiveWeb\YandexDisk\Client;

$client = new Client($authorizationToken);

// Add a new folder.
$client->addDirectory('Path to added folder');

// List content for root directory.
$client->listContent('/');

// Trash list content.
$client->trashListContent('/');
```

## Installation

You can install the package via composer:

``` bash
composer require impressiveweb/yandex-disk
```