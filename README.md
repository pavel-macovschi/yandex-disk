# Simple implementation of Yandex Disk API

## Installation

Package installation via composer:

``` bash
composer require impressiveweb/yandex-disk
```

## Usage

### Go to https://yandex.ru/dev/disk/poligon and click on a button to get OAuth token.

```php
use ImpressiveWeb\YandexDisk\Client;
// Access token.
$accessToken = 'xxxxxxxxxxxxxxxxxxx';

// Path to a whole disk.
$pathPrefix = 'disk:/';

// Client init with an access token.
$client = new Client($accessToken, $pathPrefix);
```

### Go to https://oauth.yandex.ru/client/new create your first App and add necessary permissions.

### After getting client_id and client_secret you can use it in a client initialization.

```php
// Auth credentials.
$credentials = [
    'client_id' => 'xxxxxxxxxxxxxxxxxxx',
    'client_secret' => 'xxxxxxxxxxxxxxxxxxx',
];

// If you create you first APP, use your Application path.
$pathPrefix = 'disk:/Applications/YourApp'

// If you need to use the whole disk.
$pathPrefix = 'disk:/'

// Client init with credentials.
$client = new Client($credentials, $pathPrefix);

// Create and proceed an auth url to get a code.
$authUrl = $client->getAuthUrl([
    'redirect_uri' => 'https://your-app'
    // ...other options
]);
// After redirecting to your project domain use code from a query.
// https://your-app?code=xxxxxx

// Code that is taken from a query.
$code = 'xxxxxx'

// Make a request to get an access and a refresh token. 
$data = $client->authCodeAndGetToken($code);

// If it is successful you'll get both tokens.
$accessToken = $data['access_token'];

// Save a refresh token somewhere securely to use it in farther requests.
$refreshToken = $data['refresh_token'];

// Refresh token should be set to make sure an automatic update of access token.
$client->setRefreshToken($refreshToken); 
```

## Here are several examples of API usage.

### Get a disk meta information.

```php
// Get a disk meta information.
$reply = $client->discInfo();

// Reply data.
array:11 [▼
  "max_file_size" => 1073741824
  "paid_max_file_size" => 53687091200
  "total_space" => 10737418240
  "reg_time" => "2021-06-29T10:20:40+00:00"
  "trash_size" => 165164170
  "is_paid" => false
  "used_space" => 3342277118
  "system_folders" => array:15 [▶]
  "user" => array:6 [▶]
  "unlimited_autoupload_enabled" => false
  "revision" => 1711829122396623
]

// Get a disk meta information with selected fields.
$reply = $client->discInfo(['max_file_size', 'paid_max_file_size', 'total_space']);

// Reply data.
array:3 [▼
  "max_file_size" => 1073741824
  "paid_max_file_size" => 53687091200
  "total_space" => 10737418240
]
```

### Get a list of contents for a root directory.

```php
$reply = $client->listContent();

// Reply data.
array:10 [▼
  "_embedded" => array:6 [▶]
  "name" => "disk"
  "exif" => []
  "resource_id" => "1444524506:144a2c7976ad2f6f60317baa505447af1dee1fbb4a13f4dccab8bb252846d6ee"
  "created" => "2012-04-04T20:00:00+00:00"
  "modified" => "2012-04-04T20:00:00+00:00"
  "path" => "disk:/"
  "comment_ids" => []
  "type" => "dir"
  "revision" => 1624962040946659
]
```

```php

// There is a default limit for 20 items per one request when you read catalogue.

// You can increase a default amount of items on a Client initialization step.
$client = new Client(itemsLimit: 100);

// Since php 8.0 you can use named arguments and skip a lot of default arguments.

// Get a list of contents for a root directory using selected fields.
$reply = $client->listContent(fields: ['_embedded.items.path']);

// Reply data.
array:1 [▼
  "_embedded" => array:1 [▼
    "items" => array:2 [▼
      0 => array:1 [▼
        "path" => "disk:/Applications"
      ]
      1 => array:1 [▼
        "path" => "disk:/Downloads"
      ]
      // Other items.
      [...]
    ]
  ]
]

// Get a list of contents by a path with 200 items limit.
$limit = 200;
$path = 'path/to/resource';

// Returns 100 items using a specified path.
$client->listContent($path, limit: $limit);

// Returns 100 items that are sorted by size using a specified path.
$client->listContent($path, limit: $limit, sort: 'size');

// Returns items using a specified path recursively.
// Amount of all items in this case depends on subdirectories you have in a path.  
$client->listContent($path, limit: $limit, deep: true);
```


### Work with directories.

```php
$path = 'path/to/created/dir';
$from = 'from/path';
$to = 'to/path';

// Add a new directory.
$client->addDirectory($path);

// Copy directory.
$client->copy($from, $to);

// Move directory.
$client->move($from, $to);

// Remove an existing directory.
$client->remove($path);
```

### Download | Upload resources.

```php
// Get download url.
$reply = $client->getDownloadUrl('path/to/resource');

// Reply data.
array:3 [▼
  "href" => "https://downloader.disk.yandex.ru/disk/8caa900296bd8b8daa64b36c870d9[...]"
  "method" => "GET"
  "templated" => false
]

// Depends on an application requirements you can get a link and download a resource directly via GET method. 
$link = $reply['href'];

// Or open a stream and use it for your own needs.
$resource = $client->getStream($link);
```

### Work with a Trash.

```php
// Returns all resources in a trash.
$client->trashListContent();

// Returns resources in a specified deleted resource.
$client->trashListContent('path/to/deleted/resource');

// Restore a resource from a trash.
$client->trashContentRestore('path/to/restored/dir');

// Remove a specified resource in a trash. 
$client->trashContentDelete('path/to/trash/resource/to-be/deleted');

// Clear a trash completely.
$client->trashClear();

```

### Some other methods are self-descriptive and easy to understand because most of them are similar to original API.

### Read an official API documentation to get details how to use methods and arguments.
