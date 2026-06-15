# Configuration

There are some values that you can define in your bundle config.

- `url`: url that leads to the ClamAV rest API
- `max_file_size`: maximum file size for documents (supports shorthand like `10M`, `1G`, or plain bytes)

```yaml
dbp_relay_verity_connector_clamav:
  url: '%env(CLAMAV_URL)%'
  max_file_size: '10M'
```