#!/bin/bash


git clone https://github.com/MattToegel/IT490
cd IT490 || { echo "Failed to cd into IT490"; exit 1; }


sudo apt update
sudo apt install -y composer


cat <<EOF > composer.json
{
    "name": "matt/it490",
    "type": "serum",
    "authors": [
        {
            "name": "CARLOS",
            "email": "Cmp59@njit.edu"
        }
    ],
    "require": {
        "php-amqplib/php-amqplib": "^2.10"
    }
}
EOF


composer update

# Fix line order in rabbitMQLib.inc (swap var_dump and json_encode)
# NOTE: this assumes both lines exist and are in the file.
sed -i '/var_dump.*\$json_message/{
    N
    s/var_dump.*\$json_message.*\n.*json_encode.*\$message.*/\$json_message = json_encode(\$message);\n    var_dump(\$json_message);/
}' rabbitMQLib.inc

# Create or overwrite testRabbitMQ.ini with correct values
cat <<EOF > testRabbitMQ.ini
BROKER_HOST=172.23.118.186
BROKER_PORT=5672
USER=cmp59
PASSWORD=1234
VHOST=testHost
EXCHANGE=testExchange
QUEUE=testQueue
AUTO_DELETE=true
EOF

echo "setup completed."
