# CoreFront (customer-facing API). Document root is /var/www/html, matching
# the code's own set_include_path('/var/www/html/classes/') expectations —
# no path rewriting needed, this is the Apache image's default docroot.
FROM php:8.4-apache

# redis (PECL, not a core extension) backs the per-session encryption key
# store — see class.User.php's redis()/startSession()/getSession().
# git is needed for the source clone below, not at runtime — could be
# stripped in a multi-stage build later, left simple for now.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libpng-dev \
        libzip-dev \
        libonig-dev \
        git \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql curl gd mbstring \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# CoreFront/.htaccess relies on mod_rewrite; AllowOverride All lets it apply.
RUN printf '<Directory /var/www/html>\n\tAllowOverride All\n\tRequire all granted\n</Directory>\n' \
    > /etc/apache2/conf-available/petra-corefront.conf \
    && a2enconf petra-corefront

# Pulls source from the petra-corporate-ibank repo rather than the local build
# context — GIT_REF picks the branch/tag/commit (defaults to `main`; pin it
# to a release tag for a specific deploy). The token is a BuildKit secret,
# mounted only for this RUN step, never written to a layer; only the
# CoreFront/ subdirectory is copied out of the clone, so the clone's own
# .git/config (which does carry the token, embedded in the authenticated
# clone URL) never reaches the image either — it's deleted, still inside
# this same layer, before the layer is committed.
#
# CACHEBUST: neither the Dockerfile text nor GIT_REF's value changes when
# the branch gets new commits pushed, so without this Docker's layer cache
# reuses the OLD clone forever — a well-known Docker layer-caching gotcha.
# docker-compose.yml passes the branch's current commit SHA here on every
# build, which is exactly what should invalidate the cache: an unrelated
# push to a DIFFERENT branch, or no
# push at all, correctly keeps reusing this layer.
ARG GIT_REF=main
ARG CACHEBUST=unknown
# x-access-token: as the username (token as password) rather than the token
# bare as the username -- required for GitHub Actions own installation-scoped
# GITHUB_TOKEN (git rejects it as a bare username with "could not read
# Password"), and works identically for a classic PAT, so one URL format
# covers both the CI build and a manual local one.
RUN --mount=type=secret,id=github_token \
    echo "Building from commit: $CACHEBUST" && \
    git clone --depth 1 --branch "$GIT_REF" \
        "https://x-access-token:$(cat /run/secrets/github_token)@github.com/optisoftprime/petra-corporate-ibank-web.git" \
        /tmp/petra-src \
    && cp -a /tmp/petra-src/CoreFront/. /var/www/html/ \
    && rm -rf /tmp/petra-src

RUN chown -R www-data:www-data /var/www/html

# Bridges Docker/Swarm secrets to plain env vars for the production stack
# (docker-stack.yml) -- a no-op under docker-compose.yml's env_file: .env,
# since /run/secrets won't exist there. Chains through to the base image's
# own entrypoint rather than replacing it.
COPY docker/entrypoint-secrets.sh /usr/local/bin/entrypoint-secrets.sh
RUN chmod +x /usr/local/bin/entrypoint-secrets.sh
ENTRYPOINT ["/usr/local/bin/entrypoint-secrets.sh", "docker-php-entrypoint"]
CMD ["apache2-foreground"]

# No natural always-200 endpoint here (every action needs specific POST data
# or returns 400/401 on its own) -- -f is deliberately omitted so any HTTP
# response at all, even an error one, counts as "Apache/PHP are up",  which
# is all a liveness check needs. docker-stack.yml's start-first update order
# relies on this to know when a new task is actually ready.
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
    CMD curl -s -o /dev/null http://localhost/ || exit 1