# Project continuation notes

- Preserve the existing PHP Native, PDO, PHP Session, MySQL, and XAMPP architecture; this project is intentionally not a React/Node application.
- Reuse the shared layouts and escaping/CSRF helpers for new pages, with role checks in every page and every state-changing handler.
- Keep available, borrowed, and damaged unit counts transactional; store separate light/heavy damage balances so mixed condition quantities remain accurate while satisfying the inventory balance rule.
- Keep borrower catalog pages read-only and scope borrower history queries to the authenticated user's ID.
