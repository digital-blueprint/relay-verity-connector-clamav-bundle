# DBP Relay Verity Connector ClamAV Bundle

[GitHub](https://github.com/digital-blueprint/relay-verity-connector-clamav-bundle) |
[Packagist](https://packagist.org/packages/dbp/relay-verity-connector-clamav-bundle)

The verity connector clamav bundle provides an internal API for interacting with a (remote) ClamAV service.

## Bundle installation

You can install the bundle directly from [packagist.org](https://packagist.org/packages/dbp/relay-verity-connector-clamav-bundle).

```bash
composer require dbp/relay-verity-connector-clamav-bundle
```

## Configuration

For this create `config/packages/dbp_relay_verity_connector_clamav.yaml` in the app with the following
content:

```yaml
dbp_relay_verity_connector_clamav:
  host: '%env(CLAMAV_HOST)%'
  max_file_size: 32M
```

If you were using the [DBP API Server Template](https://packagist.org/packages/dbp/relay-server-template)
as template for your Symfony application, then the configuration file should have already been generated for you.

For more info on bundle configuration see <https://symfony.com/doc/current/bundles/configuration.html>.

## Development & Testing

* Install dependencies: `composer install`
* Run tests: `composer test`
* Run linters: `composer run lint`
* Run cs-fixer: `composer run cs-fix`
