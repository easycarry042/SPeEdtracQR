# SPeED TraQR

Request tracking for the City Government of San Pedro, Laguna. Citizens and city offices file requests online and follow them by QR-coded tracking number; staff process them and supervisors confirm them before they are claimed.

The app is a Next.js project in [`web/`](web), deployed on Vercel with Supabase.

```bash
cd web
npm install
npm run dev        # http://localhost:3000
```

`web/.env` needs `DATABASE_URL`, `DIRECT_URL`, `APP_KEY` and `SESSION_SECRET` (see `web/.env.example`). Project notes for contributors are in [`CLAUDE.md`](CLAUDE.md).
