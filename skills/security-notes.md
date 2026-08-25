# Security notes

A full security audit was completed across v2.3.8–v2.3.10. All findings are closed **except**
one, which is deferred deliberately, not overlooked.

## Open: Content-Security-Policy (M-1, partial)

The `Content-Security-Policy` header itself is not yet implemented. It was deferred because three
frontend libraries have non-trivial requirements under CSP that need separate validation before
the header can be turned on:

- **Symfony UX Live Components** — may require `'unsafe-eval'` or nonces for the
  component/morphdom scripts.
- **Quill** (rich-text editor) — typically needs `'unsafe-inline'` in `style-src` for its dynamic
  styles; the alternative is `'nonce-…'`.
- **TomSelect** (autocomplete widget) — may inject inline styles.

### Before implementing CSP

1. Audit blocked-by-CSP errors in DevTools Console using a `Content-Security-Policy-Report-Only`
   policy first.
2. Add the header in `report-only` mode first, in `docker/Caddyfile`, `dist/Caddyfile`, and
   `public/.htaccess` (for Plesk/Apache deployments).
3. Evaluate whether nonces are viable (Symfony's Twig `csp_nonce()`) or whether hashes are needed
   instead.
4. Only switch to the enforcing header once there are no violations.
5. Document the change in `CHANGELOG.md` under `### Security`.

## Closed findings (for reference — no action needed)

| ID | Finding | Fixed in |
|---|---|---|
| S-1 | XSS via `exceptional_circumstances`/`raw` Twig filter | v2.3.8 |
| A-1 | Password policy ≥12 characters | v2.3.9 |
| A-2 | Timing attack on password reset | v2.3.9 |
| A-3 | Incomplete email uniqueness check | v2.3.9 |
| M-1 | HSTS, X-Content-Type-Options, Referrer-Policy, Permissions-Policy | v2.3.9 |
| M-2 | Information oracle in family/cycle filters | v2.3.10 |
| B-1..B-5 | Accessibility findings | v2.3.9 |

When doing security-relevant work, treat CSP as the one known gap — not as something nobody
noticed.
