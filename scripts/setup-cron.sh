#!/bin/bash

# Script to setup cron job for refreshing user points
# This script should be run on your server to set up automatic point refreshing

echo "Setting up cron job for user points refresh..."

# Add cron job to refresh points every 30 minutes
# Note: Adjust the path to your Laravel project and PHP executable
(crontab -l 2>/dev/null; echo "*/30 * * * * cd /path/to/your/laravel/project && php artisan points:refresh-all >> /dev/null 2>&1") | crontab -

echo "Cron job added successfully!"
echo "Points will be refreshed every 30 minutes."
echo ""
echo "To manually refresh points, run:"
echo "php artisan points:refresh-all"
echo ""
echo "To view current cron jobs:"
echo "crontab -l"
