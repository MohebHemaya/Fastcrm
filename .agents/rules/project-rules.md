# Driver Management System Project Rules

- Always verify constraints against `AGENTS.md` and `plan/phase-N-*.md`.
- Never use SQLite for tests or runtime. MySQL/MariaDB InnoDB is required.
- Maintain RTL and Arabic conventions (`dir="rtl"`, Tailwind logical properties `ms-`, `me-`, `ps-`, `pe-`, `text-start`).
- Implement one phase at a time and ensure Definition of Done tests pass.
- Record architectural decisions in `docs/decisions.md`.
