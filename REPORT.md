# Rule Assignment — Implementation Report

This note explains what was built, the key design decisions, how to run and verify the work locally, It's written as straightforward project documentation for maintainers and reviewers. I structured this project like MVC Structure because it's very easy to understand and manage.

I put this project together to manage rule groups and structured rule assignments. Below I explain what I built, why I chose this approach, and how to run and check the system on your machine. I wrote it the way I’d brief someone on the team — straightforward and practical.

I added some user friendly comment on code for understanding

## Process flow

- Lets you create groups and rules, then assign rules into a small tree per group.
- Enforces the rules we agreed on:
  - Maximum three tiers (root → child → grandchild).
  - DECISION rules can’t be parents — no children under DECISION.
  - You can’t assign the same rule twice under the same parent.
  - Moves that would create cycles are blocked.
  - Requests can be made idempotent using an `Idempotency-Key` header also user CSRF based request validation.

Backend: PHP
Frontend: tiny Vue 3(https://unpkg.com/vue@3) for simple use
Simple Design : Bootstrap
Test Cases: PHPUnit
Database : Mysql
VsCode Extension: PHP Intelephense,Prettier for code format.

## Why I did it this way

- Server-side: I wanted a single source of truth for the rules so they can’t be bypassed by a buggy client.
- Databse : I Use PDO because it's Prevents SQL Injection,Supports prepared statements,Database independent
- Simple frontend: Bootstrap keeps the UI maintainable. I avoided custom CSS so it’s easy to change later.
- Tests run against the same DB the app uses. Because the model uses its own DB connections, tests delete their test rows in `tearDown()` instead of relying on transactions.

## Where the important logic lives

- `app/models/Assignment.php` — this is the core. Look here for create/updateParent/delete, subtree/tier calculations, duplicate and cycle checks, and idempotency handling. we also have other models to handle db Group.php and Rule.php
- `app/controllers/GroupController.php` — handles request validation and wires the model responses to JSON.
- `app/views/group.php` — the Vue app. Parent dropdowns are built from the tree, and the Move modal only shows valid target parents. The modal title now shows the rule name and type so it’s clear what you’re moving.

## How I’d run it locally

I run in mac and php installed using brew so i run this project in this way
From the project root:

```
php -S localhost:8001 -t app/public
```

Visit: http://localhost:8001/index.php?action=view

Quick checks:

- Create a group and a couple of rules.
- Assign a rule at root and then create a child under it.
- Try to assign a child under a DECISION rule — it should be blocked.
- Click Move on a node — the modal title shows the rule name and available parents are only valid choices.

## Running test cases

I used PHPUnit Test, for test cases

First need to install phpunit which is already configured in composer.json just run

```bash
composer install
```

From the project root:

```bash
./vendor/bin/phpunit --testdox
```

The tests verify reuse vs duplicate behaviour, DECISION parent rules, tier overflow, and move-cycle prevention. They clean up after themselves by deleting rows they create because we use same db.

## Quick maintainer tips

- To understand the constraints, start with `app/models/Assignment.php`.
- Frontend behaviour is in `app/views/group.php` — look for the code that builds `parentOptions` and the `move` modal.
- Tests live under `tests/` and assume the same DSN from `app/config/database.php`.
