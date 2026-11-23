# Tests

    php tests/run.php

Builds a throwaway SQLite database from database.sql through tests/mkdb.py (needs Python 3), then checks shipping, cart totals, money and Paystack amounts, CSRF, redirects, order references, stock, payment marking, rate limiting, promo and COD rules, rewards, birthday rules, gift finder budgets, stone colour logic and permissions.

It never touches your real database or Paystack.
