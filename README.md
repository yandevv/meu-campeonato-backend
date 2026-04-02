<h1 align="center">Meu Campeonato ⚽ — Tournament Simulation Backend</h1>

<p align="center">
  <img alt="PHP Version" src="https://img.shields.io/badge/PHP-8.4-777BB4?style=flat&logo=php&logoColor=white" />
  <img alt="Laravel Version" src="https://img.shields.io/badge/Laravel-13-FF2D20?style=flat&logo=laravel&logoColor=white" />
  <img alt="PostgreSQL" src="https://img.shields.io/badge/PostgreSQL-18-4169E1?style=flat&logo=postgresql&logoColor=white" />
  <img alt="Coverage" src="https://img.shields.io/badge/Coverage-98.7%25-brightgreen" />
  <a href="https://unlicense.org" target="_blank">
    <img alt="License: Unlicense" src="https://img.shields.io/badge/License-Unlicense-blue.svg" />
  </a>
  <a href="https://twitter.com/yandevv_" target="_blank">
    <img alt="Twitter: yandevv_" src="https://img.shields.io/twitter/follow/yandevv_.svg?style=social" />
  </a>
</p>

<p align="center">
  A RESTful API built with Laravel for managing sports tournaments with knockout-style bracket simulation. Create teams, organize 8-team tournaments, and simulate full bracket results — from quarter-finals to the final — with AI-driven goal score predictions.
</p>

<h5 align="center">Give a &#11088; if this project helped you or if you find it interesting!</h5>

---

## Table of Contents

