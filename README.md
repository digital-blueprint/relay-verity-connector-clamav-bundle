# DBP Relay Verity Connector ClamAV Bundle

[GitHub](https://github.com/digital-blueprint/relay-verity-connector-clamav-bundle) |
[Packagist](https://packagist.org/packages/dbp/relay-verity-connector-clamav-bundle)

The verity connector clamav bundle provides an internal API for interacting with a (remote) ClamAV service.

## Bundle installation

You can install the bundle directly from [packagist.org](https://packagist.org/packages/dbp/relay-verity-connector-clamav-bundle).

```bash
composer require dbp/relay-verity-connector-clamav-bundle
```

## Integration into the Relay API Server

* Add this bundle to your `config/bundles.php` in front of `DbpRelayCoreBundle`:

```php
...
    Dbp\Relay\VerityConnectorClamavBundle\DbpRelayVerityConnectorClamavBundle::class => ['all' => true],
Dbp\Relay\CoreBundle\DbpRelayCoreBundle::class => ['all' => true],
];
```

If you were using the [DBP API Server Template](https://packagist.org/packages/dbp/relay-server-template)
as template for your Symfony application, then this should have already been generated for you.

* Run `composer install` to clear caches

## Configuration

For this create `config/packages/dbp_relay_verity_connector_clamav.yaml` in the app with the following
content:

```yaml
dbp_relay_verity_connector_clamav:
  url: '%env(CLAMAV_URI)%'
  maxsize: 33554432
```

If you were using the [DBP API Server Template](https://packagist.org/packages/dbp/relay-server-template)
as template for your Symfony application, then the configuration file should have already been generated for you.

For more info on bundle configuration see <https://symfony.com/doc/current/bundles/configuration.html>.

## Development & Testing

* Install dependencies: `composer install`
* Run tests: `composer test`
* Run linters: `composer run lint`
* Run cs-fixer: `composer run cs-fix`

## Bundle dependencies

Don't forget you need to pull down your dependencies in your main application if you are installing packages in a bundle.

```bash
# updates and installs dependencies of dbp/relay-verity-connector-clamav-bundle
composer update dbp/relay-verity-connector-clamav-bundle
```
