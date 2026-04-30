# Fly Traefik Router Guide

## Overview

Fly includes a built-in **Traefik v3 global router** that automatically manages local DNS routing and subdomains for your Docker containers. This guide shows you how to use it effectively.

## What is Traefik?

Traefik is a modern reverse proxy that automatically discovers and routes traffic to your Docker containers. With Fly, you get:

- **Automatic subdomain routing** — access your app via `myapp.localhost` or `myapp.test`
- **HTTPS support** — local SSL/TLS for development
- **Dashboard** — visual overview of all running routes
- **Zero configuration** — services are discovered automatically

## Getting Started

### 1. Start the Router

```bash
fly router:start
```

This creates:
- A dedicated Docker network called `fly-router`
- A Traefik container (`fly-router-traefik`) listening on ports 80, 443, and 8080
- Storage directory at `~/.fly/router/`

### 2. Install Your Laravel App

```bash
fly install
fly up -d
```

This automatically:
- Configures your app with Traefik labels
- Connects your app to the `fly-router` network
- Makes it accessible at `http://myapp.localhost` (or your custom domain)

### 3. Access Your App

Open your browser to:
```
https://myapp.localhost
```

Note: Your browser will show a self-signed certificate warning (normal for local development).

### 4. View the Dashboard

```bash
fly router:status
```

Or visit the Traefik dashboard directly:
```
http://localhost:8080/dashboard/
```

## Example Application Scenario

### Scenario: Multi-service E-commerce Application

Let's set up a realistic development environment with:
- Main web application (`ecommerce.localhost`)
- Admin panel (`admin.ecommerce.localhost`)
- API service (`api.ecommerce.localhost`)
- Supporting services (MySQL, Redis, Mailpit)

#### Step 1: Initialize Router

```bash
# Start the global router
fly router:start
```

#### Step 2: Create Main App Structure

```bash
# Create project directory
mkdir ecommerce-app
cd ecommerce-app

# Initialize with Fly (choose: mysql, redis, mailpit)
fly install
```

When prompted for services, select: `mysql`, `redis`, `mailpit`

#### Step 3: Configure Your App

Edit `.env`:
```env
APP_NAME=ecommerce
FLY_APP_HOST=ecommerce.localhost
FLY_ROUTER_DOMAIN=localhost

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=ecommerce
DB_USERNAME=fly
DB_PASSWORD=password

# Cache
CACHE_DRIVER=redis
REDIS_HOST=redis
REDIS_PORT=6379

# Mail
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

#### Step 4: Start Services

```bash
# Start all containers
fly up -d

# Run migrations
fly artisan migrate

# Your app is now at https://ecommerce.localhost
```

#### Step 5: Add Multiple Routes (Optional)

If you want to serve admin and API from separate containers, update `docker-compose.yml`:

```yaml
services:
  laravel.fly:
    labels:
      - traefik.enable=true
      - traefik.http.routers.ecommerce-main.rule=Host(`ecommerce.localhost`)
      - traefik.http.routers.ecommerce-main.entrypoints=websecure
      - traefik.http.routers.ecommerce-main.tls=true
      - traefik.http.services.ecommerce-main.loadbalancer.server.port=80

  admin:
    image: your-admin-image
    networks:
      - default
      - fly-router
    labels:
      - traefik.enable=true
      - traefik.http.routers.ecommerce-admin.rule=Host(`admin.ecommerce.localhost`)
      - traefik.http.routers.ecommerce-admin.entrypoints=websecure
      - traefik.http.routers.ecommerce-admin.tls=true
      - traefik.http.services.ecommerce-admin.loadbalancer.server.port=80

  api:
    image: your-api-image
    networks:
      - default
      - fly-router
    labels:
      - traefik.enable=true
      - traefik.http.routers.ecommerce-api.rule=Host(`api.ecommerce.localhost`)
      - traefik.http.routers.ecommerce-api.entrypoints=websecure
      - traefik.http.routers.ecommerce-api.tls=true
      - traefik.http.services.ecommerce-api.loadbalancer.server.port=80

networks:
  fly-router:
    external: true
    name: fly-router
