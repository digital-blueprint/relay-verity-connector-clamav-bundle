# Changelog

## v0.4.0

* Replace "url" option with "host"/"port"/"socket" to avoid confusion since we
  are not using HTTP to communicate with the clamav daemon.
* Add support for communication with the clamav daemon over a Unix socket.

## v0.3.0

* Less memory usage
* Better error handling and test coverage
* config: Rename maxsize to max_file_size and accept human-readable sizes
* Add a "dbp:relay:verity-connector-clamav:status" CLI command to check the
  status of the clamav daemon.
* Rename "dbp:relay:verity-connector-clamav:check-file" CLI command to
  "dbp:relay:verity-connector-clamav:scan-file" to better reflect what it does.
* Drop support for Symfony 6

## v0.2.5

* Add a simple health check, checking if the clamav daemon is running and responding to requests.
* Add a "dbp:relay:verity-connector-clamav:check-file" CLI command to check if a
  file with clamav.

## v0.2.4

* fix a missing dependency

## v0.2.3

* support verity 0.2.x

## v0.2.1

* Add support for Symfony 7.4