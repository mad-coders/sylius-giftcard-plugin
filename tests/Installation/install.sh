#!/usr/bin/env bash
#
# Installs the plugin into a fresh Sylius Standard application by following docs/INSTALLATION.md,
# and proves the result actually boots.
#
# The rest of CI runs against sylius/test-application, which is pre-wired through SYLIUS_TEST_APP_*
# environment variables. That is excellent for exercising behaviour and useless for answering the
# question a host actually has: "if I follow your README, do I get a working shop?" This answers it.
#
# The files are extracted from INSTALLATION.md rather than kept as a second copy in the test, so a
# snippet that is wrong, stale or incomplete fails here.
#
# Usage: tests/Installation/install.sh [target-directory]
#   DATABASE_URL   required, e.g. mysql://root@127.0.0.1:3306/gift_card_install_test
#   SYLIUS_VERSION optional, defaults to the ^2.2 line

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TARGET_DIR="${1:-$(mktemp -d)/sylius-app}"
SYLIUS_VERSION="${SYLIUS_VERSION:-^2.2}"

: "${DATABASE_URL:?DATABASE_URL must be set - the installation is not proven until the migrations run}"

export COMPOSER_NO_INTERACTION=1
export COMPOSER_MEMORY_LIMIT=-1
export APP_ENV=dev
export DATABASE_URL

step() { printf '\n\033[1;34m==> %s\033[0m\n' "$1"; }
fail() { printf '\n\033[1;31mFAILED: %s\033[0m\n' "$1" >&2; exit 1; }

# Building the full Sylius container exceeds the default CLI memory_limit, exactly as it does in the
# rest of CI. Every console call goes through here so none of them can forget.
console() { php -d memory_limit=-1 bin/console "$@"; }

step "Creating a fresh Sylius Standard application in ${TARGET_DIR}"
rm -rf "${TARGET_DIR}"
mkdir -p "$(dirname "${TARGET_DIR}")"
composer create-project "sylius/sylius-standard:${SYLIUS_VERSION}" "${TARGET_DIR}" --no-install --no-scripts

cd "${TARGET_DIR}"

step "Requiring the plugin from the working tree"
# A path repository rather than Packagist: the point is to test *this* checkout, including changes
# that have not been released. `symlink: false` so the install mirrors what a real download gives
# you - a copy, with no way for the app to accidentally write back into the plugin.
composer config repositories.plugin --json "{\"type\": \"path\", \"url\": \"${PLUGIN_DIR}\", \"options\": {\"symlink\": false}}"
composer config --no-plugins allow-plugins true
composer config extra.symfony.allow-contrib true
composer require "madcoders/sylius-giftcard-plugin:*@dev" --no-scripts

step "Applying docs/INSTALLATION.md"
# Steps 3-5 are whole new files, so they are written straight out of the guide.
php "${PLUGIN_DIR}/tests/Installation/extract_documented_files.php" "${PLUGIN_DIR}/docs/INSTALLATION.md" "${TARGET_DIR}"

# Step 6 is a modification of classes Sylius Standard already ships - which already carry other
# plugins' traits - so it is applied rather than overwritten. Replacing those files instead of
# adding to them breaks product translations, which is precisely what this job caught.
php "${PLUGIN_DIR}/tests/Installation/apply_entity_traits.php" "${TARGET_DIR}"

# Step 2 of the guide is a partial file - a host merges the line into whatever bundles they have -
# so it is applied here rather than extracted.
php -r '
$path = "config/bundles.php";
$bundle = "Madcoders\\\\SyliusGiftCardPlugin\\\\MadcodersSyliusGiftCardPlugin::class => [\"all\" => true],";
$contents = file_get_contents($path);
if (str_contains($contents, "MadcodersSyliusGiftCardPlugin")) { exit(0); }
$contents = preg_replace("/^\];$/m", "    " . $bundle . "\n];", $contents, 1);
file_put_contents($path, $contents);
'
grep -q "MadcodersSyliusGiftCardPlugin" config/bundles.php || fail "the bundle was not registered"

