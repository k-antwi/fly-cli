<p align="center"><img width="294" height="69" src="/art/logo.svg" alt="Logo Fly"></p>

## Introduction

Fly is a Docker-based development & deployment toolkit for Laravel applications. It scaffolds a `docker-compose.yml` for your project, proxies common dev workflows (artisan, composer, npm, pest, …) into the container, and ships your code to a VPS over SSH.

Fly is a **standalone CLI binary** built with [Laravel Zero](https://laravel-zero.com/). You don't need to install Fly into your Laravel app — drop the `fly` binary on your `$PATH` and run it from any project root.

## Installation

Download the prebuilt binary (or build it yourself, below) and place it on your `$PATH`:

```bash
mv fly /usr/local/bin/fly
chmod +x /usr/local/bin/fly
```

## Building from source

```bash
git clone https://github.com/k-antwi/fly.git
cd fly
composer install
php fly app:build fly --build-version=1.0.0
# binary lands in ./builds/fly
```

## Usage

From any Laravel project directory:

```bash
fly install                # scaffold docker-compose.yml + ./docker/ runtimes
fly up -d                  # start containers
fly artisan migrate        # run an artisan command inside the container
fly composer require ...   # run composer
fly npm run dev            # run npm
fly mysql                  # open a MySQL CLI
fly shell                  # bash inside the app container
fly to:vps                 # ship the source to a remote VPS over SSH
fly up:vps                 # run docker compose on the remote VPS
```

Run `fly` with no arguments to see the full command list, or `fly <command> --help` for details on any single command.

## Inspiration

Fly is inspired by [Sail](https://github.com/laravel/sail) and derived from [Vessel](https://github.com/shipping-docker/vessel) by [Chris Fidao](https://github.com/fideloper).

## License

Fly is open-sourced software licensed under the [MIT license](LICENSE.md).
