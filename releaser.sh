#!/bin/sh

helpFunction()
{
  echo ""
  echo "Usage: $0 -v version"
  echo "\t-v Version of the plugin"
  exit 1 # Exit script after printing help
}

while getopts "v:" opt
do
  case "$opt" in
    v ) version="$OPTARG" ;;
    ? ) helpFunction ;; # Print helpFunction in case parameter is non-existent
  esac
done

if ! echo $version | grep -Eq "^[0-9]+\.[0-9]+\.[0-9]+$"
then
  echo "Invalid version: ${version}"
  echo "Please specify a semantic version with no prefix (e.g. X.X.X)."
  exit 1
fi

echo "Removing old zip file"
rm -f "mondu-buy-now-pay-later-$version.zip"
mkdir -p mondu-buy-now-pay-later
echo "Generating zip file"
rsync -r --exclude "*.DS_Store" --exclude "exporter.sh" --exclude "Dockerfile" --exclude "docker-compose.yml" --exclude ".gitignore" --exclude ".git" --exclude ".github" --exclude "composer.json" --exclude "composer.lock" --exclude "vendor" --exclude "pbs-rules-set.xml" --exclude "mondu-buy-now-pay-later" . mondu-buy-now-pay-later
zip -r -D "mondu-buy-now-pay-later-$version.zip" mondu-buy-now-pay-later/*
rm -r mondu-buy-now-pay-later
echo "Done"
