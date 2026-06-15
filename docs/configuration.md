# Configuration

There are some values that you can define in your bundle config.

- `host`: hostname or IP address of the ClamAV daemon (mutually exclusive with `socket`)
- `port`: TCP port of the ClamAV daemon (optional, defaults to `3310`)
- `socket`: path to the ClamAV Unix domain socket (mutually exclusive with `host`)
- `max_file_size`: maximum file size for documents (supports shorthand like `10M`, `1G`, or plain bytes)

Either `host` or `socket` must be set, but not both.

### TCP connection

```yaml
dbp_relay_verity_connector_clamav:
  host: '%env(CLAMAV_HOST)%'
  port: 3310
  max_file_size: '10M'
```

### Unix socket connection

```yaml
dbp_relay_verity_connector_clamav:
  socket: '/var/run/clamav/clamd.ctl'
  max_file_size: '10M'
```