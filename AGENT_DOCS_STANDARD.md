# AGENT_DOCS_STANDARD.md

Standards for agent-facing docs: the root `AGENTS.md` (or `CLAUDE.md`), anything under
`docs/`, and per-component `README.md` files. Context is a finite resource — every line
competes with the task for the agent's attention. These rules keep that context
high-signal. Add a rule only from an observed defect, never to fill an inventory.

## 1. One source of truth

- Every fact lives in exactly one document. Reference it elsewhere by a pointer to its
  source — a path, name, or invocation — never by restating or summarizing it; the agent
  loads the source on demand. A duplicate drifts, a summary can be wrong, and the wrong
  copy is the one that gets read.
- When two documents would state the same rule, keep it in the more specific one and link
  from the other.
- Front-load the trigger in a pointer: lead with the condition that fires it and name the
  target once; state what to reach, not what the target contains.
- A shared or reusable document carries no project-specific facts — commands, versions,
  paths. Those live in the project's own file, so the shared document stays portable.

## 2. Add only what the agent lacks

- Assume a capable agent. Include a fact only when the agent cannot derive it from the
  environment, the code, or a linked source. The unwritten convention, the reason behind a
  choice, the gotcha no config confesses — these earn their place. Commands, flags, and
  boilerplate the agent can look up do not.
- Include a rule only when it changes behavior versus the default; an instruction the model
  already follows pays load to say nothing.

## 3. Match specificity to fragility

- Prescribe exact steps only when the operation is fragile or error-prone and consistency
  is critical — a narrow bridge. Otherwise state the intent and let the agent choose — an
  open field. A precise command anchors the agent to it, and a wrong or stale one is
  followed anyway.

## 4. Precision

- Every instruction MUST be checkable: a reader can tell done from not-done. Replace vague
  verbs ("handle", "start with a minimal skeleton") with a concrete target or a completion
  criterion.
- Every step MUST be self-contained. A step whose entire content is "see §N" is a stub —
  inline the action or drop the step.
- Number steps and sections without collision. A "Step 6 (§4)" cross-reference is a defect.

## 5. Scope to the trigger

- A document covers the workflow that triggers reading it, and nothing else. A doc read
  when *creating* something covers creation — not promoting, moving, or deleting it.
- Scope to the agent's actions; steps the reader owns (restarting, reloading, editing by
  hand) belong outside these docs.

## 6. Agent autonomy

- Describe the target behavior, not the forbidden one. Phrase rules positively where a
  positive form exists; reserve a prohibition for a hard guardrail, and pair it with the
  positive target.
- A description MUST NOT encode an action the agent could take on its own initiative
  (deleting, renaming, moving, publishing). Describe the state; never narrate the cleanup.

## 7. Decisions belong to the user

- Where a choice is the user's (scope, priorities, which option to take), state that the
  user decides and stop. Do not script the question, rank the options, or supply a
  recommended answer.
- Leave workflow cadence to the user — iteration size, when to stop, how many features.
  State the constraints only.

## 8. Pruning

- Hunt no-ops sentence by sentence. When a sentence fails the inclusion test — could the
  agent derive this already? — delete the whole sentence rather than trim words from it.
- Keep each document's length proportionate to what it uniquely carries. A line that never
  bears on the document's task is sediment — cut it.
- Prefer stable phrasing; dated or time-sensitive instructions rot.

---

Principles drawn from Anthropic's published guidance — *Effective context engineering for
AI agents*, *Be clear and direct*, *Using CLAUDE.md files*, *Skill authoring best
practices*, *Writing effective tools for agents* — and this repo's `writing-for-agents`
skill.
