#!/bin/bash

set -e

REMOTE_SYSLOG_IP="100.119.108.82"

# Install required packages
apt update
apt install -y ufw php-cli rsyslog mysql-server

# Configure UFW firewall
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 3306/tcp
ufw --force enable

# Update PHP CLI INI files to log errors to syslog
for INI in /etc/php/*/cli/php.ini; do
    sed -i 's|^;*log_errors\s*=.*|log_errors = On|' "$INI"
    sed -i 's|^;*error_log\s*=.*|error_log = syslog|' "$INI"
done

# Update MySQL config file to log errors to syslog
cat >> /etc/mysql/my.cnf <<EOL
[mysqld_safe]
syslog
EOL

# Create rsyslog config to forward logs to remote server
cat <<EOF > /etc/rsyslog.d/60-remote.conf
*.* @$REMOTE_SYSLOG_IP:514
EOF

# Create rsyslog config to forward MySQL logs to remote server
cat <<EOF > /etc/rsyslog.d/50-mysql.conf
if\$programname == 'mysqld' then
@@$REMOTE_SYSLOG_IP:514
EOF


# Restart rsyslog to apply changes
systemctl restart rsyslog

echo "Sending test MySQL Log"
logger -p local0.info -t mysqld "MySQL syslog test message from $(hostname)"
echo "Done. MySQL msg sent."



# Send test PHP log message
php -r '
    openlog("php-test", LOG_PID | LOG_PERROR, LOG_USER);
    syslog(LOG_ERR, "PHP syslog test message from $(hostname)");
    closelog();
'

echo " PHP and MySQL syslog test message sent to $REMOTE_SYSLOG_IP"
