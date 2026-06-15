# Configuration

There are some values that you can define in your bundle config.

- `host`: hostname or IP address of the ClamAV daemon
- `port`: TCP port of the ClamAV daemon (optional, defaults to `3310`)
- `max_file_size`: maximum file size for documents (supports shorthand like `10M`, `1G`, or plain bytes)

```yaml
dbp_relay_verity_connector_clamav:
  host: '%env(CLAMAV_HOST)%'
  port: 3310
  max_file_size: '10M'
```