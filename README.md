# ShopMate

A product comparison service for Indian shoppers. Describe what you want in plain English ("true wireless earbuds under ₹2,000 with a good mic"), and ShopMate compares stores, picks the 3 best products, and **checks every statement against the listing data before you see it**. Nothing is bought without your explicit approval.

Built with **PHP 8.3 / Laravel 13, MySQL, HTML/CSS and jQuery**, deployable on **AWS**.

![Results page](docs/screenshots/results-desktop.png)

| Browse with filters | Product page with store prices and history | Mobile, after the search was relaxed |
|---|---|---|
| ![Browse](docs/screenshots/browse.png) | ![Product](docs/screenshots/product.png) | ![Mobile](docs/screenshots/replan-mobile.png) |

---

## What it does

**For shoppers**
- **AI search**: type a request; get a *work order* (how it was understood), 3 ranked picks, a side-by-side comparison table, and a step-by-step trace of how the answer was reached.
- **Checked statements**: every sentence on a card carries a small tag like `T2`. Click it to jump to the trace step its facts came from.
- **Honest gaps**: if a listing doesn't state something, it shows as **?** "couldn't confirm", never as a yes.
- **One clarifying question** when the request names no product type ("something for my dad's birthday"), never two.
- **Relaxed search** when nothing fits: the yellow note says exactly what was given up (e.g. "budget raised from ₹15,000 to ₹17,000").
- **Browse** each category with price, brand, rating and must-have filters (no page reloads; the URL keeps the filters so it can be shared).
- **Product page**: prices at every store (cheapest highlighted), 90-day price history chart, specs, and **price-drop email alerts**.
- **Mock checkout (Cart Mandate)**: tick consent, the server signs the exact cart, checkout is refused if anything changes.

**For the site owner**
- **SEO admin** (`/admin/seo`): edit title, description and keywords of any product, category or page, with a live Google-style preview.
- **Operations view** (`/admin/ops`): searches in the last 7 days, failures, average speed, claims removed, checkout audit log, failed background jobs.
- **Health check** (`/health`) for uptime monitoring, plus a sitemap and robots.txt.

---

## How it maps to the job description

| Job requirement | Where it is in this project |
|---|---|
| PHP, OOP | Pipeline steps are separate classes behind one `PipelineStep` interface; the AI sits behind an `LlmClient` interface with three implementations (Groq, offline, test) |
| Laravel | Eloquent models and relationships (incl. polymorphic SEO), queues, scheduler, validation, rate limiting, mail, Artisan commands |
| SQL / MySQL | Normalised schema with indexes, `EXISTS` sub-queries for spec filters, transactions with row locking, aggregate reports, `db:explain-browse` command |
| HTML, CSS, JavaScript, jQuery | Hand-written responsive CSS (no framework); jQuery for live search progress, filters, comparison table, modal, chart |
| System integration with booking/metasearch sites | Three data sources behind one `CatalogSource` interface (demo, private Flipkart export, DummyJSON API), Groq API, Frankfurter currency API |
| Optimising processing | Denormalised best price, composite indexes, eager loading, caching, background jobs (details below) |
| Improving UI / rebuilding PC and smartphone sites | Every page is designed for phone and desktop widths |
| AWS, infrastructure, maintenance | EC2/Lightsail + RDS + S3 guide, GitHub Actions CI/CD, nightly S3 backups, health checks, cleanup jobs |

---

## How a search works

The agent is a **fixed flowchart**, not a free-roaming agent. The AI makes decisions inside three steps; plain PHP does everything that must be exact.

```
Planner (AI) → Clarify → Search (code) ⇄ Replan (AI picks, code checks; max 2) → Rank (code) → Explain (AI) → Verify (code) → Respond
```

| Step | Who | What happens | File |
|---|---|---|---|
| Plan | AI | Sentence → category, budget, must-haves. A budget written in the text ("20k", "1.5 lakh") is read by code and wins over the AI | `app/Services/Pipeline/Steps/Planner.php` |
| Clarify | code | No product type → ask one question. Never asks twice | `Steps/Clarifier.php` |
| Search | code | Removes anything over budget; checks each must-have against typed spec facts: **met** (kept, ✓), **not met** (removed), **unknown / conflict / unsupported** (kept, ?) | `Steps/Searcher.php` |
| Replan | AI + code | Nothing fits → code lists every single change that brings products back (raise budget up to 50%, or drop one must-have) with how many products each unlocks. AI picks; an invalid pick falls back to a fixed rule | `Steps/Replanner.php` |
| Rank | code | Review-weighted rating (Bayesian average), plus share of must-haves confirmed, price as tie-breaker. Colour/storage variants collapsed | `Steps/Ranker.php` |
| Explain | AI | Writes short claims; each must cite a trace step and list its facts | `Steps/Explainer.php` |
| Verify | code | Checks every fact (price, rating, reviews, rank, specs, must-haves, "within budget" against the **original** budget) and rejects any number in the text that isn't a checked fact. One rewrite; anything still wrong is deleted | `Steps/Verifier.php` |

The search runs as a **background job** (queue), and the page polls for new trace steps every second, so progress appears live.

