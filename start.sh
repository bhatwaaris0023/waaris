#!/bin/bash
# Render deployment script for PHP + MySQL

# Install PHP and dependencies (ensure PostgreSQL extension if using PG)
apt-get update
apt-get install -y php php-mysql php-pgsql php-curl php-gd php-json php-mbstring

# Start PHP built-in server
php -S 0.0.0.0:$PORT
