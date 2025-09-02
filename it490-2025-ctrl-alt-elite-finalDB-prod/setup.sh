#!/bin/bash

#echo "[+] Updating system and installing core packages..."
#sudo apt update
sudo apt install -y rsyslog ufw net-tools curl

echo "[+] Installing Tailscale..."
curl -fsSL https://tailscale.com/install.sh | sh

echo "[+] (You'll need to run 'sudo tailscale up' manually to authenticate your device after this script.)"

echo "[+] Configuring rsyslog to act as central log server..."

# Enable UDP and TCP reception
sudo sed -i 's/^#module(load="imudp")/module(load="imudp")/' /etc/rsyslog.conf
sudo sed -i 's/^#input(type="imudp"/input(type="imudp"/' /etc/rsyslog.conf
sudo sed -i 's/^#module(load="imtcp")/module(load="imtcp")/' /etc/rsyslog.conf
sudo sed -i 's/^#input(type="imtcp"/input(type="imtcp"/' /etc/rsyslog.conf

# Add template for remote logs
sudo bash -c 'cat <<EOF >> /etc/rsyslog.conf

# Central log collector template
$template RemoteLogs,"/var/log/%HOSTNAME%/%PROGRAMNAME%.log"
*.* ?RemoteLogs
EOF'

echo "[+] Setting up UFW firewall rules..."
sudo ufw allow 22           # SSH
sudo ufw allow 514/udp      # rsyslog UDP
sudo ufw allow 514/tcp      # rsyslog TCP
sudo ufw allow 3306/udp     # MySQL UDP
sudo ufw allow 3306/tcp     # MySQL TCP
sudo ufw --force enable

echo "[+] Restarting services..."
sudo systemctl restart rsyslog

echo "[+] Checking if rsyslog is listening on port 514..."
sudo ss -tulnp | grep 514

echo "[+] Setup complete!"
echo "➡️ Now run: sudo tailscale up"
echo "Then share your Tailscale IP with the rest of the team for centralized logging."

# Install MySQL and PHP
sudo apt install -y mysql-server php
