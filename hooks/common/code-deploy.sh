#!/bin/bash
#
# Cloud Hook: post-code-update
#
# The post-code-update hook runs in response to code commits.
# When you push commits to a Git branch, the post-code-update hooks runs for
# each environment that is currently running that branch. See
# ../README.md for details.
#
# Usage: post-code-update site target-env source-branch deployed-tag repo-url
#                         repo-type

set -e

site="$1"
target_env="$2"
source_branch="$3"
deployed_tag="$4"
repo_url="$5"
repo_type="$6"

if [ "$target_env" != 'prod' ]; then
  echo "$site.$target_env: The $source_branch branch has been updated on $target_env."
  cd /var/www/html/$site.$target_env

  . `dirname $0`/../../sites.sh

  total=${#SITES[@]}
  current=0

  for site_name in ${SITES[@]}; do
    current=$((current + 1))
    echo ""
    echo "=============================================="
    echo "  Site $current/$total: $site_name"
    echo "=============================================="
    DRUSH="php -d memory_limit=1024M vendor/drush/drush/drush.php @$site.$target_env -l $site_name"

    echo "[1/6] Cache rebuild..."
    $DRUSH cr

    echo "[2/6] Database updates..."
    $DRUSH updb -y

    echo "[3/6] Config import (pass 1)..."
    $DRUSH cim -y

    echo "[4/6] Cache rebuild + config import (pass 2)..."
    $DRUSH cr
    $DRUSH cim -y

    # Menu links are content entities, so config import never applies menu
    # changes. This runs after cim so it reads the freshly imported canon.
    echo "[5/6] Editorial menu sync..."
    $DRUSH bebbo:menu-sync

    echo "[6/6] Final cache rebuild..."
    $DRUSH cr

    echo "--- Done: $site_name ---"
  done

  echo ""
  echo "All $total sites updated successfully."

else
  echo "Manually do the deployment activity."
fi
