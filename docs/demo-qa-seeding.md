# Demo QA Seeding

Zenna Craft includes an optional production-like demo dataset for local and staging QA.

Run after the normal baseline seed:

```bash
php artisan db:seed --class=DemoQaSeeder
```

Reset a local environment and load the demo dataset:

```bash
php artisan migrate:fresh --seed --force
php artisan db:seed --class=DemoQaSeeder
```

`DemoQaSeeder` is not called by `DatabaseSeeder`. It is blocked in `production` unless `ZENNA_ALLOW_DEMO_QA_SEED=true` is set intentionally for a controlled QA environment.

Demo records use clear prefixes such as `demo-`, `ZC-DEMO-`, `DEMO-ORD-`, `DEMO-TRK-`, and `DEMO-EXP-` so reruns remain idempotent and do not overwrite owner-created records.
