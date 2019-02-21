#!/bin/bash

if [[ -z $1 || -z $2 ]]; then
    echo "USAGE: $0 [prod|dev] <app-dir>"
    exit 1
fi

## Get CLI parameters.
MODE=$1
APP_DIR=$(cd $2; pwd)

## Update the repo.
cd $APP_DIR
git fetch --quiet origin 2>&1

## Get some information about git.
HIGHEST_TAG=$(git tag --list "*.*.*" | sort --version-sort | tail --lines 1)
CURRENT_TAG=$(git describe --tags)
CURRENT_BRANCH=$(git symbolic-ref --short -q HEAD)
DIFF_TO_MASTER=$(git diff origin/master)

## Prod site: do nothing if we're already at the highest tag.
if [[ $MODE == 'prod' && $CURRENT_TAG == $HIGHEST_TAG ]]; then
    exit 0
fi

## Dev site: do nothing if not on master.
if [[ $MODE == "dev" && $CURRENT_BRANCH != "master" ]]; then
    ## Tell the maintainers, so they don't forget they're in
    ## the middle of testing something.
    echo "Dev site not on master branch. Not deploying."
    exit 0
fi

## Dev site: do nothing if there's no difference to master.
if [[ $MODE == "dev" && -z "$DIFF_TO_MASTER" ]]; then
    exit 0
fi

## Update the code.
if [[ $MODE == "prod" ]]; then
    git checkout $HIGHEST_TAG
fi
if [[ $MODE == "dev" ]]; then
    git pull origin master
fi

## Prod and dev sites: install the application.
composer install --no-dev --optimize-autoloader
./bin/console cache:clear
./bin/console doctrine:migrations:migrate --no-interaction
