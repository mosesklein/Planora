<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## API & OSRM health

- **Endpoint:** `GET /api/health`
- **Response:** JSON payload with the overall status plus checks for the database, Redis, and OSRM dependencies. The endpoint returns `503 Service Unavailable` when any dependency is unhealthy.

Example response:

```json
{
  "status": "ok",
  "db": true,
  "redis": true,
  "osrm": true,
  "osrm_message": "ok"
}
```

When running locally, the API allows calls from `http://localhost:3000`, which lets the Next.js app query this endpoint directly.

### Environment defaults

- Inside Docker, Laravel should reach OSRM at `http://osrm:5000` (the Compose service name). Set `OSRM_URL` to this value.
- From the host, OSRM is published on port `5001` via Docker Compose. You can hit it directly (or set an optional `OSRM_URL_HOST=http://localhost:5001` for your own shell usage). The API itself still uses `OSRM_URL`.

### macOS & Apple Silicon

- OSRM runs under the `linux/amd64` platform to ensure the binaries work on Apple Silicon when using Docker Desktop's emulation.
- To avoid port conflicts with other services on macOS, OSRM is published on `localhost:5001` while the internal container address remains `http://osrm:5000`.

### Connectivity checks

Host to OSRM (published port):

```bash
curl "http://localhost:5001/route/v1/driving/-74.0060,40.7128;-73.935242,40.73061?overview=false&steps=false"
```

Laravel container to OSRM service name:

```bash
docker compose exec laravel.test curl "http://osrm:5000/route/v1/driving/-74.0060,40.7128;-73.935242,40.73061?overview=false&steps=false"
```

API health endpoint from host:

```bash
curl http://localhost:8000/api/health
```

### Routing diagnostics from the container

Run the built-in Artisan command to verify database, Redis, and OSRM connectivity (including DNS resolution and a live routing request):

```bash
docker compose exec laravel.test php artisan routing:diagnose
```

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
