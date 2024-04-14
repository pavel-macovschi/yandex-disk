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

// Client init with an access token and default path prefix to a whole disk:/.
$client = new Client($accessToken);
```

### Go to https://oauth.yandex.ru/client/new create your first App and add necessary permissions.

### After getting client_id and client_secret you can use it for a client initialization.

```php
// Auth credentials.
$credentials = [
    'client_id' => 'xxxxxxxxxxxxxxxxxxx',
    'client_secret' => 'xxxxxxxxxxxxxxxxxxx',
];

// Client init with credentials and access to the whole disk.
// Default value for path prefix is set to disk:/.
$client = new Client($credentials);

// If you create you first APP, use path of your APP.
$pathPrefix = 'disk:/Applications/YourApp'

// Client init with credentials and access to your Application.
$client = new Client($credentials, $pathPrefix);

// Create and proceed an auth url to get a code.
$authUrl = $client->getAuthUrl([
    'redirect_uri' => 'https://your-domain-app'
    // ...other options
]);

// After redirecting to your project domain use code from a query.
// https://your-app?code=xxxxxx

// Code that is taken from a query.
$code = 'xxxxxx'

// Make a request to get an access and a refresh token. 
$reply = $client->authCodeAndGetToken($code);

// Reply data.
array:4 [▼
  "access_token" => "xx_xxxXXxxxAAAABWGbHaAAqjUwAAAADvXc-xxxxxxxxxxxxxxxxxxxx"
  "expires_in" => 25137530
  "refresh_token" => "1:xxxxxxxx:xxxxxxxxxxxxxxxxxxxxxxxx-xxxxxxxxxxxxxxxxx-xxxxxxxxxxxx"
  "token_type" => "bearer"
]

// If reply has been successful you've got both tokens.
$accessToken = $reply['access_token'];

// Save a refresh token somewhere in a secret place.
$refreshToken = $reply['refresh_token'];

// Refresh token should be set to make sure an automatic access token update.
$client->setRefreshToken($refreshToken); 

```

# Here are several examples of API usage.

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

// Get a disk meta information with a list of attributes to be returned.
$reply = $client->discInfo(['max_file_size', 'paid_max_file_size', 'total_space']);

// Reply data.
array:3 [▼
  "max_file_size" => 1073741824
  "paid_max_file_size" => 53687091200
  "total_space" => 10737418240
]
```

### Get all contents with metadata information.

```php
// If a path is not set a path prefix will be used as a root directory.
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

// There is a default limit for 20 items per one request when you read catalogues.
// You can increase a default amount of items on a Client initialization step.
$client = new Client(itemsLimit: 100);

// Since php 8.0 you can use named arguments and skip a lot of default arguments.

// Get a list of items for a root directory using selected fields.
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

// Get a list of items by a path with 200 items limit.
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

// Add a new folder.
$client->addDirectory($path);

// Copy a file or a folder.
$client->copy($from, $to);

// Move a file or a folder to a different location.
$client->move($from, $to);

// Delete a folder or a file at a given path.
$client->remove($path);
```

### Download resources.

```php
// 1. Get a download url.
$path = 'path/to/existing/resource/on/disk';
$reply = $client->getDownloadUrl($path, ['href']);

// Reply data.
array:1 [▼
  "href" => "https://downloader.disk.yandex.ru/disk/8caa900296bd8b8daa64b36c870d9[...]"
]

// 2. Depends on an application requirements you can get a link and download a resource directly via GET method. 
$link = $reply['href'];

// 2. Or open a stream and use it for your own needs.
$stream = $client->getStream($link);
```

### Upload resources.

```php
// 1. Get an upload url.
$path = 'path/to/where/resource/will/be/placed/on/disk';
$reply = $client->getUploadUrl($path);

// Reply data.
array:4 [▼
  "operation_id" => "d97f66b1638b1438825ed6d5a5433eb15f95bd5298[...]"
  "href" => "https://uploader54j.disk.yandex.net:443/upload-target/20240403T140247[...]"
  "method" => "PUT"
  "templated" => false
]
// If href in reply data will not be requested for 30 minutes it won't be available for uploading, and you need to create another one.

// 2. Use href however you want. 

// 3. Or use an upload method to upload file using getStream helper.
$name = 'picture.jpg';
$fromPath = __DIR__.DIRECTORY_SEPARATOR.$name;
$toPath = "path/on/disk/$name";
// Open a stream.
$stream = $client->getStream($fromPath, 'r+');
// Upload file.
$client->upload($toPath, $stream);
```

### Work with a Trash.

```php
$path = 'path/to/trash/resource';

// Listing a trash content.
$client->trashListContent();

// Listing a trash content in a specified path.
$client->trashListContent($path);

// Restore a specified resource from a trash.
$client->trashContentRestore($path);

// Remove a resource from a basket. 
$client->trashContentDelete($path);

// Clear the whole trash.
$client->trashClear();

```

### Some other methods are self-descriptive and easy to understand because the most of them are similar to an original Yandex Disk API.

### Read an official API documentation https://yandex.ru/dev/disk-api/doc/ru/ to get more details how to use methods and its arguments.

# If you need to use a common interface among different filesystems you can use Flysystem: https://flysystem.thephpleague.com/docs/

## This Flysystem Adapter: https://packagist.org/packages/impressiveweb/yandex-disk-flysystem-adapter is fully compatible with this version of Yandex Disk API.