# AGENTS.md

Notes for coding agents working on o2. Read `HACKING.txt` first for how o2 is structured, built, and tested.

## Run the PHP tests locally

`bin/install-wp-tests.sh` needs a MySQL server. Without one, use a throwaway Docker container:

```bash
composer install
docker run -d --rm --name o2-test-mysql -e MYSQL_ROOT_PASSWORD=root -p 127.0.0.1:33067:3306 mysql:8.0
until docker exec o2-test-mysql mysqladmin ping -h 127.0.0.1 -proot --silent; do sleep 2; done
docker exec o2-test-mysql mysql -h 127.0.0.1 -uroot -proot -e 'CREATE DATABASE IF NOT EXISTS wordpress_test'
bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1:33067 latest
vendor/bin/phpunit
docker stop o2-test-mysql
```

- Ping over TCP (`-h 127.0.0.1`). On first boot the container runs a temporary socket-only server, so a socket ping can pass before the real server is up.
- The install script's last step, `mysqladmin create`, fails here: either `mysqladmin` isn't installed, or the database already exists. WordPress and the test library are installed by then, so `phpunit` still runs.
- CI (`.github/workflows/ci.yml`) runs the same suite on PHP 8.3 against `mysql:8.0`.

## Testing the write APIs

- Both write APIs hand comments to core's `wp-comments-post.php` (`inc/write-api.php`, `inc/nopriv-write-api.php`). That file calls a bare `exit` on anything but a POST request, and the test bootstrap leaves `$_SERVER['REQUEST_METHOD']` as `GET`. A test that can reach it must set `POST` and restore the old value afterward.
- `die_failure()` ends the request through `wp_send_json_error()`. Filter `wp_doing_ajax` to true and `wp_die_ajax_handler` to a handler that throws `WPDieException`, then catch it and decode the buffered JSON. `tests/phpunit/test-nopriv-write-api.php` shows both patterns.
- Prove a guard test can fail. Remove the guard, run the suite, and confirm the test fails and `phpunit` exits non-zero. A test that ends the process early can stop the run and still exit 0.
