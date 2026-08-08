#!/bin/sh -e

composer install

if [ ! -f ".env" ]; then
    if [ ! -z "$APP_ENV" ] && [ -f ".env.$APP_ENV" ]; then
        cp .env.$APP_ENV .env
    else
        cp .env.example .env
        echo "Using example env file"
    fi
else
    echo "Using existing .env"
fi

php artisan key:generate

