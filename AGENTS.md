## Agent skills

### Issue tracker

Issues live in GitHub Issues (`s1nxian/crnp`), managed via the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

Default five-role vocabulary (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: root `CONTEXT.md` + `docs/adr/`. See `docs/agents/domain.md`.

### Flowcharts

Spec edits that change a role's flow **update that role's chart before
committing** — same commit, no exceptions; a stale chart is a bug. Charts:
`docs/flow-charts/` → customer (ordering · payment · reservations & rentals) ·
cashier (online pickup · counter tasks) · kitchen · admin. Chart text stays
declarative (what the system does), never build order.

### Data layer

Before writing any RTDB read/write, open the existing data layer and extend
it — never invent a parallel shape.

### Operations

The four scheduled sweeps and their manual fallbacks (for a down scheduler)
live in `docs/agents/operations.md`.

### Documentation standards

Any edit to agent-facing docs (`AGENTS.md`, `docs/`, extension `README.md`) MUST follow
[`AGENT_DOCS_STANDARD.md`](./AGENT_DOCS_STANDARD.md).

### Coding standards

Any code change MUST follow [`CODING_STANDARD.md`](./CODING_STANDARD.md).

### Commits

Before committing, confirm every applicable rule in [`CODING_STANDARD.md`](./CODING_STANDARD.md) holds for the staged change.
