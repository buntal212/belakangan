build:
	docker compose build --no-cache

up:
	docker compose up -d

down:
	docker compose down

logs:
	docker compose logs -f

ps:
	docker compose ps

shell:
	docker compose exec app bash

composer:
	docker compose exec app composer $(command)

artisan:
	docker compose exec app php artisan $(command)

test:
	docker compose exec app php artisan test

refresh:
	docker compose exec app php artisan config:cache && docker compose exec app php artisan route:cache && docker compose exec app php artisan cache:clear




#redis
redis-cli:
	docker compose exec redis redis-cli

redis-server:
	docker compose exec redis redis-cli -h 27.112.78.135 -p 6379 -a sasa0102