```

Now you can access:
- **Main app**: `https://ecommerce.localhost`
- **Admin panel**: `https://admin.ecommerce.localhost`
- **API**: `https://api.ecommerce.localhost`
- **Traefik Dashboard**: `http://localhost:8080/dashboard/`

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `FLY_ROUTER_DOMAIN` | `localhost` | Domain suffix (e.g., `.test`, `.local`) |
| `APP_NAME` | `fly-app` | Application name (used in subdomain) |
| `FLY_APP_HOST` | `fly-app.localhost` | Full hostname for main app |

### Change Your Domain

If you prefer `.test` or `.local` instead of `.localhost`:

```bash
export FLY_ROUTER_DOMAIN=test
fly router:stop
fly router:start
# Your app is now at https://myapp.test
```

Or add to `.env`:
```env
FLY_ROUTER_DOMAIN=test
```

## Common Tasks

### View Running Routes

```bash
fly router:status
```

This displays:
- Router status (Running/Stopped)
- Configured routes
- Service ports and URLs

### Stop the Router

```bash
fly router:stop
```

Note: This stops routing but doesn't affect your application containers.

### Restart Everything

```bash
fly router:stop
fly down
fly router:start
fly up -d
```

### View Traefik Logs

```bash
docker logs fly-router-traefik
```

### Access Individual Services

You don't need to expose every service. Only services with `traefik.enable=true` labels are routed.

Database and cache services (MySQL, Redis, etc.) are accessible only from within containers, which is safer for development.

## Troubleshooting

### App Not Accessible

1. **Check router is running:**
   ```bash
   fly router:status
   ```

2. **Verify app is running:**
   ```bash
   docker ps | grep laravel.fly
   ```

3. **Check Traefik dashboard:**
   - Visit `http://localhost:8080/dashboard/`
   - Look for your service in the list
   - Check if routes are configured

4. **View logs:**
   ```bash
   docker logs fly-router-traefik
   docker logs laravel.fly
   ```

### SSL Certificate Warning

This is normal for development. Your browser warns about self-signed certificates.

**To suppress warnings (Chrome):**
1. Click the address bar
2. Click the certificate icon
3. Click "Certificate is not valid"

**Or use HTTP** (though HTTPS is recommended):
```bash
# Edit docker-compose.yml, change entrypoints from websecure to web
```

### Port Already in Use

If ports 80, 443, or 8080 are already in use:

```bash
# Find what's using the port
lsof -i :80
lsof -i :443
lsof -i :8080

# Or use Activity Monitor (macOS) to force quit
```

### Custom Subdomain Not Working

Ensure:
1. Service has `traefik.enable=true` label
2. Host rule matches your domain: `Host(`myservice.localhost`)`
3. Service is connected to `fly-router` network
4. Service port is correctly specified

## Advanced Configuration

### Add Custom Middleware

You can add Traefik middleware to services:

```yaml
services:
  laravel.fly:
    labels:
      - traefik.enable=true
      - traefik.http.routers.myapp.rule=Host(`myapp.localhost`)
      - traefik.http.routers.myapp.middlewares=compress-middleware
      - traefik.http.middlewares.compress-middleware.compress=true
```

### Rate Limiting

```yaml
labels:
  - traefik.http.middlewares.rate-limit.ratelimit.average=100
  - traefik.http.middlewares.rate-limit.ratelimit.burst=50
  - traefik.http.routers.myapp.middlewares=rate-limit
```

## Best Practices

1. **Always start the router first** — before running `fly install` or `fly up`
2. **Keep router running** — it's lightweight and manages all your local routing
3. **Use meaningful app names** — `APP_NAME` becomes your subdomain, so use `blog`, `api`, `admin`, etc.
4. **Leverage the dashboard** — check `http://localhost:8080` when routes aren't working
5. **Don't expose database ports** — only expose your web/API services
6. **Version your docker-compose.yml** — commit Traefik labels so team members get consistent setup

## Getting Help

For issues or questions:
- Check the [Traefik v3 Documentation](https://doc.traefik.io/traefik/)
- View router logs: `docker logs fly-router-traefik`
- View app logs: `fly logs` or `docker logs laravel.fly`
