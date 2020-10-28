#!/bin/bash

if [[ "$#" -gt 4 || "$#" -lt 2  ]]; then
    echo "USAGE: $0 <prod|dev> <app-dir> [--branch git-branch]"
    exit 1
fi

## Get CLI parameters.
MODE=$1
APP_DIR=$(cd $2; pwd)
shift 2

BRANCH="master"
if [[ "$1" == "--branch" && ! -z "$2" ]]; then
    BRANCH="$2"
fi

## Update the repo.
cd $APP_DIR
git fetch --quiet origin 2>&1

## Get some information about git.
HIGHEST_TAG=$(git tag --list "*.*.*" | sort --version-sort | tail --lines 1)
CURRENT_TAG=$(git describe --tags --always)
CURRENT_BRANCH=$(git symbolic-ref --short -q HEAD)
DIFF_TO_BRANCH=$(git diff origin/$BRANCH)

## Prod site: do nothing if we're already at the highest tag.
if [[ $MODE == "prod" && $CURRENT_TAG == $HIGHEST_TAG ]]; then
    exit 0
fi

## Dev site: do nothing if not on specified branch.
if [[ $MODE == "dev" && $CURRENT_BRANCH != $BRANCH ]]; then
    ## Tell the maintainers, so they don't forget they're in
    ## the middle of testing something.
    echo "Dev site not on specified branch. Not deploying."
    exit 0
fi

## Dev site: do nothing if there's no difference to the specified branch.
if [[ $MODE == "dev" && -z "$DIFF_TO_BRANCH" ]]; then
    exit 0
fi

## Update the code.
if [[ $MODE == "prod" ]]; then
    git checkout $HIGHEST_TAG
    ## Write to to the Server Admin Log on Wikitech if dologmsg is available.
    if [[ $(command -v dologmsg) ]]; then
        dologmsg "Updating to version $HIGHEST_TAG"
    fi
fi
if [[ $MODE == "dev" ]]; then
    git pull origin $BRANCH
fi

## Prod and dev sites: install the application.
composer install --no-dev --optimize-autoloader
./bin/console cache:clear
./bin/console doctrine:migrations:migrate --no-interaction
