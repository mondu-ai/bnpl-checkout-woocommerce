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
rm -f "Woocommerce-Mondu-$version.zip"
mkdir -p Woocommerce-Mondu
echo "Generating zip file"
rsync -r --exclude "*.DS_Store" --exclude "exporter.sh" --exclude "Dockerfile" --exclude "docker-compose.yml" --exclude ".gitignore" --exclude ".git" --exclude "Woocommerce-Mondu" . Woocommerce-Mondu
zip -r -D "Woocommerce-Mondu-$version.zip" Woocommerce-Mondu/*
rm -r Woocommerce-Mondu
echo "Done"
