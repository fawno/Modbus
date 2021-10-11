# Modbus
[![GitHub license](https://img.shields.io/github/license/fawno/Modbus)](https://github.com/fawno/Modbus/blob/master/LICENSE)
[![GitHub release](https://img.shields.io/github/release/fawno/Modbus)](https://github.com/fawno/Modbus/releases)
[![Packagist](https://img.shields.io/packagist/v/fawno/modbus)](https://packagist.org/packages/fawno/modbus)
[![PHP](https://img.shields.io/packagist/php-v/fawno/modbus)](https://php.net)

Modbus RTU serial protocol in PHP

# Install
composer require fawno/modbus

# Example
```php
<?php
  // Load autoload
  require 'vendor/autoload.php';

  // Pre-load Modbus DDS238 implementation
  use Fawno\Modbus\ModbusDDS238;

  // Configure port and open it
  $dds238 = new ModbusDDS238;
  $dds238->setDevice('COM4');
  $dds238->open();

  // Read parsed data
  $data = $dds238->read();

  // Read data as raw
  $data_raw = $dds238->read();

  // Parse raw data
  $data = $dds238->parse($data_raw);

```
