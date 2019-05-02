#!/bin/sh

#Script for create the plugin artifact

if [ "$TRAVIS_TAG" = "" ]
then
   TRAVIS_TAG='1.0.0'
fi

echo "Travis tag: $TRAVIS_TAG"

SRC_DIR="transbank-webpay"
FILE1="WebpayPlugin.php"

if [ "$1" == "update" ]; then
    cd $SRC_DIR
    composer install --no-dev
    composer update --no-dev
    cd ..
fi

PLUGIN_FILE="transbank-webpay.zip"

#cd $SRC_DIR
zip -FSr $PLUGIN_FILE $SRC_DIR
#cd ..


echo "Plugin version: $TRAVIS_TAG"
echo "Plugin file: $PLUGIN_FILE"
