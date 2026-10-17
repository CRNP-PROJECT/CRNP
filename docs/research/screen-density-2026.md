# Screen density in web UI — 2026 research digest

All URLs accessed 2026-09-22. Canonical older sources are cited with their original dates.
Inline citation format: source (author, publication date), URL (accessed 2026-09-22).

## 1. Question & problem

Screens must stay minimal and cozy — not overwhelmingly full of buttons or content — without making navigation harder.
Pure minimalism starves discoverability (fewer controls, content split across sections).
Compact layouts fit everything but feel dense and overwhelming.
This digest verifies the candidate principles (progressive disclosure, adjustable density, whitespace/hierarchy, Hick's law) against primary sources and turns them into a screen-by-screen rule for a plain HTML/CSS/Blade restaurant system (README.md: "system computes, human only presses buttons / ≤3 inputs per screen / smart defaults").

## 2. Findings

### Progressive disclosure

- **Defer, don't delete.** Show only a few of the most important options initially; offer the larger specialized set on request.
— NN/g, *Progressive Disclosure* (Jakob Nielsen, 2006-12-03), https://www.nngroup.com/articles/progressive-disclosure/ (accessed 2026-09-22).
- **It signals importance:** the mere fact that something appears on the initial display tells users it's important; hiding advanced settings protects novices from mistakes and spares experts from scanning unused features — improving learnability, efficiency, and error rate (3 of usability's 5 components).
— NN/g, *Progressive Disclosure* (2006-12-03), same URL (accessed 2026-09-22).
- **Get the split right:** everything users frequently need must be up front, or they pay a navigation tax; the first screen must not carry rarely-used options either — the primary list must focus attention on truly important issues.
— NN/g, *Progressive Disclosure*, usability criteria section (2006-12-03), same URL (accessed 2026-09-22).
- **Get the progression right:** how to reach level 2 must be obvious, and its label must set clear expectations — i.e. strong information scent. In practice keep ≤2 levels; >2 and users get lost; if you'd need 3, simplify the design instead.
— NN/g, *Progressive Disclosure* (2006-12-03), same URL (accessed 2026-09-22).
- **Split by task coupling, not content type:** features used together with back-and-forth belong on ONE screen (a hotel booking worked better for room comparison on one screen); inputs not yet needed (payment during exploration) move to a second screen and free space for the exploratory interface.
— NN/g, *Progressive Disclosure*, staged-disclosure section (2006-12-03), same URL (accessed 2026-09-22).
- **Both failure modes are named:** "The more features you can defer, the simpler your design, but if you divide the task into too many steps, users get bogged down by excess navigation."
— NN/g, *Progressive Disclosure* (2006-12-03), same URL (accessed 2026-09-22).

### Information density (incl. adjustable density)

