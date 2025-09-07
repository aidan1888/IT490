#!/bin/bash
set -e
sudo apt update
sudo apt upgrade -y
sudo apt install -y php composer git
rabbitmq-server
git clone
https://github.com/MattToegel/IT490
cd IT490
jq  'if .type then .type |= ascii_downcase else . end' composer.json > composer_tmp.json && mv composer_tmp.json composer.json
composer update
php RabbitMQServer.php
