# Global reach with controlled service eligibility

Product direction approved 2026-09-28: Solveit should serve people worldwide, especially when they do not know a trustworthy expert in the needed specialty. This document defines a **proposed first catalogue**, not live country restrictions or regulatory certification. Sprint 0 does not add categories, permissions, tables, or endpoints.

## First remote-service catalogue

| Domain | Example user problem | Initial service boundary |
| --- | --- | --- |
| Technology and software | Choosing an architecture, reviewing a web product, diagnosing an integration | Technical assessment and implementation advice |
| Business operations and startups | Validating a business idea, setting processes, writing a go-to-market plan | Business planning and operations; no guaranteed outcome |
| E-commerce and digital commerce | Starting an online store, selecting tools and fulfillment workflows | Store operations and platform guidance |
| Digital marketing and growth | Acquiring customers, diagnosing campaign performance, positioning | Campaign and content strategy; no guaranteed revenue |
| Design and brand | Brand direction, UX review, product experience | Design critique, research, and deliverables with clear ownership |
| Career, hiring, and education | CV/portfolio review, interview practice, training plan, hiring process design | Coaching and skills development; no regulated credential promise |

The catalogue deliberately supports one expert for a focused question and several experts for a genuine cross-domain Case. Example: an international online store may need e-commerce, UX, technology, and marketing. The Case owner chooses the needed scope; additional experts are justified and priced transparently (SRS BR-003).

## Worldwide access is not universal professional authorization

Allow global discovery and account signup as a product goal, subject to operational availability. Before booking, match each service to the expert's verified domain, role, service type, languages, relevant client location, and permitted jurisdiction. An expert being visible does not authorize every service in every country. Payments and payouts require provider-supported countries; data location, retention, privacy, sanctions, and tax treatment need review before activation. Until those checks are encoded and verified, keep paid cross-border services disabled rather than assuming the current single `jurisdiction` string grants universal coverage.

Keep medical care, emergency response, representation of children, licensed legal opinions, tax filing, investment advice, and other location-specific regulated work behind separate approval gates. This matches SRS section 3.3 and BR-020. Educational or general information must still be described accurately; changing a label does not remove professional requirements.

The accepted expert onboarding rule is recorded in `docs/architecture/ADR-002-expert-eligibility-and-verification.md`: verified identity and one effective approved scope are required for final Expert activation, with licence verification for regulated services and proportionate evidence for other fields. Verification of the expert does not activate an otherwise blocked service catalogue item. The current code does **not** yet enforce the regulated-evidence requirement; do not advertise licensed services as purchasable based on the existing KYC flag.

## Launch gate for each domain and service

Document eligible countries/client locations, expert evidence and renewal rules, permitted service types, reviewer qualifications, excluded questions, complaint/escalation flow, private document rules, and provider coverage. Mark every catalogue item `draft`, `pilot_enabled`, or `blocked` in the future product catalogue. These are proposed product states, not current database enums.
