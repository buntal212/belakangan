# About

CARA MENJALANKANNYA 

## Daftar Isi

- [Setup](#setup)
    - [1. Copy files](#step-1-copy-files-in-your-directory)
    - [2. Execute Docker](#step-2-execute-docker)
    - [3. Run Composer](#step-3-install-composer-dependencies)
- [Enhancements](#enhancements)

# Setup

Anda harus menginstal docker di komputer. [docker-compose](https://docs.docker.com/compose/install/).

## Step 1: Copy files in your directory

Kami berasumsi bahwa Anda menambahkan ini ke proyek yang sudah ada, karena sejak Laravel 10 docker disertakan secara default.

Salin semua file kecuali `.env` dan `readme.md` di folder proyek Anda saat ini. Timpa kredensial dari .env Anda secara lokal dengan kredensial yang disediakan di sini. Jika Anda tidak ingin menimpa nama dan pengguna basis data, silakan sesuaikan file di docker-compose/mysql/init/01-databaes.sql sesuai dengan kebutuhan Anda.

Copy all files except `.env` and `readme.md` in your current project folder. Overwrite the credentions from your `.env` locally with those provided here. If you dont want to overwrite database name and user, then please adjust the file in `docker-compose/mysql/init/01-databaes.sql` according to your needs.

## Step 2: Execute docker

Jalankan container

  ```sh
  docker-compose up -d --build
  ```

Ini mungkin memerlukan waktu beberapa saat. Setelah container disiapkan, periksa status dengan ...

  ```sh
  docker-compose ps
  ```

Anda akan melihat tiga container sedang berjalan.


## Step 3: Install Composer dependencies

Bash ke dalam kontainer Anda:

  ```sh
  docker-compose exec app bash
  ```

Instal dependensi composer (ini mungkin memerlukan waktu beberapa saat):

  ```sh
  composer install
  ```

and finally generate a key

  ```sh
  php artisan key:generate
  ```

:tada: Selamat. Aplikasi Anda sekarang dapat diakses di `localhost:8182`

# Enhancements

Saya suka menggunakan alias berikut untuk menghindari masuk ke

  ```
  alias phpunit="docker-compose exec app vendor/bin/phpunit"
  alias artisan="docker-compose exec app php artisan"
  alias composer="docker-compose exec app composer"
  ```

Selain itu, jika Anda ingin agar kontainer laravel docker Anda tetap berjalan setelah komputer Anda dihidupkan ulang, Anda dapat menambahkan

  ```
  restart: unless-stopped
  ```

ke setiap layanan Anda (app,db,nginx).

# lain-lain

untuk melihat apakah ekstensi swoole aktif pada php

  ```sh
  php -m | grep swoole
  ```


untuk melihat apakah ekstensi pcntl aktif pada php

```sh
php -m | grep pcntl
```
untuk mereload octane

```sh
  docker-compose exec app bash
  php artisan octane:reload
```


