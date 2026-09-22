# Admin (staff back office). The code itself expects to live at
# /var/www/admin (see Admin/index.php's relative redirect to views/index.php,
# and views/index.php's form posting to ../src/validate.Authenticate.php) and
# separately expects CoreFront's classes reachable at /var/www/html/classes
# (Admin/src/include.all.php: set_include_path('/var/www/html/classes/:/var/www/admin/classes/')).
# Admin has no dbConnect/BankOne/Secure class of its own — it resolves them
# from CoreFront's classes dir at runtime. Reproduced here by copying that
# directory to the same absolute path inside this image, matching the
# original single-host deployment's layout rather than pretending these are
# two independent apps.
FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libpng-dev \
        libzip-dev \
        libonig-dev \
        git \
    && docker-php-ext-install pdo_mysql curl gd mbstring \
    && rm -rf /var/lib/apt/lists/*

# DocumentRoot is the whole Admin/ tree (index.php's redirect and views/'s
# relative form actions both assume classes/ and src/ are reachable as
# siblings of views/ under the same web root, not that views/ is the root).
# Only classes/ is denied over HTTP as defense in depth: it has no direct
# HTTP entry point, PHP's include path reaches it without needing it
# web-exposed. src/ is NOT denied — src/validate.Authenticate.php is the
# login form's own POST target and src/util.php is the AJAX target for
# every authenticated action (views/js/actions.js posts to '../src/util.php').
# Denying src/ here 403s those real, in-use endpoints and breaks login.
RUN { \
      echo '<VirtualHost *:80>'; \
      echo '    DocumentRoot /var/www/admin'; \
      echo '    <Directory /var/www/admin>'; \
      echo '        AllowOverride All'; \
      echo '        Require all granted'; \
      echo '    </Directory>'; \
      echo '    <Directory /var/www/admin/classes>'; \
      echo '        Require all denied'; \
      echo '    </Directory>'; \
      echo '    <Directory /var/www/html/classes>'; \
      echo '        Require all denied'; \
      echo '    </Directory>'; \
      echo '</VirtualHost>'; \
    } > /etc/apache2/sites-available/000-default.conf

# Pulls source from the petra-corporate-ibank repo rather than the local build
# context, same one clone serving both copy targets (Admin/ and CoreFront's
# classes/) since this image needs both anyway. See the matching block in
# docker/corefront/Dockerfile for the token-handling notes — identical here.
# CACHEBUST — see the matching note in docker/corefront/Dockerfile, same
# reasoning: without it this layer never re-clones on a plain rebuild.
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
        "https://x-access-token:$(cat /run/secrets/github_token)@github.com/secondcaesar-apps/petra-corporate-ibank.git" \
        /tmp/petra-src \
    && cp -a /tmp/petra-src/Admin/. /var/www/admin/ \
    && cp -a /tmp/petra-src/CoreFront/classes/. /var/www/html/classes/ \
    && rm -rf /tmp/petra-src

RUN chown -R www-data:www-data /var/www/admin /var/www/html

# Bridges Docker/Swarm secrets to plain env vars -- see the matching block
# and comments in docker/corefront/Dockerfile.
COPY docker/entrypoint-secrets.sh /usr/local/bin/entrypoint-secrets.sh
RUN chmod +x /usr/local/bin/entrypoint-secrets.sh
ENTRYPOINT ["/usr/local/bin/entrypoint-secrets.sh", "docker-php-entrypoint"]
CMD ["apache2-foreground"]

# / redirects (302) to views/index.php -- -f is deliberately omitted so that
# counts as healthy too; see the matching note in docker/corefront/Dockerfile.
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
    CMD curl -s -o /dev/null http://localhost/ || exit 1