**No API key needed.** With `GROQ_API_KEY` empty, a rule-based **offline AI** (`app/Services/Llm/OfflineLlm.php`) answers the same tasks. It is also the automatic fallback if Groq is down, and the trace says so when that happens.

### Key design decisions

- **AI never handles facts.** Prices, filtering, ranking and checking are code, so a wrong AI answer can't produce a wrong price on screen.
- **Typed spec facts.** "RAM: 8 GB" is stored as the number 8, so "at least 8 GB" is a numeric comparison, not text matching. If a listing contradicts itself, no value is stored (`is_conflict`), never a guess.
- **Config, not code, for categories.** All categories, spec label aliases and supported must-haves live in `config/shopmate_specs.php`. Adding a category needs no PHP changes.
- **Cart Mandate.** The browser only sends an offer ID; the server looks up the price, signs the exact cart with HMAC-SHA256, and on checkout re-checks signature, cart contents, current price, 15-minute expiry and single use, inside a transaction with a row lock. Every step goes to `audit_logs`.

---

## Run it locally (Windows)

**You need:** PHP 8.3+, Composer and MySQL. The easiest way on Windows is [Laragon](https://laragon.org/) (includes all three). Git Bash works for every command below.

```bash
# 1. Get the code and install packages
cd shopmate
composer install            # creates composer.lock: commit it

# 2. Configure
cp .env.example .env
php artisan key:generate
```

3. Create an empty MySQL database called `shopmate` (Laragon: right-click → MySQL → Create database). Check `DB_USERNAME` / `DB_PASSWORD` in `.env` (Laragon's default is `root` with no password). Set `ADMIN_PASSWORD` if you want the admin pages.

```bash
# 4. Create tables and load the fictional demo catalog (48 products, 3 stores, 60 days of price history)
php artisan migrate --seed

# 5. Run the site, the background worker and the scheduler (three terminals)
php artisan serve
php artisan queue:work
php artisan schedule:work
```

Open http://localhost:8000. If you have Node installed, `composer dev` starts all three in one terminal.

> Don't want to run a worker? Set `QUEUE_CONNECTION=sync` in `.env`; searches then run inside the request (the page just waits until the search is finished).

**Use Groq instead of the offline AI:** put your key in `GROQ_API_KEY` and restart `queue:work`.

### Your private Flipkart data

Put one JSON file per category in `storage/app/private/flipkart/` (this folder is git-ignored, so it never reaches GitHub):

```
storage/app/private/flipkart/earphones.json
storage/app/private/flipkart/smartphones.json
storage/app/private/flipkart/laptops.json
storage/app/private/flipkart/smartwatches.json
storage/app/private/flipkart/running-shoes.json
```

```bash
php artisan products:import --source=local --captured-on=2026-10-08
```

Field names are read from common scraper names (`title`/`name`, `price`/`selling_price`, `specifications`, ...). If your export uses others, add them to the `FIELDS` list in `app/Services/Catalog/Sources/LocalJsonSource.php`. If spec labels don't map, add aliases in `config/shopmate_specs.php`.

**Keep Flipkart data off the public demo.** Their terms don't allow republishing it. Use it for local demos only; the public site uses the demo catalog.

Other sources: `--source=demo` (fictional, safe to publish) and `--source=dummyjson` (public demo API; USD prices converted to rupees with Frankfurter and labelled "approx.").

### Tests

```bash
php artisan test
```

39 tests, no internet or API key needed (the AI is replaced by the offline or a scripted test AI). They cover the budget parser, spec cleaning, ranking, the full pipeline, clarification, replanning (including an invalid AI pick), the verifier rejecting wrong prices, invented numbers, made-up citations and made-up products, every cart refusal case, browse filters, price alerts, SEO admin and the DummyJSON + Frankfurter import.

---

## Automation

| What | When | Command |
|---|---|---|
| Refresh prices, record history only when a price changes | every 6 hours | `prices:refresh` |
| Email shoppers whose target price was reached (once each) | hourly | `alerts:check` |
| Expire old cart approvals, delete searches older than 30 days | daily 03:15 | `shopmate:cleanup` |
| Remove failed jobs older than a week | daily | `queue:prune-failed` |
| Run searches in the background, retry if the worker crashes | on demand | queue job `RunSearchPipeline` |
| Retry Groq on rate limits/server errors, then fall back to offline AI | per AI call | `GroqClient`, `ResilientLlm` |
| Test, check code style, deploy, then check `/health` | every push to `main` | `.github/workflows/` |
| Database backup to S3, keep 7 days | nightly 02:30 | `scripts/backup.sh` |

All schedules are in `routes/console.php`; one cron line runs them (`deploy/crontab.txt`).

---

## Deploying on AWS

The free plan gives new accounts credits for 6 months, so create the account when you're about to apply, not while building. Set a budget alert (AWS Budgets) on day one.

**Cheapest setup (one server):** one Lightsail instance or small EC2 instance (Ubuntu) running Nginx, PHP-FPM and MySQL. **Resume-friendly setup:** the same server plus **RDS** for MySQL (costs more credits; share one RDS instance across all your projects).

1. **Server:** Ubuntu 24.04. Install `nginx`, `php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-intl php8.3-bcmath`, `composer`, `supervisor`, `mysql-client`, `awscli` (and `mysql-server` if not using RDS).
2. **Code:** `git clone` into `/var/www/shopmate`, then `composer install --no-dev --optimize-autoloader`, `cp .env.example .env`, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, the `DB_*` values, `ADMIN_PASSWORD`, `MANDATE_SECRET` (any long random string), `php artisan key:generate`, `php artisan migrate --seed --force`. Make `storage/` and `bootstrap/cache/` writable by `www-data`.
3. **Web server:** copy `deploy/nginx.conf` to `/etc/nginx/sites-available/shopmate`, enable it, reload Nginx. For HTTPS, run `certbot --nginx` once you have a domain.
4. **Queue worker:** copy `deploy/shopmate-worker.conf` to `/etc/supervisor/conf.d/`, then `supervisorctl reread && supervisorctl update`.
5. **Scheduler and backups:** add the two lines in `deploy/crontab.txt` with `crontab -e`. Create an S3 bucket, set `AWS_BUCKET`, and give the server an IAM role that can write to it.
6. **Automatic deploys:** in GitHub → Settings → Secrets, add `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`. After that, every push to `main` runs tests and, if they pass, runs `scripts/deploy.sh` on the server (maintenance mode on, pull, migrate, rebuild caches, restart workers, maintenance mode off) and checks `/health`.
7. **Monitoring:** point a free uptime monitor (or a CloudWatch alarm) at `https://your-domain/health`. It returns `503` if the database is down or jobs are stuck in the queue.

For the public demo, set `DEMO_SIMULATE_PRICES=true` so the fictional prices move a little and the history charts stay alive. Real data is never simulated.

---

## Performance notes

| Technique | Where | Why |
|---|---|---|
| **Denormalised best price** (a copied column) | `products.best_price`, `best_offer_id`, `offers_count` | Browse sorting and price filters need no join or sub-query per product; updated after every import or price change |
| **Composite indexes** (indexes on two columns together) | `(category_id, best_price)`, `(category_id, weighted_rating)`, `(product_id, key)` on spec facts, `(product_offer_id, recorded_at)` on history | The browse query and spec filters read only the rows they need |
| **`EXISTS` sub-queries** for must-have filters | `app/Services/Catalog/BrowseQuery.php` | Uses the `(product_id, key)` index; scales better than joining all specs |
| **Eager loading** (fixing the N+1 query problem) | search pool, browse list, results | Specs and offers for 100 products load in 3 queries, not 201. Outside production, any lazy loading is logged as a warning |
| **Caching** | home category tiles, nav, browse filter options, exchange rates, sitemap | Cleared automatically after imports and price refreshes |
| **Background jobs** | search pipeline | The page responds instantly and shows progress |
| **Static asset caching** | `?v=` file-time versions + Nginx `expires 30d`, gzip | Repeat visits download almost nothing |
| **Bulk inserts** | spec facts on import | One query per product instead of one per fact |

See the plan and timing of the browse query:

```bash
php artisan db:explain-browse smartphones --must=5g --max=20000
```

**Before/after story to fill in for your interview:** drop the `products_category_id_best_price_index` index in a test copy, load the full Flipkart snapshot, run the command above, then add the index back and run it again. Note both timings and the `EXPLAIN` rows here.

---

## User testing log

Real evidence that you listen to users. Fill this in after showing the site to 3–5 people.

| Who (role, phone/desktop) | Task given | What confused them | What I changed | Result after the change |
|---|---|---|---|---|
| | "Find earbuds under ₹2,000" | | | |
| | | | | |

---

## Project structure

```
app/
  Console/Commands/     products:import, prices:refresh, alerts:check, shopmate:cleanup, db:explain-browse
  Http/Controllers/     pages, admin (SEO, ops), api (searches, products, alerts, cart mandates), health, sitemap
  Jobs/                 RunSearchPipeline (background search)
  Models/               Product, ProductOffer, SpecFact, PriceHistory, SearchSession, TraceStep, Claim, CartMandate, ...
  Services/
    Catalog/            importer, spec cleaning, variant grouping, browse SQL, data sources
    Pipeline/           runner, steps, verifier, trace, fact sheet, prompts
    Llm/                Groq client, offline AI, fallback wrapper
    Cart/               cart signing and checkout rules
config/shopmate.php         AI, pipeline weights, mandate, data sources
config/shopmate_specs.php   categories, spec label aliases, supported must-haves
database/data/demo_catalog.json   fictional catalog (safe to publish)
public/css, public/js       hand-written CSS and jQuery (no build step)
deploy/, scripts/, .github/ server config, deploy and backup scripts, CI/CD
```

## What I would improve next

- Product images in the demo catalog (currently category icons).
- More data sources (a second real store) so cross-store comparison uses real prices.
- Re-run the 66-request evaluation from the Python version against this PHP pipeline and publish the numbers here.
- Redis for cache and queue once traffic justifies it.