- **Density has a ceiling; both extremes fail.** "Higher information density = less need to move around and higher likelihood that you see what you want," but "in a UI, you can't cram the screen full of too much info or users will feel overwhelmed."
— NN/g, *Utilize Available Screen Space* (Jakob Nielsen, 2011-05-08; canonical, still the topic's authority), https://www.nngroup.com/articles/utilize-available-screen-space/ (accessed 2026-09-22).
- **Cramming is the named anti-pattern:** sites "frequently cram highly valuable content or action items into tiny spaces" into parts of screen too small to understand, and important features hidden in tiny pop-ups; conversely "spending more screen space is okay, as long as you give users an easy way back to the main view."
— NN/g, *Utilize Available Screen Space* (2011-05-08), same URL (accessed 2026-09-22).
- **Adjustable density is real in Material (web):** first-party guide "A hands-on guide to applying default, comfortable, and compact density to your application" — three named levels (default / comfortable / compact).
— Material Design, *Use Material Density on the Web*, https://m3.material.io/blog/material-density-web (meta-description verified; body JS-rendered; publication date [UNVERIFIED]; accessed 2026-09-22).
- **Ant Design: density is developer-set, not an end-user toggle:** the global size control is the ConfigProvider `componentSize` prop ("small" | "middle" | "large") — the app picks one; no end-user density switch found in the current docs checked.
— Ant Design, *ConfigProvider*, https://ant.design/components/config-provider (accessed 2026-09-22).
- **IBM Carbon: density is an author-time style model, not a runtime control:** three documented style models — editorial (default, comfortable browsing), product, and "High density interface model — Sometimes every inch of the screen needs be utilized to display information and controls… uses the full width grid, so the bigger the screen, the more information the user will see."
— IBM Carbon / IBM Standards, *Style models*, https://www.ibm.com/standards/carbon/guidelines/style-models (accessed 2026-09-22).
- **W3C explicitly endorses a user-facing density control (a11y):** SC 2.5.8 Note: "Another option is to provide a mechanism to control the density of layout and thereby change target size or spacing, or both… users with visual field loss may prefer a more condensed layout with smaller sized controls."
— W3C, *Understanding SC 2.5.8 Target Size (Minimum)*, WCAG 2.2, https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum (accessed 2026-09-22).
- **Responsive density: density is a function of window, not a fixed choice:** viewport is classified compact / medium / expanded and a different layout strategy applies per class (columns, stacking) as the window grows.
— Android Developers (first-party Google), *Use window size classes*, https://developer.android.com/develop/ui/compose/layouts/adaptive/window-size-classes (accessed 2026-09-22).

### Whitespace, visual hierarchy & perceived complexity

- **Clutter = two problems, two fixes:** peer-reviewed measurement (n = 2,761 ratings of 57 websites) separates *Content Clutter* (irrelevant content occupying space → **discard**) from *Design Clutter* (too much text, unpleasant/disorganized layout, too much visual noise, insufficient white space → **reorganize**).
— Lewis & Sauro, *Measuring the Perceived Clutter of Websites*, Int. J. Human–Computer Interaction, published 2024-06-03, DOI 10.1080/10447318.2024.2359205, https://measuringu.com/wp-content/uploads/2024/06/2024_MeasuringThePerceivedClutterOfWebsites.pdf (accessed 2026-09-22).
- **What clutter actually costs:** content + design clutter explain ≈44% of variance in overall clutter; overall clutter drives Appearance ratings far more than Usability (β ≈ −0.56 vs −0.11) and did not significantly affect Trust — clutter reads as "not clean/simple" before it reads as "unusable."
— Lewis & Sauro (2024-06-03), same URL (accessed 2026-09-22).
- **Classic density recommendations still stand (as summarized there):** keep overall density as low as possible *while still displaying task-relevant data*; use white space to reduce local density; group related items (aid to performance); predictable layouts measure less complex (Tullis, 1983).
— Tullis recommendations cited in Lewis & Sauro (2024), same URL (accessed 2026-09-22).
- **Hierarchy beats reduction:** a clear visual hierarchy guides the eye via color/contrast, scale, and grouping (proximity, common region); when elements are all equal in size/color and lack breathing room, "few users will be willing to spend time parsing through that visual clutter" — check with the squint test.
— NN/g, *Visual Hierarchy in UX* (Kelley Gordon, 2021-01-17), https://www.nngroup.com/articles/visual-hierarchy-ux-definition/ (accessed 2026-09-22).

### Hick's law / choice overload as applied to button counts

- **More visible choices = slower decisions:** "The more choices presented to users, the longer it will take them to make a decision"; UIs with many choices (e.g. long menus) must minimize decision time by combining Hick's law with other techniques — grouping, labels.
— NN/g, *Hick's Law* (video, 3 min, 2018-07-06), https://www.nngroup.com/videos/hicks-law-long-menus/ (accessed 2026-09-22).
- **Effort grows with count:** "As the number of choices increases, so does the effort required to [choose]" — simplicity wins over abundance of choice.
— NN/g, *Simplicity Wins Over Abundance of Choice* (2015-11-22), https://www.nngroup.com/articles/simplicity-vs-choice/ (accessed 2026-09-22).
- **POS implication:** every extra always-visible button taxes every transaction; choice reduction is a per-screen budget, not a global style preference (synthesis from the two NN/g sources above).

### Discoverability compensators (what to do instead of showing everything)

- **Maximize the content-to-chrome ratio, not the amount of content.** Hiding chrome costs three things: users must *discover* it, later *recall* it ("out of sight is truly out of mind"), and pay extra *interaction* to reach it.
— NN/g, *Maximize Content-to-Chrome Ratio…* (Raluca Budiu, 2014-08-03), https://www.nngroup.com/articles/content-chrome-ratio/ (accessed 2026-09-22).
- **The ratio is screen-size dependent:** on a large screen an 8-item nav barely dents the ratio, so hiding it "is not better enough to justify the cost of hiding the chrome" — keep chrome visible; tiny icons are *easier* to overlook on big screens.
— NN/g, *Maximize Content-to-Chrome Ratio* (2014-08-03), same URL (accessed 2026-09-22).
- **Labels carry scent:** replacing a search box with an icon or nav text with unlabeled icons saves space but loses information scent; recognition beats recall (heuristic-backed).
— NN/g, *Maximize Content-to-Chrome Ratio* (2014-08-03), same URL (accessed 2026-09-22).
- **Overflow is conforming if reversible:** content must reflow at 320 CSS px without two-dimensional scrolling (layout tables/images excepted); repositioning must not lose information, and truncation with a "Show more" mechanism revealing the rest is an explicitly conforming way to reduce scrolling.
— W3C, *Understanding SC 1.4.10 Reflow*, WCAG 2.2, https://www.w3.org/WAI/WCAG22/Understanding/reflow (accessed 2026-09-22).
- **Target-size floors cap how compact you may go:** SC 2.5.8 requires ≥24×24 CSS px targets (spacing exception applies) — "dense" must never mean sub-minimum tap areas; a density control is the sanctioned escape hatch (quote in the density section above).
— W3C, *Understanding SC 2.5.8*, WCAG 2.2, same URL (accessed 2026-09-22).

### Not verified (do not repeat as fact)

- **Apple HIG** (decluttering / progressive disclosure / density): HIG page bodies are JS-rendered and were not retrievable in this session; no current first-party HIG text on density or decluttering was confirmed — [UNVERIFIED].
- Material density blog publication date — [UNVERIFIED]; the density-levels claim rests on the page's own meta-description.
- Any *end-user-facing* density toggle in Carbon or Ant docs — not found in the current docs checked (absence stated, not asserted as nonexistent).

## 3. Tension resolution (minimal-vs-discoverable, per the sources)

- The sources do not put minimal and dense on one axis; they split the problem into **what earns a place on the screen** and **how obvious the exits are**.
- Minimalism's real failure is *hiding chrome*, not showing less: the discoverability loss users feel is NN/g's discover → recall → interaction-cost chain — so the fix is visible, well-labeled navigation and strong information scent, not more buttons (content-chrome ratio; progressive disclosure).
- Density's real failure is *irrelevant content plus weak hierarchy*, not information itself: keep enough density to cut navigation and support comparison, but discard content clutter and reorganize what's left with whitespace/grouping/hierarchy before declaring a screen "dense" (Lewis & Sauro 2024; visual hierarchy).
- Progressive disclosure is the reconciliation: first level = frequent/task-blocking items only (≤2 levels), obvious labeled transition, co-used items on one screen — under-splitting and over-splitting are both named failure modes (NN/g PD).
- Adjustable density is real and even accessibility-sanctioned (Material default/comfortable/compact; Ant componentSize; W3C density-control note), but every source treats it as a setting chosen per app/user/need — never a substitute for prioritization.
- Screen size modulates the answer: keep chrome visible on the cashier's fixed monitor; on a big passive kitchen screen higher information density is explicitly the point (Carbon: "the bigger the screen, the more information the user will see").

## 4. Candidate screen-level rule — SYNTHESIS (from the cited findings, not a quote)

**The "3 + 1" cozy-density rule, applied screen-by-screen:**
1. **Action budget:** ≤3 primary actions visible per view (aligns with the project README's ≤3-inputs rule) plus at most 1 secondary/overflow control (⋯, "More", "Advanced"). Every extra visible choice costs decision time (Hick); everything else goes behind exactly one disclosed step, ≤2 levels, labeled with clear information scent (progressive disclosure).
2. **Earn its place:** first-paint content must be needed in the current task moment or used daily; otherwise defer it. Inputs used together stay on one screen; inputs not yet relevant move to a second step (staged disclosure). Declutter order: *discard* task-irrelevant content first, then *reorganize* the rest (grouping + whitespace) (Lewis & Sauro 2024).
3. **Measurable checks per screen:** (i) ≤9 visible actionable controls in a viewport before grouping/overflow hides the rest; (ii) no two same-weight, same-style buttons in one visual group (squint test); (iii) navigation chrome always visible, never behind an unlabeled icon (content-to-chrome ratio); (iv) targets ≥24×24 CSS px (WCAG 2.5.8); (v) reflows at 320 px, overflow = "Show more" (WCAG 1.4.10).
4. **Density by surface:** comfortable default for menu site and cashier POS; compact only for read-only, glanceable surfaces (kitchen screen, admin tables) and only after grouping/whitespace — mirroring Material's three tiers and Carbon's per-screen high-density model, never a user toggle as an excuse to pack the POS.
