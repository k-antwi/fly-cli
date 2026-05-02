<p align="center"><img width="294" height="69" src="/art/logo.svg" alt="Logo Fly"></p>

## Introduction

Fly is a Docker-based development & deployment toolkit for Laravel applications. It scaffolds a `docker-compose.yml` for your project, proxies common dev workflows (artisan, composer, npm, pest, …) into the container, and ships your code to a VPS over SSH.

Fly is a **standalone CLI binary** built with [Laravel Zero](https://laravel-zero.com/). You don't need to install Fly into your Laravel app — drop the `fly` binary on your `$PATH` and run it from any project root.

## Installation

Run the installer script — it downloads the latest binary, creates `~/.fly`, and adds it to your `PATH`:

```bash
curl -fsSL https://raw.githubusercontent.com/k-antwi/fly-cli/main/release/install.sh | sh
```

Then reload your shell:

```bash
source ~/.zshrc   # or ~/.bashrc
```

Verify the install:

```bash
fly --version
```

### Manual install

If you'd prefer to place the binary yourself:

```bash
curl -fsSL https://github.com/k-antwi/fly-cli/releases/latest/download/fly -o fly
chmod +x fly
mv fly /usr/local/bin/fly
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

### Global Router (Traefik)

Manage local routing and subdomains with a global Traefik router:

```bash
fly router:start           # start the global Traefik router
fly router:status          # view router status and configured routes
fly router:stop            # stop the global Traefik router
```

The router provides:
- **Automatic subdomain routing** to your containers (e.g., `*.localhost` or `*.test`)
- **Dashboard** at `http://localhost:8080` to view routes and services
- **Dynamic routing** — services are automatically discovered and routed

**Example workflow:**
```bash
fly router:start
fly install
fly up -d
# Your app is now accessible at http://myapp.localhost (or your configured domain)
fly router:status  # see what's running
```

Configure the router domain in your `.env`:
```env
FLY_ROUTER_DOMAIN=localhost  # or .test, .local, etc.
```

### Generate an nginx site config

Scaffold a production-ready nginx server block into `docker/nginx/<domain>.conf`:

```bash
fly gen:nginx-conf                                  # fully interactive
fly gen:nginx-conf --ip=203.0.113.10 --domain=example.com --upstream=myapp --port=3000 --letsencrypt
```

Options:

| Option | Description |
| --- | --- |
| `--ip` | Host IP the server should listen on (validated; prompted if omitted) |
| `--domain` | Domain used for `server_name` and Let's Encrypt cert paths |
| `--upstream` | Upstream / app name (defaults to the first label of `--domain`) |
| `--port` | Upstream backend port (default `3000`) |
| `--letsencrypt` / `--no-letsencrypt` | Enable or disable Let's Encrypt cert lines without prompting |
| `--force` | Overwrite an existing conf file without prompting |

When `--letsencrypt` is enabled the generated file references `/etc/letsencrypt/live/<domain>/fullchain.pem` and `privkey.pem`; otherwise those lines are emitted as comments so you can wire in your own certificate later.

## Inspiration

Fly is inspired by [Sail](https://github.com/laravel/sail) and derived from [Vessel](https://github.com/shipping-docker/vessel) by [Chris Fidao](https://github.com/fideloper).

## License

Fly is open-sourced software licensed under the [MIT license](LICENSE.md).