step "Building the container"
console cache:clear --no-warmup
console cache:warmup

step "Creating the database and running the migrations"
console doctrine:database:drop --force --if-exists
console doctrine:database:create
console doctrine:migrations:migrate --no-interaction --allow-no-migration

step "Checking the mapping matches the schema the migrations produced"
# The assertion that catches the whole class of "the plugin works but cannot be installed" bugs: a
# model changed and its migration did not. It caught a real one - the plugin registered its
# migrations under the application's own `DoctrineMigrations` namespace, so a host's configuration
# overwrote the path and `doctrine:migrations:migrate` created no gift card tables whatsoever.
#
# Scoped to the plugin's own schema rather than `doctrine:schema:validate`, because a stock Sylius
# Standard is itself not in sync: its bundled plugins leave `sylius_product.product_type_id`, some
# Mollie columns on `sylius_order` and a few messenger indexes behind. Failing on those would make
# this job red for reasons that have nothing to do with the plugin.
console doctrine:schema:update --dump-sql > /tmp/pending-schema.sql 2>&1 || true

if grep -qE "madcoders_gift_card|\bgift_card\b" /tmp/pending-schema.sql; then
    echo "--- pending changes that touch the plugin's schema ---"
    grep -E "madcoders_gift_card|\bgift_card\b" /tmp/pending-schema.sql
    fail "the migrations do not produce the schema the models map to"
fi

step "Checking the plugin's services and routes are wired"
console lint:container

# Private services are inlined away, so this checks the ones a host can actually reach: the
# controllers the documented routes point at. `lint:container` above has already proved every
# service in the graph - private ones included - is injected with something of the right type.
for service in \
    "Madcoders\\SyliusGiftCardPlugin\\Controller\\Shop\\GiftCardCartController" \
    "Madcoders\\SyliusGiftCardPlugin\\Controller\\Admin\\AdjustGiftCardBalanceController" \
    "Madcoders\\SyliusGiftCardPlugin\\Controller\\Shop\\AccountGiftCardController"
do
    console debug:container "${service}" >/dev/null 2>&1 \
        || fail "service ${service} is not registered"
done

for route in \
    madcoders_sylius_gift_card_admin_gift_card_index \
    madcoders_sylius_gift_card_admin_gift_card_adjust_balance \
    madcoders_sylius_gift_card_admin_gift_card_configuration_index \
    madcoders_sylius_gift_card_shop_cart_apply \
    madcoders_sylius_gift_card_shop_cart_remove \
    madcoders_sylius_gift_card_shop_account_index \
    madcoders_sylius_gift_card_shop_account_show
do
    console debug:router "${route}" >/dev/null 2>&1 || fail "route ${route} is not registered"
done

step "Checking the schema the guide promises actually exists"
for table in \
    madcoders_gift_card__gift_card \
    madcoders_gift_card__gift_card_transaction \
    madcoders_gift_card__configuration \
    madcoders_gift_card__order_gift_cards
do
    console dbal:run-sql "SELECT 1 FROM ${table} LIMIT 1" >/dev/null 2>&1 \
        || fail "table ${table} was not created by the migrations"
done

# Documented in step 7 of the guide, and the one column that lives on a Sylius table.
console dbal:run-sql "SELECT gift_card FROM sylius_product LIMIT 1" >/dev/null 2>&1 \
    || fail "the gift_card column was not added to sylius_product"

step "Loading the plugin's fixtures"
console sylius:fixtures:load default --no-interaction
console sylius:fixtures:load madcoders_gift_card --no-interaction

# The end-to-end proof: the fixtures exercise the factories, the code generator, the configuration
# provider and the balance ledger, and the cards have to actually land in the host's database.
console dbal:run-sql "SELECT COUNT(*) FROM madcoders_gift_card__gift_card" | grep -qE "[1-9]" \
    || fail "the fixtures ran but no gift card reached the database"

printf '\n\033[1;32mThe plugin installs on a clean Sylius %s by following docs/INSTALLATION.md.\033[0m\n' "${SYLIUS_VERSION}"