- [Table of Contents](#table-of-contents)
- [About](#about)
  - [How the Simulation Works](#how-the-simulation-works)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Prerequisites](#prerequisites)
  - [Using Laravel Sail (Recommended)](#using-laravel-sail-recommended)
  - [Running Locally](#running-locally)
- [Installation](#installation)
  - [Option 1: Using Laravel Sail (Recommended)](#option-1-using-laravel-sail-recommended)
  - [Option 2: Running Locally](#option-2-running-locally)
- [Running in Production](#running-in-production)
  - [Prerequisites](#prerequisites-1)
  - [1. Configure environment](#1-configure-environment)
  - [2. Build and start](#2-build-and-start)
  - [3. Verify](#3-verify)
  - [Useful commands](#useful-commands)
- [Configuration](#configuration)
  - [Environment Variables](#environment-variables)
- [Usage](#usage)
  - [Quick Example](#quick-example)
- [API Documentation](#api-documentation)
- [Project Structure](#project-structure)
- [Running Tests](#running-tests)
- [About Me](#about-me)
- [License](#license)

---

## About

**Meu Campeonato API** is a backend application that lets you create and manage knockout-style sports tournaments. The core feature is the **tournament simulation engine**, which takes 8 teams and produces a complete bracket with realistic match results.

### How the Simulation Works

1. **Quarter-finals** — 8 teams are paired into 4 matches
2. **Semi-finals** — 4 winners advance into 2 matches
3. **Third Place** — 2 semi-final losers play for 3rd place
4. **Finals** — 2 semi-final winners play for the championship

Each match's goals are predicted by an external **Python script** (designed to be swappable with a real ML model). The winner is determined by:

1. Higher goal count wins
2. If tied — better goal balance wins
3. If still tied — the team registered first in the tournament wins

The simulation result includes a **podium** (1st, 2nd, 3rd place) and detailed round-by-round match data with scores.

---

## Features

- **Team Management** — Full CRUD for teams with UUID identifiers
- **Tournament Management** — Create tournaments and attach up to 8 teams
- **Bracket Simulation** — Simulate a full knockout tournament (Quarter-finals through Finals)
- **Goal Score Prediction** — Pluggable Python-based predictor (mock ML model included)
- **Match Winner Resolution** — Multi-criteria tiebreaker logic for determining winners
- **Podium Results** — Returns 1st, 2nd, and 3rd place after simulation
- **API Documentation** — Auto-generated OpenAPI/Swagger docs via Scramble
- **Comprehensive Testing** — Unit, Feature, and Integration test suites
- **Standardized Responses** — Consistent JSON response format across all endpoints
- **Containerized** — Production-grade Docker setup (Nginx + PHP-FPM) and Laravel Sail for development

---

## Tech Stack

| Category             | Technology                                                                              |
| -------------------- | --------------------------------------------------------------------------------------- |
| **Framework**        | [Laravel 13](https://laravel.com/)                                                      |
| **Language**         | [PHP 8.4](https://www.php.net/)                                                         |
| **Database**         | [PostgreSQL 18](https://www.postgresql.org/)                                            |
| **API Docs**         | [Scramble](https://scramble.dedoc.co/) (OpenAPI/Swagger)                                |
| **Testing**          | [PHPUnit 12](https://phpunit.de/) + [ParaTest](https://github.com/paratestphp/paratest) |
| **Code Formatting**  | [Laravel Pint](https://laravel.com/docs/pint)                                           |
| **Goal Prediction**  | [Python 3](https://www.python.org/) (pluggable ML script)                               |
| **Containerization** | [Laravel Sail](https://laravel.com/docs/sail) (Docker)                                  |

---

## Prerequisites

### Using Laravel Sail (Recommended)

- [Docker](https://www.docker.com/) (version 20.10 or higher)
- [Docker Compose](https://docs.docker.com/compose/) (version 2.0 or higher)

### Running Locally

- [PHP](https://www.php.net/) 8.4 or higher
- [Composer](https://getcomposer.org/) 2.x
- [PostgreSQL](https://www.postgresql.org/) 16 or higher
- [Python](https://www.python.org/) 3.x (for goal score prediction script)

---

## Installation

### Option 1: Using Laravel Sail (Recommended)

1. **Clone the repository**

```bash
git clone https://github.com/yandevv/meu-campeonato-backend.git
cd meu-campeonato-backend
```

2. **Install PHP dependencies**

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
```

3. **Copy environment file and configure for Sail**

```bash
cp .env.example .env
```

Update `.env` with the Sail database settings:

```env
DB_HOST=pgsql
DB_USERNAME=sail
DB_PASSWORD=password
```

4. **Start the containers**

```bash
./vendor/bin/sail up -d
```

5. **Generate app key and run migrations**

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

The API will be available at **http://localhost**.

**Stop the containers:**

```bash
./vendor/bin/sail down
```

### Option 2: Running Locally

1. **Clone the repository**

```bash
git clone https://github.com/yandevv/meu-campeonato-backend.git
cd meu-campeonato-backend
```

2. **Create the PostgreSQL database**

```bash
createdb meu_campeonato_backend
```

3. **Run the setup script** (installs dependencies, copies `.env`, generates key, and runs migrations)

```bash
composer setup
```

4. **Edit `.env`** with your local PostgreSQL credentials:

```env
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=meu_campeonato_backend
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

5. **Run migrations** (if not already run by `composer setup`)

```bash
php artisan migrate
```

6. **Start the development server**

```bash
composer dev
```

The API will be available at **http://localhost:8000**.

---

## Running in Production

The production setup uses two separate Docker images — **Nginx** and **PHP-FPM** — orchestrated via `compose.prod.yml`. They share a network namespace, with Nginx proxying HTTP requests to PHP-FPM over `localhost`.

### Prerequisites

- [Docker](https://www.docker.com/) 20.10+
- [Docker Compose](https://docs.docker.com/compose/) 2.0+

### 1. Configure environment

```bash
cp .env.example .env
```

Edit `.env` and set the required production values:

```env
APP_KEY=                       # generate below
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com

DB_DATABASE=meu_campeonato
DB_USERNAME=meu_campeonato_user
DB_PASSWORD=your_secure_password
```

Generate `APP_KEY` (no local PHP needed):

```bash
docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

### 2. Build and start

```bash
docker compose -f compose.prod.yml up -d --build
```

This will:
- Build the PHP-FPM image (installs dependencies, runs migrations, warms up caches)
- Build the Nginx image
- Start PostgreSQL and wait for it to be healthy before starting the app

### 3. Verify

```bash
# Check all services are healthy
docker compose -f compose.prod.yml ps

# Hit the health check endpoint
curl http://localhost:8080/up
```

The API is available at **http://localhost:8080** (or `APP_PORT` if overridden in `.env`).

### Useful commands

```bash
# Follow application logs
docker compose -f compose.prod.yml logs -f php

# Stop all services
docker compose -f compose.prod.yml down

# Stop and remove volumes (destructive — deletes DB data)
docker compose -f compose.prod.yml down -v
```

---

## Configuration

### Environment Variables

| Variable        | Description          | Default                  |
| --------------- | -------------------- | ------------------------ |
| `APP_NAME`      | Application name     | `Meu Campeonato API`     |
| `APP_ENV`       | Environment mode     | `local`                  |
| `APP_DEBUG`     | Enable debug mode    | `true`                   |
| `APP_URL`       | Application base URL | `http://localhost`       |
| `DB_CONNECTION` | Database driver      | `pgsql`                  |
| `DB_HOST`       | Database host        | `127.0.0.1`              |
| `DB_PORT`       | Database port        | `5432`                   |
| `DB_DATABASE`   | Database name        | `meu_campeonato_backend` |
| `DB_USERNAME`   | Database username    | `root`                   |
| `DB_PASSWORD`   | Database password    | _(empty)_                |

---

## Usage

### Quick Example

1. **Create some teams:**

```bash
curl -X POST http://localhost/api/teams \
  -H "Content-Type: application/json" \
  -d '{"name": "Team Alpha"}'
```

2. **Create a tournament:**

```bash
curl -X POST http://localhost/api/tournaments \
  -H "Content-Type: application/json" \
  -d '{"name": "Summer Cup 2026"}'
```

3. **Attach 8 teams to the tournament:**

```bash
curl -X POST http://localhost/api/tournaments/{tournament_id}/teams \
  -H "Content-Type: application/json" \
  -d '{"team_ids": ["id1", "id2", "id3", "id4", "id5", "id6", "id7", "id8"]}'
```

4. **Simulate the tournament:**

```bash
curl -X POST http://localhost/api/tournaments/{tournament_id}/simulate
```

5. **View the simulation results:**

```bash
curl http://localhost/api/tournaments/{tournament_id}/simulation
```

---

## API Documentation

Once the application is running, access the interactive **Swagger/OpenAPI** documentation at:

- **With Sail:** http://localhost/docs/api
- **Locally:** http://localhost:8000/docs/api

Powered by [Scramble](https://scramble.dedoc.co/) — auto-generated from route definitions and type hints.

---

## Project Structure

```
meu-campeonato-backend/
├── app/
│   ├── Enums/
│   │   └── RoundPhase.php             # Quarter-finals, Semi-finals, Third Place, Finals
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── TeamController.php     # Team CRUD endpoints
│   │   │   └── TournamentController.php # Tournament + simulation endpoints
│   │   ├── Requests/                  # Form request validation
│   │   │   ├── Team/
│   │   │   └── Tournament/
│   │   └── Resources/                 # API response transformers
│   │       ├── TeamResource.php
│   │       ├── TournamentResource.php
│   │       ├── TournamentRoundResource.php
│   │       ├── RoundGameResource.php
│   │       └── TournamentTeamResource.php
│   ├── Models/
│   │   ├── Team.php
│   │   ├── Tournament.php
│   │   ├── TournamentRound.php
│   │   ├── RoundGame.php
│   │   └── TournamentTeam.php         # Pivot model
│   ├── Services/
│   │   ├── TeamService.php                       # Team business logic
│   │   ├── TournamentService.php                 # Tournament business logic
│   │   ├── TournamentSimulationService.php       # Simulation orchestrator
│   │   ├── TournamentSimulationRoundBuilder.php  # Builds rounds and plays matches
│   │   ├── TournamentMatchWinnerResolver.php     # Tiebreaker logic
│   │   └── PythonGoalScorePredictor.php          # Python ML script integration
│   └── Support/
│       └── ApiResponse.php            # Standardized API response helper
│
├── database/
│   ├── factories/                     # Model factories for testing
│   ├── migrations/                    # Database schema migrations
│   └── seeders/                       # Database seeders
│
├── routes/
│   └── api.php                        # API route definitions
│
├── scripts/
│   └── predict_match_score.py         # Python goal prediction script (mock ML)
│
├── tests/
│   ├── Unit/                          # Unit tests
│   ├── Integration/                   # Integration tests
│   └── Feature/                       # Feature tests
│
├── docker/
│   ├── nginx/
│   │   ├── Dockerfile                 # Nginx production image
│   │   └── nginx.conf                 # Nginx server block
│   └── php/
│       ├── Dockerfile                 # PHP-FPM production image (multi-stage)
│       ├── entrypoint.sh              # Migrations + cache warmup on startup
│       ├── php-fpm.conf               # FPM pool configuration
│       └── php.ini                    # Production PHP settings + OPcache
├── compose.yaml                       # Docker Compose (Laravel Sail — development)
├── compose.prod.yml                   # Docker Compose (production)
├── composer.json                      # PHP dependencies & scripts
└── .env.example                       # Environment variables template
```

---

## Running Tests

The project has three test suites: **Unit**, **Integration**, and **Feature**.

```bash
# Run all tests
composer test

# Run only unit tests
composer test:unit

# Run only feature tests
composer test:feature

# Run only integration tests
composer test:integration

# Run tests in parallel
composer test:parallel

# Run tests with coverage report
composer test:coverage
```

**With Laravel Sail**, prefix commands with `./vendor/bin/sail`:

```bash
./vendor/bin/sail composer test
./vendor/bin/sail composer test:unit
./vendor/bin/sail composer test:integration
```

---

## About Me

**YanDevv (author)**

* &#128038; Twitter: [@yandevv_](https://twitter.com/yandevv_)
* &#128188; LinkedIn: [@yandevv](https://linkedin.com/in/yandevv)
* &#128025; GitHub: [@yandevv](https://github.com/yandevv)

---

## License

This project is licensed under the [Unlicense](https://unlicense.org) — it is released into the public domain. You are free to copy, modify, distribute, and use this software for any purpose, without any conditions.

See the [LICENSE](LICENSE) file for details.

---

<p align="center">Made with &#10084;&#65039; by YanDevv</p>
