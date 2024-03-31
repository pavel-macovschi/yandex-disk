# Simple implementation of Yandex Disk API

## Installation

You can install the package via composer:

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

### Go to https://oauth.yandex.ru/client/new and create your first App and add necessary permissions. After getting client_id and client_secret you can use it in a client initialization.

```php
// Auth credentials.
$credentials = [
    'client_id' => 'xxxxxxxxxxxxxxxxxxx',
    'client_secret' => 'xxxxxxxxxxxxxxxxxxx',
];
// If you create you first APP, use that path.
$pathPrefix = 'disk:/Applications/APP'
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
$code = 'xxxxxx';
// Make a request to get an access and a refresh token. 
$data = $client->authCodeAndGetToken($code);
// If it is successful you'll get both tokens.
$accessToken = $data['access_token'];
// Save a refresh token somewhere securely to use it in farther requests.
$refreshToken = $data['refresh_token'];
// Refresh token should be set to make sure an automatic update of access token.
$client->setRefreshToken($refreshToken); 
```

### Methods of API are relatively matching to methods of an original API. So you can read an offical documentation how to use it.

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

// Get a disk meta information using preferable fields of attributes.
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

### Get a list of contents for a root directory using preferable fields of attributes.

#### There is a default limit for 20 items on catalogue reading.

#### You can increase which amount of items will be returned on a method level or on a Client initialization step.

```php
// Since php 8.0 you can use named arguments.
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
```

### Get a list of contents for a preferable path with extra fields.

```php
$path = 'custom/disk/path';
$fields = ['_embedded.items'];
// Default limit is set to 20 items.
$limit = 100;
// Returns 100 items using custom path and returned fields.
$client->listContent($path, $fields, $limit);

// Returns 100 items using custom path and returned fields that are sorted by size.
$client->listContent($path, $fields, $limit, sort: 'size');

// Returns items using custom path and returned fields recursively.
$client->listContent($path, $fields, deep: true);
```

### Work with directories.

```php
// Add a new directory.
$path = 'path/to/created/dir';
$client->addDirectory($path);

// Copy directory.
$from = 'path/to/created/dir';
$to = 'path/to/another';
$client->copy($from, $to);

// Move directory.
$from = 'path/from';
$to = 'path/to';
$client->move($from, $to);

// Remove an existing directory.
$client->remove($path);
```

### Other methods are self descriptive and easy to understand.