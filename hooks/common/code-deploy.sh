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

# Set the -e option to exit on failure.
set -ev

site="$1"
target_env="$2"
source_branch="$3"
deployed_tag="$4"
repo_url="$5"
repo_type="$6"

if [ "$target_env" != 'prod' ]; then
  # Go to the deployment directory.
  echo "$site.$target_env: The $source_branch branch has been updated on $target_env."
  cd /var/www/html/$site.$target_env

  # Get the list of sites.
  . `dirname $0`/../../sites.sh


  # Run deploymnet steps for each site.
  for site_name in ${SITES[@]}; do
    echo "-------------Running for site: $site_name--------------"
    DRUSH="php -d memory_limit=1024M vendor/bin/drush @$site.$target_env -l $site_name"
    $DRUSH cr
    echo "Running updb."
    $DRUSH updb -y
    echo "Running config import."
    $DRUSH cim -y
    $DRUSH cim -y
    echo "Running cache clear."
    $DRUSH cr
    echo "Running cache clear."
    $DRUSH cr
    echo "-----------Update complete for site $site_name----------"
  done
  echo "Update complete."

else
  echo "Manually do the deployment activity."
fi

set +v
