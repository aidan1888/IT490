#!/bin/bash

# Loop indefinitely to check if the process is running
while true; do
    # Check if the process is running
    if pgrep -f "/home/dev-db/IT490/get_db_1.php" > /dev/null; then
        echo "Process /home/dev-db/IT490/get_db_1.php is running."
    fi
    # Wait for 1 second before checking again (adjust for a more frequent check if needed)
done
