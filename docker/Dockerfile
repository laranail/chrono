# A PHP that agrees with this repository about what time it is.
#
# This is a *development* environment, not a shipped artifact. The package is a library; you consume
# it with Composer and run it on your own PHP. Nothing here is published to a registry, and building
# it is never a prerequisite for using `laranail/chrono`.
#
# It exists for one reason. Byte-for-byte parity of the generated data is a statement about a single
# tz release, so `composer sync-check` and the generated-data tests only mean anything on a host
# carrying the release those files were built against. Almost no host does: Ubuntu and the official
# PHP images read the OS database and report `0.system`, macOS bundles whatever Homebrew last
# shipped, and every one of them drifts on its own schedule. That is the problem this package exists
# to describe, so it would be strange to require contributors to solve it by hand.
#
#   docker compose run --rm chrono composer test
#
# The tz release is read from resources/tzdata-version.txt at build time rather than passed as an
# argument. The version the generators wrote is therefore the version installed here, with no way to
# hold them wrong — the same single-sourcing the CI workflows use.

FROM php:8.5-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        libicu-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip \
        zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

# `calendar` for Julian Day and Easter; `intl` for every locale-aware rendering. Both are hard
# requirements of the package, not conveniences — see composer.json.
RUN docker-php-ext-install -j"$(nproc)" calendar intl pdo_sqlite zip

# The whole point of the image. Copied in on its own so this layer rebuilds when the pin moves and
# not when a source file changes.
COPY resources/tzdata-version.txt /tmp/tzdata-version.txt
RUN pecl install "timezonedb-$(cat /tmp/tzdata-version.txt)" \
    && docker-php-ext-enable timezonedb \
    && rm /tmp/tzdata-version.txt

# Fail the build rather than hand over an image that is quietly wrong. Without this the pin could
# silently fail to load and every check inside would skip, which looks identical to passing.
COPY resources/tzdata-version.txt /tmp/expected-tzdata
RUN EXPECTED="$(cat /tmp/expected-tzdata)"; \
    ACTUAL="$(php -r 'echo timezone_version_get();')"; \
    if [ "$ACTUAL" != "$EXPECTED" ]; then \
        echo "timezonedb did not take: PHP reports '$ACTUAL', expected '$EXPECTED'." >&2; \
        exit 1; \
    fi; \
    echo "tzdata $ACTUAL"; \
    rm /tmp/expected-tzdata

# The stock image caps memory at 128M, which the suite exceeds while booting Testbench, and leaves
# date.timezone unset. CI sets both through setup-php; without them the container disagrees with CI
# about the two settings most likely to change a result.
RUN { \
        echo 'memory_limit = -1'; \
        echo 'date.timezone = UTC'; \
    } > "$PHP_INI_DIR/conf.d/zz-chrono.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_PROCESS_TIMEOUT=0

WORKDIR /app

CMD ["php", "-a"]
