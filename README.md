# PSR-7 HTTP Emitter

A lightweight PHP HTTP response emitter.

## Installation
`composer require sabroan/http-emitter`

## Usage

Pass a PSR-7 ResponseInterface instance to SapiEmitter::emit():

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Sabroan\Http\Emitter\SapiEmitter;

/** @var ResponseInterface $response */

$emitter = new SapiEmitter();

$emitter->emit($response);
```

The emitter sends the response status and headers using PHP's native SAPI functions, then writes the response body to the output. The emit() method returns true when emission completes successfully.

## License

This package is released under the MIT License. See the LICENSE file for details.